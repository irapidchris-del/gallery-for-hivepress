<?php
/**
 * Gallery folders block.
 *
 * @package AdditionalGalleryForHivePress\Blocks
 */

namespace HivePress\Blocks;

use HivePress\Helpers as hp;
use HivePress\Forms;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Renders the account gallery overview: shareable public link, drag-sortable
 * folder list and the folder creation form.
 */
class Gallery_Folders extends Block {

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
			$vendor = hivepress()->gallery->get_current_vendor();
		}

		if ( ! $vendor instanceof \HivePress\Models\Vendor ) {
			return $output;
		}

		// Get folders.
		$folders = $this->get_context( 'gallery_folders' );

		if ( ! $folders instanceof \HivePress\Queries\Post ) {
			$folders = null;
		}

		$output .= '<div class="hp-agl-account">';

		// Shareable public link.
		$listed_count = 0;

		foreach ( hivepress()->gallery->get_listed_folders( $vendor->get_id() ) as $listed_folder ) {
			$listed_count += count( (array) $listed_folder->get_images__id() );
		}

		if ( $listed_count ) {
			$public_url = hivepress()->router->get_url( 'gallery_view_page', [ 'vendor_id' => $vendor->get_id() ] );

			$output .= '<div class="hp-agl-account__share">';
			$output .= '<strong>' . esc_html__( 'Your public gallery link', 'additional-gallery-for-hivepress' ) . '</strong>';
			$output .= '<div class="hp-agl-account__share-row">';
			$output .= '<input type="text" readonly value="' . esc_url( $public_url ) . '">';
			$output .= '<button type="button" class="button hp-agl-copy" data-agl-copy="' . esc_url( $public_url ) . '">' . esc_html__( 'Copy', 'additional-gallery-for-hivepress' ) . '</button>';
			$output .= '<a href="' . esc_url( $public_url ) . '" target="_blank" class="button alt">' . esc_html__( 'View', 'additional-gallery-for-hivepress' ) . '</a>';
			$output .= '</div>';
			$output .= '</div>';
		}

		// Folder list.
		if ( $folders && $folders->count() ) {

			// Total gallery weight, so vendors can see their storage footprint.
			$total_bytes = 0;

			foreach ( $folders as $folder ) {
				if ( $folder instanceof \HivePress\Models\Gallery_Folder ) {
					$total_bytes += hivepress()->gallery->get_folder_size( $folder );
				}
			}

			if ( $total_bytes ) {
				/* translators: %s: total size, e.g. "45 MB". */
				$output .= '<p class="hp-agl-account__weight"><i class="hp-icon fas fa-database"></i> ' . esc_html( sprintf( __( 'Gallery size: %s', 'additional-gallery-for-hivepress' ), hivepress()->gallery->format_size( $total_bytes ) ) ) . '</p>';
			}

			if ( $folders->count() > 1 ) {
				$output .= '<p class="hp-agl-account__hint">' . esc_html__( 'Drag folders to reorder them in your gallery.', 'additional-gallery-for-hivepress' ) . '</p>';
			}

			$output .= '<div class="hp-agl-folders" data-component="sortable">';

			foreach ( $folders as $folder ) {
				if ( ! $folder instanceof \HivePress\Models\Gallery_Folder ) {
					continue;
				}

				$edit_url = hivepress()->router->get_url( 'gallery_folder_edit_page', [ 'gallery_folder_id' => $folder->get_id() ] );
				$sort_url = hivepress()->router->get_url( 'gallery_folder_sort_action', [ 'gallery_folder_id' => $folder->get_id() ] );

				// Get visibility details (unknown values are treated as private).
				$visibility = $folder->get_visibility();

				if ( ! in_array( $visibility, [ 'public', 'members' ], true ) ) {
					$visibility = 'private';
				}

				$badges = [
					'public'  => [ 'fa-folder-open', esc_html__( 'Public', 'additional-gallery-for-hivepress' ) ],
					'members' => [ 'fa-user-lock', esc_html__( 'Members only', 'additional-gallery-for-hivepress' ) ],
					'private' => [ 'fa-lock', esc_html__( 'Private', 'additional-gallery-for-hivepress' ) ],
				];

				$output .= '<div class="hp-agl-folder" data-url="' . esc_url( $sort_url ) . '">';
				$output .= '<i class="hp-icon fas fa-grip-lines hp-agl-folder__handle" title="' . esc_attr__( 'Drag to reorder', 'additional-gallery-for-hivepress' ) . '"></i>';
				$output .= '<a href="' . esc_url( $edit_url ) . '" class="hp-agl-folder__link">';
				$output .= '<i class="hp-icon fas ' . esc_attr( $badges[ $visibility ][0] ) . '"></i>';
				$output .= '<span class="hp-agl-folder__title">' . esc_html( $folder->get_title() ) . '</span>';
				$output .= '</a>';
				$output .= '<span class="hp-agl-badge hp-agl-badge--' . esc_attr( $visibility ) . '">' . $badges[ $visibility ][1] . '</span>';
				$count_label = hivepress()->gallery->get_media_count_label( hivepress()->gallery->get_media_counts( $folder ) );
				$folder_size = hivepress()->gallery->get_folder_size( $folder );

				if ( $folder_size ) {
					$count_label .= ' · ' . hivepress()->gallery->format_size( $folder_size );
				}

				$output .= '<span class="hp-agl-folder__count">' . esc_html( $count_label ) . '</span>';

				// Copy the folder link for shareable folders.
				if ( 'private' !== $visibility ) {
					$output .= '<button type="button" class="hp-agl-folder__copy" data-agl-copy="' . esc_url( hivepress()->gallery->get_folder_url( $folder ) ) . '" title="' . esc_attr__( 'Copy folder link', 'additional-gallery-for-hivepress' ) . '"><i class="hp-icon fas fa-link"></i></button>';
				}

				$output .= '</div>';
			}

			$output .= '</div>';
		} else {
			$output .= '<p class="hp-agl-account__empty">' . esc_html__( 'You have no gallery folders yet. Create your first folder below to start showcasing your work.', 'additional-gallery-for-hivepress' ) . '</p>';
		}

		// Folder creation form (respecting the folder limit).
		$max_folders  = hp_agl_int( get_option( 'hp_gallery_max_folders' ) );
		$folder_count = $folders ? $folders->count() : 0;

		if ( $max_folders && $folder_count >= $max_folders ) {
			/* translators: %s: folders number. */
			$output .= '<p class="hp-agl-account__limit">' . esc_html( sprintf( _n( 'You have reached the limit of %s folder.', 'You have reached the limit of %s folders.', $max_folders, 'additional-gallery-for-hivepress' ), number_format_i18n( $max_folders ) ) ) . '</p>';
		} else {
			$output .= '<div class="hp-agl-account__create">';
			$output .= '<h3>' . esc_html__( 'New Folder', 'additional-gallery-for-hivepress' ) . '</h3>';
			$output .= ( new Forms\Gallery_Folder_Create() )->render();
			$output .= '</div>';
		}

		$output .= '</div>';

		return $output;
	}
}
