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
		$member_view    = hivepress()->agl_gallery->user_can_view_member_folders( $vendor );
		$locked_display = hivepress()->agl_gallery->get_locked_display();

		$output .= '<div class="hp-agl-gallery">';

		// Link back to the vendor profile if vendor pages are enabled.
		if ( get_option( 'hp_vendor_enable_display' ) ) {
			$profile_url = get_permalink( $vendor->get_id() );

			if ( $profile_url ) {
				/* translators: %s: vendor name. */
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

		$updated_time = hivepress()->agl_gallery->get_updated_time( $folder_ids );

		if ( $updated_time ) {
			/* translators: %s: human-readable time difference. */
			$output .= '<p class="hp-agl-gallery__updated hp-meta"><i class="hp-icon fas fa-clock"></i> ' . esc_html( sprintf( __( 'Updated %s ago', 'additional-gallery-for-hivepress' ), human_time_diff( $updated_time ) ) ) . '</p>';
		}

		// Render folders.
		$rendered = 0;

		if ( $folders ) {
			if ( 'single' === hivepress()->agl_gallery->get_layout() ) {

				// Single page: every folder expanded.
				foreach ( $folders as $folder ) {
					if ( ! $folder instanceof \HivePress\Models\Gallery_Folder ) {
						continue;
					}

					if ( ! count( (array) $folder->get_images__id() ) ) {
						continue;
					}

					// Check folder access.
					$locked = 'members' === hivepress()->agl_gallery->get_effective_visibility( $folder ) && ! $member_view;

					if ( $locked && 'hide' === $locked_display ) {
						continue;
					}

					++$rendered;

					$output .= '<section class="hp-agl-gallery__folder' . ( $locked ? ' hp-agl-gallery__folder--locked' : '' ) . '" id="folder-' . esc_attr( (string) $folder->get_id() ) . '">';

					// Folder header with a permalink to the folder page.
					$output .= '<h2 class="hp-agl-gallery__folder-title">' . esc_html( $folder->get_title() );

					if ( $locked ) {
						$output .= ' <span class="hp-status hp-status--pending"><span><i class="hp-icon fas fa-lock"></i> ' . esc_html__( 'Members only', 'additional-gallery-for-hivepress' ) . '</span>';
					}

					$output .= ' <a href="' . esc_url( hivepress()->agl_gallery->get_folder_url( $folder ) ) . '" class="hp-agl-gallery__folder-link" title="' . esc_attr__( 'Folder link', 'additional-gallery-for-hivepress' ) . '"><i class="hp-icon fas fa-link"></i></a>';
					$output .= '</h2>';

					if ( $folder->get_description() ) {
						$output .= '<p class="hp-agl-gallery__folder-description">' . esc_html( $folder->get_description() ) . '</p>';
					}

					if ( $locked ) {
						/* translators: %s: media counts. */
						$output .= '<p class="hp-agl-gallery__folder-locked-note">' . esc_html( sprintf( __( 'This folder contains %s.', 'additional-gallery-for-hivepress' ), hivepress()->agl_gallery->get_media_count_label( hivepress()->agl_gallery->get_media_counts( $folder ) ) ) ) . '</p>';
					}

					$output .= hivepress()->agl_gallery->render_folder_media( $folder, $locked );

					// Unlock actions: buy access and/or upgrade a membership.
					if ( $locked ) {
						$output .= hivepress()->agl_gallery->render_unlock_actions( $vendor );
					}

					$output .= '</section>';
				}
			} else {

				// Folder covers linking to folder pages.
				$output .= '<div class="hp-agl-covers">';

				foreach ( $folders as $folder ) {
					if ( ! $folder instanceof \HivePress\Models\Gallery_Folder ) {
						continue;
					}

					if ( ! count( (array) $folder->get_images__id() ) ) {
						continue;
					}

					// Check folder access.
					$locked = 'members' === hivepress()->agl_gallery->get_effective_visibility( $folder ) && ! $member_view;

					if ( $locked && 'hide' === $locked_display ) {
						continue;
					}

					++$rendered;

					// Get the cover image.
					$cover    = '';
					$cover_id = hivepress()->agl_gallery->get_folder_cover_id( $folder );

					if ( $locked ) {
						$teaser_url = null;

						if ( 'blur' === $locked_display && $cover_id ) {
							$teaser_url = hivepress()->agl_gallery->get_teaser_url( $cover_id );
						}

						if ( $teaser_url ) {
							$cover = '<img src="' . esc_url( $teaser_url ) . '" alt="" loading="lazy">';
						}
					} elseif ( $cover_id ) {
						$cover = wp_get_attachment_image(
							$cover_id,
							'medium_large',
							false,
							[
								'loading' => 'lazy',
								'alt'     => $folder->get_title(),
							]
						);
					}

					$output .= '<a href="' . esc_url( hivepress()->agl_gallery->get_folder_url( $folder ) ) . '" class="hp-agl-cover' . ( $locked ? ' hp-agl-cover--locked' : '' ) . '">';
					$output .= '<span class="hp-agl-cover__image' . ( $cover ? '' : ' hp-agl-cover__image--placeholder' ) . '">' . $cover;

					if ( $locked ) {
						$output .= '<i class="hp-icon fas fa-lock"></i>';
					}

					$output .= '</span>';
					$output .= '<span class="hp-agl-cover__title">' . esc_html( $folder->get_title() ) . '</span>';
					$output .= '<span class="hp-agl-cover__count hp-meta">' . esc_html( hivepress()->agl_gallery->get_media_count_label( hivepress()->agl_gallery->get_media_counts( $folder ) ) ) . '</span>';

					if ( $locked ) {
						$output .= '<span class="hp-status hp-status--pending"><span>' . esc_html__( 'Members only', 'additional-gallery-for-hivepress' ) . '</span>';
					}

					$output .= '</a>';
				}

				$output .= '</div>';
			}
		}

		// Empty state.
		if ( ! $rendered ) {
			$output .= '<p class="hp-agl-gallery__empty">' . esc_html__( 'This gallery has no photos yet. Check back soon!', 'additional-gallery-for-hivepress' ) . '</p>';
		}

		$output .= '</div>';

		return $output;
	}
}
