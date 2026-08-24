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
class Agl_Gallery_Link extends Block {

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
		$counts = hivepress()->agl_gallery->get_visible_media_counts( $vendor );

		if ( ! $counts['images'] && ! $counts['videos'] ) {
			return $output;
		}

		// Render the link, with the count unless the site switched it off.
		$gallery_url = hivepress()->router->get_url( 'gallery_view_page', [ 'vendor_id' => $vendor->get_id() ] );

		if ( get_option( 'hp_gallery_show_button_count' ) ) {
			/* translators: %s: media counts, e.g. "12 photos". */
			$label = sprintf( __( 'View Gallery (%s)', 'additional-gallery-for-hivepress' ), hivepress()->agl_gallery->get_media_count_label( $counts ) );
		} else {
			$label = __( 'View Gallery', 'additional-gallery-for-hivepress' );
		}

		// The wrapper is a HivePress widget, so the sidebar spaces it like every other one.
		$output .= '<div class="hp-agl-link widget hp-widget">';

		/*
		 * Two sets of classes, and both are needed.
		 *
		 * `hp-button hp-button--wide` is HivePress's own structure: width:100%, inline-flex centring
		 * and a 0.5rem margin on a leading icon (hivepress/assets/css/frontend.min.css). Dropping
		 * them left the button shrink-wrapped to its text with the icon almost touching the label.
		 *
		 * `button button--large button--primary alt` is the appearance, copied from the Send Message
		 * button directly above this one
		 * (hivepress-messages/templates/vendor/view/page/message-send-link.php). `button--large` is
		 * the one that sets the height; without it this sat visibly shorter than that button.
		 */
		$output .= '<a href="' . esc_url( $gallery_url ) . '" class="hp-agl-link__button hp-button hp-button--wide button button--large button--primary alt"><i class="hp-icon fas fa-images"></i><span>' . esc_html( $label ) . '</span></a>';
		$output .= '</div>';

		return $output;
	}
}
