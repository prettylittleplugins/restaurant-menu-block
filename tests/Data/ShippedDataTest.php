<?php
/**
 * Validates the shipped reference data in data/*.json.
 *
 * These files have legal consequences (UK/EU allergen labelling), so every edit to them
 * must keep passing this test. It checks structure and referential integrity only;
 * whether a mapping is *correct* is a job for someone who cooks.
 *
 * @package PrettyLittlePlugins\RestaurantMenuBlock
 */

namespace PrettyLittlePlugins\RestaurantMenuBlock\Tests\Data;

use PHPUnit\Framework\TestCase;

final class ShippedDataTest extends TestCase {

	private const DATA_DIR = __DIR__ . '/../../data/';
	private const SLUG     = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

	/** @var array<int, array> */
	private static array $groups;
	/** @var array<int, array> */
	private static array $ingredients;
	/** @var array<int, array> */
	private static array $profiles;

	/** @var array<string, true> */
	private static array $group_slugs;
	/** @var array<string, array> */
	private static array $by_slug;

	public static function setUpBeforeClass(): void {
		self::$groups      = self::load( 'groups.json' );
		self::$ingredients = self::load( 'ingredients.json' );
		self::$profiles    = self::load( 'profiles.json' );

		self::$group_slugs = array_fill_keys( array_column( self::$groups, 'slug' ), true );
		self::$by_slug     = array_column( self::$ingredients, null, 'slug' );
	}

	private static function load( string $file ): array {
		$json = file_get_contents( self::DATA_DIR . $file );
		self::assertNotFalse( $json, "$file is readable" );
		$data = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
		self::assertIsArray( $data, "$file decodes to an array" );
		self::assertSame( array_keys( $data ), range( 0, count( $data ) - 1 ), "$file is a list, not an object" );
		return $data;
	}

	// ---- groups -----------------------------------------------------------

	public function test_groups_have_required_fields_and_unique_slugs(): void {
		$seen = [];
		foreach ( self::$groups as $g ) {
			$this->assertMatchesRegularExpression( self::SLUG, $g['slug'] ?? '', 'group slug format' );
			$this->assertArrayNotHasKey( $g['slug'], $seen, "duplicate group slug {$g['slug']}" );
			$seen[ $g['slug'] ] = true;
			$this->assertNotSame( '', $g['label'] ?? '', "{$g['slug']}: label" );
			$this->assertContains( $g['type'] ?? '', [ 'legal', 'intolerance', 'choice' ], "{$g['slug']}: type" );
			$this->assertIsInt( $g['sort_order'] ?? null, "{$g['slug']}: sort_order" );
		}
	}

	public function test_the_fourteen_legal_allergens_are_present_in_statutory_order(): void {
		$legal = array_values( array_filter( self::$groups, fn( $g ) => 'legal' === $g['type'] ) );
		usort( $legal, fn( $a, $b ) => $a['sort_order'] <=> $b['sort_order'] );

		$this->assertSame(
			[
				'gluten-cereals',
				'crustaceans',
				'eggs',
				'fish',
				'peanuts',
				'soya',
				'milk',
				'tree-nuts',
				'celery',
				'mustard',
				'sesame',
				'sulphites',
				'lupin',
				'molluscs',
			],
			array_column( $legal, 'slug' ),
			'The 14 allergens under UK FIC / EU 1169/2011, in the order they are listed in Annex II.'
		);
	}

	public function test_group_sort_orders_are_unique(): void {
		$orders = array_column( self::$groups, 'sort_order' );
		$this->assertSame( count( $orders ), count( array_unique( $orders ) ) );
	}

	// ---- ingredients ------------------------------------------------------

	public function test_ingredients_have_required_fields_and_unique_slugs(): void {
		$slugs = array_column( self::$ingredients, 'slug' );
		$dupes = array_keys( array_filter( array_count_values( $slugs ), fn( $n ) => $n > 1 ) );
		$this->assertSame( [], $dupes, 'duplicate ingredient slugs' );

		foreach ( self::$ingredients as $i ) {
			$s = $i['slug'] ?? '';
			$this->assertMatchesRegularExpression( self::SLUG, $s, 'ingredient slug format' );
			$this->assertNotSame( '', $i['label'] ?? '', "$s: label" );
			$this->assertIsArray( $i['synonyms'] ?? null, "$s: synonyms must be an array (may be empty)" );

			// Exactly one of groups / made_of. A compound derives its groups from its parts.
			$this->assertTrue(
				isset( $i['groups'] ) xor isset( $i['made_of'] ),
				"$s: must have exactly one of 'groups' or 'made_of'"
			);
			if ( isset( $i['made_of'] ) ) {
				$this->assertNotEmpty( $i['made_of'], "$s: made_of must not be empty" );
			}
		}
	}

	public function test_ingredient_group_references_exist(): void {
		foreach ( self::$ingredients as $i ) {
			foreach ( array_merge( $i['groups'] ?? [], $i['may_contain'] ?? [] ) as $g ) {
				$this->assertArrayHasKey( $g, self::$group_slugs, "{$i['slug']}: unknown group '$g'" );
			}
		}
	}

	public function test_made_of_and_swap_references_exist(): void {
		foreach ( self::$ingredients as $i ) {
			foreach ( $i['made_of'] ?? [] as $part ) {
				$this->assertArrayHasKey( $part, self::$by_slug, "{$i['slug']}: made_of references unknown ingredient '$part'" );
				$this->assertNotSame( $i['slug'], $part, "{$i['slug']}: made_of references itself" );
			}
			if ( isset( $i['swap'] ) ) {
				$this->assertArrayHasKey( $i['swap'], self::$by_slug, "{$i['slug']}: swap references unknown ingredient '{$i['swap']}'" );
				$this->assertNotSame( $i['slug'], $i['swap'], "{$i['slug']}: swap references itself" );
			}
		}
	}

