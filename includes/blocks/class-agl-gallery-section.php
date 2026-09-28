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
 * The sidebar button sends visitors away to the gallery page; this puts the gallery on the page
 * they are already reading, for sites where the vendor's work is the thing being sold. Both
 * placements are off by default and independent of the sidebar buttons.
 *
 * Covers are rendered here rather than by the gallery page's own block, which prints a "back to
 * profile" link and an empty-state message that make no sense inside a profile; a section with
 * nothing to show does not appear. The markup is the same `hp-agl-covers` structure, so one
 * stylesheet dresses both. A section always shows covers, whatever the layout setting says.
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

		/*
		 * The entitlement check every other surface makes, and the one this block was missing.
		 * The account menu item, the View Gallery button, the gallery pages and the file proxy all
		 * ask vendor_can_use_gallery() first; this did not, so a vendor whose membership lapsed
		 * kept showing folder titles, media counts and full-size cover images on the two
		 * highest-traffic pages on the site - their profile and every one of their listings -
		 * while every link out of them bounced the visitor to the home page. Gating is opt-in, so
		 * on a site that has never gated anything this is always true and costs one option read.
		 */
		if ( ! hivepress()->agl_gallery->vendor_can_use_gallery( $vendor ) ) {
			return '';
		}

		// The Vendor's own choice for this page type, where the site lets them make one.
		$surface = $this->get_context( 'listing' ) instanceof Models\Listing ? 'listing' : 'vendor';

		if ( ! hivepress()->agl_gallery->vendor_shows_gallery_on( $vendor, $surface ) ) {
			return '';
		}

		// A vendor who has nothing to show gets no empty section and no heading.
		$folders = $this->get_folders( $vendor, $surface );

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
		$locked_display = $gallery->get_locked_display();

		/*
		 * The Maximum Rows setting applies here and only here. This section sits inside somebody
		 * else's page, so a vendor with forty folders would otherwise push a listing's reviews far
		 * below the fold; whatever does not fit is reached through the link added underneath, so
		 * nothing becomes unreachable. The gallery page itself is deliberately never capped, because
		 * there is no "rest of it" to link to from there.
		 */
		$columns = $gallery->get_grid_columns();
		$rows    = $gallery->get_grid_rows();
		$cap     = ( $columns && $rows ) ? $columns * $rows : 0;

		$output   = '';
		$rendered = 0;
		$hidden   = 0;

		foreach ( $folders as $folder ) {
			$locked = 'members' === $gallery->get_effective_visibility( $folder ) && ! $gallery->user_can_view_folder( $folder, $vendor );

			if ( $locked && 'hide' === $locked_display ) {
				continue;
			}

			if ( $cap && $rendered >= $cap ) {
				++$hidden;

				continue;
			}

			++$rendered;

			$output .= $gallery->render_folder_cover( $folder, $locked );
		}

		if ( ! $rendered ) {
			return '';
		}

		$output = '<div' . $gallery->get_covers_attributes() . '>' . $output . '</div>';

		if ( $hidden ) {
			$output .= '<p class="hp-agl-section__more"><a href="' . esc_url( hivepress()->router->get_url( 'gallery_view_page', [ 'vendor_id' => $vendor->get_id() ] ) ) . '" class="hp-link">' . esc_html(
				sprintf(
					/* translators: %s: number of further folders. */
					_n( 'View %s more folder', 'View %s more folders', $hidden, 'additional-gallery-for-hivepress' ),
					number_format_i18n( $hidden )
				)
			) . ' <i class="hp-icon fas fa-arrow-right"></i></a></p>';
		}

		return $output;
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
	 * @param string                   $surface Page type the section is on, `vendor` or `listing`.
	 * @return array
	 */
	protected function get_folders( $vendor, $surface ) {
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

			// A folder its Vendor has chosen to keep to the gallery page alone.
			if ( ! hivepress()->agl_gallery->folder_shows_on_pages( $folder, $surface ) ) {
				continue;
			}

			$visible[] = $folder;
		}

		return $visible;
	}
}
