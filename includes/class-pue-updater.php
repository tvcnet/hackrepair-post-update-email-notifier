<?php
/**
 * Update checks against this plugin's GitHub releases.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api;

/**
 * Points WordPress at GitHub releases for this plugin's updates.
 *
 * This plugin is not distributed on the WordPress.org repository, so it
 * would otherwise never show an "update available" notice. This class
 * wires in the Plugin Update Checker library (vendored in
 * `plugin-update-checker/`) so WordPress checks this plugin's GitHub
 * releases the same way it checks wordpress.org for everything else.
 *
 * A release's own uploaded zip asset is used instead of GitHub's
 * auto-generated source archive, because the auto-generated archive
 * extracts into a version-stamped folder name (e.g.
 * `hackrepair-post-update-email-notifier-2.0.0/`) which would change the
 * installed plugin's directory name and deactivate it on update.
 *
 * @since 1.5.0
 */
final class PUE_Updater {

	const REPOSITORY_URL = 'https://github.com/tvcnet/hackrepair-post-update-email-notifier/';
	const BRANCH         = 'main';
	const ASSET_PATTERN  = '/^hackrepair-post-update-email-notifier\.[0-9]+(?:\.[0-9]+){2}\.zip$/';

	/**
	 * Underlying Plugin Update Checker instance.
	 *
	 * @var \YahnisElsts\PluginUpdateChecker\v5\Vcs\Api|null
	 */
	private $checker = null;

	/**
	 * Build and configure the update checker.
	 *
	 * Safe to call even if the vendored library failed to load for some
	 * reason (e.g. a stripped-down copy of the plugin folder) — it just
	 * no-ops rather than fataling.
	 */
	public function init() {
		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		$this->checker = PucFactory::buildUpdateChecker(
			self::REPOSITORY_URL,
			PUE_PLUGIN_FILE,
			'hackrepair-post-update-email-notifier'
		);

		$GLOBALS['pue_update_checker'] = $this->checker;
		$this->checker->setBranch( self::BRANCH );

		$api = $this->checker->getVcsApi();

		// A different loaded version of the update checker may lack one of the
		// controls below. Disable its hooks instead of offering a source archive.
		if ( ! $api || ! method_exists( $api, 'enableReleaseAssets' ) ||
			! method_exists( $api, 'setReleaseFilter' ) || ! method_exists( $this->checker, 'addFilter' ) ) {
			$this->checker->removeHooks();
			$this->checker = null;
			return;
		}

		require_once __DIR__ . '/class-pue-update-notices.php';
		new PUE_Update_Notices( $this->checker );

		$api->enableReleaseAssets( self::ASSET_PATTERN, Api::REQUIRE_RELEASE_ASSETS );

		// The library normally tries a tag and then a branch when a release is
		// rejected. Both alternatives download an auto-generated source archive.
		$this->checker->addFilter(
			'vcs_update_detection_strategies',
			static function ( $strategies ) {
				return isset( $strategies[ Api::STRATEGY_LATEST_RELEASE ] )
					? array( Api::STRATEGY_LATEST_RELEASE => $strategies[ Api::STRATEGY_LATEST_RELEASE ] )
					: array();
			}
		);

		// GitHubApi selects the first asset matching ASSET_PATTERN. Validate
		// that same first match against the release tag before it is selected.
		$api->setReleaseFilter(
			static function ( $version, $release ) {
				if ( ! is_string( $version ) || ! preg_match( '/^[0-9]+(?:\.[0-9]+){2}$/', $version ) ||
					! isset( $release->tag_name, $release->assets ) ||
					'v' . $version !== $release->tag_name || ! is_array( $release->assets ) ) {
					return false;
				}

				$expected = 'hackrepair-post-update-email-notifier.' . $version . '.zip';
				foreach ( $release->assets as $asset ) {
					if ( isset( $asset->name ) && is_string( $asset->name ) &&
						preg_match( self::ASSET_PATTERN, $asset->name ) ) {
						return $expected === $asset->name;
					}
				}

				return false;
			},
			Api::RELEASE_FILTER_SKIP_PRERELEASE,
			1
		);
	}

	/**
	 * Expose the underlying checker instance, mostly for debugging.
	 *
	 * @return \YahnisElsts\PluginUpdateChecker\v5\Vcs\Api|null
	 */
	public function checker() {
		return $this->checker;
	}
}
