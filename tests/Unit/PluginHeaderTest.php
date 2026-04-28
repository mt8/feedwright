<?php
/**
 * Sanity test: plugin header parses to the expected metadata.
 *
 * @package Feedwright
 */

namespace Feedwright\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PluginHeaderTest extends TestCase {

	/**
	 * @return array<string,string>
	 */
	private function read_header(): array {
		$file   = file_get_contents( dirname( __DIR__, 2 ) . '/feedwright.php' );
		$fields = array(
			'Plugin Name',
			'Version',
			'Requires PHP',
			'Requires at least',
			'Text Domain',
			'License',
		);
		$out    = array();
		foreach ( $fields as $key ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $key, '/' ) . ':\s*(.+)$/mi', $file, $m ) ) {
				$out[ $key ] = trim( $m[1] );
			}
		}
		return $out;
	}

	public function test_plugin_header_metadata(): void {
		$header = $this->read_header();
		$this->assertSame( 'Feedwright', $header['Plugin Name'] ?? '' );
		$this->assertSame( '0.2.1', $header['Version'] ?? '' );
		$this->assertSame( '8.3', $header['Requires PHP'] ?? '' );
		$this->assertSame( '6.5', $header['Requires at least'] ?? '' );
		$this->assertSame( 'feedwright', $header['Text Domain'] ?? '' );
		$this->assertSame( 'GPL-2.0-or-later', $header['License'] ?? '' );
	}
}
