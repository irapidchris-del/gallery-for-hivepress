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
	 * Whether the button stands on its own in the sidebar.
	 *
	 * Set from the template merge map (see Agl_Gallery::get_gallery_link_blocks()), which decides it
	 * from the Gallery Button Position setting. Standalone means the button has to bring the widget
	 * wrapper and spacing that core's actions container would otherwise have given it.
	 *
	 * @var bool
	 */
	protected $standalone = false;

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

		/*
		 * No wrapper of its own. This block renders inside core's primary actions container now, and
		 * that container IS the widget (`hp-widget widget` on
		 * hivepress/includes/templates/class-vendor-view-page.php:158-160 and
		 * class-listing-view-page.php:199-201). The div this used to carry brought those same two
		 * classes with it, and a widget nested inside a widget is what produced the loose,
		 * disconnected gap around the button that Kseniia reported. It also broke the spacing rule
		 * that ties the actions together, which core writes as
		 * `--view-page &__actions--primary &__action:not(:last-child)`
		 * (hivepress/assets/css/frontend.less:856-860, :1124-1128) and which only reaches a DIRECT
		 * `__action` child.
		 *
		 * Hence the model prefix below: the same button sits in two differently named containers, so
		 * it has to answer to `hp-vendor__action` on a profile and `hp-listing__action` on a listing
		 * to inherit either one's spacing and the theme's own rules for them.
		 */
		$prefix = $listing ? 'hp-listing' : 'hp-vendor';

		/*
		 * Three sets of classes, and all three are needed.
		 *
		 * `{model}__action` is the position: sibling spacing inside the actions container, plus
		 * whatever the active theme does to the actions on that page. It is dropped when the button
		 * stands alone, because those rules are written as
		 * `--view-page &__actions--primary &__action` (hivepress/assets/css/frontend.less:856-860,
		 * :1124-1128) and match nothing outside that container anyway.
		 *
		 * `hp-button hp-button--wide` is HivePress's own structure: width:100%, inline-flex centring
		 * and a 0.5rem margin on a leading icon (hivepress/assets/css/frontend.min.css). Dropping
		 * them left the button shrink-wrapped to its text with the icon almost touching the label,
		 * and the container centres its children (`align-items: center`, frontend.less:50-54), so
		 * without the explicit width this would not fill the sidebar at all.
		 *
		 * `button button--large button--primary alt` is the appearance, copied from the Send Message
		 * button directly above this one
		 * (hivepress-messages/templates/vendor/view/page/message-send-link.php). `button--large` is
		 * the one that sets the height; without it this sat visibly shorter than that button.
		 */
		$classes = 'hp-agl-link__button hp-button hp-button--wide button button--large button--primary alt';

		if ( ! $this->standalone ) {
			$classes = $prefix . '__action ' . $prefix . '__action--gallery ' . $classes;
		}

		$button = '<a href="' . esc_url( $gallery_url ) . '" class="' . esc_attr( $classes ) . '"><i class="hp-icon fas fa-images"></i><span>' . esc_html( $label ) . '</span></a>';

		/*
		 * Standing on its own it needs the widget wrapper, which is what gives it the 2rem gap every
		 * other sidebar block has (`hp-widget:not(:last-child)`, frontend.less:547-551). Inside the
		 * actions container that wrapper is exactly what must NOT be there: the container is already
		 * the widget, and nesting one inside another is what produced the disconnected gap Kseniia
		 * reported.
		 */
		if ( $this->standalone ) {
			return '<div class="hp-agl-link hp-widget widget">' . $button . '</div>';
		}

		return $button;
	}
}
