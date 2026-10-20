<?php
/**
 * Compatibility functions extracted from the original bootstrap.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

// ---------- 9. Helpers ----------
/**
 * Build identity headers (From, Reply-To) from plugin options with placeholder support.
 *
 * @param array   $headers Existing headers array.
 * @param array   $placeholders Map of placeholders for From Name (e.g., [site_name], [editor_name], [year]).
 * @param WP_User $user Current user context (may be null in some flows).
 * @return array Headers with identity lines appended when valid.
 */
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Public compatibility signature includes the user argument.
function pue_build_identity_headers( $headers, $placeholders, $user ) {
	$id_from_name  = get_option( 'pue_from_name', '' );
	$id_from_email = sanitize_email( get_option( 'pue_from_email', '' ) );
	$id_reply_to   = sanitize_email( get_option( 'pue_reply_to_email', '' ) );
	if ( ! empty( $id_from_name ) || ! empty( $id_from_email ) ) {
		$from_name = $id_from_name ? strtr( $id_from_name, is_array( $placeholders ) ? $placeholders : array() ) : '';
		$from_name = $from_name ? wp_specialchars_decode( $from_name, ENT_QUOTES ) : '';
		if ( $from_name ) {
			$from_name = str_replace( array( "\r", "\n" ), '', $from_name ); }
		if ( $id_from_email && is_email( $id_from_email ) ) {
			$headers[] = 'From: ' . ( $from_name ? $from_name . ' ' : '' ) . '<' . $id_from_email . '>';
		}
	}
	if ( $id_reply_to && is_email( $id_reply_to ) ) {
		$headers[] = 'Reply-To: ' . $id_reply_to;
	}
	return $headers;
}
// Attach the plaintext part only while this plugin is sending a message.
add_action( 'phpmailer_init', 'pue_phpmailer_set_altbody' );
function pue_phpmailer_set_altbody( $phpmailer ) {
	PUE_Delivery::apply_altbody( $phpmailer );
}

/**
 * Capability required to manage this plugin's settings.
 *
 * @return string
 */
function pue_capability() {
	return (string) apply_filters( 'pue_settings_capability', 'manage_options' );
}

/**
 * Settings page URL, optionally anchored to a section.
 *
 * @param string $section Section id.
 * @return string
 */
function pue_settings_url( $section = '' ) {
	return admin_url( 'options-general.php?page=pue-settings' ) . ( $section ? '#' . rawurlencode( $section ) : '' );
}

/**
 * Unique, valid email addresses of users in the given roles.
 *
 * @param array  $roles Role keys.
 * @param string $exclude_email Address to leave out (case-insensitive).
 * @return string[]
 */
function pue_get_recipients( $roles, $exclude_email = '' ) {
	$roles = array_values( array_filter( (array) $roles, 'is_string' ) );
	if ( empty( $roles ) ) {
		return array();
	}
	$users = get_users(
		array(
			'role__in' => $roles,
			'fields'   => array( 'user_email' ),
			'number'   => -1,
		)
	);
	$out   = array();
	foreach ( (array) $users as $user ) {
		$email = isset( $user->user_email ) ? sanitize_email( $user->user_email ) : '';
		if ( ! $email || ! is_email( $email ) ) {
			continue;
		}
		if ( $exclude_email && 0 === strcasecmp( $email, $exclude_email ) ) {
			continue;
		}
		$out[ strtolower( $email ) ] = $email;
	}
	return array_values( $out );
}

/**
 * Format a timestamp using site date/time formats with sensible fallbacks.
 * Uses `wp_date()` when available, otherwise `date_i18n()`.
 */
