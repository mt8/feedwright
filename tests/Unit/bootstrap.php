<?php
/**
 * PHPUnit bootstrap file for Unit tests (no WordPress runtime).
 *
 * 仕様 §6 で各 PHP ファイル冒頭に `defined( 'ABSPATH' ) || exit;` を
 * 記述するルールがあるため、ユニットテスト環境では偽の ABSPATH を
 * 定義してオートロード時の exit を回避する。
 *
 * @package Feedwright
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
