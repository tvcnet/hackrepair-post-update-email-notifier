<?php
/**
 * Compatibility functions extracted from the original bootstrap.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

// ---------- 0.2 Per‑post "Do Not Notify" toggle ----------
// Register post meta so both Classic and Block editor UIs can read/write it.
add_action(
	'init',
	function () {
		if ( ! function_exists( 'register_post_meta' ) ) {
			return;
		}
		// Register the flag for all public post types so Gutenberg saves it everywhere
		$pts = get_post_types( array( 'public' => true ), 'names' );
		foreach ( (array) $pts as $pt ) {
			register_post_meta(
				$pt,
				'_pue_do_not_notify',
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'boolean',
					'default'       => true, // default to skip until explicitly unchecked
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' ); },
				)
			);
		}
	}
);

// Classic editor: add a checkbox in the Submit box (appears above Move to Trash).
// Use a high priority so it renders after most other misc lines.
add_action(
	'post_submitbox_misc_actions',
	function () {
		global $post;
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}
		// Limit to public post types by default
		$ptype = get_post_type( $post );
		$obj   = get_post_type_object( $ptype );
		if ( ! $obj || empty( $obj->public ) ) {
			return;
		}
		$meta_exists = metadata_exists( 'post', $post->ID, '_pue_do_not_notify' );
		$checked     = $meta_exists ? (bool) get_post_meta( $post->ID, '_pue_do_not_notify', true ) : true;
		wp_nonce_field( 'pue_dnn_meta', 'pue_dnn_nonce' );
		echo '<div class="misc-pub-section pue-dnn"><label>'
		. '<input type="checkbox" name="pue_do_not_notify" value="1" ' . checked( $checked, true, false ) . ' /> '
		. esc_html__( 'Don’t send email notifications', 'hackrepair-post-update-email-notifier' )
		. '</label></div>';
	},
	99
);

// Save handler for Classic editor checkbox.
add_action(
	'save_post',
	function ( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['pue_dnn_nonce'] ) || ! wp_verify_nonce( $_POST['pue_dnn_nonce'], 'pue_dnn_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$val = isset( $_POST['pue_do_not_notify'] ) ? 1 : 0;
		// Store explicit values so defaults don't mask user intent
		update_post_meta( $post_id, '_pue_do_not_notify', $val );
	}
);

// Block editor UI: small checkbox in Status & visibility panel.
add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_enqueue_script(
			'pue-editor',
			plugins_url( 'assets/js/editor.js', PUE_MAIN_FILE ),
			array( 'wp-plugins', 'wp-edit-post', 'wp-components', 'wp-element', 'wp-data', 'wp-core-data', 'wp-i18n' ),
			PUE_VERSION,
			true
		);
	}
);

// Honor the per‑post flag: if set, skip notifications once and clear it.
add_filter(
	'pue_should_notify',
	function ( $should, $post_ID, $post_after, $post_before ) {
		if ( ! $should ) {
			return false;
		}
		$has_meta = metadata_exists( 'post', $post_ID, '_pue_do_not_notify' );
		$skip     = $has_meta ? (bool) get_post_meta( $post_ID, '_pue_do_not_notify', true ) : true; // default to skip until explicitly unchecked

		// If flagged to skip, optionally log only when the user explicitly set the flag.
		if ( $skip ) {
			if ( $has_meta && (int) get_option( 'pue_enable_logging', 0 ) === 1 ) {
				$subject_tmpl  = get_option( 'pue_subject', __( 'Post Updated: [post_title]', 'hackrepair-post-update-email-notifier' ) );
				$message_tmpl  = get_option( 'pue_message', __( '<p>A post at [site_name] has been updated by [editor_name]:<br /><strong>[post_title]</strong>, <a href="[post_url]">View the latest update?</a></p>', 'hackrepair-post-update-email-notifier' ) );
				$editor        = wp_get_current_user();
				$maps          = pue_build_placeholder_maps_update( $post_ID, $post_after, $post_before, $editor );
				$maps          = apply_filters( 'pue_placeholders', $maps, $post_ID, $post_after, $post_before, $editor );
				list($subject) = pue_compose_email(
					'update',
					$subject_tmpl,
					$message_tmpl,
					$maps,
					array(
						'post_id'     => (int) $post_ID,
						'post_after'  => $post_after,
						'post_before' => $post_before,
					),
					$editor
				);
				pue_log_event(
					'update',
					false,
					array(),
					$subject,
					$post_ID,
					array(
						'post_title' => $post_after ? $post_after->post_title : get_the_title( $post_ID ),
						'post_type'  => $post_after ? $post_after->post_type : get_post_type( $post_ID ),
						'editor_id'  => $editor ? (int) $editor->ID : 0,
						'mode'       => 'skipped', // signal UI to show "Not sent"
						'sent_count' => 0,
						'total'      => 0,
					)
				);
			}
			return false;
		}

		// If we got here, user explicitly unchecked; allow sending and reset to skip afterward.
		update_post_meta( $post_ID, '_pue_do_not_notify', 1 );
		return true;
	},
	5,
	4
);

// Ensure certain options are created with autoload disabled (performance/privacy)
add_action(
	'init',
	function () {
		if ( false === get_option( 'pue_logs', false ) ) {
			add_option( 'pue_logs', array(), '', 'no' );
		}
	}
);
