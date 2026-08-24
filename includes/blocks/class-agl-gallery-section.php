<?php
/**
 * Gallery section block.
 *
 * @package HivePress\Blocks
 */

namespace HivePress\Blocks;

use HivePress\Helpers as hp;
use HivePress\Models;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Shows a vendor's gallery folders inside another page.
 *
 * The sidebar button sends somebody away to the gallery page. This puts the gallery itself on the
 * page they are already reading, which suits a site whose vendors are photographers or stylists -
 * the work is the thing being sold, so it belongs beside the listing rather than one click away.
 *
 * Both placements are off unless the site owner asks for them, and they are independent of the
 * sidebar buttons: a site can show the section instead of the button, as well as it, or neither.
 *
 * The covers are rendered here rather than by handing the job to the gallery page's own block. That
 * block is written for a page of its own: it prints a "back to this vendor's profile" link, which
 * is nonsense on the profile itself, and an empty-state message, which would leave a Gallery heading
 * standing over the words "no photos yet". A section that has nothing to show should not appear at
 * all. The markup below is the same `hp-agl-covers` structure, so one stylesheet still dresses both.
 *
 * Whatever the site's gallery layout setting says, a section always shows covers. The single-gallery
 * layout is a whole page of photographs, and dropping that into the middle of a listing would bury
 * the listing.
 */
class Agl_Gallery_Section extends Block {

	/**
	 * Renders the block.
	 *
	 * @return string
	 */
	public function render() {
		$vendor = $this->get_section_vendor();

		if ( ! $vendor ) {
			return '';
		}

		// A vendor who has nothing to show gets no empty section and no heading.
		$folders = $this->get_folders( $vendor );

		if ( ! $folders ) {
			return '';
		}

		$covers = $this->render_covers( $vendor, $folders );

		if ( ! $covers ) {
			return '';
		}

		return '<div class="hp-agl-section">' . $covers . '</div>';
	}

	/**
	 * Renders the folder covers.
	 *
	 * Access is decided exactly as the gallery page decides it, so a members-only folder is blurred,
	 * shown as a placeholder or hidden here according to the same Locked Folder Display setting.
	 *
	 * @param \HivePress\Models\Vendor $vendor Vendor object.
	 * @param array                    $folders Folder objects.
	 * @return string Empty when nothing is visible to this reader.
	 */
	protected function render_covers( $vendor, $folders ) {
		$gallery        = hivepress()->agl_gallery;
		$member_view    = $gallery->user_can_view_member_folders( $vendor );
		$locked_display = get_option( 'hp_gallery_locked_display', 'blur' );

		$output   = '';
		$rendered = 0;

		foreach ( $folders as $folder ) {
			$locked = 'members' === $gallery->get_effective_visibility( $folder ) && ! $member_view;

			if ( $locked && 'hide' === $locked_display ) {
				continue;
			}

			++$rendered;

			$cover    = '';
			$cover_id = $gallery->get_folder_cover_id( $folder );

			if ( $locked ) {
				$teaser_url = ( 'blur' === $locked_display && $cover_id ) ? $gallery->get_teaser_url( $cover_id ) : null;

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

			$output .= '<a href="' . esc_url( $gallery->get_folder_url( $folder ) ) . '" class="hp-agl-cover' . ( $locked ? ' hp-agl-cover--locked' : '' ) . '">';
			$output .= '<span class="hp-agl-cover__image' . ( $cover ? '' : ' hp-agl-cover__image--placeholder' ) . '">' . $cover;

			if ( $locked ) {
				$output .= '<i class="hp-icon fas fa-lock"></i>';
			}

			$output .= '</span>';
			$output .= '<span class="hp-agl-cover__title">' . esc_html( $folder->get_title() ) . '</span>';
			$output .= '<span class="hp-agl-cover__count hp-meta">' . esc_html( $gallery->get_media_count_label( $gallery->get_media_counts( $folder ) ) ) . '</span>';

			if ( $locked ) {
				$output .= '<span class="hp-status hp-status--pending"><span>' . esc_html__( 'Members only', 'additional-gallery-for-hivepress' ) . '</span></span>';
			}

			$output .= '</a>';
		}

		if ( ! $rendered ) {
			return '';
		}

		return '<div class="hp-agl-covers">' . $output . '</div>';
	}

	/**
	 * Gets the vendor whose gallery this section shows.
	 *
	 * On a vendor profile the vendor is the page. On a listing it is the listing's owner, which is
	 * the post parent - the same relationship every other part of this plugin uses.
	 *
	 * @return \HivePress\Models\Vendor|null
	 */
	protected function get_section_vendor() {
		$vendor = $this->get_context( 'vendor' );

		if ( $vendor instanceof Models\Vendor ) {
			return $vendor;
		}

		$listing = $this->get_context( 'listing' );

		if ( ! $listing instanceof Models\Listing ) {
			return null;
		}

		$vendor_id = hp_agl_int( $listing->get_vendor__id() );

		if ( ! $vendor_id ) {
			return null;
		}

		$vendor = Models\Vendor::query()->get_by_id( $vendor_id );

		return $vendor instanceof Models\Vendor ? $vendor : null;
	}

	/**
	 * Gets the folders to show, in the vendor's own order.
	 *
	 * The same query and ordering the gallery page uses, so a vendor who has arranged their folders
	 * sees that arrangement here too.
	 *
	 * @param \HivePress\Models\Vendor $vendor Vendor object.
	 * @return array
	 */
	protected function get_folders( $vendor ) {
		$folders = Models\Gallery_Folder::query()->filter(
			[
				'status' => 'publish',
				'vendor' => $vendor->get_id(),
			]
		)->order(
			[
				'sort_order'   => 'asc',
				'created_date' => 'asc',
			]
		)->get();

		$visible = [];

		foreach ( $folders as $folder ) {
			if ( ! $folder instanceof Models\Gallery_Folder ) {
				continue;
			}

			// A private folder is nobody else's business, and an empty one is not worth a section.
			if ( 'private' === hivepress()->agl_gallery->get_effective_visibility( $folder ) ) {
				continue;
			}

			if ( ! count( (array) $folder->get_images__id() ) ) {
				continue;
			}

			$visible[] = $folder;
		}

		return $visible;
	}
}
