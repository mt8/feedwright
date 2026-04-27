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
}
