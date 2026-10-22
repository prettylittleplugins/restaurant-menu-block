<?php

namespace PrettyLittlePlugins\RestaurantMenuBlock\Tests\Engine;

use PHPUnit\Framework\TestCase;
use PrettyLittlePlugins\RestaurantMenuBlock\Engine\Outcome;

/**
 * What one customer sees for one dish. These are the scenarios agreed on 22 Oct 2026;
 * the front-end JavaScript must give the same answers for the same inputs.
 */
final class OutcomeTest extends TestCase {

	/** derive_dish() output for a plain burger (scenario 6), written out so this file stands alone. */
	private array $burger = [
		'complete'         => true,
		'contains'         => [ 'animal-derived', 'beef', 'gluten-cereals', 'lactose', 'meat', 'milk', 'sesame' ],
		'fixed'            => [ 'beef', 'meat' ],
		'swappable'        => [ 'animal-derived', 'gluten-cereals', 'lactose', 'milk', 'sesame' ],
		'swap_adds'        => [ 'eggs' ],
		'may_contain'      => [],
		'swap_may_contain' => [],
		'swaps'            => [ [ 'from' => 'burger-bun', 'to' => 'gluten-free-bun', 'removes' => [ 'animal-derived', 'gluten-cereals', 'lactose', 'milk', 'sesame' ], 'adds' => [ 'eggs' ] ] ],
	];

	/** Latte (scenario 11). */
	private array $latte = [
		'complete'         => true,
		'contains'         => [ 'animal-derived', 'caffeine', 'lactose', 'milk' ],
		'fixed'            => [ 'caffeine' ],
		'swappable'        => [ 'animal-derived', 'lactose', 'milk' ],
		'swap_adds'        => [],
		'may_contain'      => [],
		'swap_may_contain' => [ 'gluten-cereals' ],
		'swaps'            => [ [ 'from' => 'cows-milk', 'to' => 'oat-milk', 'removes' => [ 'animal-derived', 'lactose', 'milk' ], 'adds' => [] ] ],
	];

	/** Burger with Nan's sauce (scenario 4). */
	private array $incomplete_burger = [
		'complete'         => false,
		'contains'         => [ 'beef', 'meat' ],
		'fixed'            => [ 'beef', 'meat' ],
		'swappable'        => [],
		'swap_adds'        => [],
		'may_contain'      => [],
		'swap_may_contain' => [],
		'swaps'            => [],
	];

	/** Prawn linguine (scenario 17). */
	private array $prawn_linguine = [
		'complete'         => true,
		'contains'         => [ 'crustaceans', 'gluten-cereals', 'shellfish' ],
		'fixed'            => [ 'crustaceans', 'gluten-cereals', 'shellfish' ],
		'swappable'        => [],
		'swap_adds'        => [],
		'may_contain'      => [],
		'swap_may_contain' => [],
		'swaps'            => [],
	];

	public function test_no_filter_shows_everything_even_incomplete_dishes(): void {
		$this->assertSame( Outcome::SHOWN, Outcome::decide( $this->burger, [] ) );
		$this->assertSame( Outcome::SHOWN, Outcome::decide( $this->incomplete_burger, [] ) );
	}

	/** 8. Avoiding gluten: burger shown with the swap flag. */
	public function test_swappable_conflict_is_flagged(): void {
		$this->assertSame( Outcome::FLAGGED, Outcome::decide( $this->burger, [ 'gluten-cereals' ] ) );
	}

	/** 9. Avoiding milk AND eggs: the GF bun fixes the milk but brings eggs → hidden. */
	public function test_swap_that_introduces_an_excluded_group_does_not_rescue(): void {
		$this->assertSame( Outcome::HIDDEN, Outcome::decide( $this->burger, [ 'milk', 'eggs' ] ) );
	}

	/** 10. Avoiding eggs only: no eggs as served, so shown plain. */
	public function test_group_only_present_in_a_swap_does_not_hide(): void {
		$this->assertSame( Outcome::SHOWN, Outcome::decide( $this->burger, [ 'eggs' ] ) );
	}

	/** Avoiding beef: fixed, nothing to be done. */
	public function test_fixed_conflict_is_hidden_with_a_reason(): void {
		$this->assertSame( Outcome::HIDDEN, Outcome::decide( $this->burger, [ 'beef' ] ) );
		$this->assertSame( [ 'beef' ], Outcome::reasons( $this->burger, [ 'beef', 'gluten-cereals' ] ) );
	}

