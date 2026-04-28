<?php
/**
 * Unit tests for the XML sanitization helpers.
 *
 * @package Feedwright
 */

namespace Feedwright\Tests\Unit;

use Feedwright\Renderer\Sanitize;
use PHPUnit\Framework\TestCase;

final class SanitizeTest extends TestCase {

	public function test_valid_xml_names(): void {
		foreach ( array( 'title', 'pubDate', 'media:thumbnail', 'ext:logo', 'dc_creator', 'a-b' ) as $name ) {
			$this->assertTrue( Sanitize::is_valid_xml_name( $name ), "{$name} must be valid" );
		}
	}

	public function test_invalid_xml_names(): void {
		foreach ( array( '', '123foo', 'foo bar', 'a:b:c', 'a:', ':b' ) as $name ) {
			$this->assertFalse( Sanitize::is_valid_xml_name( $name ), "{$name} must be invalid" );
		}
	}

	public function test_xml_chars_strips_control_characters(): void {
		$input = "Hello\x00\x01World\x08";
		$this->assertSame( 'HelloWorld', Sanitize::xml_chars( $input ) );
	}

	public function test_xml_chars_keeps_tab_newline_cr(): void {
		$input = "A\tB\nC\rD";
		$this->assertSame( $input, Sanitize::xml_chars( $input ) );
	}

	public function test_normalize_mode_defaults_to_strict(): void {
		$this->assertSame( Sanitize::MODE_STRICT, Sanitize::normalize_mode( '' ) );
		$this->assertSame( Sanitize::MODE_STRICT, Sanitize::normalize_mode( 'gibberish' ) );
		$this->assertSame( Sanitize::MODE_STRICT, Sanitize::normalize_mode( 'strict' ) );
		$this->assertSame( Sanitize::MODE_COMPAT, Sanitize::normalize_mode( 'compat' ) );
	}

	public function test_build_text_nodes_compat_returns_single_text_node(): void {
		$dom   = new \DOMDocument();
		$nodes = Sanitize::build_text_nodes( $dom, 'plain text', Sanitize::MODE_COMPAT );
		$this->assertCount( 1, $nodes );
		$this->assertInstanceOf( \DOMText::class, $nodes[0] );
		$this->assertSame( 'plain text', $nodes[0]->nodeValue );
	}

	public function test_build_text_nodes_strict_with_no_special_chars(): void {
		$dom   = new \DOMDocument();
		$nodes = Sanitize::build_text_nodes( $dom, 'plain text', Sanitize::MODE_STRICT );
		$this->assertCount( 1, $nodes );
		$this->assertInstanceOf( \DOMText::class, $nodes[0] );
	}

	public function test_build_text_nodes_strict_splits_quotes_and_apostrophes(): void {
		$dom   = new \DOMDocument();
		$nodes = Sanitize::build_text_nodes( $dom, 'a "b" c \'d\' e', Sanitize::MODE_STRICT );
		// Expect: text("a "), entityref(quot), text("b"), entityref(quot),
		// text(" c "), entityref(apos), text("d"), entityref(apos), text(" e")
		$this->assertCount( 9, $nodes );
		$this->assertInstanceOf( \DOMEntityReference::class, $nodes[1] );
		$this->assertSame( 'quot', $nodes[1]->nodeName );
		$this->assertInstanceOf( \DOMEntityReference::class, $nodes[3] );
		$this->assertSame( 'quot', $nodes[3]->nodeName );
		$this->assertInstanceOf( \DOMEntityReference::class, $nodes[5] );
		$this->assertSame( 'apos', $nodes[5]->nodeName );
		$this->assertInstanceOf( \DOMEntityReference::class, $nodes[7] );
		$this->assertSame( 'apos', $nodes[7]->nodeName );
	}

	public function test_build_text_nodes_returns_empty_for_empty_input(): void {
		$dom = new \DOMDocument();
		$this->assertSame( array(), Sanitize::build_text_nodes( $dom, '', Sanitize::MODE_STRICT ) );
		$this->assertSame( array(), Sanitize::build_text_nodes( $dom, '', Sanitize::MODE_COMPAT ) );
	}

	public function test_build_text_nodes_strict_serializes_to_entities_in_xml(): void {
		// End-to-end: build nodes, attach to a DOM, save XML, verify entities
		// survive the libxml round-trip.
		$dom  = new \DOMDocument( '1.0', 'UTF-8' );
		$root = $dom->createElement( 'r' );
		$dom->appendChild( $root );
		foreach ( Sanitize::build_text_nodes( $dom, 'a "b" \'c\' &amp', Sanitize::MODE_STRICT ) as $node ) {
			$root->appendChild( $node );
		}
		$xml = (string) $dom->saveXML();
		$this->assertStringContainsString( '<r>a &quot;b&quot; &apos;c&apos; &amp;amp</r>', $xml );
	}
}
