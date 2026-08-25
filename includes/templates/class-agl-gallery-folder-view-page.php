<?php
/**
 * Gallery folder view page template.
 *
 * @package AdditionalGalleryForHivePress\Templates
 */

namespace HivePress\Templates;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Public page displaying a single gallery folder.
 *
 * Built exactly like the gallery page above it, and for the same reason: see the note on
 * Agl_Gallery_View_Page for why the sidebar parent is unconditional and how the "no sidebar" state
 * gets its width back.
 */
class Agl_Gallery_Folder_View_Page extends Page_Sidebar_Left {

	/**
	 * Class constructor.
	 *
	 * @param array $args Template arguments.
	 */
	public function __construct( $args = [] ) {
		$position = hivepress()->agl_gallery->get_folder_sidebar_position();

		$args = hp\merge_trees(
			[
				'blocks' => [
					'page_header'  => [
						'blocks' => [
							'gallery_settings_link' => [
								'type'   => 'agl_gallery_settings_link',
								'_label' => esc_html__( 'Folder Settings Link', 'additional-gallery-for-hivepress' ),
								'_order' => 10,
							],
						],
					],

					'page_content' => [
						'_order'     => 'left' === $position ? 20 : 10,

						'attributes' => [
							'class' => 'none' === $position ? [ 'hp-agl-page--full' ] : [],
						],

						'blocks'     => [
							'gallery_folder_view' => [
								'type'   => 'agl_gallery_folder_view',
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
							'gallery_folder_vendor' => [
								'type'     => 'template',
								'template' => 'vendor_view_block',
								'_order'   => 10,
							],

							'page_sidebar_widgets'  => [
								'type'   => 'widgets',
								'area'   => 'hp_agl_folder_sidebar',
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
