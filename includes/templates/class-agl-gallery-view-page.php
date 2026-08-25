<?php
/**
 * Gallery view page template.
 *
 * @package AdditionalGalleryForHivePress\Templates
 */

namespace HivePress\Templates;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Public page displaying a vendor's gallery.
 *
 * Extends the left-sidebar page whether or not the site wants a sidebar, because a template's parent
 * is fixed when the class is declared and the setting is not known until a request. With the sidebar
 * switched off it is given no blocks at all and `optional` makes an empty container render nothing
 * (hivepress/includes/blocks/class-container.php:106), while the content column takes an extra class
 * that widens it back to the full row - the grid classes core puts there cannot be removed by
 * merging, only added to.
 */
class Agl_Gallery_View_Page extends Page_Sidebar_Left {

	/**
	 * Class constructor.
	 *
	 * @param array $args Template arguments.
	 */
	public function __construct( $args = [] ) {
		$position = hivepress()->agl_gallery->get_page_sidebar_position();

		$args = hp\merge_trees(
			[
				'blocks' => [

					/*
					 * The settings quick link, above the page title and aligned right, which is where
					 * a profile puts its own Edit link. It renders nothing for a visitor.
					 */
					'page_header'  => [
						'blocks' => [
							'gallery_settings_link' => [
								'type'   => 'agl_gallery_settings_link',
								'_label' => esc_html__( 'Gallery Settings Link', 'additional-gallery-for-hivepress' ),
								'_order' => 10,
							],
						],
					],

					'page_content' => [

						// Sidebar left means content second; sidebar right or none means content first.
						'_order'     => 'left' === $position ? 20 : 10,

						'attributes' => [
							'class' => 'none' === $position ? [ 'hp-agl-page--full' ] : [],
						],

						'blocks'     => [
							'gallery_view' => [
								'type'   => 'agl_gallery_view',
								'_order' => 10,
							],
						],
					],

					'page_sidebar' => [
						'optional'   => true,
						'_order'     => 'left' === $position ? 10 : 20,

						'attributes' => [
							'class'          => [ 'hp-agl-gallery__sidebar' ],
							'data-component' => 'sticky',
						],

						'blocks'     => 'none' === $position ? [] : [
							'gallery_vendor'       => [
								'type'     => 'template',
								'template' => 'vendor_view_block',
								'_order'   => 10,
							],

							'page_sidebar_widgets' => [
								'type'   => 'widgets',
								'area'   => 'hp_agl_gallery_sidebar',
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
