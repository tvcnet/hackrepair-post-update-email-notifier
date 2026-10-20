<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PUE_Placeholder_Engine' ) ) {
	/** Builds subject, message and branding placeholder maps. */
	class PUE_Placeholder_Engine {
		private static $instance = null;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Assemble the three maps from plain values. URL keys are escaped as URLs in the message map.
		 *
		 * @param array $values Placeholder => plain value.
		 * @param array $html   Placeholder => pre-built safe HTML for the message map.
		 * @return array
		 */
		private function maps( $values, $html = array() ) {
			$url_keys    = array( '[post_url]', '[post_edit_url]', '[revision_url]' );
			$message_map = array();
			foreach ( $values as $key => $value ) {
				if ( isset( $html[ $key ] ) ) {
					$message_map[ $key ] = $html[ $key ];
				} elseif ( in_array( $key, $url_keys, true ) ) {
					$message_map[ $key ] = esc_url( (string) $value );
				} else {
					$message_map[ $key ] = esc_html( (string) $value );
				}
			}
			$brand_map = array_intersect_key( $values, array_flip( array( '[site_name]', '[updated_at]', '[editor_name]', '[year]' ) ) );
			return array(
				'subject' => $values,
				'message' => $message_map,
				'brand'   => $brand_map,
			);
		}

		public function build_test_maps( $user ) {
			$editor_name = $user ? (string) $user->display_name : '';
			$summary     = array( __( 'Title changed from “Draft title” to “Sample Post”.', 'hackrepair-post-update-email-notifier' ), __( 'Content: 42 words added (now 350).', 'hackrepair-post-update-email-notifier' ) );
			// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Existing log and notice values use site-local timestamps; preserve compatibility.
			$now_str = pue_format_datetime( current_time( 'timestamp' ) );
			return $this->maps(
				array(
					'[post_title]'     => __( 'Sample Post', 'hackrepair-post-update-email-notifier' ),
					'[post_url]'       => home_url( '/' ),
					'[editor_name]'    => $editor_name,
					'[site_name]'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
					'[post_type]'      => __( 'Post', 'hackrepair-post-update-email-notifier' ),
					'[updated_at]'     => $now_str,
					'[author_name]'    => $editor_name,
					'[post_edit_url]'  => admin_url( 'edit.php' ),
					'[year]'           => wp_date( 'Y' ),
					'[event]'          => __( 'updated', 'hackrepair-post-update-email-notifier' ),
					'[previous_title]' => __( 'Draft title', 'hackrepair-post-update-email-notifier' ),
					'[change_summary]' => implode( ' ', $summary ),
					'[revision_url]'   => admin_url( 'edit.php' ),
				),
				array( '[change_summary]' => $this->summary_html( $summary ) )
			);
		}

		public function build_update_maps( $post_ID, $post_after, $post_before, $editor ) {
			$post_title  = $post_after ? $post_after->post_title : get_the_title( $post_ID );
			$ptype_obj   = $post_after ? get_post_type_object( $post_after->post_type ) : null;
			$ptype_label = ( $ptype_obj && isset( $ptype_obj->labels->singular_name ) ) ? $ptype_obj->labels->singular_name : ( $post_after ? $post_after->post_type : get_post_type( $post_ID ) );
			$author_name = $post_after ? (string) get_the_author_meta( 'display_name', $post_after->post_author ) : '';
			$editor_name = ( $editor && ! empty( $editor->display_name ) ) ? (string) $editor->display_name : $author_name;
			$edit_url    = get_edit_post_link( $post_ID, 'raw' );
			if ( ! $edit_url ) {
				$edit_url = admin_url( 'post.php?post=' . (int) $post_ID . '&action=edit' );
			}
			$is_publish = $post_before && isset( $post_before->post_status ) && 'publish' !== $post_before->post_status;
			$summary    = $this->change_summary( $post_ID, $post_after, $post_before );
			// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Existing log and notice values use site-local timestamps; preserve compatibility.
			$updated_at = pue_format_datetime( current_time( 'timestamp' ) );

			return $this->maps(
				array(
					'[post_title]'     => wp_strip_all_tags( (string) $post_title ),
					'[post_url]'       => (string) get_permalink( $post_ID ),
					'[editor_name]'    => $editor_name,
					'[site_name]'      => (string) wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
					'[post_type]'      => (string) $ptype_label,
					'[updated_at]'     => (string) $updated_at,
					'[author_name]'    => $author_name,
					'[post_edit_url]'  => (string) $edit_url,
					'[year]'           => wp_date( 'Y' ),
					'[event]'          => $is_publish ? __( 'published', 'hackrepair-post-update-email-notifier' ) : __( 'updated', 'hackrepair-post-update-email-notifier' ),
					'[previous_title]' => $post_before ? wp_strip_all_tags( (string) $post_before->post_title ) : '',
					'[change_summary]' => implode( ' ', $summary ),
					'[revision_url]'   => $this->revision_url( $post_ID, $edit_url ),
				),
				array( '[change_summary]' => $this->summary_html( $summary ) )
			);
		}

		/**
		 * Plain-language list of what changed between two versions.
		 *
		 * @param int          $post_ID Post ID.
		 * @param WP_Post|null $after Saved post.
		 * @param WP_Post|null $before Post before the save.
		 * @return string[]
		 */
		public function change_summary( $post_ID, $after, $before ) {
			$items = array();
			if ( $after && $before ) {
				if ( isset( $before->post_status ) && 'publish' !== $before->post_status ) {
					$items[] = __( 'First published.', 'hackrepair-post-update-email-notifier' );
				}
				if ( (string) $before->post_title !== (string) $after->post_title ) {
					/* translators: 1: previous title, 2: new title. */
					$items[] = sprintf( __( 'Title changed from “%1$s” to “%2$s”.', 'hackrepair-post-update-email-notifier' ), wp_strip_all_tags( (string) $before->post_title ), wp_strip_all_tags( (string) $after->post_title ) );
				}
				$old_words = $this->word_count( isset( $before->post_content ) ? $before->post_content : '' );
				$new_words = $this->word_count( isset( $after->post_content ) ? $after->post_content : '' );
				if ( $new_words > $old_words ) {
					/* translators: 1: words added, 2: new total. */
					$items[] = sprintf( _n( 'Content: %1$d word added (now %2$d).', 'Content: %1$d words added (now %2$d).', $new_words - $old_words, 'hackrepair-post-update-email-notifier' ), $new_words - $old_words, $new_words );
				} elseif ( $new_words < $old_words ) {
					/* translators: 1: words removed, 2: new total. */
					$items[] = sprintf( _n( 'Content: %1$d word removed (now %2$d).', 'Content: %1$d words removed (now %2$d).', $old_words - $new_words, 'hackrepair-post-update-email-notifier' ), $old_words - $new_words, $new_words );
				} elseif ( ( isset( $before->post_content ) ? $before->post_content : '' ) !== ( isset( $after->post_content ) ? $after->post_content : '' ) ) {
					$items[] = __( 'Content edited.', 'hackrepair-post-update-email-notifier' );
				}
				if ( isset( $before->post_excerpt, $after->post_excerpt ) && (string) $before->post_excerpt !== (string) $after->post_excerpt ) {
					$items[] = __( 'Excerpt updated.', 'hackrepair-post-update-email-notifier' );
				}
				if ( ! $items ) {
					$items[] = __( 'No title, content or excerpt changes.', 'hackrepair-post-update-email-notifier' );
				}
			}
			return (array) apply_filters( 'pue_change_summary', $items, $post_ID, $after, $before );
		}

		private function word_count( $content ) {
			$text = trim( wp_strip_all_tags( (string) $content ) );
			return '' === $text ? 0 : count( preg_split( '/\s+/u', $text ) );
		}

		private function summary_html( $items ) {
			if ( ! $items ) {
				return '';
			}
			return '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $items ) ) . '</li></ul>';
		}

		private function revision_url( $post_ID, $fallback ) {
			if ( function_exists( 'wp_get_post_revisions' ) ) {
				$revisions = wp_get_post_revisions(
					$post_ID,
					array(
						'posts_per_page' => 1,
						'orderby'        => 'date ID',
						'order'          => 'DESC',
					)
				);
				if ( $revisions ) {
					$latest = reset( $revisions );
					return admin_url( 'revision.php?revision=' . (int) $latest->ID );
				}
			}
			return (string) $fallback;
		}
	}
}
