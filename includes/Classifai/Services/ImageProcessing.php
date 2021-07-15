<?php
/**
 * Service definition for Language Processing
 */

namespace Classifai\Services;

use Classifai\Taxonomy\ImageTagTaxonomy;
use function Classifai\attachment_is_pdf;

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
		wp_enqueue_script( 'media-script', CLASSIFAI_PLUGIN_URL . '/dist/js/media.min.js', array( 'jquery', 'media-editor', 'lodash' ), CLASSIFAI_PLUGIN_VERSION, true );
	}

	/**
	 * Adds the rescan buttons to the media modal.
	 *
	 * @param array    $form_fields Array of fields
	 * @param \WP_post $post        Post object for the attachment being viewed.
	 */
	public function add_rescan_button_to_media_modal( $form_fields, $post ) {
		$settings = get_option( 'classifai_computer_vision' );

		if ( attachment_is_pdf( $post ) && $settings && isset( $settings['enable_read_pdf'] ) && '1' === $settings['enable_read_pdf'] ) {
			$read_text   = empty( get_the_content( null, false, $post ) ) ? __( 'Scan', 'classifai' ) : __( 'Rescan', 'classifai' );
			$status = get_post_meta( $post->ID, '_classifai_azure_read_status', true );
			if ( ! empty( $status['status'] ) && 'running' === $status['status'] ) {
				$html = '<button class="button secondary" disabled>' . esc_html__( 'In progress!', 'classifai' ) . '</button>';
			} else {
				$html = '<button class="button secondary" id="classifai-rescan-pdf" data-id="' . esc_attr( absint( $post->ID ) ) . '">' . esc_html( $read_text ) . '</button>';
			}

			$form_fields['rescan_pdf'] = [
				'label'        => __( 'Classifai Read PDF', 'classifai' ),
				'input'        => 'html',
				'html'         => $html,
				'show_in_edit' => false,
			];
		}

		if ( wp_attachment_is_image( $post ) ) {
			$alt_tags_text   = empty( get_post_meta( $post->ID, '_wp_attachment_image_alt', true ) ) ? __( 'Generate', 'classifai' ) : __( 'Rescan', 'classifai' );
			$image_tags_text = empty( wp_get_object_terms( $post->ID, 'classifai-image-tags' ) ) ? __( 'Generate', 'classifai' ) : __( 'Rescan', 'classifai' );
			$ocr_text        = empty( get_post_meta( $post->ID, 'classifai_computer_vision_ocr', true ) ) ? __( 'Generate', 'classifai' ) : __( 'Rescan', 'classifai' );
			$smart_crop_text = empty( get_transient( 'classifai_azure_computer_vision_smart_cropping_latest_response' ) ) ? __( 'Generate', 'classifai' ) : __( 'Regenerate', 'classifai' );

			$form_fields['rescan_alt_tags'] = [
				'label'        => __( 'Classifai Alt Tags', 'classifai' ),
				'input'        => 'html',
				'html'         => '<button class="button secondary" id="classifai-rescan-alt-tags" data-id="' . esc_attr( absint( $post->ID ) ) . '">' . esc_html( $alt_tags_text ) . '</button><span class="spinner" style="display:none;float:none;"></span><span class="error" style="display:none;color:#bc0b0b;padding:5px;"></span>',
				'show_in_edit' => false,
			];
			$form_fields['rescan_captions'] = [
				'label'        => __( 'Classifai Image Tags', 'classifai' ),
				'input'        => 'html',
				'html'         => '<button class="button secondary" id="classifai-rescan-image-tags" data-id="' . esc_attr( absint( $post->ID ) ) . '">' . esc_html( $image_tags_text ) . '</button><span class="spinner" style="display:none;float:none;"></span><span class="error" style="display:none;color:#bc0b0b;padding:5px;"></span>',
				'show_in_edit' => false,
			];

			if ( $settings && isset( $settings['enable_smart_cropping'] ) && '1' === $settings['enable_smart_cropping'] ) {
				$form_fields['rescan_smart_crop'] = [
					'label'        => __( 'Classifai Smart Crop', 'classifai' ),
					'input'        => 'html',
					'html'         => '<button class="button secondary" id="classifai-rescan-smart-crop" data-id="' . esc_attr( absint( $post->ID ) ) . '">' . esc_html( $smart_crop_text ) . '</button><span class="spinner" style="display:none;float:none;"></span><span class="error" style="display:none;color:#bc0b0b;padding:5px;"></span>',
					'show_in_edit' => false,
				];
			}

			$form_fields['rescan_ocr'] = [
				'label'        => __( 'Detect Text', 'classifai' ),
				'input'        => 'html',
				'html'         => '<button class="button secondary" id="classifai-rescan-ocr" data-id="' . esc_attr( absint( $post->ID ) ) . '">' . esc_html( $ocr_text ) . '</button><span class="spinner" style="display:none;float:none;"></span><span class="error" style="display:none;color:#bc0b0b;padding:5px;"></span>',
				'show_in_edit' => false,
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
				'methods'             => 'GET',
				'callback'            => [ $this, 'provider_endpoint_callback' ],
				'args'                => [ 'route' => [ 'alt-tags' ] ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'classifai/v1',
			'image-tags/(?P<id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'provider_endpoint_callback' ],
				'args'                => [ 'route' => [ 'image-tags' ] ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'classifai/v1',
			'ocr/(?P<id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'provider_endpoint_callback' ],
				'args'                => [ 'route' => [ 'ocr' ] ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'classifai/v1',
			'smart-crop/(?P<id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'provider_endpoint_callback' ],
				'args'                => [ 'route' => [ 'smart-crop' ] ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'classifai/v1',
			'read-pdf/(?P<id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'provider_endpoint_callback' ],
				'args'                => [ 'route' => 'read-pdf' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Single call back to pass the route callback to the provider.
	 *
	 * @param \WP_REST_Request $request The full request object.
	 *
	 * @return array|bool|string|\WP_Error
	 */
	public function provider_endpoint_callback( $request ) {
		$response          = true;
		$attachment_id     = $request->get_param( 'id' );
		$custom_attributes = $request->get_attributes();
		$route_to_call     = empty( $custom_attributes['args']['route'] ) ? false : $custom_attributes['args']['route'][0];

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

	/**
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
