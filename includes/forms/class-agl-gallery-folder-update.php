<?php
/**
 * Gallery folder update form.
 *
 * @package AdditionalGalleryForHivePress\Forms
 */

namespace HivePress\Forms;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Updates a gallery folder.
 *
 * The images field uploads via the core HivePress attachments endpoint and
 * supports drag-and-drop sorting. Field values are populated automatically
 * from the model by `Model_Form::boot()`.
 */
class Agl_Gallery_Folder_Update extends Model_Form {

	/**
	 * Class initializer.
	 *
	 * @param array $meta Class meta values.
	 * @return void
	 */
	public static function init( $meta = [] ) {
		$meta = hp\merge_arrays(
			[
				'label' => esc_html__( 'Edit Folder', 'additional-gallery-for-hivepress' ),
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
		/*
		 * The Vendor's per-folder display tick, offered only where the site lets Vendors choose.
		 * `_separate` keeps it out of the model: it is stored as a "hide" flag, so a folder nobody
		 * has saved since this arrived has no row and stays shown, as before.
		 */
		$gallery = function_exists( 'hivepress' ) ? hivepress()->agl_gallery : null;

		if ( $gallery && $gallery->get_owner_display_surfaces() ) {
			$folder = hp\get_array_value( $args, 'model' );

			$args = hp\merge_arrays(
				[
					'fields' => [
						'agl_show_on_pages' => [
							'label'       => esc_html__( 'Where It Appears', 'additional-gallery-for-hivepress' ),
							'caption'     => esc_html( $gallery->get_display_wording( 'folder' ) ),
							'description' => esc_html__( 'Unticked, the folder stays in your gallery but is left out of the gallery shown on your other pages. A private folder is never shown to visitors, and a members-only folder stays locked for them, whatever this says.', 'additional-gallery-for-hivepress' ),
							'type'        => 'checkbox',
							'default'     => $folder instanceof \HivePress\Models\Gallery_Folder && $folder->get_id() ? $gallery->folder_shows_on_pages( $folder ) : true,
							'_separate'   => true,
							'_order'      => 45,
						],
					],
				],
				$args
			);
		}

		$args = hp\merge_arrays(
			[
				'method'  => 'POST',
				'message' => esc_html__( 'Changes saved.', 'additional-gallery-for-hivepress' ),

				'fields'  => [
					'images'      => [
						'_order' => 10,
					],

					'title'       => [
						'_order' => 20,
					],

					'description' => [
						'_order' => 30,
					],

					'visibility'  => [
						'_order' => 40,
					],
				],

				'button'  => [
					'label' => esc_html__( 'Save Changes', 'additional-gallery-for-hivepress' ),
				],
			],
			$args
		);

		parent::__construct( $args );
	}

	/**
	 * Bootstraps form properties.
	 *
	 * @return void
	 */
	protected function boot() {

		/** @var \HivePress\Models\Gallery_Folder $model */
		$model = $this->model;

		// Set action.
		if ( $model->get_id() ) {
			$this->action = hivepress()->router->get_url(
				'gallery_folder_update_action',
				[
					'gallery_folder_id' => $model->get_id(),
				]
			);
		}

		parent::boot();
	}
}
