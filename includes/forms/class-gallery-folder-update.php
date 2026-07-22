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
class Gallery_Folder_Update extends Model_Form {

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
