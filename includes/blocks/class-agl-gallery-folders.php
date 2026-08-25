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
class Agl_Gallery_Folders extends Block {

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
			$vendor = hivepress()->agl_gallery->get_current_vendor();
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

		foreach ( hivepress()->agl_gallery->get_listed_folders( $vendor->get_id() ) as $listed_folder ) {
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
		} elseif ( $folders && $folders->count() ) {
			/*
			 * There is no link to show, and until now nothing said so: a vendor with only private
			 * folders, or a public folder they have not put a photo in yet, simply found the whole
			 * block missing with no way of knowing whether it was a fault or a rule.
			 */
			$output .= '<div class="hp-agl-account__share hp-agl-account__share--empty">';
			$output .= '<strong>' . esc_html__( 'Your public gallery link', 'additional-gallery-for-hivepress' ) . '</strong>';
			$output .= '<p class="hp-agl-account__share-note">' . esc_html__( 'Your gallery gets a link to share once a public or members-only folder has a photo in it. Private folders stay visible to you alone, so they never appear here.', 'additional-gallery-for-hivepress' ) . '</p>';
			$output .= '</div>';
		}

		// Folder list.
		if ( $folders && $folders->count() ) {

			// Storage usage is only the vendor's business when a quota applies;
			// with no quota the number is just noise, so nothing is shown.
			$storage_limit = hivepress()->agl_gallery->get_storage_limit( $vendor );

			if ( $storage_limit ) {
				$storage_used = hivepress()->agl_gallery->get_storage_used( $vendor );

				/* translators: 1: used size, 2: allowed size. */
				$output .= '<p class="hp-agl-account__weight hp-meta"><i class="hp-icon fas fa-database"></i> ' . esc_html( sprintf( __( 'Storage: %1$s used of %2$s allowed', 'additional-gallery-for-hivepress' ), hivepress()->agl_gallery->format_size( $storage_used ), hivepress()->agl_gallery->format_size( $storage_limit ) ) ) . '</p>';
			}

			if ( $folders->count() > 1 ) {
				$output .= '<p class="hp-agl-account__hint hp-meta">' . esc_html__( 'Drag folders to reorder them in your gallery.', 'additional-gallery-for-hivepress' ) . '</p>';
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

				// Visibility maps onto core's own status pill, so the badges
				// inherit HivePress' colours instead of inventing new ones:
				// publish reads as live, pending as gated, draft as hidden.
				$badges = [
					'public'  => [ 'fa-folder-open', esc_html__( 'Public', 'additional-gallery-for-hivepress' ), 'publish' ],
					'members' => [ 'fa-user-lock', esc_html__( 'Members only', 'additional-gallery-for-hivepress' ), 'pending' ],
					'private' => [ 'fa-lock', esc_html__( 'Private', 'additional-gallery-for-hivepress' ), 'draft' ],
				];

				$output .= '<div class="hp-agl-folder" data-url="' . esc_url( $sort_url ) . '">';
				$output .= '<i class="hp-icon fas fa-grip-lines hp-agl-folder__handle" title="' . esc_attr__( 'Drag to reorder', 'additional-gallery-for-hivepress' ) . '"></i>';
				$output .= '<a href="' . esc_url( $edit_url ) . '" class="hp-agl-folder__link">';
				$output .= '<i class="hp-icon fas ' . esc_attr( $badges[ $visibility ][0] ) . '"></i>';
				$output .= '<span class="hp-agl-folder__title">' . esc_html( $folder->get_title() ) . '</span>';
				$output .= '</a>';

				// The members-only pill comes from the one place that renders it, so this row's badge
				// carries the same padlock as the badge on the public gallery and the folder page.
				if ( 'members' === $visibility ) {
					$output .= hivepress()->agl_gallery->render_members_badge();
				} else {
					$output .= '<span class="hp-status hp-status--' . esc_attr( $badges[ $visibility ][2] ) . '"><span>' . $badges[ $visibility ][1] . '</span></span>';
				}

				$count_label = hivepress()->agl_gallery->get_media_count_label( hivepress()->agl_gallery->get_media_counts( $folder ) );

				// Per-folder sizes only matter under a quota.
				if ( $storage_limit ) {
					$folder_size = hivepress()->agl_gallery->get_folder_size( $folder );

					if ( $folder_size ) {
						$count_label .= ' · ' . hivepress()->agl_gallery->format_size( $folder_size );
					}
				}

				$output .= '<span class="hp-agl-folder__count hp-meta">' . esc_html( $count_label ) . '</span>';

				// Copy the folder link for shareable folders.
				if ( 'private' !== $visibility ) {
					$output .= '<button type="button" class="hp-agl-folder__copy" data-agl-icon-only="1" data-agl-copy="' . esc_url( hivepress()->agl_gallery->get_folder_url( $folder ) ) . '" title="' . esc_attr__( 'Copy folder link', 'additional-gallery-for-hivepress' ) . '"><i class="hp-icon fas fa-link"></i></button>';
				}

				$output .= '</div>';
			}

			$output .= '</div>';
		} else {
			$output .= '<p class="hp-agl-account__empty">' . esc_html__( 'You have no gallery folders yet. Create your first folder below to start showcasing your work.', 'additional-gallery-for-hivepress' ) . '</p>';
		}

		// Folder creation form (respecting the folder limit, which a membership
		// plan may raise or lower for this vendor).
		$max_folders  = hivepress()->agl_gallery->get_folder_limit( $vendor );
		$folder_count = $folders ? $folders->count() : 0;

		if ( $max_folders && $folder_count >= $max_folders ) {
			/* translators: %s: folders number. */
			$output .= '<p class="hp-agl-account__limit">' . esc_html( sprintf( _n( 'You have reached the limit of %s folder.', 'You have reached the limit of %s folders.', $max_folders, 'additional-gallery-for-hivepress' ), number_format_i18n( $max_folders ) ) ) . '</p>';
		} else {
			$output .= '<div class="hp-agl-account__create">';

			/*
			 * `hp-section__title` is core's own heading class, and it is what gives a heading the
			 * accent rule above it that every theme draws in its own colour
			 * (listinghive/style.css:681-698 draws it as a 45px bar). Without it this heading was the
			 * only one on the account pages with nothing above it, which read as an unstyled leftover
			 * rather than as a section of its own.
			 */
			$output .= '<h3 class="hp-section__title">' . esc_html__( 'New Folder', 'additional-gallery-for-hivepress' ) . '</h3>';
			$output .= ( new Forms\Agl_Gallery_Folder_Create() )->render();
			$output .= '</div>';
		}

		/*
		 * Paid access pricing, when the site sells it AND sells it per vendor. Under "each folder
		 * separately" the panel belongs on each folder's own edit page instead, and render_price_panel()
		 * is what decides which of the two it is - passing no folder here is what asks it for the
		 * whole-gallery prices.
		 */
		$output .= hivepress()->agl_gallery->render_price_panel( $vendor );

		$output .= '</div>';

		return $output;
	}
}
