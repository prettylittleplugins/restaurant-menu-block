<?php
/**
 * Seeds the shipped groups and ingredients (data/*.json) into their taxonomies.
 *
 * Idempotent: terms are matched by slug, shipped terms are flagged with the
 * prettylp_shipped term meta, and only flagged terms are ever updated. Terms the
 * site owner adds themselves are never touched. Terms removed from a later
 * data file are left in place (dishes may still reference them) and reported.
 *
 * @package PrettyLittlePlugins\RestaurantMenuBlock
 */

namespace PrettyLittlePlugins\RestaurantMenuBlock;

defined( 'ABSPATH' ) || exit;

final class Seeder {

	/** Option holding the hash of the data files last seeded. */
	public const VERSION_OPTION = 'prettylp_data_version';

	/** Term meta flag marking a term as shipped (and therefore ours to update). */
	public const SHIPPED_META = 'prettylp_shipped';

	/**
	 * Hash of the shipped data files. Changes whenever either file changes,
	 * so "data changed" and "needs reseeding" can never drift apart.
	 */
	public static function data_version(): string {
		return md5(
			(string) self::read( 'groups.json' ) . (string) self::read( 'ingredients.json' )
		);
	}

	/** True when the stored stamp is behind the shipped files (or no seed has run). */
	public static function needs_seed(): bool {
		return get_option( self::VERSION_OPTION, '' ) !== self::data_version();
	}

	/** Seeds only if the data has changed since the last run. */
	public static function maybe_seed(): array {
		if ( ! self::needs_seed() ) {
			return [ 'skipped' => true ];
		}
		return self::seed();
	}

	/**
	 * Seeds unconditionally. Groups first so every ingredient reference resolves.
	 *
	 * @param bool $dry_run Report what would change without writing.
	 * @return array{groups: array, ingredients: array, warnings: string[], skipped: bool}
	 */
	public static function seed( bool $dry_run = false ): array {
		$groups      = self::load( 'groups.json' );
		$ingredients = self::load( 'ingredients.json' );
		$warnings    = [];

		$group_slugs      = array_column( $groups, 'slug' );
		$ingredient_slugs = array_column( $ingredients, 'slug' );

		// Dangling references are a data bug; say so rather than seed them silently.
		foreach ( $ingredients as $ing ) {
			foreach ( [ 'groups', 'may_contain' ] as $key ) {
				foreach ( array_diff( $ing[ $key ] ?? [], $group_slugs ) as $missing ) {
					$warnings[] = sprintf( '%s: unknown group "%s" in %s', $ing['slug'], $missing, $key );
				}
			}
			foreach ( array_diff( $ing['made_of'] ?? [], $ingredient_slugs ) as $missing ) {
				$warnings[] = sprintf( '%s: unknown ingredient "%s" in made_of', $ing['slug'], $missing );
			}
			if ( ! empty( $ing['swap'] ) && ! in_array( $ing['swap'], $ingredient_slugs, true ) ) {
				$warnings[] = sprintf( '%s: unknown ingredient "%s" in swap', $ing['slug'], $ing['swap'] );
			}
		}

		$group_result = self::seed_taxonomy(
			Taxonomies::GROUP,
			$groups,
			static fn( array $g ): array => [
				'prettylp_type'  => (string) ( $g['type'] ?? '' ),
				'prettylp_order' => (int) ( $g['sort_order'] ?? 0 ),
			],
			$dry_run
		);

		$ingredient_result = self::seed_taxonomy(
			Taxonomies::INGREDIENT,
			$ingredients,
			static fn( array $i ): array => [
				'prettylp_groups'      => array_values( $i['groups'] ?? [] ),
				'prettylp_made_of'     => array_values( $i['made_of'] ?? [] ),
				'prettylp_may_contain' => array_values( $i['may_contain'] ?? [] ),
				'prettylp_synonyms'    => array_values( $i['synonyms'] ?? [] ),
				'prettylp_swap'        => (string) ( $i['swap'] ?? '' ),
				// Shipped data is always classified; the flag exists for site-added ingredients.
				'prettylp_unclassified' => false,
			],
			$dry_run
		);

		if ( ! $dry_run ) {
			update_option( self::VERSION_OPTION, self::data_version(), false );
		}

		return [
			'groups'      => $group_result,
			'ingredients' => $ingredient_result,
			'warnings'    => $warnings,
			'skipped'     => false,
		];
	}

