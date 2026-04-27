<?php
/**
 * Binding provider contract.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Bindings;

use Feedwright\Renderer\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Each provider owns one binding namespace ("post", "option", ...).
 *
 * Spec §14.3.
 */
interface ProviderInterface {

	/**
	 * Binding namespace this provider claims (e.g. "post", "option").
	 */
	public function namespace_name(): string;

	/**
	 * Resolve a binding to its string value.
	 *
	 * @param string  $path     Path part (e.g. "post_title", "thumbnail_url").
	 *                          May be empty for namespace-only bindings like `now`.
	 * @param string  $modifier Modifier after the colon (may itself contain colons).
	 * @param Context $ctx      Render context.
	 * @return string|null      Null when the binding cannot be resolved (renders as empty string).
	 */
	public function resolve( string $path, string $modifier, Context $ctx ): ?string;

	/**
	 * Binding catalogue exposed via REST for editor autocompletion.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function describe(): array;
}
