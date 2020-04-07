<?php
/**
 * Service definition for Language Processing
 */

namespace Classifai\Services;

use Classifai\Taxonomy\ImageTagTaxonomy;

class ImageProcessing extends Service {

	/**
	 * ImageProcessing constructor.
	 */
	public function __construct() {
		parent::__construct(
			'Image Processing',
			'image_processing',
			[
				'Classifai\Providers\Azure\ComputerVision',
			]
		);
	}

	/**
	 * Register the Image Tags taxonomy along with
	 */
	public function init() {
		parent::init();
		$this->register_image_tags_taxonomy();
		add_filter( 'attachment_fields_to_edit', [ $this, 'custom_fields_edit' ] );
		add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );
		add_filter( 'attachment_fields_to_edit', [ $this, 'add_rescan_button_to_media_modal' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_media_scripts' ] );
	}

	/**
	 * Enqueue the script for the media modal.
	 */
	public function enqueue_media_scripts() {
		wp_enqueue_script( 'media-script', CLASSIFAI_PLUGIN_URL . '/dist/js/media.js', array( 'jquery', 'media-editor' ), CLASSIFAI_PLUGIN_VERSION, true );
	}

	/**
	 * Adds the rescan buttons to the media modal.
	 *
	 * @param array    $form_fields Array of fields
	 * @param \WP_post $post        Post object for the attachment being viewed.
	 */
	public function add_rescan_button_to_media_modal( $form_fields, $post ) {
		$screen = get_current_screen();
		// Screen returns null on the Media library page.
		if ( ! $screen ) {
			$alt_tags_text   = empty( get_post_meta( $post->ID, '_wp_attachment_image_alt', true ) ) ? __( 'Generate', 'classifai' ) : __( 'Rescan', 'classifai' );
			$image_tags_text = empty( wp_get_object_terms( $post->ID, 'classifai-image-tags' ) ) ? __( 'Generate', 'classifai' ) : __( 'Rescan', 'classifai' );
			$form_fields['rescan_alt_tags'] = [
				'label' => __( 'Classifai Alt Tags', 'classifai' ),
				'input' => 'html',
				'html'  => '<button class="button secondary" id="classifai-rescan-alt-tags" data-id="' . esc_attr( absint( $post->ID ) ) . '">' . esc_html( $alt_tags_text ) . '</button><span class="spinner" style="display:none;float:none;"></span>',
			];
			$form_fields['rescan_captions'] = [
				'label' => __( 'Classifai Image Tags', 'classifai' ),
				'input' => 'html',
				'html'  => '<button class="button secondary" id="classifai-rescan-image-tags" data-id="' . esc_attr( absint( $post->ID ) ) . '">' . esc_html( $image_tags_text ) . '</button><span class="spinner" style="display:none;float:none;"></span>',
			];
		}
		return $form_fields;
	}

	/**
	 * Create endpoints for services
	 */
	public function register_endpoints() {
		register_rest_route(
			'classifai/v1',
			'alt-tags/(?P<id>\d+)',
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'provider_endpoint_callback' ],
				'args'     => [ 'route' => 'alt-tags' ],
			]
		);
		register_rest_route(
			'classifai/v1',
			'image-tags/(?P<id>\d+)',
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'provider_endpoint_callback' ],
				'args'     => [ 'route' => 'image-tags' ],
			]
		);
	}

	/**
	 * Single call back to pass the route callback to the provider.
	 *
	 * @param \WP_REST_Request $request The full request object.
	 *
	 * @return mixed
	 */
	public function provider_endpoint_callback( $request ) {
		$response          = true;
		$attachment_id     = $request->get_param( 'id' );
		$custom_attributes = $request->get_attributes();
		$route_to_call     = isset( $custom_attributes['args'] ) && isset( $custom_attributes['args']['route'] ) ? $custom_attributes['args']['route'] : false;

		// Check to be sure the post both exists and is an attachment.
		if ( ! get_post( $attachment_id ) || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new \WP_Error( 'incorrect ID', "{$attachment_id} is not found or not an attachement", array( 'status' => 404 ) );
		}
		// If no args, we can't pass the call into the active provider.
		if ( false === $route_to_call ) {
			return new \WP_Error( 'no route', 'No route indicated for the provider class to use.', array( 'status' => 404 ) );
		}

		// Call the provider endpoint function
		if ( isset( $this->provider_classes[0] ) ) {
			$response = $this->provider_classes[0]->rest_endpoint_callback( $attachment_id, $route_to_call );
		}

		return $response;
	}


	/**s
	 * Register a common image tag taxonomy
	 */
	protected function register_image_tags_taxonomy() {
		$tax = new ImageTagTaxonomy();
		$tax->register();
		register_taxonomy_for_object_type( 'classifai-image-tags', 'attachment' );
	}

	/**
	 * Removes the UI on attachment modals for all taxonomies introduced by this plugin.
	 *
	 * @param array $form_fields The forms fields being rendered on the modal.
	 *
	 * @return mixed
	 */
	public function custom_fields_edit( $form_fields ) {
		unset( $form_fields['classifai-image-tags'] );
		unset( $form_fields['watson-category'] );
		unset( $form_fields['watson-keyword'] );
		unset( $form_fields['watson-concept'] );
		unset( $form_fields['watson-entity'] );
		return $form_fields;
	}
}