	/**
	 * Inserts or updates one taxonomy's shipped terms.
	 *
	 * @param string   $taxonomy  Taxonomy name.
	 * @param array    $items     Decoded JSON list; each needs slug and label.
	 * @param callable $meta_for  Maps one item to [ meta_key => value ].
	 * @param bool     $dry_run   Report only.
	 * @return array{created: string[], updated: string[], unchanged: string[], skipped: string[], orphaned: string[]}
	 */
	private static function seed_taxonomy( string $taxonomy, array $items, callable $meta_for, bool $dry_run ): array {
		$result = [
			'created'   => [],
			'updated'   => [],
			'unchanged' => [],
			'skipped'   => [], // Exists but isn't ours (site-added term with a clashing slug).
			'orphaned'  => [], // Shipped previously, no longer in the data file.
		];

		$seen = [];

		foreach ( $items as $item ) {
			$slug        = (string) $item['slug'];
			$label       = (string) $item['label'];
			$description = (string) ( $item['notes'] ?? '' );
			$meta        = $meta_for( $item );
			$seen[ $slug ] = true;

			$term = get_term_by( 'slug', $slug, $taxonomy );

			if ( ! $term ) {
				$result['created'][] = $slug;
				if ( $dry_run ) {
					continue;
				}
				$inserted = wp_insert_term( $label, $taxonomy, [ 'slug' => $slug, 'description' => $description ] );
				if ( is_wp_error( $inserted ) ) {
					// Surface it loudly: a missing group makes every dish that uses it wrong.
					throw new \RuntimeException( sprintf( '%s/%s: %s', $taxonomy, $slug, $inserted->get_error_message() ) );
				}
				self::write_meta( (int) $inserted['term_id'], $meta );
				continue;
			}

			if ( ! get_term_meta( $term->term_id, self::SHIPPED_META, true ) ) {
				$result['skipped'][] = $slug;
				continue;
			}

			$changed = $term->name !== $label || $term->description !== $description;
			foreach ( $meta as $key => $value ) {
				if ( ! self::meta_equals( get_term_meta( $term->term_id, $key, true ), $value ) ) {
					$changed = true;
					break;
				}
			}

			if ( ! $changed ) {
				$result['unchanged'][] = $slug;
				continue;
			}

			$result['updated'][] = $slug;
			if ( $dry_run ) {
				continue;
			}
			wp_update_term( $term->term_id, $taxonomy, [ 'name' => $label, 'description' => $description ] );
			self::write_meta( $term->term_id, $meta );
		}

		// Shipped terms no longer in the file: report, never delete.
		$shipped = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'meta_key'   => self::SHIPPED_META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => '1',                // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'     => 'slugs',
			]
		);
		if ( is_array( $shipped ) ) {
			$result['orphaned'] = array_values( array_diff( $shipped, array_keys( $seen ) ) );
		}

		return $result;
	}

	/**
	 * Stored meta comes back as strings ("12", "" for false), so compare loosely by type.
	 */
	private static function meta_equals( mixed $stored, mixed $wanted ): bool {
		if ( is_array( $wanted ) ) {
			return is_array( $stored ) && array_values( $stored ) === array_values( $wanted );
		}
		return (string) $stored === (string) $wanted;
	}

	private static function write_meta( int $term_id, array $meta ): void {
		foreach ( $meta as $key => $value ) {
			update_term_meta( $term_id, $key, $value );
		}
		update_term_meta( $term_id, self::SHIPPED_META, 1 );
	}

	private static function read( string $file ): string|false {
		return file_get_contents( PRETTYLP_RMB_DIR . 'data/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/** @return array<int, array> */
	private static function load( string $file ): array {
		$data = json_decode( (string) self::read( $file ), true );
		if ( ! is_array( $data ) ) {
			throw new \RuntimeException( "data/$file is missing or not valid JSON." );
		}
		return $data;
	}
}
