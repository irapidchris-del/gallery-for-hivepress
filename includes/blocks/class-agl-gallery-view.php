<?php
/**
 * Gallery view block.
 *
 * @package AdditionalGalleryForHivePress\Blocks
 */

namespace HivePress\Blocks;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Renders a vendor's public gallery, either as a grid of folder covers
 * linking to folder pages, or as a single page with every folder expanded.
 * Members-only folders are rendered locked for visitors without access,
 * without ever exposing the original file URLs.
 */
class Agl_Gallery_View extends Block {

	/**
	 * Renders block HTML.
	 *
	 * @return string
	 */
	public function render() {
		$output = '';

		// Get vendor.
		$vendor = $this->get_context( 'vendor' );

		if ( ! $vendor instanceof \HivePress\Models\Vendor ) {
			return $output;
		}

		// Get folders.
		$folders = $this->get_context( 'gallery_folders' );

		if ( ! $folders instanceof \HivePress\Queries\Post ) {
			$folders = null;
		}

		// Get access details.
		$gallery        = hivepress()->agl_gallery;
		$manages        = $gallery->can_manage_gallery( $vendor );
		$locked_display = $gallery->get_locked_display();

		$output .= '<div class="hp-agl-gallery">';

		// Link back to the vendor profile if vendor pages are enabled.
		if ( get_option( 'hp_vendor_enable_display' ) ) {
			$profile_url = get_permalink( $vendor->get_id() );

			if ( $profile_url ) {
				/*
				 * The comment below says "person" rather than "vendor" because the identical string is
				 * also used for a comment author (components/class-agl-gallery.php). Gettext keys on
				 * the string, so the two share one entry whatever each site calls them, and two
				 * disagreeing translator comments only make `wp i18n make-pot` warn and pick one.
				 * This rationale is a separate comment so it stays out of the translator note itself.
				 */

				/* translators: %s: person's name. */
				$output .= '<p class="hp-agl-gallery__profile"><a href="' . esc_url( $profile_url ) . '"><i class="hp-icon fas fa-arrow-left"></i> ' . esc_html( sprintf( __( 'View %s\'s profile', 'additional-gallery-for-hivepress' ), $vendor->get_name() ) ) . '</a></p>';
			}
		}

		// Last updated.
		$folder_ids = [];

		if ( $folders ) {
			foreach ( $folders as $folder ) {
				if ( $folder instanceof \HivePress\Models\Gallery_Folder ) {
					$folder_ids[] = $folder->get_id();
				}
			}
		}

		$updated_time = $gallery->get_updated_time( $folder_ids );

		if ( $updated_time ) {
			/* translators: %s: human-readable time difference. */
			$output .= '<p class="hp-agl-gallery__updated hp-meta"><i class="hp-icon fas fa-clock"></i> ' . esc_html( sprintf( __( 'Updated %s ago', 'additional-gallery-for-hivepress' ), human_time_diff( $updated_time ) ) ) . '</p>';
		}

		/*
		 * Whether any private folder is on show, which only ever happens for the vendor themselves or
		 * a site owner. It is said out loud below the grid, because a private folder looks exactly
		 * like a public one here and a vendor checking how their gallery reads to a stranger has no
		 * other way of knowing that what they are looking at is not what a stranger sees.
		 */
		$showed_private = false;

		// Render folders.
		$rendered = 0;

		if ( $folders ) {
			if ( 'single' === $gallery->get_layout() ) {

				// Single page: every folder expanded.
				foreach ( $folders as $folder ) {
					if ( ! $folder instanceof \HivePress\Models\Gallery_Folder ) {
						continue;
					}

					if ( ! count( (array) $folder->get_images__id() ) ) {
						continue;
					}

					$visibility = $gallery->get_effective_visibility( $folder );

					// Check folder access.
					$locked = 'members' === $visibility && ! $gallery->user_can_view_folder( $folder, $vendor );

					if ( $locked && 'hide' === $locked_display ) {
						continue;
					}

					if ( 'private' === $visibility ) {
						$showed_private = true;
					}

					++$rendered;

					$output .= '<section class="hp-agl-gallery__folder' . ( $locked ? ' hp-agl-gallery__folder--locked' : '' ) . '" id="folder-' . esc_attr( (string) $folder->get_id() ) . '">';

					// Folder header with a permalink to the folder page.
					$output .= '<h2 class="hp-agl-gallery__folder-title">' . esc_html( $folder->get_title() );

					if ( $locked ) {
						$output .= ' ' . $gallery->render_members_badge();
					} elseif ( 'private' === $visibility ) {
						$output .= ' ' . $gallery->render_private_badge();
					}

					$output .= ' <a href="' . esc_url( $gallery->get_folder_url( $folder ) ) . '" class="hp-agl-gallery__folder-link" title="' . esc_attr__( 'Folder link', 'additional-gallery-for-hivepress' ) . '"><i class="hp-icon fas fa-link"></i></a>';
					$output .= '</h2>';

					if ( $folder->get_description() ) {
						$output .= '<p class="hp-agl-gallery__folder-description">' . esc_html( $folder->get_description() ) . '</p>';
					}

					if ( $locked ) {
						/* translators: %s: media counts. */
						$output .= '<p class="hp-agl-gallery__folder-locked-note">' . esc_html( sprintf( __( 'This folder contains %s.', 'additional-gallery-for-hivepress' ), $gallery->get_media_count_label( $gallery->get_media_counts( $folder ) ) ) ) . '</p>';
					}

					$output .= $gallery->render_folder_media( $folder, $locked );

					// Unlock actions: buy access and/or upgrade a membership.
					if ( $locked ) {
						$output .= $gallery->render_unlock_actions( $vendor, $folder );
					}

					$output .= '</section>';
				}
			} else {

				// Folder covers linking to folder pages.
				$covers = '';

				foreach ( $folders as $folder ) {
					if ( ! $folder instanceof \HivePress\Models\Gallery_Folder ) {
						continue;
					}

					if ( ! count( (array) $folder->get_images__id() ) ) {
						continue;
					}

					$visibility = $gallery->get_effective_visibility( $folder );

					// Check folder access.
					$locked = 'members' === $visibility && ! $gallery->user_can_view_folder( $folder, $vendor );

					if ( $locked && 'hide' === $locked_display ) {
						continue;
					}

					if ( 'private' === $visibility ) {
						$showed_private = true;
					}

					++$rendered;

					$covers .= $gallery->render_folder_cover( $folder, $locked, $visibility );
				}

				if ( $covers ) {
					$output .= '<div' . $gallery->get_covers_attributes() . '>' . $covers . '</div>';
				}
			}
		}

		// Empty state.
		if ( ! $rendered ) {
			$output .= '<p class="hp-agl-gallery__empty">' . esc_html__( 'This gallery has no photos yet. Check back soon!', 'additional-gallery-for-hivepress' ) . '</p>';
		}

		if ( $showed_private && $manages ) {
			$output .= '<p class="hp-agl-gallery__private-note hp-meta"><i class="hp-icon fas fa-lock"></i> ' . esc_html__( 'Folders marked Private are shown here because this is your own gallery. Visitors do not see them at all.', 'additional-gallery-for-hivepress' ) . '</p>';
		}

		$output .= '</div>';

		return $output;
	}
}
