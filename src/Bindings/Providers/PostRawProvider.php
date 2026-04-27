<?php
/**
 * Binding provider for `post_raw.*` — raw WP_Post fields.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Bindings\Providers;

use Feedwright\Renderer\Context;
use Feedwright\Bindings\ProviderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §14.4 `post_raw.*`. Returns null outside item context.
 */
final class PostRawProvider implements ProviderInterface {

	private const RAW_FIELDS = array(
		'ID',
		'post_title',
		'post_content',
		'post_excerpt',
		'post_status',
		'post_name',
		'post_author',
		'post_parent',
		'post_type',
		'menu_order',
		'guid',
	);

	private const DATE_FIELDS = array(
		'post_date',
		'post_date_gmt',
		'post_modified',
		'post_modified_gmt',
	);

	/**
	 * {@inheritDoc}
	 */
	public function namespace_name(): string {
		return 'post_raw';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string  $path     Binding path.
	 * @param string  $modifier Optional date format for date columns.
	 * @param Context $ctx      Render context.
	 */
	public function resolve( string $path, string $modifier, Context $ctx ): ?string {
		$post = $ctx->current_post();
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		if ( in_array( $path, self::RAW_FIELDS, true ) ) {
			$value = $post->{$path} ?? '';
			return is_scalar( $value ) ? (string) $value : '';
		}

		if ( in_array( $path, self::DATE_FIELDS, true ) ) {
			$raw = (string) $post->{$path};
			if ( '' === $raw || '0000-00-00 00:00:00' === $raw ) {
				return '';
			}
			if ( '' === $modifier ) {
				return $raw;
			}
			$is_gmt    = str_ends_with( $path, '_gmt' );
			$timezone  = $is_gmt ? new \DateTimeZone( 'UTC' ) : wp_timezone();
			$date_time = date_create( $raw, $timezone );
			if ( false === $date_time ) {
				return $raw;
			}
			return (string) wp_date( $modifier, $date_time->getTimestamp(), $timezone );
		}

		return null;
	}

	/**
	 * Binding catalogue for this provider.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function describe(): array {
		$rows = array();
		foreach ( self::RAW_FIELDS as $field ) {
			$rows[] = array(
				'expression' => "post_raw.{$field}",
				'label'      => "Raw {$field}",
				'context'    => 'item',
				'namespace'  => 'post_raw',
			);
		}
		foreach ( self::DATE_FIELDS as $field ) {
			$rows[] = array(
				'expression' => "post_raw.{$field}",
				'label'      => "Raw {$field}",
				'context'    => 'item',
				'namespace'  => 'post_raw',
			);
		}
		return $rows;
	}
}
