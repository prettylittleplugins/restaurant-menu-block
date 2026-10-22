<?php

namespace PrettyLittlePlugins\RestaurantMenuBlock\Tests\Engine;

use PHPUnit\Framework\TestCase;
use PrettyLittlePlugins\RestaurantMenuBlock\Engine\Derivation;

/**
 * Each test is one of the agreed scenarios (22 Oct 2026). The fixture is a small
 * hand-written kitchen that mirrors the shipped slugs, so a failure here reads as
 * "the burger is wrong", not "array index 3 differs".
 */
final class DerivationTest extends TestCase {

	private array $kitchen = [
		// Plain things with nothing we track.
		'carrot'          => [ 'groups' => [] ],
		'olive-oil'       => [ 'groups' => [] ],
		'beef-patty'      => [ 'groups' => [ 'beef', 'meat' ] ],
		'prawns'          => [ 'groups' => [ 'crustaceans', 'shellfish' ] ],
		'espresso'        => [ 'groups' => [ 'caffeine' ] ],

		// Dairy. Butter / hard cheese are milk only; milk itself is milk + lactose.
		'cows-milk'       => [ 'groups' => [ 'milk', 'lactose', 'animal-derived' ], 'swap' => 'oat-milk' ],
		'oat-milk'        => [ 'groups' => [], 'may_contain' => [ 'gluten-cereals' ] ],
		'cheddar'         => [ 'groups' => [ 'milk', 'animal-derived' ] ],
		'parmesan'        => [ 'groups' => [ 'milk', 'animal-derived' ] ],

		// Satay: recipe inside a recipe.
		'peanuts'         => [ 'groups' => [ 'peanuts' ] ],
		'wheat'           => [ 'groups' => [ 'gluten-cereals' ] ],
		'soya-bean'       => [ 'groups' => [ 'soya' ] ],
		'soy-sauce'       => [ 'made_of' => [ 'soya-bean', 'wheat' ] ],
		'satay-sauce'     => [ 'made_of' => [ 'peanuts', 'soy-sauce' ] ],

		// Pesto: three levels down to milk. Pine nut is NOT a tree nut in UK law.
		'basil'           => [ 'groups' => [] ],
		'pine-nut'        => [ 'groups' => [] ],
		'pesto'           => [ 'made_of' => [ 'basil', 'pine-nut', 'parmesan', 'olive-oil' ] ],
		'linguine'        => [ 'made_of' => [ 'wheat' ] ],

		// The burger bun and its swap.
		'sesame-seed'     => [ 'groups' => [ 'sesame' ] ],
		'burger-bun'      => [ 'made_of' => [ 'wheat', 'sesame-seed', 'cows-milk' ], 'swap' => 'gluten-free-bun' ],
		'gluten-free-bun' => [ 'groups' => [ 'eggs' ] ],

		// Data-entry problems.
		'nans-sauce'      => [ 'unclassified' => true ],
		'loop-a'          => [ 'made_of' => [ 'loop-b' ] ],
		'loop-b'          => [ 'made_of' => [ 'loop-a' ] ],
		'brioche'         => [ 'made_of' => [ 'wheat', 'cows-milk' ], 'swap' => 'deleted-bun' ],
		'ciabatta'        => [ 'made_of' => [ 'wheat' ], 'swap' => 'house-gf-bun' ],
		'house-gf-bun'    => [ 'unclassified' => true ],
	];

	private function dish( array $ingredients, array $extra = [] ): array {
		return Derivation::derive_dish( [ 'ingredients' => $ingredients ] + $extra, $this->kitchen );
	}

	// ---- Everyday dishes -------------------------------------------------------------

	/** 1. Roast carrots: nothing we track, and that is NOT "no information". */
	public function test_plain_ingredients_are_complete_and_clean(): void {
		$d = $this->dish( [ 'carrot', 'olive-oil' ] );
		$this->assertTrue( $d['complete'] );
		$this->assertSame( [], $d['contains'] );
		$this->assertSame( [], $d['fixed'] );
		$this->assertSame( [], $d['may_contain'] );
		$this->assertSame( [], $d['swaps'] );
	}

	/** 2. Chicken satay: the wheat is two recipes down. */
	public function test_compound_resolves_recursively(): void {
		$d = $this->dish( [ 'satay-sauce' ] );
		$this->assertSame( [ 'gluten-cereals', 'peanuts', 'soya' ], $d['contains'] );
		$this->assertTrue( $d['complete'] );
	}

	/** 3. Pasta with pesto: milk three levels down, no nuts flag. */
	public function test_three_levels_deep_and_pine_nut_is_not_a_tree_nut(): void {
		$d = $this->dish( [ 'linguine', 'pesto' ] );
		$this->assertSame( [ 'animal-derived', 'gluten-cereals', 'milk' ], $d['contains'] );
		$this->assertNotContains( 'tree-nuts', $d['contains'] );
	}

	/** 4. Nan's special sauce, never classified: dish incomplete, but the rest still derives. */
	public function test_unclassified_ingredient_makes_dish_incomplete_but_keeps_known_groups(): void {
		$d = $this->dish( [ 'beef-patty', 'nans-sauce' ] );
		$this->assertFalse( $d['complete'] );
		$this->assertSame( [ 'beef', 'meat' ], $d['contains'] );
	}

	/** 4b. An ingredient the site doesn't have at all is the same as unclassified. */
	public function test_unknown_ingredient_slug_makes_dish_incomplete(): void {
		$d = $this->dish( [ 'carrot', 'does-not-exist' ] );
		$this->assertFalse( $d['complete'] );
	}

