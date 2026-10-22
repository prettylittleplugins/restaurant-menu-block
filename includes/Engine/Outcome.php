<?php
/**
 * Decides what one customer sees for one dish, given what they are avoiding.
 *
 * PURE PHP. This is THE rule the front-end JavaScript must mirror exactly; the PHP
 * is the reference and the tests pin it. Badges (V, VE, P) are this same rule run
 * against a profile's groups. Halal / kosher never come through here — they are an
 * explicit certification checkbox, not derived.
 *
 * @package PrettyLittlePlugins\RestaurantMenuBlock
 */

namespace PrettyLittlePlugins\RestaurantMenuBlock\Engine;

final class Outcome {

	public const SHOWN   = 'shown';   // Nothing the customer avoids is in the dish.
	public const FLAGGED = 'flagged'; // Shown with "available with X — ask about preparation".
	public const HIDDEN  = 'hidden';  // Contains something they avoid, and no swap rescues it.
	public const NO_DATA = 'no_data'; // Ingredient data incomplete; hidden while any filter is on.

	/**
	 * @param array    $derived          Output of Derivation::derive_dish().
	 * @param string[] $excluded         Groups the customer is avoiding.
	 * @param bool     $hide_may_contain Treat "may contain" as "contains" (medical profiles).
	 */
	public static function decide( array $derived, array $excluded, bool $hide_may_contain = false ): string {
		if ( empty( $excluded ) ) {
			// No filter active: everyone sees everything, incomplete dishes included.
			return self::SHOWN;
		}

		// 1. Something unavoidable in the dish → hidden. Checked before completeness so
		//    the "why hidden" reason can say "contains milk" even on a half-entered dish.
		if ( self::hits( $derived['fixed'], $excluded ) ) {
			return self::HIDDEN;
		}
		if ( $hide_may_contain && self::hits( $derived['may_contain'], $excluded ) ) {
			// May-contain risks are never treated as swappable in v1.
			return self::HIDDEN;
		}

		// 2. We don't know everything that's in it → fail safe.
		if ( empty( $derived['complete'] ) ) {
			return self::NO_DATA;
		}

		// 3. Only a swappable group is in the way. The swap rescues the dish unless what
		//    it brings in is also on the avoid list (GF bun → egg; oat milk → may contain gluten).
		if ( self::hits( $derived['swappable'], $excluded ) ) {
			if ( self::hits( $derived['swap_adds'], $excluded ) ) {
				return self::HIDDEN;
			}
			if ( $hide_may_contain && self::hits( $derived['swap_may_contain'], $excluded ) ) {
				return self::HIDDEN;
			}
			return self::FLAGGED;
		}

		return self::SHOWN;
	}

	/**
	 * Does this dish earn a profile's badge (V, VE, P)?
	 *
	 * Deliberately stricter than decide(): a flagged dish is NOT badged — "vegan if you
	 * ask for oat milk" is not a vegan badge. Incomplete data → no badge, same as the filter.
	 *
	 * @param array $derived Output of Derivation::derive_dish().
	 * @param array $profile [ 'excluded_groups' => string[], 'hide_may_contain' => bool ].
	 */
	public static function passes_profile( array $derived, array $profile ): bool {
		if ( empty( $derived['complete'] ) ) {
			return false;
		}
		$excluded = (array) ( $profile['excluded_groups'] ?? [] );
		if ( empty( $excluded ) ) {
			return false; // A profile that excludes nothing is not a claim worth badging.
		}
		return self::SHOWN === self::decide( $derived, $excluded, ! empty( $profile['hide_may_contain'] ) );
	}

	/** Groups a customer would be told about if the dish is hidden: "contains milk, eggs". */
	public static function reasons( array $derived, array $excluded ): array {
		$reasons = array_values( array_intersect( $derived['fixed'], $excluded ) );
		sort( $reasons );
		return $reasons;
	}

	private static function hits( array $groups, array $excluded ): bool {
		return [] !== array_intersect( $groups, $excluded );
	}
}