	public function test_made_of_has_no_cycles(): void {
		foreach ( array_keys( self::$by_slug ) as $slug ) {
			$this->assertNull( $this->find_cycle( $slug, [] ), "cycle through $slug" );
		}
	}

	/** Depth-first walk; returns the first cycle path found, or null. */
	private function find_cycle( string $slug, array $stack ): ?string {
		if ( in_array( $slug, $stack, true ) ) {
			return implode( ' -> ', array_merge( $stack, [ $slug ] ) );
		}
		$stack[] = $slug;
		foreach ( self::$by_slug[ $slug ]['made_of'] ?? [] as $part ) {
			if ( ! isset( self::$by_slug[ $part ] ) ) {
				continue; // reported by the reference test
			}
			$found = $this->find_cycle( $part, $stack );
			if ( null !== $found ) {
				return $found;
			}
		}
		return null;
	}

	public function test_every_ingredient_derives_to_a_group_or_is_deliberately_empty(): void {
		// A compound whose parts all have empty groups is fine (e.g. sorbet = sugar + lemon),
		// but a compound whose parts don't exist, or a dangling made_of, would have been
		// caught above. Here we just make sure derivation terminates for every entry.
		foreach ( array_keys( self::$by_slug ) as $slug ) {
			$this->assertIsArray( $this->derive( $slug ) );
		}
	}

	private function derive( string $slug ): array {
		$def = self::$by_slug[ $slug ];
		if ( isset( $def['groups'] ) ) {
			return $def['groups'];
		}
		$out = [];
		foreach ( $def['made_of'] as $part ) {
			$out = array_merge( $out, $this->derive( $part ) );
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Spot checks on derivation for a few compounds whose mappings carry the most risk.
	 * If one of these fails, a recipe changed; make sure that was on purpose.
	 */
	public function test_known_compound_derivations(): void {
		$this->assertContains( 'fish', $this->derive( 'worcestershire-sauce' ), 'Worcestershire sauce contains anchovy' );
		$this->assertContains( 'gluten-cereals', $this->derive( 'soy-sauce' ), 'soy sauce is brewed with wheat' );
		$this->assertNotContains( 'gluten-cereals', $this->derive( 'tamari' ), 'tamari is the gluten-free swap' );
		$this->assertContains( 'crustaceans', $this->derive( 'red-curry-paste' ), 'Thai curry paste contains shrimp paste' );
		$this->assertContains( 'eggs', $this->derive( 'mayonnaise' ) );
		$this->assertContains( 'celery', $this->derive( 'ketchup' ) );
		$this->assertNotContains( 'alcohol', $this->derive( 'vanilla-extract' ), 'decided: cooked-off alcohol does not flag' );
		$this->assertNotContains( 'eggs', $this->derive( 'ice-cream' ), 'decided: commercial ice cream has no egg' );
		$this->assertNotContains( 'tree-nuts', $this->derive( 'coconut' ), 'coconut is not a tree nut under UK law' );
	}

	// ---- profiles ---------------------------------------------------------

	public function test_profiles_have_required_fields_and_unique_slugs(): void {
		$seen = [];
		foreach ( self::$profiles as $p ) {
			$s = $p['slug'] ?? '';
			$this->assertMatchesRegularExpression( self::SLUG, $s, 'profile slug format' );
			$this->assertArrayNotHasKey( $s, $seen, "duplicate profile slug $s" );
			$seen[ $s ] = true;
			$this->assertNotSame( '', $p['label'] ?? '', "$s: label" );
			$this->assertIsArray( $p['excluded_groups'] ?? null, "$s: excluded_groups" );
			$this->assertNotEmpty( $p['excluded_groups'], "$s: excluded_groups must not be empty" );
			$this->assertIsBool( $p['hide_may_contain'] ?? null, "$s: hide_may_contain" );
			$this->assertArrayHasKey( 'badge', $p, "$s: badge (string or null)" );
		}
	}

	public function test_profile_group_references_exist(): void {
		foreach ( self::$profiles as $p ) {
			foreach ( $p['excluded_groups'] as $g ) {
				$this->assertArrayHasKey( $g, self::$group_slugs, "profile {$p['slug']}: unknown group '$g'" );
			}
		}
	}

	public function test_vegetarian_does_not_exclude_egg_or_dairy(): void {
		// Regression guard: egg and dairy ingredients are tagged animal-derived, so putting that
		// group on vegetarian would hide every dish with cheese or egg in it.
		$by = array_column( self::$profiles, null, 'slug' );
		foreach ( [ 'vegetarian', 'pescatarian' ] as $slug ) {
			$this->assertNotContains( 'animal-derived', $by[ $slug ]['excluded_groups'], "$slug must not exclude animal-derived" );
			$this->assertNotContains( 'eggs', $by[ $slug ]['excluded_groups'] );
			$this->assertNotContains( 'milk', $by[ $slug ]['excluded_groups'] );
		}
		$this->assertContains( 'animal-derived', $by['vegan']['excluded_groups'] );
		$this->assertContains( 'eggs', $by['vegan']['excluded_groups'] );
		$this->assertContains( 'milk', $by['vegan']['excluded_groups'] );
	}

	public function test_medical_profiles_hide_may_contain(): void {
		$by = array_column( self::$profiles, null, 'slug' );
		foreach ( [ 'coeliac', 'dairy-free', 'nut-free', 'egg-free', 'soya-free' ] as $slug ) {
			$this->assertTrue( $by[ $slug ]['hide_may_contain'], "$slug is medical: must hide may-contain" );
		}
	}
}
