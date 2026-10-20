<?php
/**
 * Companion plugins for the native View Details dialog.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Append the companion guide only to this plugin's information.
 *
 * @param mixed  $result Plugin information or the original API result.
 * @param string $action Requested API action.
 * @param object $args API arguments.
 * @return mixed
 */
function pue_companion_plugin_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! is_object( $args ) || ! isset( $args->slug ) || 'hackrepair-post-update-email-notifier' !== $args->slug || ! is_object( $result ) || ! isset( $result->sections ) || ! is_array( $result->sections ) ) {
		return $result;
	}
	wp_enqueue_style( 'hackrepair-post-update-email-notifier-brand', plugins_url( 'assets/brand/thrg-brand.css', PUE_PLUGIN_FILE ), array(), PUE_VERSION );
	wp_enqueue_style( 'hackrepair-post-update-email-notifier-brand-details', plugins_url( 'assets/brand/thrg-details.css', PUE_PLUGIN_FILE ), array( 'hackrepair-post-update-email-notifier-brand' ), PUE_VERSION );
	wp_enqueue_style( 'pue-plugin-details', plugins_url( 'assets/css/pue-plugin-details.css', PUE_PLUGIN_FILE ), array( 'hackrepair-post-update-email-notifier-brand-details' ), PUE_VERSION );
	$result->version                        = PUE_VERSION;
	$result->sections['other_THRG_plugins'] = '<div class="pue-toolkit"><p class="pue-toolkit-intro">More tools from The Hack Repair Guy</p><p>Four focused ways to look after your WordPress site. Use only what you need.</p><div class="pue-toolkit-card"><h3>Group Password Reset</h3><p>Reset passwords by role, choose who to exclude, and send secure reset links when needed.</p><p><a href="https://github.com/hackrepair/group-password-reset" target="_blank" rel="noopener noreferrer">Explore Group Password Reset &rarr;</a></p></div><div class="pue-toolkit-card"><h3>Plugin Detective</h3><p>Find the plugin behind a conflict, with recovery tools for when your dashboard is unavailable.</p><p><a href="https://github.com/hackrepair/hackrepair-plugin-detective" target="_blank" rel="noopener noreferrer">Explore Plugin Detective &rarr;</a></p></div><div class="pue-toolkit-card"><h3>Site Inspector</h3><p>Review security-related observations, keep inspection reports, and see what changed.</p><p><a href="https://github.com/hackrepair/thrg-site-inspector" target="_blank" rel="noopener noreferrer">Explore Site Inspector &rarr;</a></p></div><div class="pue-toolkit-card"><h3>Disable Other Plugin Notices</h3><p>Keep third-party admin notices in an expandable panel.</p><p><a href="https://github.com/hackrepair/disable-other-plugin-notices" target="_blank" rel="noopener noreferrer">Explore Disable Other Plugin Notices &rarr;</a></p></div><p class="pue-toolkit-note">Each plugin works independently. Links open the project pages in a new tab.</p></div>';
	return $result;
}
add_filter( 'plugins_api', 'pue_companion_plugin_info', 100, 3 );

/**
 * Provide bundled details when remote information is unavailable.
 *
 * @param mixed  $result Existing result.
 * @param string $action API action.
 * @param object $args Request arguments.
 * @return mixed
 */
function pue_local_plugin_info( $result, $action = null, $args = null ) {
	if ( false !== $result || 'plugin_information' !== $action || ! is_object( $args ) ||
		! isset( $args->slug ) || 'hackrepair-post-update-email-notifier' !== $args->slug ) {
		return $result;
	}

	$readme_file = PUE_PLUGIN_DIR . 'readme.txt';
	if ( ! is_readable( $readme_file ) || ! class_exists( 'PucReadmeParser' ) ) {
		return $result;
	}

	$parser = new \PucReadmeParser();
	$readme = $parser->parse_readme( $readme_file );
	if ( empty( $readme['sections'] ) ) {
		return $result;
	}

	// The readme parser files the FAQ under its long heading; WordPress's
	// details window calls that tab "faq".
	$wanted = array(
		'description'                => 'description',
		'installation'               => 'installation',
		'frequently_asked_questions' => 'faq',
		'changelog'                  => 'changelog',
	);

	$sections = array();
	foreach ( $wanted as $from => $to ) {
		if ( ! empty( $readme['sections'][ $from ] ) ) {
			$sections[ $to ] = $readme['sections'][ $from ];
		}
	}

	$info = new \stdClass();

	$info->external     = true;
	$info->name         = "The Hack Repair Guy's Post Update Email Notifier";
	$info->slug         = 'hackrepair-post-update-email-notifier';
	$info->version      = PUE_VERSION;
	$info->author       = '<a href="https://hackrepair.com/">Jim Walker, The Hack Repair Guy</a>';
	$info->homepage     = 'https://hackrepair.com/';
	$info->requires     = isset( $readme['requires_at_least'] ) ? $readme['requires_at_least'] : '';
	$info->tested       = isset( $readme['tested_up_to'] ) ? $readme['tested_up_to'] : '';
	$info->requires_php = isset( $readme['requires_php'] ) ? $readme['requires_php'] : '';
	$info->last_updated = gmdate( 'Y-m-d H:i:s', (int) filemtime( PUE_PLUGIN_FILE ) );
	$info->sections     = $sections;
	$info->banners      = array(
		'low'  => PUE_PLUGIN_URL . 'assets/banner-772x250.png',
		'high' => PUE_PLUGIN_URL . 'assets/banner-772x250.png',
	);
	$info->icons        = array(
		'1x' => PUE_PLUGIN_URL . 'assets/icon-128x128.png',
		'2x' => PUE_PLUGIN_URL . 'assets/icon-256x256.png',
	);

	return $info;
}
add_filter( 'plugins_api', 'pue_local_plugin_info', 20, 3 );