function pue_format_datetime( $timestamp ) {
	// Our logs store timestamps using current_time('timestamp') (site-local time).
	// To avoid double-applying offsets, format using date_i18n() directly on that value.
	$ts = is_numeric( $timestamp ) ? (int) $timestamp : 0;
	if ( $ts <= 0 ) {
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Existing log and notice values use site-local timestamps; preserve compatibility.
		$ts = current_time( 'timestamp' ); }
	$date_format = trim( (string) get_option( 'date_format' ) );
	$time_format = trim( (string) get_option( 'time_format' ) );
	$format      = trim( $date_format . ' ' . $time_format );
	if ( '' === $format ) {
		$format = 'F j, Y g:i a'; }
	return date_i18n( $format, $ts );
}
/**
 * Compose an email (subject, HTML body, headers) with shared logic for update/test.
 * Ensures placeholder rendering, message normalization, branded wrapping, identity headers,
 * optional plaintext AltBody, and header filters are applied consistently.
 *
 * @param string  $context      'update' or 'test'.
 * @param string  $subject_tmpl Subject template with placeholders.
 * @param string  $message_tmpl Message template (HTML/plain) with placeholders.
 * @param array   $maps         ['subject'=>[], 'message'=>[], 'brand'=>[]] maps. If 'brand' missing, uses union of subject+message maps.
 * @param array   $meta         Extra context: ['post_id'=>int, 'post_after'=>WP_Post|null, 'post_before'=>WP_Post|null].
 * @param WP_User $user         Current user context for filters.
 * @return array                [ $subject, $body_html, $headers ]
 */
function pue_compose_email( $context, $subject_tmpl, $message_tmpl, $maps, $meta, $user ) {
	return PUE_Email_Composer::instance()->compose( $context, $subject_tmpl, $message_tmpl, $maps, $meta, $user );
}

/**
 * Build placeholder maps for a test (no post context).
 * Returns ['subject'=>[], 'message'=>[], 'brand'=>[]].
 */
function pue_build_placeholder_maps_test( $user ) {
	return PUE_Placeholder_Engine::instance()->build_test_maps( $user );
}

/**
 * Build placeholder maps for an update email context.
 * Returns ['subject'=>[], 'message'=>[], 'brand'=>[]].
 */
function pue_build_placeholder_maps_update( $post_ID, $post_after, $post_before, $editor ) {
	return PUE_Placeholder_Engine::instance()->build_update_maps( $post_ID, $post_after, $post_before, $editor );
}
function pue_sanitize_emails( $emails ) {
	$out = array();
	foreach ( (array) $emails as $e ) {
		$e = sanitize_email( $e );
		if ( $e && is_email( $e ) ) {
			$out[ strtolower( $e ) ] = $e; }
	}
	return array_values( $out );
}

function pue_csv_safe( $value ) {
	return PUE_Logger::instance()->csv_safe( $value );
}

function pue_generate_plaintext( $html, $width = 78 ) {
	// Convert anchors to "text (URL)"
	$html = preg_replace_callback(
		'/<a\s[^>]*href=[\"\']([^\"\']+)[\"\'][^>]*>(.*?)<\\/a>/is',
		function ( $m ) {
			$text = trim( wp_strip_all_tags( $m[2] ) );
			$url  = trim( $m[1] );
			if ( $text && $url ) {
				return $text . ' (' . $url . ')'; }
			return $url ? $url : $text;
		},
		$html
	);
	$text = wp_specialchars_decode( wp_strip_all_tags( $html ) );
	$text = preg_replace( '/[ \t]+/', ' ', $text );
	$text = trim( $text );
	if ( $width > 20 ) {
		$text = wordwrap( $text, $width );
	}
	return $text;
}

/**
 * Find a related last error message for a summary log entry.
 * Scans the full $logs array for the most recent failure matching type/post within the last hour.
 *
 * @param array  $logs  Full log array from option.
 * @param string $type  'update' or 'test'.
 * @param int    $post_id Post ID (0 for tests).
 * @param int    $since_ts Timestamp of the summary row.
 * @return string Error message (short) or empty string.
 */
function pue_get_related_error( $logs, $type, $post_id, $since_ts ) {
	if ( empty( $logs ) || ! is_array( $logs ) ) {
		return '';
	}
	$latest_ts = 0;
	$msg       = '';
	$cut       = max( 0, (int) $since_ts - HOUR_IN_SECONDS );
	foreach ( $logs as $e ) {
		if ( ! empty( $e['sent'] ) ) {
			continue; // only failures
		}
		$t = isset( $e['type'] ) ? (string) $e['type'] : '';
		if ( $t !== $type ) {
			continue;
		}
		$pid = isset( $e['post_id'] ) ? (int) $e['post_id'] : 0;
		if ( $pid !== (int) $post_id ) {
			continue;
		}
		$ts = isset( $e['ts'] ) ? (int) $e['ts'] : 0;
		if ( $ts < $cut ) {
			continue;
		}
		if ( $ts >= $latest_ts ) {
			if ( ! empty( $e['error'] ) ) {
				$latest_ts = $ts;
				$msg       = (string) $e['error'];
			}
		}
	}
	// Trim overly long messages for table display
	if ( $msg && strlen( $msg ) > 120 ) {
		$msg = substr( $msg, 0, 117 ) . '…';
	}
	return $msg;
}

