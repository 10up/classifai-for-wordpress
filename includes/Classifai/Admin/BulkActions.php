<?php
namespace Classifai\Admin;

use function Classifai\get_supported_post_types;

/**
 * Handle bulk actions.
 */
class BulkActions {
	/**
	 * Check to see if we can register this class.
	 *
	 * @return bool
	 */
	public function can_register() {
		return is_admin();
	}

	/**
	 * @var $save_post_handler SavePostHandler Triggers a classification with Watson
	 */
	private $save_post_handler;

	/**
	 * Register the actions needed.
	 */
	public function register() {
		$post_types = get_supported_post_types();

		if ( empty( $post_types ) ) {
			return;
		}

		$this->save_post_handler = new SavePostHandler();

		foreach ( $post_types as $post_type ) {
			if ( 'attachment' === $post_type ) {
				add_filter( 'bulk_actions-upload', [ $this, 'register_media_bulk_actions' ] );
				add_filter( 'handle_bulk_actions-upload', [ $this, 'media_bulk_action_handler' ], 10, 3 );
			} else {
				add_filter( "bulk_actions-edit-$post_type", [ $this, 'register_bulk_actions' ] );
				add_filter( "handle_bulk_actions-edit-$post_type", [ $this, 'bulk_action_handler' ], 10, 3 );
			}

			if ( is_post_type_hierarchical( $post_type ) ) {
				add_action( 'page_row_actions', [ $this, 'register_row_action' ], 10, 2 );
			} else {
				add_action( 'post_row_actions', [ $this, 'register_row_action' ], 10, 2 );
			}
		}

		add_action( 'admin_notices', [ $this, 'bulk_action_admin_notice' ] );
	}

	/**
	 * Register Classifai bulk action.
	 *
	 * @param array $bulk_actions Current bulk actions.
	 *
	 * @return array
	 */
	public function register_bulk_actions( $bulk_actions ) {
		$bulk_actions['classify'] = __( 'Classify', 'classifai' );
		return $bulk_actions;
	}

	/**
	 * Register Classifai media bulk actions.
	 *
	 * @param array $bulk_actions Current bulk actions.
	 *
	 * @return array
	 */
	public function register_media_bulk_actions( $bulk_actions ) {
		$bulk_actions['alt_tags']   = __( 'Add Alt Text', 'classifai' );
		$bulk_actions['image_tags'] = __( 'Add Image Tags', 'classifai' );
		$bulk_actions['smart_crop'] = __( 'Smart Crop', 'classifai' );
		return $bulk_actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param string $redirect_to Redirect URL after bulk actions.
	 * @param string $doaction    Action ID.
	 * @param array  $post_ids    Post ids to apply bulk actions to.
	 *
	 * @return string
	 */
	public function bulk_action_handler( $redirect_to, $doaction, $post_ids ) {
		if ( 'classify' !== $doaction ) {
			return $redirect_to;
		}
		foreach ( $post_ids as $post_id ) {
			$this->save_post_handler->classify( $post_id );
		}
		$redirect_to = add_query_arg( 'bulk_classified', count( $post_ids ), $redirect_to );
		return $redirect_to;
	}

	/**
	 * Handle media bulk actions.
	 *
	 * @param string $redirect_to       Redirect URL after bulk actions.
	 * @param string $doaction          Action ID.
	 * @param array  $attachment_ids    Attachment ids to apply bulk actions to.
	 *
	 * @return string
	 */
	public function media_bulk_action_handler( $redirect_to, $doaction, $attachment_ids ) {
		if ( empty( $attachment_ids ) ) {
			return $redirect_to;
		}
		switch ( $doaction ) {
			case 'alt_tags':
				$action = 'alt_tagged';
				break;
			case 'image_tags':
				$action = 'image_tagged';
				break;
			case 'smart_crop':
				$action = 'smart_cropped';
				break;
			default:
				return $redirect_to;
		}
		foreach ( $attachment_ids as $attachment_id ) {
			$this->save_post_handler->classify( $attachment_id );
		}
		$redirect_to = add_query_arg( "bulk_{$action}", count( $attachment_ids ), $redirect_to );
		return $redirect_to;
	}

	/**
	 * Display an admin notice after bulk updates.
	 */
	public function bulk_action_admin_notice() {
		if ( empty( $_REQUEST['bulk_classified'] ) && empty( $_REQUEST['bulk_alt_tagged'] ) && empty( $_REQUEST['bulk_image_tagged'] ) && empty( $_REQUEST['bulk_smart_cropped'] ) ) {
			return;
		}

		if ( ! empty( $_REQUEST['bulk_classified'] ) ) {
			$classified_posts_count = intval( $_REQUEST['bulk_classified'] );
			$post_type              = 'post';
			$action                 = 'Classified';
		} elseif ( ! empty( $_REQUEST['bulk_alt_tagged'] ) ) {
			$classified_posts_count = intval( $_REQUEST['bulk_alt_tagged'] );
			$post_type              = 'image';
			$action                 = 'Alt tags generated for';
		} elseif ( ! empty( $_REQUEST['bulk_image_tagged'] ) ) {
			$classified_posts_count = intval( $_REQUEST['bulk_image_tagged'] );
			$post_type              = 'image';
			$action                 = 'Image tags generated for';
		} elseif ( ! empty( $_REQUEST['bulk_smart_cropped'] ) ) {
			$classified_posts_count = intval( $_REQUEST['bulk_smart_cropped'] );
			$post_type              = 'image';
			$action                 = 'Smart cropped';
		}

		$output  = '<div id="message" class="notice notice-success is-dismissible fade"><p>';
		$output .= sprintf(
			_n(
				'%1$s %2$s %3$s.',
				'%1$s %2$s %3$ss.',
				$classified_posts_count,
				'classifai'
			),
			$action,
			$classified_posts_count,
			$post_type
		);
		$output .= '</p></div>';
		echo wp_kses(
			$output,
			[
				'div' => [
					'class' => [],
					'id'    => [],
				],
				'p'   => [],
			]
		);
	}

	/**
	 * Register Classifai row action.
	 *
	 * @param array    $actions Current row actions.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return array
	 */
	public function register_row_action( $actions, $post ) {
		$actions['classify'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( wp_nonce_url( admin_url( 'edit.php?action=classify&ids=' . $post->ID ), 'bulk-posts' ) ),
			esc_html__( 'Classify', 'classifai' )
		);

		return $actions;
	}
}
