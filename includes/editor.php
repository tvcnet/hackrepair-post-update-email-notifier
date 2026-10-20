<?php
/**
 * Per-post "Don't send email notifications" control for the Classic and Block editors.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Site-wide default for the per-post flag: 'skip' (editors opt in) or 'send' (editors opt out).
 *
 * @return string
 */
function pue_send_policy() {
	return 'send' === PUE_Defaults::option( 'pue_send_policy' ) ? 'send' : 'skip';
}

/**
 * Whether the next published update of this post is skipped.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function pue_post_is_skipped( $post_id ) {
	if ( metadata_exists( 'post', $post_id, '_pue_do_not_notify' ) ) {
		return (bool) get_post_meta( $post_id, '_pue_do_not_notify', true );
	}
	return 'skip' === pue_send_policy();
}

/**
 * Return a post to the site default once an update has been processed.
 *
 * @param int $post_id Post ID.
 */
function pue_consume_opt_in( $post_id ) {
	delete_post_meta( $post_id, '_pue_do_not_notify' );
}

// Register the flag for all public post types so the Block editor saves it everywhere.
add_action(
	'init',
	function () {
		foreach ( (array) get_post_types( array( 'public' => true ), 'names' ) as $pt ) {
			register_post_meta(
				$pt,
				'_pue_do_not_notify',
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'boolean',
					'default'       => 'skip' === pue_send_policy(),
					'auth_callback' => function ( $allowed, $meta_key, $object_id ) {
						return current_user_can( 'edit_post', (int) $object_id );
					},
				)
			);
		}
	}
);

/**
 * Plain-language recipient note for the editor controls.
 *
 * @param string $post_type Post type.
 * @return string
 */
function pue_editor_recipient_note( $post_type ) {
	$allowed = (array) PUE_Defaults::option( 'pue_post_types' );
	if ( ! empty( $allowed ) && ! in_array( $post_type, $allowed, true ) ) {
		return __( 'This post type is not set to send update emails.', 'hackrepair-post-update-email-notifier' );
	}
	$user    = wp_get_current_user();
	$exclude = (int) PUE_Defaults::option( 'pue_exclude_updater' ) && ! empty( $user->user_email ) ? $user->user_email : '';
	$count   = count( pue_get_recipients( (array) PUE_Defaults::option( 'pue_roles' ), $exclude ) );
	if ( 0 === $count ) {
		return __( 'No recipients are selected in Settings, so no email would be sent.', 'hackrepair-post-update-email-notifier' );
	}
	/* translators: %d: number of recipients. */
	return sprintf( _n( 'When sent, this update emails %d person.', 'When sent, this update emails %d people.', $count, 'hackrepair-post-update-email-notifier' ), $count );
}

// Classic editor: checkbox in the Submit box.
add_action(
	'post_submitbox_misc_actions',
	function () {
		global $post;
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}
		$obj = get_post_type_object( get_post_type( $post ) );
		if ( ! $obj || empty( $obj->public ) ) {
			return;
		}
		wp_nonce_field( 'pue_dnn_meta', 'pue_dnn_nonce' );
		echo '<div class="misc-pub-section pue-dnn"><label>'
		. '<input type="checkbox" name="pue_do_not_notify" value="1" ' . checked( pue_post_is_skipped( $post->ID ), true, false ) . ' /> '
		. esc_html__( 'Don’t send email notifications', 'hackrepair-post-update-email-notifier' )
		. '</label><p class="description">' . esc_html( pue_editor_recipient_note( $post->post_type ) ) . '</p></div>';
	},
	99
);

// Classic editor save: store a value only when it differs from the site default.
add_action(
	'save_post',
	function ( $post_id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['pue_dnn_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pue_dnn_nonce'] ) ), 'pue_dnn_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$skip         = isset( $_POST['pue_do_not_notify'] );
		$default_skip = 'skip' === pue_send_policy();
		if ( $default_skip === $skip ) {
			delete_post_meta( $post_id, '_pue_do_not_notify' );
		} else {
			update_post_meta( $post_id, '_pue_do_not_notify', $skip ? 1 : 0 );
		}
	}
);

// Block editor: checkbox in the Status & visibility panel.
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
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$type   = $screen && ! empty( $screen->post_type ) ? $screen->post_type : 'post';
		$obj    = get_post_type_object( $type );
		wp_localize_script(
			'pue-editor',
			'PUE_Editor',
			array(
				'enabled' => $obj && ! empty( $obj->public ),
				'note'    => pue_editor_recipient_note( $type ),
			)
		);
		wp_set_script_translations( 'pue-editor', 'hackrepair-post-update-email-notifier', PUE_PLUGIN_DIR . 'languages' );
	}
);

// Honor the per-post flag. Consumption happens in the notification service after the decision.
add_filter(
	'pue_should_notify',
	function ( $should, $post_ID, $post_after ) {
		if ( ! $should ) {
			return false;
		}
		if ( ! pue_post_is_skipped( $post_ID ) ) {
			return true;
		}
		// Log a skip only when an editor explicitly chose it (not the silent default).
		if ( metadata_exists( 'post', $post_ID, '_pue_do_not_notify' ) ) {
			$editor = wp_get_current_user();
			pue_log_event(
				'update',
				false,
				array(),
				'',
				$post_ID,
				array(
					'post_title' => $post_after ? $post_after->post_title : get_the_title( $post_ID ),
					'post_type'  => $post_after ? $post_after->post_type : get_post_type( $post_ID ),
					'editor_id'  => $editor ? (int) $editor->ID : 0,
					'mode'       => 'skipped',
					'reason'     => 'editor_choice',
					'sent_count' => 0,
					'total'      => 0,
				)
			);
		}
		return false;
	},
	5,
	3
);

// Ensure the log option is created with autoload disabled.
add_action(
	'init',
	function () {
		if ( false === get_option( 'pue_logs', false ) ) {
			add_option( 'pue_logs', array(), '', 'no' );
		}
	}
);