/**
 * Detailed variant: returns short, full, and code for tooltip display.
 */
function pue_get_related_error_detail( $logs, $type, $post_id, $since_ts ) {
	$out = array(
		'short' => '',
		'full'  => '',
		'code'  => '',
	);
	if ( empty( $logs ) || ! is_array( $logs ) ) {
		return $out;
	}
	$latest_ts = 0;
	$cut       = max( 0, (int) $since_ts - HOUR_IN_SECONDS );
	foreach ( $logs as $e ) {
		if ( ! empty( $e['sent'] ) ) {
			continue;
		}
		$t = isset( $e['type'] ) ? (string) $e['type'] : '';
		if ( $t !== $type ) {
			continue;
		}
		$pid = isset( $e['post_id'] ) ? (int) $e['post_id'] : 0;
		if ( $pid !== (int) $post_id ) {
			continue;
		}
		$ts = isset( $e['ts'] ) ? (int) $e['ts'] : 0;
		if ( $ts < $cut ) {
			continue;
		}
		if ( $ts >= $latest_ts && ! empty( $e['error'] ) ) {
			$latest_ts = $ts;
			$msg       = (string) $e['error'];
			$code      = isset( $e['error_code'] ) ? (string) $e['error_code'] : '';
			$full      = $code ? ( $code . ': ' . $msg ) : $msg;
			$short     = $full;
			if ( strlen( $short ) > 120 ) {
				$short = substr( $short, 0, 117 ) . '…'; }
			$out = array(
				'short' => $short,
				'full'  => $full,
				'code'  => $code,
			);
		}
	}
	return $out;
}

// Normalize message body for HTML emails: handle literal \n tokens, plain text bodies, and trim artifacts
function pue_normalize_message_html( $message ) {
	if ( null === $message ) {
		return ''; }
	$message = (string) $message;
	// Convert visible backslash sequences to actual newlines
	$message = str_replace( array( "\\r\\n", "\\r", "\\n" ), "\n", $message );
	// Trim leading/trailing whitespace and newlines
	$message = trim( $message );
	// If message is plain text (no HTML tags), convert newlines to <br>
	if ( ! preg_match( '/<\\w+[^>]*>/', $message ) ) {
		$message = nl2br( $message );
	} else {
		// For HTML content, remove any stray visible \n that may remain
		$message = str_replace( array( '\\n', '\\r' ), '', $message );
		// Strip leading/trailing <br> artifacts
		$message = preg_replace( '/^(?:<br\s*\/?>\s*)+/', '', $message );
		$message = preg_replace( '/(?:<br\s*\/?>\s*)+$/', '', $message );
		// If no block-level tags present, auto-paragraph for better visual parity
		if ( ! preg_match( '/<\s*(p|ul|ol|table|div|h[1-6])\b/i', $message ) ) {
			if ( function_exists( 'wpautop' ) ) {
				$message = wpautop( $message );
			}
		}
	}
	return $message;
}

