<?php
/**
 * Unit tests for the binding resolver itself.
 *
 * @package Feedwright
 */

namespace Feedwright\Tests\Unit;

use Feedwright\Renderer\Context;
use Feedwright\Bindings\ProviderInterface;
use Feedwright\Bindings\Resolver;
use PHPUnit\Framework\TestCase;

/**
 * Minimal in-memory provider used to drive the resolver without WordPress.
 */
final class FakeProvider implements ProviderInterface {

	private string $ns;

	/**
	 * @var array<string,?string> path => value
	 */
	private array $values;

	/**
	 * @param string                $ns     Namespace this provider claims.
	 * @param array<string,?string> $values Mapping path => value.
	 */
	public function __construct( string $ns, array $values ) {
		$this->ns     = $ns;
		$this->values = $values;
	}

	public function namespace_name(): string {
		return $this->ns;
	}

	public function resolve( string $path, string $modifier, Context $ctx ): ?string {
		if ( '' !== $modifier ) {
			$path .= ':' . $modifier;
		}
		return $this->values[ $path ] ?? null;
	}

	public function describe(): array {
		return array();
	}
}

final class BindingResolverTest extends TestCase {

	private function ctx(): Context {
		// Resolver tests never touch the WP runtime, but Context's signature
		// requires a real WP_Post. Build one from a stdClass so this works
		// against both the real WP_Post (CI integration bootstrap) and a
		// minimal stub (Unit-only local runs).
		if ( ! class_exists( 'WP_Post' ) ) {
			eval(
				'final class WP_Post { public int $ID = 0; public string $post_title = ""; public string $post_content = ""; public string $post_modified_gmt = ""; '
				. 'public function __construct( $stub = null ) { if ( null !== $stub ) { foreach ( get_object_vars( (object) $stub ) as $k => $v ) { $this->$k = $v; } } } }'
			);
		}
		$stub                    = new \stdClass();
		$stub->ID                = 0;
		$stub->post_title        = '';
		$stub->post_content      = '';
		$stub->post_modified_gmt = '';

		$post = new \WP_Post( $stub );
		$dom  = new \DOMDocument();
		return new Context( $post, $dom, array() );
	}

	public function test_simple_substitution(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'foo', array( 'bar' => 'BAR' ) ) );
		$this->assertSame( 'before BAR after', $r->resolve( 'before {{foo.bar}} after', $this->ctx() ) );
	}

	public function test_namespace_only_binding(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'now', array( '' => '2026-04-27' ) ) );
		$this->assertSame( '2026-04-27', $r->resolve( '{{now}}', $this->ctx() ) );
	}

	public function test_modifier_appended(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'post', array( 'post_date:r' => 'Mon, 27 Apr 2026 10:00:00 +0900' ) ) );
		$this->assertSame(
			'Mon, 27 Apr 2026 10:00:00 +0900',
			$r->resolve( '{{post.post_date:r}}', $this->ctx() )
		);
	}

	public function test_unknown_provider_resolves_to_empty(): void {
		$r = new Resolver();
		// The binding disappears; surrounding whitespace is left as-is.
		$this->assertSame( 'X  Y', $r->resolve( 'X {{unknown.path}} Y', $this->ctx() ) );
	}

	public function test_provider_returning_null_resolves_to_empty(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'foo', array() ) );
		$this->assertSame( '[]', $r->resolve( '[{{foo.bar}}]', $this->ctx() ) );
	}

	public function test_escape_sequence(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'foo', array( 'bar' => 'BAR' ) ) );
		$this->assertSame(
			'literal {{foo.bar}} and BAR',
			$r->resolve( 'literal \{{foo.bar}} and {{foo.bar}}', $this->ctx() )
		);
	}

	public function test_multiple_bindings_in_one_string(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'option', array( 'home_url' => 'https://example.com/' ) ) );
		$r->add( new FakeProvider( 'feed', array( 'slug' => 'demo' ) ) );
		$this->assertSame(
			'https://example.com/feeds/demo/',
			$r->resolve( '{{option.home_url}}feeds/{{feed.slug}}/', $this->ctx() )
		);
	}

	public function test_empty_input_returns_empty(): void {
		$r = new Resolver();
		$this->assertSame( '', $r->resolve( '', $this->ctx() ) );
	}

	public function test_truncate_processor_cuts_to_length(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'foo', array( 'bar' => '0123456789' ) ) );
		$this->assertSame( '01234', $r->resolve( '{{foo.bar|truncate:5}}', $this->ctx() ) );
	}

	public function test_truncate_processor_passes_through_when_short(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'foo', array( 'bar' => 'hi' ) ) );
		$this->assertSame( 'hi', $r->resolve( '{{foo.bar|truncate:50}}', $this->ctx() ) );
	}

	public function test_truncate_zero_or_negative_is_noop(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'foo', array( 'bar' => 'hello' ) ) );
		$this->assertSame( 'hello', $r->resolve( '{{foo.bar|truncate:0}}', $this->ctx() ) );
		$this->assertSame( 'hello', $r->resolve( '{{foo.bar|truncate:-3}}', $this->ctx() ) );
	}

	public function test_unknown_processor_passes_through(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'foo', array( 'bar' => 'value' ) ) );
		$this->assertSame( 'value', $r->resolve( '{{foo.bar|nonsense:1}}', $this->ctx() ) );
	}

	public function test_chained_processors_apply_in_order(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'foo', array( 'bar' => '0123456789' ) ) );
		$this->assertSame( '012', $r->resolve( '{{foo.bar|truncate:5|truncate:3}}', $this->ctx() ) );
	}

	public function test_modifier_and_processor_combine(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'foo', array( 'bar:r' => 'Mon, 27 Apr 2026 10:00:00 +0900' ) ) );
		$this->assertSame( 'Mon, 27 Apr', $r->resolve( '{{foo.bar:r|truncate:11}}', $this->ctx() ) );
	}

	public function test_map_processor_returns_matched_value(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'foo', array( 'status' => 'publish' ) ) );
		$this->assertSame( '1', $r->resolve( '{{foo.status|map:publish=1,*=0}}', $this->ctx() ) );
	}

	public function test_map_processor_returns_fallback_when_no_match(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'foo', array( 'status' => 'trash' ) ) );
		$this->assertSame( '0', $r->resolve( '{{foo.status|map:publish=1,*=0}}', $this->ctx() ) );
	}

	public function test_map_processor_returns_empty_when_no_match_no_fallback(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'foo', array( 'status' => 'trash' ) ) );
		$this->assertSame( '', $r->resolve( '{{foo.status|map:publish=1}}', $this->ctx() ) );
	}

	public function test_map_processor_trims_keys_and_values(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'foo', array( 'level' => 'high' ) ) );
		$this->assertSame( 'top', $r->resolve( '{{foo.level|map: high = top , * = bottom }}', $this->ctx() ) );
	}

	public function test_map_processor_value_can_contain_equals(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'foo', array( 'k' => 'a' ) ) );
		$this->assertSame( 'x=y', $r->resolve( '{{foo.k|map:a=x=y}}', $this->ctx() ) );
	}

	public function test_map_processor_chains_with_other_processors(): void {
		$r = new Resolver();
		$r->add( new FakeProvider( 'foo', array( 'k' => 'publish' ) ) );
		// map then truncate (no-op since '1' is already 1 char).
		$this->assertSame( '1', $r->resolve( '{{foo.k|map:publish=1,*=0|truncate:5}}', $this->ctx() ) );
	}
}
