<?php
/**
 * Gallery folder delete form.
 *
 * @package AdditionalGalleryForHivePress\Forms
 */

namespace HivePress\Forms;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Deletes a gallery folder.
 */
class Agl_Gallery_Folder_Delete extends Model_Form {

	/**
	 * Class initializer.
	 *
	 * @param array $meta Class meta values.
	 * @return void
	 */
	public static function init( $meta = [] ) {
		$meta = hp\merge_arrays(
			[
				'label' => esc_html__( 'Delete Folder', 'additional-gallery-for-hivepress' ),
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
				'description' => esc_html__( 'Deleting a folder permanently removes all of its images.', 'additional-gallery-for-hivepress' ),
				'method'      => 'DELETE',

				'button'      => [
					'label' => esc_html__( 'Delete Folder', 'additional-gallery-for-hivepress' ),
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

		// Set action and redirect.
		if ( $model->get_id() ) {
			$this->action = hivepress()->router->get_url(
				'gallery_folder_delete_action',
				[
					'gallery_folder_id' => $model->get_id(),
				]
			);

			$this->redirect = hivepress()->router->get_url( 'gallery_edit_page' );
		}

		parent::boot();
	}
}