// SMTP active detection (heuristic)
function pue_is_smtp_active() {
	$active = false;
	// 1) Known SMTP plugins active?
	$plugins = (array) get_option( 'active_plugins', array() );
	$network = (array) get_site_option( 'active_sitewide_plugins', array() );
	$slugs   = array(
		'wp-mail-smtp/wp_mail_smtp.php',
		'post-smtp/postman-smtp.php',
		'post-smtp/post-smtp.php',
		'fluent-smtp/fluent-smtp.php',
		'easy-wp-smtp/easy-wp-smtp.php',
		'smtp-mailer/main.php',
	);
	foreach ( $slugs as $slug ) {
		if ( in_array( $slug, $plugins, true ) || isset( $network[ $slug ] ) ) {
			$active = true;
			break; }
	}
	// 2) Runtime PHPMailer probe (no send). It runs other plugins' phpmailer_init hooks, so cache the result.
	if ( ! $active ) {
		$cached = get_transient( 'pue_smtp_probe' );
		if ( false === $cached ) {
			$cached = pue_probe_phpmailer_smtp() ? 'yes' : 'no';
			set_transient( 'pue_smtp_probe', $cached, 10 * MINUTE_IN_SECONDS );
		}
		$active = 'yes' === $cached;
	}
	return (bool) apply_filters( 'pue_smtp_active', $active );
}

