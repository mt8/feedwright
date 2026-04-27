<?php
/**
 * Plugin Name:       Feedwright
 * Plugin URI:        https://github.com/mt8/feedwright
 * Description:       Edit custom RSS / Atom / XML feeds visually in the WordPress block editor.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.3
 * Author:            mt8
 * Author URI:        https://github.com/mt8/feedwright
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       feedwright
 * Domain Path:       /languages
 *
 * @package Feedwright
 */

defined( 'ABSPATH' ) || exit;

define( 'FEEDWRIGHT_VERSION', '0.1.0' );
define( 'FEEDWRIGHT_PLUGIN_FILE', __FILE__ );
define( 'FEEDWRIGHT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FEEDWRIGHT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$feedwright_autoload = FEEDWRIGHT_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! is_readable( $feedwright_autoload ) ) {
	// Composer 依存が未インストール: ブートストラップを諦め、管理画面に警告だけ出す.
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p><strong>Feedwright:</strong> Composer dependencies are missing. Run <code>composer install --no-dev</code> from the plugin directory, or install the release zip which includes <code>vendor/</code>.</p></div>';
		}
	);
	unset( $feedwright_autoload );
	return;
}
require_once $feedwright_autoload;
unset( $feedwright_autoload );

add_action( 'plugins_loaded', array( \Feedwright\Plugin::class, 'instance' ) );

register_activation_hook( __FILE__, array( \Feedwright\Plugin::class, 'on_activation' ) );
register_deactivation_hook( __FILE__, array( \Feedwright\Plugin::class, 'on_deactivation' ) );
