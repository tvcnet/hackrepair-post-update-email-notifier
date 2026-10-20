<?php
/**
 * Contextual settings help shared by the server-rendered page and translations.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

/** Native disclosures enhanced for hover, focus and touch without requiring JavaScript. */
final class PUE_Help {
	/**
	 * Explanation for a setting or action.
	 *
	 * @param string $key Feature key.
	 * @return string
	 */
	public static function text( $key ) {
		$text = array(
			'pue_send_policy'      => __( 'Start here: choose what happens when an editor saves eligible published content. The recommended option starts with “Don’t send email notifications” checked; uncheck it in the editor for an update you want to announce. “Send unless the editor opts out” starts unchecked; check it to skip one update. After a processed update, the post returns to this site default. Save Notifications to apply your choice. Existing posts may retain a saved choice until their next update. No mail is sent unless recipient roles are selected and the other sending rules allow it.', 'hackrepair-post-update-email-notifier' ),
			'pue_roles'            => __( 'Select the WordPress roles whose users should receive content updates, then Save Notifications. Each recipient gets a separate email. No roles are selected on a new installation. Uncheck every role and save to pause new notifications; this does not cancel messages already queued.', 'hackrepair-post-update-email-notifier' ),
			'pue_recipients'       => __( 'This estimate follows the selected roles and the exclude-updating-user option without saving. Developer recipient filters, the person making the update and later account changes can alter the final list. Save Notifications before announcing an update.', 'hackrepair-post-update-email-notifier' ),
			'pue_notify_events'    => __( 'Choose whether notifications may accompany a draft or scheduled post going live as well as later edits, or only edits to content that is already published. The editor’s notification choice and other rules still apply. Brand-new programmatic inserts do not notify.', 'hackrepair-post-update-email-notifier' ),
			'pue_cooldown_minutes' => __( 'Choose a minimum interval between notification attempts for the same post. Saves inside that interval do not generate another notification; they are not collected into a later digest. An opted-in update blocked by cooldown is consumed and can be recorded in the log. Off applies no interval. Save Notifications after changing this.', 'hackrepair-post-update-email-notifier' ),
			'pue_delivery_mode'    => __( 'Automatic sends small lists during the save and, by default, queues lists above 20 recipients. Always send right away can slow the save on large lists. Background mode uses WP-Cron batches and requires site traffic or a server cron job. Scheduling recovery is bounded; interrupted sends are not automatically replayed. Save Notifications to apply the mode.', 'hackrepair-post-update-email-notifier' ),
			'pue_subject'          => __( 'Write the email purpose and use placeholders such as [post_title] for the updated content. The plugin automatically adds [domain] [Post Update Email Notifier] before the purpose, so you do not need to type those tags. Avoid sensitive subject text. Save Notifications before sending a test email.', 'hackrepair-post-update-email-notifier' ),
			'pue_message'          => __( 'Write the notification body using the visual or text editor. Placeholders are replaced with post and site details. Add [post_url] for the public link, [change_summary] for a brief change summary, or [revision_url] for the latest revision comparison. That admin revision link requires appropriate WordPress access. Preview uses sample details; save before sending a real test.', 'hackrepair-post-update-email-notifier' ),
			'pue_post_types'       => __( 'Select the content types that may generate notifications, then Save Notifications. An empty selection imposes no post-type restriction; the editor checkbox is shown for public types. Role selection, send policy, publication rules and cooldown still apply.', 'hackrepair-post-update-email-notifier' ),
			'pue_exclude_updater'  => __( 'Enable this to remove the currently updating user’s email from the selected role recipients. Other users in the same role remain eligible. Automated or scheduled updates may have no logged-in editor to exclude. Save Notifications after changing this.', 'hackrepair-post-update-email-notifier' ),
			'pue_enable_logging'   => __( 'Enable and save to record notification outcomes, skipped reasons and background progress on this site. The log can contain recipient addresses, subjects and post details. It is optional; background recovery still works when logging is off. Choose a retention limit appropriate for your site.', 'hackrepair-post-update-email-notifier' ),
			'pue_log_retention'    => __( 'Choose the maximum number of log entries to retain: 50, 200 or 1,000. New log writes discard the oldest entries above that limit. The page displays the newest 20 retained entries; CSV exports include the retained log. This is an entry count, not a number of days.', 'hackrepair-post-update-email-notifier' ),
			'pue_header_text'      => __( 'Set the heading at the top of the HTML email. Use [site_name] to insert your site name or [year] for the year. Save Branding to use it in sent messages, or preview unsaved values first.', 'hackrepair-post-update-email-notifier' ),
			'pue_header_bg_color'  => __( 'Choose the email header background with the color picker or enter a HEX value such as #0073aa. Keep sufficient contrast with the white heading text. Preview the result and Save Branding to apply it.', 'hackrepair-post-update-email-notifier' ),
			'pue_footer_text'      => __( 'Set the short closing line below the email body. Placeholders such as [site_name] and [year] are supported. Footer text is displayed as text, not custom HTML. Save Branding after editing.', 'hackrepair-post-update-email-notifier' ),
			'pue_from_name'        => __( 'Optionally set the sender’s display name together with a valid From Email address below. Use supported placeholders such as [site_name] to identify the site. A name alone does not override the sender. Leave blank to use your mail setup’s name. Save Email Identity; your SMTP plugin may override it.', 'hackrepair-post-update-email-notifier' ),
			'pue_from_email'       => __( 'Optionally set the sender address used by this plugin. Use an address approved by your mail provider and configure authentication there; matching the site domain alone does not guarantee delivery. Leave blank to use your existing mail setup. Save Email Identity and send a test. SMTP settings may override this address.', 'hackrepair-post-update-email-notifier' ),
			'pue_reply_to_email'   => __( 'Set the inbox that should receive replies to these notifications. It does not change who receives the notification or configure a mailbox. Leave blank to use the normal reply behavior, then Save Email Identity.', 'hackrepair-post-update-email-notifier' ),
			'pue_test_email'       => __( 'Send one real test email using the saved template and settings to your current WordPress account address, or the site administrator address if your account has none. It uses sample post details. Save your edits first. Acceptance for sending is not proof that the email reached the inbox.', 'hackrepair-post-update-email-notifier' ),
			'pue_preview'          => __( 'Open an on-screen preview without sending mail or saving changes. It combines current notification and branding form values with sample post details. Use it to check wording and layout, then save the relevant sections before sending a test.', 'hackrepair-post-update-email-notifier' ),
			'pue_log'              => __( 'Review the newest 20 retained entries for accepted sends, failures, skipped reasons and queued progress. Accepted does not confirm delivery. Queued means background work is pending. A skipped entry is not a delivery failure. Use your mail provider’s logs to investigate missing inbox delivery.', 'hackrepair-post-update-email-notifier' ),
			'pue_clear_log'        => __( 'Delete the retained email log after confirming the action. This cannot be undone here and does not cancel queued notifications or reset your settings. Export first if you need a record.', 'hackrepair-post-update-email-notifier' ),
			'pue_export_log'       => __( 'Download all retained log entries as a CSV file, not only the newest 20 shown on screen. It can include recipient addresses and message details. Store and share it carefully. Exporting does not clear the log or send email.', 'hackrepair-post-update-email-notifier' ),
			'pue_bulk_test'        => __( 'This is a diagnostic tool, not a normal content notification. It sends real individual test emails using saved settings. Use a mail catcher or an address you control, start with a small count and respect provider limits. The daily allowance is shared by this site. It does not email selected roles.', 'hackrepair-post-update-email-notifier' ),
			'pue_bulk_email'       => __( 'Enter an address you control or a local mail-catcher address. All bulk test messages go here unless plus-address aliases are enabled. This address does not change your normal notification recipients.', 'hackrepair-post-update-email-notifier' ),
			'pue_bulk_count'       => __( 'Choose how many real test messages to attempt, from 1 to 200 per request. The remaining daily allowance can reduce this count. Start small to avoid overwhelming an inbox or exceeding your mail provider’s limits.', 'hackrepair-post-update-email-notifier' ),
			'pue_bulk_plus'        => __( 'Send to numbered aliases such as name+1@example.com and name+2@example.com. Existing plus tags are replaced. Only use this if the destination supports plus addressing; otherwise the messages may fail.', 'hackrepair-post-update-email-notifier' ),
			'pue_bulk_log_detail'  => __( 'When logging is enabled, record each bulk test message separately instead of one summary entry. This can quickly fill the retention limit. It does not turn logging on or change whether messages are sent.', 'hackrepair-post-update-email-notifier' ),
		);
		return isset( $text[ $key ] ) ? $text[ $key ] : '';
	}

	/**
	 * Render a focusable, native help disclosure.
	 *
	 * @param string $key Feature key.
	 * @param string $label Visible field or action label.
	 */
	public static function render( $key, $label ) {
		$text = self::text( $key );
		if ( '' === $text ) {
			return;
		}
		$id = wp_unique_id( 'pue-help-' );
		/* translators: %s: feature label. */
		$name = sprintf( __( 'Help for %s', 'hackrepair-post-update-email-notifier' ), $label );
		echo '<details class="pue-help" data-help-key="' . esc_attr( $key ) . '"><summary aria-label="' . esc_attr( $name ) . '" aria-controls="' . esc_attr( $id ) . '"><span aria-hidden="true">?</span></summary><span class="pue-help-content" id="' . esc_attr( $id ) . '">' . esc_html( $text ) . '</span></details>';
	}
}
