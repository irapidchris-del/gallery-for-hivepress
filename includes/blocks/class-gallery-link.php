<?php
/**
 * Gallery link block.
 *
 * @package AdditionalGalleryForHivePress\Blocks
 */

namespace HivePress\Blocks;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Renders a "View Gallery" button in vendor and listing sidebars when the
 * vendor has at least one public photo.
 */
class Gallery_Link extends Block {

	/**
	 * Renders block HTML.
	 *
	 * @return string
	 */
	public function render() {
		$output = '';

		// Resolve the vendor and the page-specific display setting.
		$vendor  = null;
		$listing = $this->get_context( 'listing' );

		if ( ! $listing instanceof \HivePress\Models\Listing ) {
			$listing = null;
		}

		if ( $listing ) {

			// Listing page.
			if ( get_option( 'hp_gallery_hide_listing_link' ) ) {
				return $output;
			}

			$vendor = $this->get_context( 'vendor' );

			if ( ! $vendor instanceof \HivePress\Models\Vendor ) {
				$vendor = $listing->get_vendor();
			}
		} else {

			// Vendor page.
			if ( get_option( 'hp_gallery_hide_vendor_link' ) ) {
				return $output;
			}

			$vendor = $this->get_context( 'vendor' );
		}

		if ( ! $vendor instanceof \HivePress\Models\Vendor || 'publish' !== $vendor->get_status() ) {
			return $output;
		}

		// Count the media items a visitor would see (includes locked
		// previews). Both counts are zero when the vendor has no gallery
		// access.
		$counts = hivepress()->gallery->get_visible_media_counts( $vendor );

		if ( ! $counts['images'] && ! $counts['videos'] ) {
			return $output;
		}

		// Render the link.
		$gallery_url = hivepress()->router->get_url( 'gallery_view_page', [ 'vendor_id' => $vendor->get_id() ] );

		/* translators: %s: media counts, e.g. "12 photos". */
		$label = sprintf( __( 'View Gallery (%s)', 'additional-gallery-for-hivepress' ), hivepress()->gallery->get_media_count_label( $counts ) );

		$output .= '<div class="hp-agl-link widget hp-widget">';
		$output .= '<a href="' . esc_url( $gallery_url ) . '" class="hp-agl-link__button button alt"><i class="hp-icon fas fa-images"></i><span>' . esc_html( $label ) . '</span></a>';
		$output .= '</div>';

		return $output;
	}
}
