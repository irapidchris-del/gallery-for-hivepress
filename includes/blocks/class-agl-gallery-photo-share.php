<?php
/**
 * Gallery photo share block.
 *
 * @package AdditionalGalleryForHivePress\Blocks
 */

namespace HivePress\Blocks;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * The Share button in a gallery sidebar, with its pop-up, sharing the address of the page it sits on:
 * the photo page, and since 1.10.8 the folder page and the Vendor's gallery page too (the block reads
 * whichever of those the page's context carries, most specific first). Everyone who can see the page
 * sees it; the page itself decides what a visitor may view.
 */
class Agl_Gallery_Photo_Share extends Block {

	/**
	 * Class initializer.
	 *
	 * @param array $meta Class meta values.
	 */
	public static function init( $meta = [] ) {
		$meta = hp\merge_arrays(
			[
				'label' => null,
			],
			$meta
		);

		parent::init( $meta );
	}

	/**
	 * Renders block HTML.
	 *
	 * @return string
	 */
	public function render() {
		$folder = $this->get_context( 'gallery_folder' );
		$photo  = $this->get_context( 'gallery_photo' );
		$vendor = $this->get_context( 'vendor' );

		if ( $folder instanceof \HivePress\Models\Gallery_Folder && $photo instanceof \HivePress\Models\Attachment ) {
			$url = hivepress()->router->get_url(
				'gallery_photo_view_page',
				[
					'vendor_id'         => $folder->get_vendor__id(),
					'gallery_folder_id' => $folder->get_id(),
					'attachment_id'     => $photo->get_id(),
				]
			);

			// The photo's own title when it has a real one, otherwise the folder's: a raw file name is
			// noise in a shared message (the same test the Manage Photo card uses).
			$title = trim( (string) get_the_title( $photo->get_id() ) );
			$file  = pathinfo( (string) get_post_meta( $photo->get_id(), '_wp_attached_file', true ), PATHINFO_FILENAME );

			if ( '' === $title || sanitize_title( $title ) === sanitize_title( $file ) ) {
				$title = (string) $folder->get_title();
			}
		} elseif ( $folder instanceof \HivePress\Models\Gallery_Folder ) {

			// The folder page (controllers/class-agl-gallery.php, render_gallery_folder_view_page()).
			$url   = hivepress()->router->get_url(
				'gallery_folder_view_page',
				[
					'vendor_id'         => $folder->get_vendor__id(),
					'gallery_folder_id' => $folder->get_id(),
				]
			);
			$title = (string) $folder->get_title();
		} elseif ( $vendor instanceof \HivePress\Models\Vendor ) {

			// The Vendor's gallery page (render_gallery_view_page()), shared with the page's own title.
			$url = hivepress()->router->get_url( 'gallery_view_page', [ 'vendor_id' => $vendor->get_id() ] );

			/* translators: %s: vendor name. */
			$title = sprintf( esc_html__( 'Gallery: %s', 'additional-gallery-for-hivepress' ), $vendor->get_name() );
		} else {
			return '';
		}

		$output = hivepress()->agl_gallery->render_share( (string) $url, $title );

		return '' === $output ? '' : '<div class="hp-agl-share__block">' . $output . '</div>';
	}
}
