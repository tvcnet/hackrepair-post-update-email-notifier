<?php
/*
Plugin Name: The Hack Repair Guy's Post Update Email Notifier
Plugin URI: https://github.com/tvcnet/hackrepair-post-update-email-notifier
Update URI: https://github.com/tvcnet/hackrepair-post-update-email-notifier
Description: Sends an HTML email to selected user roles whenever a post or page is updated. Includes settings to configure roles, subject, message, branding, email identity, and a test email.
Version: 1.6.2
Author: Jim Walker, The Hack Repair Guy
Author URI: https://hackrepair.com/
License: GPL2
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Text Domain: hackrepair-post-update-email-notifier
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PUE_VERSION' ) ) {
	define( 'PUE_VERSION', '1.6.2' );
}
if ( ! defined( 'PUE_MAIN_FILE' ) ) {
	define( 'PUE_MAIN_FILE', __FILE__ );
}


/** Installed plugin paths. */
defined( 'PUE_PLUGIN_FILE' ) || define( 'PUE_PLUGIN_FILE', PUE_MAIN_FILE );
defined( 'PUE_PLUGIN_DIR' ) || define( 'PUE_PLUGIN_DIR', plugin_dir_path( PUE_MAIN_FILE ) );
defined( 'PUE_PLUGIN_URL' ) || define( 'PUE_PLUGIN_URL', plugin_dir_url( PUE_MAIN_FILE ) );

spl_autoload_register(
	static function ( $pue_class ) {
		if ( 0 !== strpos( $pue_class, 'PUE_' ) ) {
			return; }
		$pue_file = PUE_PLUGIN_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $pue_class ) ) . '.php';
		if ( is_readable( $pue_file ) ) {
			require_once $pue_file; }
	}
);

foreach ( array( 'helpers', 'sanitizers', 'logging', 'notifications', 'editor', 'settings', 'admin', 'admin-handlers' ) as $pue_component ) {
	require_once PUE_PLUGIN_DIR . 'includes/' . $pue_component . '.php';
}
unset( $pue_component );
require_once PUE_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
require_once PUE_PLUGIN_DIR . 'includes/companion-plugins.php';
PUE_Delivery::init();
PUE_Site_Health::init();
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'pue', 'PUE_CLI' );
}
add_action(
	'plugins_loaded',
	static function () {
		$pue_updater = new PUE_Updater();
		$pue_updater->init();
	}
);