	/** 4. Incomplete dish disappears into "no information" the moment any filter is on. */
	public function test_incomplete_dish_is_no_data_under_any_filter(): void {
		$this->assertSame( Outcome::NO_DATA, Outcome::decide( $this->incomplete_burger, [ 'peanuts' ] ) );
	}

	/** …unless we already know it contains something they avoid — then "contains beef" is the better answer. */
	public function test_incomplete_dish_with_known_fixed_conflict_is_hidden_not_no_data(): void {
		$this->assertSame( Outcome::HIDDEN, Outcome::decide( $this->incomplete_burger, [ 'beef' ] ) );
	}

	/** 13. Coeliac looks at the latte: as served there's no gluten → shown. The oat-milk risk is only on the swap. */
	public function test_coeliac_sees_the_latte_plain(): void {
		$this->assertSame( Outcome::SHOWN, Outcome::decide( $this->latte, [ 'gluten-cereals' ], true ) );
	}

	/** Dairy-free customer: flagged "available with oat milk". */
	public function test_dairy_free_sees_latte_flagged(): void {
		$this->assertSame( Outcome::FLAGGED, Outcome::decide( $this->latte, [ 'milk' ], true ) );
	}

	/** Avoiding milk AND gluten, medical (hide may-contain): oat milk may contain gluten → hidden. */
	public function test_swap_whose_may_contain_is_excluded_does_not_rescue_a_medical_filter(): void {
		$this->assertSame( Outcome::HIDDEN, Outcome::decide( $this->latte, [ 'milk', 'gluten-cereals' ], true ) );
		// Same customer, but choosing to see may-contain dishes: flagged.
		$this->assertSame( Outcome::FLAGGED, Outcome::decide( $this->latte, [ 'milk', 'gluten-cereals' ], false ) );
	}

	/** Dish's own may-contain: hidden for medical, shown for lifestyle. */
	public function test_dish_may_contain_respects_hide_may_contain(): void {
		$chocolate_torte = $this->prawn_linguine; // shape only
		$chocolate_torte['contains'] = $chocolate_torte['fixed'] = [ 'eggs', 'milk' ];
		$chocolate_torte['may_contain'] = [ 'tree-nuts' ];
		$this->assertSame( Outcome::HIDDEN, Outcome::decide( $chocolate_torte, [ 'tree-nuts' ], true ) );
		$this->assertSame( Outcome::SHOWN, Outcome::decide( $chocolate_torte, [ 'tree-nuts' ], false ) );
	}

	// ---- Badges ----------------------------------------------------------------------------

	private array $vegan       = [ 'excluded_groups' => [ 'meat', 'poultry', 'pork', 'beef', 'lamb', 'game', 'fish', 'crustaceans', 'molluscs', 'shellfish', 'animal-derived', 'eggs', 'milk' ], 'hide_may_contain' => false ];
	private array $vegetarian  = [ 'excluded_groups' => [ 'meat', 'poultry', 'pork', 'beef', 'lamb', 'game', 'fish', 'crustaceans', 'molluscs', 'shellfish' ], 'hide_may_contain' => false ];
	private array $pescatarian = [ 'excluded_groups' => [ 'meat', 'poultry', 'pork', 'beef', 'lamb', 'game' ], 'hide_may_contain' => false ];

	/** 17. Prawn linguine: P badge only. */
	public function test_prawn_linguine_is_pescatarian_only(): void {
		$this->assertFalse( Outcome::passes_profile( $this->prawn_linguine, $this->vegan ) );
		$this->assertFalse( Outcome::passes_profile( $this->prawn_linguine, $this->vegetarian ) );
		$this->assertTrue( Outcome::passes_profile( $this->prawn_linguine, $this->pescatarian ) );
	}

	/** A dish that is only vegan via a swap gets no badge — a flag, never a claim. */
	public function test_flagged_dish_does_not_earn_a_badge(): void {
		$porridge = $this->latte;
		$porridge['fixed'] = []; // oats are fine; only the milk is in the way
		$this->assertSame( Outcome::FLAGGED, Outcome::decide( $porridge, $this->vegan['excluded_groups'] ) );
		$this->assertFalse( Outcome::passes_profile( $porridge, $this->vegan ) );
	}

	/** Incomplete data: no badge, however clean the known part looks. */
	public function test_incomplete_dish_never_earns_a_badge(): void {
		$side_salad = $this->incomplete_burger;
		$side_salad['contains'] = $side_salad['fixed'] = [];
		$this->assertFalse( Outcome::passes_profile( $side_salad, $this->vegan ) );
	}
}
