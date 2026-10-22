<?php
/**
 * Derives a dish's allergen/dietary groups from its ingredients.
 *
 * PURE PHP. No WordPress functions anywhere in includes/Engine/. Arrays in, arrays out,
 * so it can be unit-tested with plain PHPUnit and reasoned about on its own.
 *
 * Vocabulary used throughout:
 *   - "as served"  : the dish with the ingredients the kitchen puts on the plate by default.
 *   - "with swaps" : the dish with every applicable swap applied (one alternative build,
 *                    not every combination — see derive_dish()).
 *   - fixed        : groups present both as served and with swaps. Cannot be avoided.
 *   - swappable    : groups present as served but gone once swaps are applied.
 *   - swap_adds    : groups NOT present as served but introduced by a swap
 *                    (gluten-free bun brings egg). Needed so a swap that fixes one
 *                    problem and causes another is never offered as a rescue.
 *
 * @package PrettyLittlePlugins\RestaurantMenuBlock
 */

namespace PrettyLittlePlugins\RestaurantMenuBlock\Engine;

final class Derivation {

	/**
	 * Resolve one ingredient's groups, following made_of recursively.
	 *
	 * An ingredient is unclassified ONLY when its definition says so (or it is unknown,
	 * or part of a cycle). An ingredient with no groups and no recipe is a perfectly
	 * good "contains nothing we track" — carrot, olive oil, salt. 140 of the 395
	 * shipped ingredients are like that; they must not fail safe.
	 *
	 * Swaps are not applied here: a swap is a dish-level choice ("can I have the
	 * oat milk?"), not something that happens inside a recipe.
	 *
	 * @param string $slug        Ingredient slug.
	 * @param array  $ingredients Map of slug => [ 'groups' => string[], 'made_of' => string[],
	 *                            'may_contain' => string[], 'swap' => string, 'unclassified' => bool ].
	 * @param array  $seen        Cycle guard (internal).
	 * @return array{groups: string[], may_contain: string[], unclassified: bool}
	 */
	public static function resolve_ingredient( string $slug, array $ingredients, array $seen = [] ): array {
		if ( isset( $seen[ $slug ] ) || ! isset( $ingredients[ $slug ] ) ) {
			// Cycle, or unknown ingredient: fail safe.
			return [ 'groups' => [], 'may_contain' => [], 'unclassified' => true ];
		}
		$seen[ $slug ] = true;
		$def           = $ingredients[ $slug ];

		if ( ! empty( $def['unclassified'] ) ) {
			return [ 'groups' => [], 'may_contain' => [], 'unclassified' => true ];
		}

		$groups       = (array) ( $def['groups'] ?? [] );
		$may_contain  = (array) ( $def['may_contain'] ?? [] );
		$unclassified = false;

		foreach ( (array) ( $def['made_of'] ?? [] ) as $part ) {
			$resolved     = self::resolve_ingredient( $part, $ingredients, $seen );
			$groups       = array_merge( $groups, $resolved['groups'] );
			$may_contain  = array_merge( $may_contain, $resolved['may_contain'] );
			$unclassified = $unclassified || $resolved['unclassified'];
		}

		return [
			'groups'       => self::set( $groups ),
			'may_contain'  => self::set( $may_contain ),
			'unclassified' => $unclassified,
		];
	}

	/**
	 * Derive everything the front end and the badge logic need for one dish.
	 *
	 * @param array $dish        [ 'ingredients' => string[] slugs,
	 *                             'may_contain'  => string[] groups ticked on the dish itself,
	 *                             'swap_opt_out' => string[] ingredient slugs whose swap this dish refuses ].
	 * @param array $ingredients Same map as resolve_ingredient().
	 * @return array{
	 *   complete: bool,
	 *   contains: string[],
	 *   fixed: string[],
	 *   swappable: string[],
	 *   swap_adds: string[],
	 *   may_contain: string[],
	 *   swap_may_contain: string[],
	 *   swaps: array<int, array{from: string, to: string, removes: string[], adds: string[]}>
	 * }
	 */
	public static function derive_dish( array $dish, array $ingredients ): array {
		$slugs   = array_values( array_unique( (array) ( $dish['ingredients'] ?? [] ) ) );
		$opt_out = array_fill_keys( (array) ( $dish['swap_opt_out'] ?? [] ), true );

		// 1. As served.
		$complete    = true;
		$contains    = [];
		$may_contain = (array) ( $dish['may_contain'] ?? [] );
		$resolved    = [];

		foreach ( $slugs as $slug ) {
			$r                 = self::resolve_ingredient( $slug, $ingredients );
			$resolved[ $slug ] = $r;
			$contains          = array_merge( $contains, $r['groups'] );
			$may_contain       = array_merge( $may_contain, $r['may_contain'] );
			$complete          = $complete && ! $r['unclassified'];
		}
		$contains    = self::set( $contains );
		$may_contain = self::set( $may_contain );

		// 2. Which swaps actually apply on this dish.
		//    Not applicable (so the ingredient counts as fixed) when: the dish opted out,
		//    the swap target doesn't exist on this site, or the target is itself unclassified.
		//    An unusable swap does NOT make the dish incomplete — what's on the plate is known.
		$applied = []; // from-slug => to-slug
		foreach ( $slugs as $slug ) {
			$to = (string) ( $ingredients[ $slug ]['swap'] ?? '' );
			if ( '' === $to || isset( $opt_out[ $slug ] ) || ! isset( $ingredients[ $to ] ) ) {
				continue;
			}
			$target = self::resolve_ingredient( $to, $ingredients );
			if ( $target['unclassified'] ) {
				continue;
			}
			$applied[ $slug ]  = $to;
			$resolved[ $to ] ??= $target;
		}

		// 3. With swaps: the same dish, every applicable swap made at once.
		$swapped_groups      = [];
		$swapped_may_contain = (array) ( $dish['may_contain'] ?? [] );
		foreach ( $slugs as $slug ) {
			$use                 = $applied[ $slug ] ?? $slug;
			$swapped_groups      = array_merge( $swapped_groups, $resolved[ $use ]['groups'] );
			$swapped_may_contain = array_merge( $swapped_may_contain, $resolved[ $use ]['may_contain'] );
		}
		$swapped_groups      = self::set( $swapped_groups );
		$swapped_may_contain = self::set( $swapped_may_contain );

		$fixed            = self::set( array_intersect( $contains, $swapped_groups ) );
		$swappable        = self::set( array_diff( $contains, $swapped_groups ) );
		$swap_adds        = self::set( array_diff( $swapped_groups, $contains ) );
		$swap_may_contain = self::set( array_diff( $swapped_may_contain, $may_contain ) );

		// 4. Per-swap notes, so the flag can say "available with gluten-free bun".
		$swaps = [];
		foreach ( $applied as $from => $to ) {
			$swaps[] = [
				'from'    => $from,
				'to'      => $to,
				'removes' => self::set( array_intersect( $resolved[ $from ]['groups'], $swappable ) ),
				'adds'    => self::set( array_intersect( $resolved[ $to ]['groups'], $swap_adds ) ),
			];
		}

		return [
			'complete'         => $complete,
			'contains'         => $contains,
			'fixed'            => $fixed,
			'swappable'        => $swappable,
			'swap_adds'        => $swap_adds,
			'may_contain'      => $may_contain,
			'swap_may_contain' => $swap_may_contain,
			'swaps'            => $swaps,
		];
	}

	/** Unique, sorted, re-indexed. Every list the engine emits goes through this so comparisons are stable. */
	private static function set( array $items ): array {
		$items = array_values( array_unique( $items ) );
		sort( $items );
		return $items;
	}
}
