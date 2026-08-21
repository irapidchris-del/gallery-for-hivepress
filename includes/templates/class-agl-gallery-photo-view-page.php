<?php
/**
 * Gallery photo view page template.
 *
 * @package AdditionalGalleryForHivePress\Templates
 */

namespace HivePress\Templates;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Public page displaying a single photo with its comment thread, and a
 * sidebar holding the vendor's profile card, the owner's management options
 * and the "Photo Page (sidebar)" widget area.
 */
class Agl_Gallery_Photo_View_Page extends Page_Sidebar_Left {

	/**
	 * Class constructor.
	 *
	 * @param array $args Template arguments.
	 */
	public function __construct( $args = [] ) {

		// The core sidebar templates place the columns purely by order:
		// Page_Sidebar_Left is sidebar 10 / content 20, and its Right variant
		// only swaps those numbers, so the admin setting does the same here.
		$left = 'left' === hivepress()->agl_gallery->get_photo_sidebar_position();

		$args = hp\merge_trees(
			[
				'blocks' => [
					'page_content' => [
						'_order' => $left ? 20 : 10,

						'blocks' => [
							'gallery_photo_view' => [
								'type'   => 'agl_gallery_photo_view',
								'_order' => 10,
							],
						],
					],

					'page_sidebar' => [
						'_order'     => $left ? 10 : 20,

						'attributes' => [
							'class'          => [ 'hp-agl-photo__sidebar' ],
							'data-component' => 'sticky',
						],

						'blocks'     => [
							'gallery_photo_manage' => [
								'type'   => 'agl_gallery_photo_manage',
								'_order' => 10,
							],

							'gallery_photo_vendor' => [
								'type'     => 'template',
								'template' => 'vendor_view_block',
								'_order'   => 20,
							],

							'page_sidebar_widgets' => [
								'type'   => 'widgets',
								'area'   => 'hp_agl_photo_sidebar',
								'_order' => 100,
							],
						],
					],
				],
			],
			$args
		);

		parent::__construct( $args );
	}
}