function pue_probe_phpmailer_smtp() {
	try {
		if ( class_exists( 'PHPMailer\\PHPMailer\\PHPMailer' ) ) {
			$php = new PHPMailer\PHPMailer\PHPMailer( true );
		} elseif ( class_exists( 'PHPMailer' ) ) {
			$php = new PHPMailer( true );
		// phpcs:ignore Universal.ControlStructures.DisallowLonelyIf.Found -- Keep legacy PHPMailer loading fallback grouped together.
		} else {
			// Attempt to load core vendor classes (paths may vary across WP versions)
			if ( file_exists( ABSPATH . WPINC . '/PHPMailer/PHPMailer.php' ) ) {
				require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
				require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
				require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
				$php = new PHPMailer\PHPMailer\PHPMailer( true );
			} elseif ( file_exists( ABSPATH . WPINC . '/class-phpmailer.php' ) ) {
				require_once ABSPATH . WPINC . '/class-phpmailer.php';
				$php = new PHPMailer( true );
		// phpcs:ignore Universal.ControlStructures.DisallowLonelyIf.Found -- Keep legacy PHPMailer loading fallback grouped together.
			} else {
				return false;
			}
		}
		// Let any SMTP plugin configure this PHPMailer instance
		do_action_ref_array( 'phpmailer_init', array( &$php ) );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer defines this public property name.
		$mailer = isset( $php->Mailer ) ? strtolower( (string) $php->Mailer ) : '';
		// SMTP if Mailer is smtp, or Host set, or SMTPAuth explicitly true
		if ( 'smtp' === $mailer ) {
			return true;
		}
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer defines this public property name.
		if ( ! empty( $php->Host ) ) {
			return true;
		}
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer defines this public property name.
		if ( isset( $php->SMTPAuth ) && $php->SMTPAuth ) {
			return true;
		}
	} catch ( \Throwable $e ) {
		return false;
	}
	return false;
}
// Show SMTP recommendation notice after repeated failures in the last 24h
add_action( 'admin_init', 'pue_handle_dismiss_smtp_notice' );
function pue_handle_dismiss_smtp_notice() {
	if ( ! is_admin() || ! current_user_can( pue_capability() ) ) {
		return;
	}
	if ( ! isset( $_GET['pue_dismiss_smtp_notice'] ) || ! isset( $_GET['_wpnonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'pue_dismiss_smtp_notice' ) ) {
		return;
	}
	// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Existing log and notice values use site-local timestamps; preserve compatibility.
	update_option( 'pue_smtp_notice_dismiss_until', current_time( 'timestamp' ) + DAY_IN_SECONDS, false );
}

add_action( 'admin_notices', 'pue_maybe_show_smtp_notice' );
function pue_maybe_show_smtp_notice() {
	if ( ! is_admin() || ! current_user_can( pue_capability() ) ) {
		return;
	}
	// Only show on our settings page to avoid noise.
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'settings_page_pue-settings' !== $screen->id ) {
		return;
	}
	// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Existing log and notice values use site-local timestamps; preserve compatibility.
	$now           = current_time( 'timestamp' );
	$dismiss_until = (int) get_option( 'pue_smtp_notice_dismiss_until', 0 );
	if ( $dismiss_until && $dismiss_until > $now ) {
		return;
	}
	// Skipped and queued entries are not delivery failures.
	if ( PUE_Logger::instance()->count_failures_since( $now - DAY_IN_SECONDS ) < 3 ) {
		return;
	}

	$url         = wp_nonce_url( add_query_arg( 'pue_dismiss_smtp_notice', '1' ), 'pue_dismiss_smtp_notice' );
	$install_url = admin_url( 'plugin-install.php?s=SMTP&tab=search&type=term' );
	/* translators: Placeholders contain the recipient address, message counts, or linked SMTP plugin described in this message. */
	echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Email delivery appears to be failing.', 'hackrepair-post-update-email-notifier' ) . '</strong> ' . esc_html__( 'We detected repeated mail failures in the last 24 hours.', 'hackrepair-post-update-email-notifier' ) . ' ' . sprintf( wp_kses_post( __( 'Consider installing an SMTP plugin (e.g., <a href="%1$s">WP Mail SMTP</a>, Post SMTP, FluentSMTP) and using a domain‑aligned From address.', 'hackrepair-post-update-email-notifier' ) ), esc_url( $install_url ) ) . '</p><p><a href="' . esc_url( $url ) . '" class="button">' . esc_html__( 'Dismiss for 24 hours', 'hackrepair-post-update-email-notifier' ) . '</a></p></div>';
}

// Branded template (uses options)
function pue_email_template_branded_legacy( $content, $placeholders = array() ) {
	$heading     = PUE_Defaults::option( 'pue_header_text' );
	$bg          = PUE_Defaults::option( 'pue_header_bg_color' );
	$footer_text = PUE_Defaults::option( 'pue_footer_text' );
	$bg          = pue_sanitize_hex( $bg );
	if ( empty( $bg ) ) {
		$bg = '#0073aa'; }
	// Defensive: normalize any visible \n tokens that might slip through
	if ( is_string( $content ) ) {
		$content = str_replace( array( "\\r\\n", "\\r", "\\n" ), '<br>', $content );
		$content = preg_replace( '/^(?:<br\s*\/?>\s*)+/', '', $content );
		$content = preg_replace( '/(?:<br\s*\/?>\s*)+$/', '', $content );
	}
	// Allow all known placeholders (including [year]) in header/footer
	if ( ! empty( $placeholders ) && is_array( $placeholders ) ) {
		$heading     = strtr( (string) $heading, $placeholders );
		$footer_text = strtr( (string) $footer_text, $placeholders );
	}
	// Always ensure [year] works even if not passed
	$heading     = strtr( (string) $heading, array( '[year]' => wp_date( 'Y' ) ) );
	$footer_text = strtr( (string) $footer_text, array( '[year]' => wp_date( 'Y' ) ) );
	$heading     = esc_html( $heading );
	$footer_out  = esc_html( $footer_text );

	return '<div style="font-family: Arial, sans-serif; background-color: #f8f9fa; padding:20px;"><div style="width:100%;margin:0;background:white;border-radius:8px;overflow:hidden;border:1px solid #e0e0e0;"><div style="background-color:' . esc_attr( $bg ) . ';color:white;padding:12px 20px;"><h2 style="margin:0;">' . $heading . '</h2></div><div style="padding:20px;font-size:15px;line-height:1.6;color:#333;">' . $content . '</div><div style="background-color:#f1f1f1;text-align:center;padding:10px;color:#666;font-size:13px;">' . $footer_out . '</div></div></div>';
}

// New wrapper preserving original function name
function pue_email_template_branded( $content, $placeholders = array() ) {
	return pue_email_template_branded_legacy( $content, $placeholders );
}

/** Build the shared domain/plugin subject tags without changing saved templates.
 * @param string $subject Customized purpose.
 * @return string
 */
function pue_email_subject( $subject ) {
	$parts = wp_parse_url( home_url( '/' ) );
	if ( empty( $parts['host'] ) ) {
		$parts = wp_parse_url( site_url( '/' ) ); }
	$host = ! empty( $parts['host'] ) ? strtolower( $parts['host'] ) : 'WordPress';
	if ( ! empty( $parts['port'] ) ) {
		$host .= ':' . (int) $parts['port']; }
	$prefix  = '[' . $host . '] [Post Update Email Notifier] ';
	$subject = sanitize_text_field( $subject );
	return 0 === strpos( $subject, $prefix ) ? $subject : $prefix . $subject;
}
