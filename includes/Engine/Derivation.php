<?php
/**
 * Derives a dish's allergen/dietary groups from its ingredients.
 *
 * PURE PHP. No WordPress functions anywhere in includes/Engine/. Arrays in, arrays out,
 * so it can be unit-tested with plain PHPUnit and reasoned about on its own.
 *
 * @package PrettyLittlePlugins\RestaurantMenuBlock
 */

namespace PrettyLittlePlugins\RestaurantMenuBlock\Engine;

final class Derivation {

	/**
	 * Resolve one ingredient's groups, following made_of recursively.
	 *
	 * @param string $slug        Ingredient slug.
	 * @param array  $ingredients Map of slug => [ 'groups' => string[], 'made_of' => string[], 'unclassified' => bool ].
	 * @param array  $seen        Cycle guard (internal).
	 * @return array{groups: string[], unclassified: bool}
	 */
	public static function resolve_ingredient( string $slug, array $ingredients, array $seen = [] ): array {
		if ( isset( $seen[ $slug ] ) || ! isset( $ingredients[ $slug ] ) ) {
			// Cycle, or unknown ingredient: fail safe.
			return [ 'groups' => [], 'unclassified' => true ];
		}
		$seen[ $slug ] = true;
		$def           = $ingredients[ $slug ];

		if ( ! empty( $def['unclassified'] ) ) {
			return [ 'groups' => [], 'unclassified' => true ];
		}

		$groups       = $def['groups'] ?? [];
		$unclassified = false;

		foreach ( $def['made_of'] ?? [] as $part ) {
			$resolved     = self::resolve_ingredient( $part, $ingredients, $seen );
			$groups       = array_merge( $groups, $resolved['groups'] );
			$unclassified = $unclassified || $resolved['unclassified'];
		}

		if ( empty( $groups ) && empty( $def['made_of'] ) ) {
			// Neither groups nor a recipe: nothing to derive from.
			$unclassified = true;
		}

		$groups = array_values( array_unique( $groups ) );
		sort( $groups );

		return [ 'groups' => $groups, 'unclassified' => $unclassified ];
	}
}
