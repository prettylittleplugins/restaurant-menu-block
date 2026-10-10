<?php

namespace PrettyLittlePlugins\RestaurantMenuBlock\Tests\Engine;

use PHPUnit\Framework\TestCase;
use PrettyLittlePlugins\RestaurantMenuBlock\Engine\Derivation;

final class DerivationTest extends TestCase {

	private array $ingredients = [
		'peanuts'     => [ 'groups' => [ 'peanuts' ] ],
		'soy-sauce'   => [ 'groups' => [ 'soya', 'gluten' ] ],
		'satay-sauce' => [ 'made_of' => [ 'peanuts', 'soy-sauce' ] ],
		'mystery'     => [ 'unclassified' => true ],
		'loop-a'      => [ 'made_of' => [ 'loop-b' ] ],
		'loop-b'      => [ 'made_of' => [ 'loop-a' ] ],
	];

	public function test_direct_groups(): void {
		$r = Derivation::resolve_ingredient( 'peanuts', $this->ingredients );
		$this->assertSame( [ 'peanuts' ], $r['groups'] );
		$this->assertFalse( $r['unclassified'] );
	}

	public function test_compound_resolves_recursively(): void {
		$r = Derivation::resolve_ingredient( 'satay-sauce', $this->ingredients );
		$this->assertSame( [ 'gluten', 'peanuts', 'soya' ], $r['groups'] );
		$this->assertFalse( $r['unclassified'] );
	}

	public function test_unclassified_fails_safe(): void {
		$r = Derivation::resolve_ingredient( 'mystery', $this->ingredients );
		$this->assertTrue( $r['unclassified'] );
	}

	public function test_cycle_does_not_hang(): void {
		$r = Derivation::resolve_ingredient( 'loop-a', $this->ingredients );
		$this->assertTrue( $r['unclassified'] );
	}
}