	/** 5. Looping recipe: must return, must fail safe. */
	public function test_cycle_does_not_hang_and_fails_safe(): void {
		$d = $this->dish( [ 'loop-a' ] );
		$this->assertFalse( $d['complete'] );
		$r = Derivation::resolve_ingredient( 'loop-a', $this->kitchen );
		$this->assertTrue( $r['unclassified'] );
	}

	// ---- The burger ----------------------------------------------------------------------

	/** 6. Plain burger: the bun's groups are all swappable; the GF bun adds egg. */
	public function test_swappable_bun_groups_and_what_the_swap_adds(): void {
		$d = $this->dish( [ 'beef-patty', 'burger-bun' ] );
		$this->assertTrue( $d['complete'] );
		$this->assertSame( [ 'beef', 'meat' ], $d['fixed'] );
		$this->assertSame( [ 'animal-derived', 'gluten-cereals', 'lactose', 'milk', 'sesame' ], $d['swappable'] );
		$this->assertSame( [ 'eggs' ], $d['swap_adds'] );
		$this->assertSame(
			[ [ 'from' => 'burger-bun', 'to' => 'gluten-free-bun', 'removes' => [ 'animal-derived', 'gluten-cereals', 'lactose', 'milk', 'sesame' ], 'adds' => [ 'eggs' ] ] ],
			$d['swaps']
		);
	}

	/** 7. Cheeseburger: milk now also comes from the cheese, so milk becomes fixed. */
	public function test_group_from_a_non_swappable_ingredient_is_fixed_even_if_also_in_swappable_one(): void {
		$d = $this->dish( [ 'beef-patty', 'burger-bun', 'cheddar' ] );
		$this->assertSame( [ 'animal-derived', 'beef', 'meat', 'milk' ], $d['fixed'] );
		$this->assertSame( [ 'gluten-cereals', 'lactose', 'sesame' ], $d['swappable'] );
		// The swap note only claims what it actually removes from THIS dish.
		$this->assertSame( [ 'gluten-cereals', 'lactose', 'sesame' ], $d['swaps'][0]['removes'] );
	}

	// ---- The latte ------------------------------------------------------------------------

	/** 11. Latte: milk swappable; oat milk's gluten risk kept separate from the dish's own. */
	public function test_swap_may_contain_is_separate_from_dish_may_contain(): void {
		$d = $this->dish( [ 'espresso', 'cows-milk' ] );
		$this->assertSame( [ 'caffeine' ], $d['fixed'] );
		$this->assertSame( [ 'animal-derived', 'lactose', 'milk' ], $d['swappable'] );
		$this->assertSame( [], $d['may_contain'] );
		$this->assertSame( [ 'gluten-cereals' ], $d['swap_may_contain'] );
	}

	/** 12. Café ticked "no swap" on this dish: milk is fixed, no swap offered. */
	public function test_per_dish_swap_opt_out_makes_groups_fixed(): void {
		$d = $this->dish( [ 'espresso', 'cows-milk' ], [ 'swap_opt_out' => [ 'cows-milk' ] ] );
		$this->assertSame( [ 'animal-derived', 'caffeine', 'lactose', 'milk' ], $d['fixed'] );
		$this->assertSame( [], $d['swappable'] );
		$this->assertSame( [], $d['swaps'] );
		$this->assertSame( [], $d['swap_may_contain'] );
	}

	// ---- Edge cases in the data ----------------------------------------------------------

	/** 14. Swap points at an ingredient the restaurant deleted: treat as fixed. */
	public function test_swap_to_missing_ingredient_is_ignored(): void {
		$d = $this->dish( [ 'brioche' ] );
		$this->assertTrue( $d['complete'] );
		$this->assertSame( [ 'animal-derived', 'gluten-cereals', 'lactose', 'milk' ], $d['fixed'] );
		$this->assertSame( [], $d['swaps'] );
	}

	/** 15. Swap target itself unclassified: swap ignored, dish still complete. */
	public function test_swap_to_unclassified_ingredient_is_ignored_and_dish_stays_complete(): void {
		$d = $this->dish( [ 'ciabatta' ] );
		$this->assertTrue( $d['complete'] );
		$this->assertSame( [ 'gluten-cereals' ], $d['fixed'] );
		$this->assertSame( [], $d['swaps'] );
	}

	/** 16. "May contain peanuts" ticked on the dish: in may_contain, never in contains. */
	public function test_dish_level_may_contain_is_kept_apart_from_contains(): void {
		$d = $this->dish( [ 'carrot' ], [ 'may_contain' => [ 'peanuts' ] ] );
		$this->assertSame( [], $d['contains'] );
		$this->assertSame( [ 'peanuts' ], $d['may_contain'] );
	}

	/** May-contain inside a recipe bubbles up too (a sauce made with oat milk). */
	public function test_may_contain_propagates_through_made_of(): void {
		$kitchen = $this->kitchen + [ 'oat-bechamel' => [ 'made_of' => [ 'oat-milk', 'olive-oil' ] ] ];
		$r       = Derivation::resolve_ingredient( 'oat-bechamel', $kitchen );
		$this->assertSame( [ 'gluten-cereals' ], $r['may_contain'] );
		$this->assertFalse( $r['unclassified'] );
	}

	/** Same ingredient listed twice is not double-counted and produces one swap note. */
	public function test_duplicate_ingredient_is_collapsed(): void {
		$d = $this->dish( [ 'burger-bun', 'burger-bun' ] );
		$this->assertCount( 1, $d['swaps'] );
	}

	/** Output lists are always sorted and re-indexed, so the cache compares byte-for-byte. */
	public function test_output_lists_are_sorted_sets(): void {
		$d = $this->dish( [ 'prawns', 'beef-patty', 'cheddar' ] );
		$this->assertSame( [ 'animal-derived', 'beef', 'crustaceans', 'meat', 'milk', 'shellfish' ], $d['contains'] );
	}
}
