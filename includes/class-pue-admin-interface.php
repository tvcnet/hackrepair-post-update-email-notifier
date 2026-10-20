<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PUE_Admin_Interface' ) ) {
	/** Settings page markup. Requests are handled by PUE_Admin_Actions. */
	class PUE_Admin_Interface {
		private static $instance = null;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Opening tag and hidden fields for a form posted to admin-post.php.
		 *
		 * @param string $id Form id.
		 * @param string $action Action name.
		 * @param string $nonce_action Nonce action.
		 * @param string $nonce_field Nonce field.
		 * @param string $css_class Form class.
		 */
		private function form_open( $id, $action, $nonce_action, $nonce_field, $css_class = 'pue-form' ) {
			echo '<form id="' . esc_attr( $id ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="' . esc_attr( $css_class ) . '">';
			echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
			wp_nonce_field( $nonce_action, $nonce_field );
		}

		/**
		 * Select control.
		 *
		 * @param string $name Option name.
		 * @param array  $choices Value => label.
		 */
		private function select( $name, $choices ) {
			$current = (string) PUE_Defaults::option( $name );
			echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
			foreach ( $choices as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		}

		/**
		 * Settings row with a select and help text.
		 *
		 * @param string $name Option.
		 * @param string $label Label.
		 * @param array  $choices Choices.
		 * @param string $help Help text.
		 */
		private function select_row( $name, $label, $choices, $help ) {
			echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
			PUE_Help::render( $name, $label );
			echo '</th><td>';
			$this->select( $name, $choices );
			echo '<p class="description">' . esc_html( $help ) . '</p></td></tr>';
		}

		/**
		 * Text input row.
		 *
		 * @param string $name Option.
		 * @param string $label Label.
		 * @param string $type Input type.
		 * @param string $help Help text.
		 */
		private function input_row( $name, $label, $type = 'text', $help = '' ) {
			echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
			PUE_Help::render( $name, $label );
			echo '</th><td>';
			echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) PUE_Defaults::option( $name ) ) . '" class="regular-text" />';
			if ( $help ) {
				echo '<p class="description">' . esc_html( $help ) . '</p>';
			}
			echo '</td></tr>';
		}

		/**
		 * Submit row closing a settings table.
		 *
		 * @param string $label Button label.
		 */
		private function submit_row( $label ) {
			echo '<tr class="pue-submit-row"><th scope="row"></th><td>';
			submit_button( $label, 'primary', 'submit', false );
			echo '</td></tr></table></form>';
		}

		public function render_settings_page() {
			$enable_logging = (int) PUE_Defaults::option( 'pue_enable_logging' );
			$notice         = pue_take_admin_notice();
			?>
			<div class="wrap">
				<div class="pue-head"><img class="thrg-brand-heart" src="<?php echo esc_url( PUE_PLUGIN_URL . 'assets/brand/thrg-heart-128.png' ); ?>" alt="" width="56" height="56"><div><p class="pue-eyebrow"><?php esc_html_e( 'The Hack Repair Guy’s', 'hackrepair-post-update-email-notifier' ); ?></p><h1><?php esc_html_e( 'Post Update Email Notifier', 'hackrepair-post-update-email-notifier' ); ?> <span class="pue-version">v<?php echo esc_html( PUE_VERSION ); ?></span></h1><p><?php esc_html_e( 'Choose who hears about your content updates, and review the outcome.', 'hackrepair-post-update-email-notifier' ); ?></p></div></div>
				<p class="description"><?php echo pue_is_smtp_active() ? esc_html__( 'SMTP transport detected. Acceptance for sending does not confirm delivery.', 'hackrepair-post-update-email-notifier' ) : esc_html__( 'Using your WordPress email configuration. Test delivery before enabling notifications.', 'hackrepair-post-update-email-notifier' ); ?></p>
				<hr class="wp-header-end">
				<input type="hidden" id="pue-read-nonce" value="<?php echo esc_attr( wp_create_nonce( 'pue_admin_read' ) ); ?>" />
				<div class="pue-container">
					<aside class="pue-sidebar">
					<ul class="pue-nav" id="pue-nav">
						<li><a href="#notifications">🔔 <?php esc_html_e( 'Notifications', 'hackrepair-post-update-email-notifier' ); ?></a></li>
						<li><a href="#branding">🎨 <?php esc_html_e( 'Branding', 'hackrepair-post-update-email-notifier' ); ?></a></li>
						<li><a href="#identity">📧 <?php esc_html_e( 'Email Identity', 'hackrepair-post-update-email-notifier' ); ?></a></li>
						<li><a href="#test-email">✉️ <?php esc_html_e( 'Test & Preview', 'hackrepair-post-update-email-notifier' ); ?></a></li>
						<?php if ( $enable_logging ) : ?>
						<li><a href="#logging">🧾 <?php esc_html_e( 'Logging', 'hackrepair-post-update-email-notifier' ); ?></a></li>
						<?php endif; ?>
						<li><a href="#updates">⬆️ <?php esc_html_e( 'Update Status', 'hackrepair-post-update-email-notifier' ); ?></a></li>
						<li><a href="#bulk-test">📦 <?php esc_html_e( 'Bulk Test', 'hackrepair-post-update-email-notifier' ); ?></a></li>
						<li><a href="#reset-defaults">↩️ <?php esc_html_e( 'Reset', 'hackrepair-post-update-email-notifier' ); ?></a></li>
						<li><a href="#support">❓ <?php esc_html_e( 'Support', 'hackrepair-post-update-email-notifier' ); ?></a></li>
					</ul>
					</aside>
					<div class="pue-main">
					<div class="pue-theme">
			<?php
			if ( $notice && 'test-email' !== $notice['section'] ) {
				echo wp_kses_post( pue_notice_html( $notice['type'], $notice['message'] ) );
			}
			$this->render_notifications();
			$this->render_branding_identity();
			$this->render_test( $notice );
			if ( $enable_logging ) {
				$this->render_log();
			}
			$this->render_tools();
			?>
					</div><!-- .pue-theme -->
					</div><!-- .pue-main -->
				</div><!-- .pue-container -->
			<?php $this->render_modals(); ?>
			</div>
			<?php
		}

		private function render_notifications() {
			$selected_roles = (array) PUE_Defaults::option( 'pue_roles' );
			$selected_types = (array) PUE_Defaults::option( 'pue_post_types' );
			$exclude        = (int) PUE_Defaults::option( 'pue_exclude_updater' );
			$user           = wp_get_current_user();
			$count          = count( pue_get_recipients( $selected_roles, $exclude && ! empty( $user->user_email ) ? $user->user_email : '' ) );
			$placeholders   = '[post_title], [post_url], [site_name], [editor_name], [author_name], [post_type], [updated_at], [post_edit_url], [year], [event], [previous_title], [change_summary], [revision_url]';
			?>
			<h2 id="notifications"><?php esc_html_e( 'Notifications', 'hackrepair-post-update-email-notifier' ); ?></h2>
			<?php $this->form_open( 'pue-form-notifications', 'pue_save_notifications', 'pue_save_notifications', 'pue_save_notifications_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
					<?php
					esc_html_e( 'Select Roles to Notify', 'hackrepair-post-update-email-notifier' );
					PUE_Help::render( 'pue_roles', __( 'Select Roles to Notify', 'hackrepair-post-update-email-notifier' ) );
					?>
					</th>
					<td>
						<div class="pue-right-note">
							<p><?php echo wp_kses_post( __( 'The <strong>Hack Repair Guy’s Post Update Email Notifier</strong> keeps your team in the loop by sending branded HTML updates whenever posts or pages are modified.', 'hackrepair-post-update-email-notifier' ) ); ?></p>
							<p class="pue-muted"><?php esc_html_e( 'Tip: Uncheck all roles and save to pause notifications. Leaving post types empty allows all post types.', 'hackrepair-post-update-email-notifier' ); ?></p>
						</div>
						<fieldset id="pue-roles">
						<?php foreach ( wp_roles()->roles as $role_key => $role ) : ?>
							<label><input type="checkbox" name="pue_roles[]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $selected_roles, true ) ); ?> /> <?php echo esc_html( translate_user_role( $role['name'] ) ); ?></label><br />
						<?php endforeach; ?>
						</fieldset>
						<p class="pue-recipient-count" id="pue-recipient-count" aria-live="polite"><?php echo esc_html( pue_recipient_count_text( $count ) ); ?></p><?php PUE_Help::render( 'pue_recipients', __( 'Recipient count', 'hackrepair-post-update-email-notifier' ) ); ?>
					</td>
				</tr>
				<?php
				$this->select_row(
					'pue_send_policy',
					__( 'Default for each update', 'hackrepair-post-update-email-notifier' ),
					array(
						'skip' => __( 'Don’t send unless the editor opts in (recommended)', 'hackrepair-post-update-email-notifier' ),
						'send' => __( 'Send unless the editor opts out', 'hackrepair-post-update-email-notifier' ),
					),
					__( 'Sets the starting state of the “Don’t send email notifications” checkbox. After each update the post returns to this default.', 'hackrepair-post-update-email-notifier' )
				);
				$this->select_row(
					'pue_notify_events',
					__( 'Notify on', 'hackrepair-post-update-email-notifier' ),
					array(
						'all'     => __( 'First publish and later updates', 'hackrepair-post-update-email-notifier' ),
						'updates' => __( 'Updates to already published content only', 'hackrepair-post-update-email-notifier' ),
					),
					__( 'First publish includes drafts and scheduled posts going live.', 'hackrepair-post-update-email-notifier' )
				);
				$this->select_row(
					'pue_cooldown_minutes',
					__( 'Cooldown per post', 'hackrepair-post-update-email-notifier' ),
					array(
						'0'  => __( 'Off', 'hackrepair-post-update-email-notifier' ),
						'5'  => __( '5 minutes', 'hackrepair-post-update-email-notifier' ),
						'15' => __( '15 minutes', 'hackrepair-post-update-email-notifier' ),
						'60' => __( '1 hour', 'hackrepair-post-update-email-notifier' ),
					),
					__( 'Prevents a burst of emails when the same post is saved several times in a row.', 'hackrepair-post-update-email-notifier' )
				);
				$this->select_row(
					'pue_delivery_mode',
					__( 'Delivery', 'hackrepair-post-update-email-notifier' ),
					array(
						'auto'       => __( 'Automatic: small lists right away, larger lists in the background', 'hackrepair-post-update-email-notifier' ),
						'sync'       => __( 'Always send right away', 'hackrepair-post-update-email-notifier' ),
						'background' => __( 'Always send in the background', 'hackrepair-post-update-email-notifier' ),
					),
					__( 'Background sending uses WP-Cron in small batches so saving a post stays fast.', 'hackrepair-post-update-email-notifier' )
				);
				/* translators: %s: list of placeholders. */
				$this->input_row( 'pue_subject', __( 'Subject', 'hackrepair-post-update-email-notifier' ), 'text', sprintf( __( 'Placeholders supported: %s', 'hackrepair-post-update-email-notifier' ), $placeholders ) );
				?>
				<tr>
					<th scope="row">
					<?php
					esc_html_e( 'Message', 'hackrepair-post-update-email-notifier' );
					PUE_Help::render( 'pue_message', __( 'Message', 'hackrepair-post-update-email-notifier' ) );
					?>
					</th>
					<td>
						<?php
						wp_editor(
							PUE_Defaults::option( 'pue_message' ),
							'pue_message',
							array(
								'textarea_name' => 'pue_message',
								'textarea_rows' => 7,
								'teeny'         => true,
								'media_buttons' => false,
								'quicktags'     => true,
							)
						);
						?>
						<p class="description"><?php esc_html_e( '[change_summary] lists what changed (title, content length, excerpt). [revision_url] links to the latest revision comparison.', 'hackrepair-post-update-email-notifier' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
					<?php
					esc_html_e( 'Post Types', 'hackrepair-post-update-email-notifier' );
					PUE_Help::render( 'pue_post_types', __( 'Post Types', 'hackrepair-post-update-email-notifier' ) );
					?>
					</th>
					<td>
						<fieldset>
						<?php foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) : ?>
							<label><input type="checkbox" name="pue_post_types[]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $selected_types, true ) ); ?> /> <?php echo esc_html( $pt->labels->singular_name ); ?></label><br />
						<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'If none are selected, every post type can notify. The editor checkbox appears on public post types.', 'hackrepair-post-update-email-notifier' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
					<?php
					esc_html_e( 'Exclude Updating User', 'hackrepair-post-update-email-notifier' );
					PUE_Help::render( 'pue_exclude_updater', __( 'Exclude Updating User', 'hackrepair-post-update-email-notifier' ) );
					?>
					</th>
					<td><label><input type="checkbox" id="pue_exclude_updater" name="pue_exclude_updater" value="1" <?php checked( $exclude ); ?> /> <?php esc_html_e( 'Do not email the user who performed the update.', 'hackrepair-post-update-email-notifier' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row">
					<?php
					esc_html_e( 'Enable Logging', 'hackrepair-post-update-email-notifier' );
					PUE_Help::render( 'pue_enable_logging', __( 'Enable Logging', 'hackrepair-post-update-email-notifier' ) );
					?>
					</th>
					<td>
						<label><input type="checkbox" name="pue_enable_logging" value="1" <?php checked( (int) PUE_Defaults::option( 'pue_enable_logging' ) ); ?> /> <?php esc_html_e( 'Log recent emails (50/200/1000 entries) with CSV export.', 'hackrepair-post-update-email-notifier' ); ?></label>
						<p class="description"><?php esc_html_e( 'The log stores recipient addresses. It is included in WordPress personal data export and erasure requests.', 'hackrepair-post-update-email-notifier' ); ?></p>
					</td>
				</tr>
				<?php
				$this->select_row(
					'pue_log_retention',
					__( 'Log Retention', 'hackrepair-post-update-email-notifier' ),
					array(
						'50'   => '50',
						'200'  => '200',
						'1000' => '1000',
					),
					__( 'Number of recent entries kept.', 'hackrepair-post-update-email-notifier' )
				);
				$this->submit_row( __( 'Save Notifications', 'hackrepair-post-update-email-notifier' ) );
		}

		private function render_branding_identity() {
			?>
			<h2 id="branding"><?php esc_html_e( 'Branding', 'hackrepair-post-update-email-notifier' ); ?></h2>
			<?php $this->form_open( 'pue-form-branding', 'pue_save_branding', 'pue_save_branding', 'pue_save_branding_nonce' ); ?>
			<table class="form-table" role="presentation">
				<?php $this->input_row( 'pue_header_text', __( 'Header Text', 'hackrepair-post-update-email-notifier' ) ); ?>
				<tr>
					<th scope="row"><label for="pue_header_bg_color"><?php esc_html_e( 'Header Background Color', 'hackrepair-post-update-email-notifier' ); ?></label><?php PUE_Help::render( 'pue_header_bg_color', __( 'Header Background Color', 'hackrepair-post-update-email-notifier' ) ); ?></th>
					<td>
						<?php $bg_val = (string) PUE_Defaults::option( 'pue_header_bg_color' ); ?>
						<input type="text" id="pue_header_bg_color" name="pue_header_bg_color" value="<?php echo esc_attr( $bg_val ); ?>" class="regular-text pue-color-input" />
						<span class="pue-color-swatch" id="pue-swatch-header-bg" aria-hidden="true" style="background: <?php echo esc_attr( $bg_val ); ?>;"></span>
						<p class="description"><?php esc_html_e( 'Use a HEX color (e.g., #0073aa).', 'hackrepair-post-update-email-notifier' ); ?></p>
					</td>
				</tr>
				<?php
				$this->input_row( 'pue_footer_text', __( 'Footer Text', 'hackrepair-post-update-email-notifier' ) );
				$this->submit_row( __( 'Save Branding', 'hackrepair-post-update-email-notifier' ) );
				?>

			<h2 id="identity"><?php esc_html_e( 'Email Identity', 'hackrepair-post-update-email-notifier' ); ?></h2>
			<?php $this->form_open( 'pue-form-identity', 'pue_save_identity', 'pue_save_identity', 'pue_save_identity_nonce' ); ?>
			<table class="form-table" role="presentation">
				<?php
				$this->input_row( 'pue_from_name', __( 'From Name', 'hackrepair-post-update-email-notifier' ), 'text', __( 'Optional. Overrides the sender name. Supports [site_name], [editor_name], [updated_at] and [year].', 'hackrepair-post-update-email-notifier' ) );
				$this->input_row( 'pue_from_email', __( 'From Email', 'hackrepair-post-update-email-notifier' ), 'email', __( "Optional: For better deliverability, use an email address that matches your website's domain. Messages sent this way are less likely to end up in spam or junk folders. If you're using an SMTP plugin, note that its settings may override the email address entered here.", 'hackrepair-post-update-email-notifier' ) );
				$this->input_row( 'pue_reply_to_email', __( 'Reply-To Email', 'hackrepair-post-update-email-notifier' ), 'email', __( 'Optional. Where replies should go.', 'hackrepair-post-update-email-notifier' ) );
				$this->submit_row( __( 'Save Email Identity', 'hackrepair-post-update-email-notifier' ) );
		}

		/**
		 * Test and preview card.
		 *
		 * @param array|null $notice Pending notice.
		 */
		private function render_test( $notice ) {
			?>
			<h2 id="test-email"><?php esc_html_e( 'Test & Preview', 'hackrepair-post-update-email-notifier' ); ?></h2>
			<div class="pue-card">
				<div class="pue-inline pue-inline--top">
					<?php $this->form_open( 'pue-test-form', 'pue_send_test_email', 'pue_test_email_action', 'pue_test_email_nonce', 'pue-inline-form' ); ?>
						<?php submit_button( __( 'Send a Test', 'hackrepair-post-update-email-notifier' ), 'primary', 'submit', false ); ?>
						<?php PUE_Help::render( 'pue_test_email', __( 'Send a Test', 'hackrepair-post-update-email-notifier' ) ); ?>
					</form>
					<button type="button" class="button" id="pue-preview-btn"><?php esc_html_e( 'Preview without sending', 'hackrepair-post-update-email-notifier' ); ?></button><?php PUE_Help::render( 'pue_preview', __( 'Preview without sending', 'hackrepair-post-update-email-notifier' ) ); ?>
					<span class="description"><?php esc_html_e( 'Send a sample of the saved template to your address, or preview the current form values here with sample post details.', 'hackrepair-post-update-email-notifier' ); ?></span>
				</div>
				<div id="pue-test-result">
				<?php
				if ( $notice && 'test-email' === $notice['section'] ) {
					echo wp_kses_post( pue_notice_html( $notice['type'], $notice['message'] ) );
				}
				?>
				</div>
			</div>
			<?php
		}

		private function render_log() {
			$logs = get_option( 'pue_logs', array() );
			$logs = is_array( $logs ) ? $logs : array();
			?>
			<h2 id="logging"><?php esc_html_e( 'Recent Email Log', 'hackrepair-post-update-email-notifier' ); ?></h2>
			<div class="pue-card">
			<?php PUE_Help::render( 'pue_log', __( 'Recent Email Log', 'hackrepair-post-update-email-notifier' ) ); ?>
			<?php if ( empty( $logs ) ) : ?>
				<p><?php esc_html_e( 'No log entries yet.', 'hackrepair-post-update-email-notifier' ); ?></p>
			<?php else : ?>
				<div class="pue-table-scroll">
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Time', 'hackrepair-post-update-email-notifier' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Type', 'hackrepair-post-update-email-notifier' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'hackrepair-post-update-email-notifier' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Post', 'hackrepair-post-update-email-notifier' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Recipients', 'hackrepair-post-update-email-notifier' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Subject', 'hackrepair-post-update-email-notifier' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( array_slice( array_reverse( $logs ), 0, 20 ) as $entry ) {
							$this->render_log_row( $entry, $logs );
						}
						?>
					</tbody>
				</table>
				</div>
				<div class="pue-inline pue-log-actions">
					<?php $this->form_open( 'pue-form-clear-log', 'pue_clear_log', 'pue_clear_log_action', 'pue_clear_log_nonce', 'pue-inline-form' ); ?>
						<?php submit_button( __( 'Clear Log', 'hackrepair-post-update-email-notifier' ), 'primary', 'submit', false, array( 'data-confirm' => __( 'Clear the email log?', 'hackrepair-post-update-email-notifier' ) ) ); ?>
						<?php PUE_Help::render( 'pue_clear_log', __( 'Clear Log', 'hackrepair-post-update-email-notifier' ) ); ?>
					</form>
					<?php $this->form_open( 'pue-form-export-csv', 'pue_export_log', 'pue_export_log_action', 'pue_export_log_nonce', 'pue-inline-form' ); ?>
						<?php submit_button( __( 'Export CSV', 'hackrepair-post-update-email-notifier' ), 'primary', 'submit', false ); ?>
						<?php PUE_Help::render( 'pue_export_log', __( 'Export CSV', 'hackrepair-post-update-email-notifier' ) ); ?>
					</form>
				</div>
			<?php endif; ?>
			</div>
			<?php
		}

		private function render_tools() {
			$manifest   = get_site_transient( 'pue_github_status' );
			$latest_v   = is_array( $manifest ) && ! empty( $manifest['latest_version'] ) ? $manifest['latest_version'] : '';
			$fetched_at = is_array( $manifest ) && ! empty( $manifest['fetched_at'] ) ? $manifest['fetched_at'] : 0;
			?>
			<h2 id="updates"><?php esc_html_e( 'Update status', 'hackrepair-post-update-email-notifier' ); ?></h2>
			<div class="pue-card">
				<p><strong><?php esc_html_e( 'Current version:', 'hackrepair-post-update-email-notifier' ); ?></strong> <span class="pue-updates-current"><?php echo esc_html( PUE_VERSION ); ?></span></p>
				<p><strong><?php esc_html_e( 'Latest available (GitHub):', 'hackrepair-post-update-email-notifier' ); ?></strong> <span class="pue-updates-latest"><?php echo $latest_v ? esc_html( $latest_v ) : esc_html__( 'Not checked yet', 'hackrepair-post-update-email-notifier' ); ?></span></p>
				<p><strong><?php esc_html_e( 'Last checked:', 'hackrepair-post-update-email-notifier' ); ?></strong> <span class="pue-updates-fetched"><?php echo $fetched_at ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $fetched_at ) ) : '—'; ?></span></p>
				<div class="pue-inline">
					<button type="button" id="pue-check-updates-btn" class="button button-primary"><?php esc_html_e( 'Check for updates now', 'hackrepair-post-update-email-notifier' ); ?></button><?php PUE_Help::render( 'pue_updates', __( 'Check for updates now', 'hackrepair-post-update-email-notifier' ) ); ?>
					<input type="hidden" id="pue-force-check-nonce" value="<?php echo esc_attr( wp_create_nonce( 'pue_force_check' ) ); ?>" />
				</div>
			</div>

			<h2 id="bulk-test"><?php esc_html_e( 'Bulk Test (Testing Only)', 'hackrepair-post-update-email-notifier' ); ?></h2>
			<div class="pue-card">
				<p class="description">
					<?php
					/* translators: %d: messages remaining today. */
					echo esc_html( sprintf( __( 'Enter an email address and a count to send multiple test messages (individual sends) for deliverability and error testing. Uses the current template. Please respect provider rate limits. Remaining today: %d.', 'hackrepair-post-update-email-notifier' ), pue_bulk_remaining() ) );
					?>
				</p>
				<?php $this->form_open( 'pue-form-bulk-test', 'pue_bulk_test_send', 'pue_bulk_test_action', 'pue_bulk_test_nonce', 'pue-bulk-form' ); ?>
					<label><?php esc_html_e( 'Email:', 'hackrepair-post-update-email-notifier' ); ?> <input type="email" name="pue_bulk_email" required placeholder="you@example.com" /></label><?php PUE_Help::render( 'pue_bulk_email', __( 'Bulk test email address', 'hackrepair-post-update-email-notifier' ) ); ?>
					<label><?php esc_html_e( 'Count:', 'hackrepair-post-update-email-notifier' ); ?> <input type="number" name="pue_bulk_count" min="1" max="200" value="50" /></label><?php PUE_Help::render( 'pue_bulk_count', __( 'Bulk test count', 'hackrepair-post-update-email-notifier' ) ); ?>
					<label><input type="checkbox" name="pue_bulk_plus" value="1"> <?php esc_html_e( 'Generate plus-aliases (name+1..+N@domain) — useful for Gmail to see N deliveries in one inbox.', 'hackrepair-post-update-email-notifier' ); ?></label><?php PUE_Help::render( 'pue_bulk_plus', __( 'Generate plus-aliases', 'hackrepair-post-update-email-notifier' ) ); ?>
					<label><input type="checkbox" name="pue_bulk_log_detail" value="1"> <?php esc_html_e( 'Log detail (one row per message)', 'hackrepair-post-update-email-notifier' ); ?></label><?php PUE_Help::render( 'pue_bulk_log_detail', __( 'Log detail', 'hackrepair-post-update-email-notifier' ) ); ?>
					<?php submit_button( __( 'Run Bulk Test', 'hackrepair-post-update-email-notifier' ), 'primary', 'submit', false ); ?>
					<?php PUE_Help::render( 'pue_bulk_test', __( 'Run Bulk Test', 'hackrepair-post-update-email-notifier' ) ); ?>
				</form>
				<div id="pue-bulk-result"></div>
			</div>

			<h2 id="reset-defaults"><?php esc_html_e( 'Reset to Defaults', 'hackrepair-post-update-email-notifier' ); ?></h2>
			<div class="pue-card">
				<p><strong><?php esc_html_e( 'Restore Default Settings', 'hackrepair-post-update-email-notifier' ); ?>:</strong>
				<?php esc_html_e( 'This will reset all plugin settings to their original defaults, including Notifications, Branding, Email Identity, and Logging. Your posts and users are not affected.', 'hackrepair-post-update-email-notifier' ); ?></p>
				<?php $this->form_open( 'pue-form-reset-defaults', 'pue_reset_defaults', 'pue_reset_defaults_action', 'pue_reset_defaults_nonce', 'pue-inline-form' ); ?>
					<button type="submit" class="button button-primary" data-confirm="<?php echo esc_attr__( 'Are you sure you want to reset all settings to defaults?', 'hackrepair-post-update-email-notifier' ); ?>"><?php esc_html_e( 'Reset to Defaults', 'hackrepair-post-update-email-notifier' ); ?></button><?php PUE_Help::render( 'pue_reset', __( 'Reset to Defaults', 'hackrepair-post-update-email-notifier' ) ); ?>
				</form>
			</div>

			<h2 id="support"><?php esc_html_e( 'Support', 'hackrepair-post-update-email-notifier' ); ?></h2>
			<div class="pue-card">
				<?php PUE_Help::render( 'pue_support', __( 'Support', 'hackrepair-post-update-email-notifier' ) ); ?>
				<p>☕ <?php echo wp_kses_post( __( 'If you found this plugin helpful, please <a href="https://hackrepair.com/donations/buy-jim-a-coffee" target="_blank" rel="noopener">buy Jim a cup of coffee?</a>', 'hackrepair-post-update-email-notifier' ) ); ?></p>
				<p><span class="dashicons dashicons-editor-help" aria-hidden="true"></span> <?php echo wp_kses_post( __( 'Questions and Feedback: For questions, ideas, or help with the plugin, <a href="https://github.com/tvcnet/hackrepair-post-update-email-notifier/discussions" target="_blank" rel="noopener">visit GitHub Discussions</a>.', 'hackrepair-post-update-email-notifier' ) ); ?></p>
			</div>
			<?php
		}

		private function render_modals() {
			$close = esc_attr__( 'Close', 'hackrepair-post-update-email-notifier' );
			?>
			<div id="pue-modal" class="pue-modal" aria-hidden="true" hidden>
				<div class="pue-modal__backdrop" data-modal-close="1"></div>
				<div class="pue-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="pue-modal-title" tabindex="-1">
					<div class="pue-modal__header">
						<h3 class="pue-modal__title" id="pue-modal-title"><?php esc_html_e( 'Delivery Error', 'hackrepair-post-update-email-notifier' ); ?></h3>
						<button type="button" class="pue-modal__close" aria-label="<?php echo $close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>" data-modal-close="1">&times;</button>
					</div>
					<div class="pue-modal__body">
						<p class="pue-modal__line"><strong><?php esc_html_e( 'Code:', 'hackrepair-post-update-email-notifier' ); ?></strong> <span id="pue-error-code"></span></p>
						<p class="pue-modal__line"><strong><?php esc_html_e( 'When:', 'hackrepair-post-update-email-notifier' ); ?></strong> <span id="pue-error-time"></span></p>
						<p class="pue-modal__line"><strong><?php esc_html_e( 'Mode:', 'hackrepair-post-update-email-notifier' ); ?></strong> <span id="pue-error-mode"></span></p>
						<pre id="pue-error-full" class="pue-modal__pre"></pre>
					</div>
				</div>
			</div>
			<div id="pue-preview-modal" class="pue-modal" aria-hidden="true" hidden>
				<div class="pue-modal__backdrop" data-modal-close="1"></div>
				<div class="pue-modal__dialog pue-modal__dialog--wide" role="dialog" aria-modal="true" aria-labelledby="pue-preview-title" tabindex="-1">
					<div class="pue-modal__header">
						<h3 class="pue-modal__title" id="pue-preview-title"><?php esc_html_e( 'Email preview', 'hackrepair-post-update-email-notifier' ); ?></h3>
						<button type="button" class="pue-modal__close" aria-label="<?php echo $close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>" data-modal-close="1">&times;</button>
					</div>
					<div class="pue-modal__body">
						<p class="pue-modal__line"><strong><?php esc_html_e( 'Subject:', 'hackrepair-post-update-email-notifier' ); ?></strong> <span id="pue-preview-subject"></span></p>
						<iframe id="pue-preview-frame" class="pue-preview-frame" sandbox="" title="<?php echo esc_attr__( 'Email preview', 'hackrepair-post-update-email-notifier' ); ?>"></iframe>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * One log table row.
		 *
		 * @param array $entry Entry.
		 * @param array $logs All entries (for older rows without inline error detail).
		 */
		private function render_log_row( $entry, $logs ) {
			$time     = isset( $entry['ts'] ) ? (int) $entry['ts'] : time();
			$type     = isset( $entry['type'] ) ? (string) $entry['type'] : 'update';
			$post_id  = isset( $entry['post_id'] ) ? (int) $entry['post_id'] : 0;
			$title    = isset( $entry['post_title'] ) ? (string) $entry['post_title'] : '';
			$recips   = isset( $entry['recipients'] ) && is_array( $entry['recipients'] ) ? $entry['recipients'] : array();
			$time_str = pue_format_datetime( $time );
			$label    = $post_id ? ( $title ? $title : '#' . $post_id ) : __( '(no post)', 'hackrepair-post-update-email-notifier' );
			$display  = implode( ', ', array_slice( $recips, 0, 3 ) ) . ( count( $recips ) > 3 ? ', …' : '' );
			$status   = PUE_Logger::instance()->status( $entry );
			$counts   = ' (' . ( isset( $entry['sent_count'] ) ? (int) $entry['sent_count'] : 0 ) . '/' . ( isset( $entry['total'] ) ? (int) $entry['total'] : 0 ) . ')';
			$reasons  = array(
				'editor_choice' => __( 'editor chose not to send', 'hackrepair-post-update-email-notifier' ),
				'post_type'     => __( 'post type not selected', 'hackrepair-post-update-email-notifier' ),
				'no_roles'      => __( 'no roles selected', 'hackrepair-post-update-email-notifier' ),
				'no_recipients' => __( 'no recipients', 'hackrepair-post-update-email-notifier' ),
				'cooldown'      => __( 'cooldown', 'hackrepair-post-update-email-notifier' ),
			);
			$labels   = array(
				'sent'    => __( 'Sent', 'hackrepair-post-update-email-notifier' ),
				'skipped' => __( 'Not sent', 'hackrepair-post-update-email-notifier' ),
				'queued'  => __( 'Queued', 'hackrepair-post-update-email-notifier' ) . $counts,
				'partial' => __( 'Partial', 'hackrepair-post-update-email-notifier' ) . $counts,
				'failed'  => __( 'Failed', 'hackrepair-post-update-email-notifier' ),
			);
			$text     = $labels[ $status ];
			if ( 'skipped' === $status && isset( $entry['reason'], $reasons[ $entry['reason'] ] ) ) {
				$text .= ' (' . $reasons[ $entry['reason'] ] . ')';
			}
			$html = '<span class="pue-status pue-status--' . esc_attr( $status ) . '">' . esc_html( $text ) . '</span>';
			if ( in_array( $status, array( 'partial', 'failed' ), true ) ) {
				if ( ! empty( $entry['error'] ) ) {
					$code   = isset( $entry['error_code'] ) ? (string) $entry['error_code'] : '';
					$detail = array(
						'full' => $code ? $code . ': ' . $entry['error'] : (string) $entry['error'],
						'code' => $code,
					);
				} else {
					$detail = pue_get_related_error_detail( $logs, $type, $post_id, $time );
				}
				if ( ! empty( $detail['full'] ) ) {
					$html .= ' <a href="#" class="pue-err-link" data-error-full="' . esc_attr( $detail['full'] ) . '" data-error-code="' . esc_attr( $detail['code'] ) . '" data-error-time="' . esc_attr( $time_str ) . '" data-error-mode="' . esc_attr( isset( $entry['mode'] ) ? (string) $entry['mode'] : '' ) . '">' . esc_html__( 'View error', 'hackrepair-post-update-email-notifier' ) . '</a>';
				}
			}
			?>
			<tr>
				<td><?php echo esc_html( $time_str ); ?></td>
				<td><?php echo esc_html( ucfirst( $type ) ); ?></td>
				<td><?php echo wp_kses_post( $html ); ?></td>
				<td><?php echo $post_id ? '<a href="' . esc_url( (string) get_edit_post_link( $post_id ) ) . '">' . esc_html( $label ) . '</a>' : esc_html( $label ); ?></td>
				<td><?php echo esc_html( $display ); ?></td>
				<td><?php echo esc_html( wp_html_excerpt( isset( $entry['subject'] ) ? (string) $entry['subject'] : '', 80, '…' ) ); ?></td>
			</tr>
			<?php
		}

		public function ajax_send_test_email() {
			PUE_Admin_Actions::instance()->handle( 'pue_send_test_email' );
		}

		public function ajax_bulk_test_send() {
			PUE_Admin_Actions::instance()->handle( 'pue_bulk_test_send' );
		}

		public function ajax_save_notifications() {
			PUE_Admin_Actions::instance()->handle( 'pue_save_notifications' );
		}

		public function ajax_save_branding() {
			PUE_Admin_Actions::instance()->handle( 'pue_save_branding' );
		}

		public function ajax_save_identity() {
			PUE_Admin_Actions::instance()->handle( 'pue_save_identity' );
		}

		public function ajax_force_check_now() {
			if ( ! current_user_can( 'update_plugins' ) ) {
				wp_send_json_error( array( 'msg' => 'forbidden' ), 403 );
			}
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'pue_force_check' ) ) {
				wp_send_json_error( array( 'msg' => 'bad_nonce' ), 400 );
			}
			$checker = isset( $GLOBALS['pue_update_checker'] ) ? $GLOBALS['pue_update_checker'] : null;
			if ( ! $checker ) {
				wp_send_json_error( array( 'error' => __( 'Update checking is temporarily unavailable. Plugin features continue to work.', 'hackrepair-post-update-email-notifier' ) ), 503 );
			}
			$info = $checker->requestInfo();
			if ( ! $info ) {
				wp_send_json_error( array( 'error' => __( 'GitHub update information is temporarily unavailable. Plugin features continue to work. Try again later.', 'hackrepair-post-update-email-notifier' ) ), 503 );
			}
			$ts = time();
			set_site_transient(
				'pue_github_status',
				array(
					'latest_version' => $info->version,
					'fetched_at'     => $ts,
				),
				12 * HOUR_IN_SECONDS
			);
			wp_send_json_success(
				array(
					'latest_version' => $info->version,
					'last_checked'   => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ),
					'download_url'   => $info->download_url,
				)
			);
		}
	}
}
