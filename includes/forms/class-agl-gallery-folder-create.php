<?php
/**
 * Gallery folder create form.
 *
 * @package AdditionalGalleryForHivePress\Forms
 */

namespace HivePress\Forms;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Creates a gallery folder.
 *
 * Images are added on the folder edit page after creation, because the
 * HivePress upload field requires an existing parent object to attach to.
 */
class Agl_Gallery_Folder_Create extends Model_Form {

	/**
	 * Class initializer.
	 *
	 * @param array $meta Class meta values.
	 * @return void
	 */
	public static function init( $meta = [] ) {
		$meta = hp\merge_arrays(
			[
				'label' => esc_html__( 'Create Folder', 'additional-gallery-for-hivepress' ),
				'model' => 'gallery_folder',
			],
			$meta
		);

		parent::init( $meta );
	}

	/**
	 * Class constructor.
	 *
	 * @param array $args Form arguments.
	 */
	public function __construct( $args = [] ) {
		$args = hp\merge_arrays(
			[
				'action'   => hivepress()->router->get_url( 'gallery_folders_resource' ),
				'method'   => 'POST',
				'message'  => esc_html__( 'Folder created.', 'additional-gallery-for-hivepress' ),
				'redirect' => true,

				'fields'   => [
					'title'       => [
						'_order' => 10,
					],

					'description' => [
						'_order' => 20,
					],

					'visibility'  => [
						'default' => 'public',
						'_order'  => 30,
					],
				],

				'button'   => [
					'label' => esc_html__( 'Create Folder', 'additional-gallery-for-hivepress' ),
				],
			],
			$args
		);

		parent::__construct( $args );
	}
}
