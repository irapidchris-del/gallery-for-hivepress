<?php
/**
 * Gallery comment like model.
 *
 * @package AdditionalGalleryForHivePress\Models
 */

namespace HivePress\Models;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * A like on a gallery photo comment.
 *
 * Stored as an `hp_agl_clike` comment whose `comment_parent` is the liked
 * comment and whose `comment_post_ID` is the photo, so one person can like
 * each comment once and a whole thread's likes load in one query.
 *
 * Short class name on purpose: the comment type is `hp_` + the class name and
 * WordPress' `comment_type` column holds only 20 characters.
 *
 * @method int|null get_user__id()
 * @method int|null get_parent__id()
 * @method int|null get_photo__id()
 */
class Agl_Clike extends Comment {

	/**
	 * Class constructor.
	 *
	 * @param array $args Model arguments.
	 */
	public function __construct( $args = [] ) {
		$args = hp\merge_arrays(
			[
				'fields' => [
					'created_date' => [
						'type'   => 'date',
						'format' => 'Y-m-d H:i:s',
						'_alias' => 'comment_date',
					],

					'approved'     => [
						'type'      => 'number',
						'min_value' => 0,
						'max_value' => 1,
						'_alias'    => 'comment_approved',
					],

					'user'         => [
						'type'      => 'number',
						'min_value' => 1,
						'required'  => true,
						'_alias'    => 'user_id',
						'_model'    => 'user',
					],

					'parent'       => [
						'type'      => 'number',
						'min_value' => 1,
						'required'  => true,
						'_alias'    => 'comment_parent',
						'_model'    => 'agl_comment',
					],

					'photo'        => [
						'type'      => 'number',
						'min_value' => 1,
						'required'  => true,
						'_alias'    => 'comment_post_ID',
						'_model'    => 'attachment',
					],
				],
			],
			$args
		);

		parent::__construct( $args );
	}
}
