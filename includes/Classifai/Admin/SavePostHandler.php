<?php

namespace Classifai\Admin;

use function Classifai\allow_language_processing_for_published_content;

/**
 * Classifies Posts based on the current ClassifAI configuration.
 */
class SavePostHandler {

	/**
	 * @var $classifier \Classifai\PostClassifier Lazy loaded classifier object
	 */
	public $classifier;

	/**
	 * Enables the classification on save post behaviour.
	 */
	public function register() {
		add_action( 'save_post', [ $this, 'did_save_post' ] );
		add_action( 'admin_notices', [ $this, 'show_error_if' ] );
		add_action( 'post_submitbox_start', [ $this, 'add_generate_tags_button' ] );
		add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );
	}

	/**
	 * Save Post handler only runs on admin or REST requests
	 */
	public function can_register() {
		if ( ! get_option( 'classifai_configured', false ) ) {
			return false;
		} elseif ( empty( get_option( 'classifai_watson_nlu' ) ) ) {
			return false;
		} elseif ( empty( get_option( 'classifai_watson_nlu' )['credentials']['watson_url'] ) ) {
			return false;
		} elseif ( is_admin() ) {
			return true;
		} elseif ( $this->is_rest_route() ) {
			return true;
		} elseif ( defined( 'PHPUNIT_RUNNER' ) && PHPUNIT_RUNNER ) {
			return false;
		} elseif ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		} else {
			return false;
		}
	}

	/**
	 * If current post type support is enabled in ClassifAI settings, it
	 * is tagged using the IBM Watson classification result.
	 *
	 * Skips classification if running under the Gutenberg Metabox
	 * compatibility request. The classification is performed during the REST
	 * lifecyle when using Gutenberg.
	 *
	 * @param int $post_id The post that was saved
	 */
	public function did_save_post( $post_id ) {
		if ( ! empty( $_GET['classic-editor'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$supported   = \Classifai\get_supported_post_types();
		$post_type   = get_post_type( $post_id );
		$post_status = get_post_status( $post_id );

		// Only process published, supported items and only if features are enabled
		if ( 'publish' === $post_status && in_array( $post_type, $supported, true ) && \Classifai\language_processing_features_enabled() ) {
			$this->classify( $post_id );
		}
	}

	/**
	 * Classifies the post specified with the PostClassifier object.
	 * Existing terms relationships are removed before classification.
	 *
	 * @param int $post_id the post to classify & link
	 *
	 * @return array
	 */
	public function classify( $post_id ) {
		/**
		 * Filter whether ClassifAI should classify a post.
		 *
		 * Default is true, return false to skip classifying a post.
		 *
		 * @since 1.2.0
		 * @hook classifai_should_classify_post
		 *
		 * @param {bool} $should_classify Whether the post should be classified. Default `true`, return `false` to skip
		 *                              classification for this post.
		 * @param {int}  $post_id         The ID of the post to be considered for classification.
		 *
		 * @return {bool} Whether the post should be classified.
		 */
		$classifai_should_classify_post = apply_filters( 'classifai_should_classify_post', true, $post_id );
		if ( ! $classifai_should_classify_post ) {
			return false;
		}

		$classifier = $this->get_classifier();

		if ( \Classifai\get_feature_enabled( 'category' ) ) {
			wp_delete_object_term_relationships( $post_id, \Classifai\get_feature_taxonomy( 'category' ) );
		}

		if ( \Classifai\get_feature_enabled( 'keyword' ) ) {
			wp_delete_object_term_relationships( $post_id, \Classifai\get_feature_taxonomy( 'keyword' ) );
		}

		if ( \Classifai\get_feature_enabled( 'concept' ) ) {
			wp_delete_object_term_relationships( $post_id, \Classifai\get_feature_taxonomy( 'concept' ) );
		}

		if ( \Classifai\get_feature_enabled( 'entity' ) ) {
			wp_delete_object_term_relationships( $post_id, \Classifai\get_feature_taxonomy( 'entity' ) );
		}

		$output = $classifier->classify_and_link( $post_id );

		if ( is_wp_error( $output ) ) {
			update_post_meta(
				$post_id,
				'_classifai_error',
				wp_json_encode(
					[
						'code'    => $output->get_error_code(),
						'message' => $output->get_error_message(),
					]
				)
			);
		} else {
			// If there is no error, clear any existing error states.
			delete_post_meta( $post_id, '_classifai_error' );
		}

		return $output;
	}

	/**
	 * Lazy initializes the Post Classifier object
	 */
	public function get_classifier() {
		if ( is_null( $this->classifier ) ) {
			$this->classifier = new \Classifai\PostClassifier();
		}

		return $this->classifier;
	}

	/**
	 * Outputs an Admin Notice with the error message if NLU
	 * classification had failed earlier.
	 */
	public function show_error_if() {
		global $post;

		if ( empty( $post ) ) {
			return;
		}

		$post_id = $post->ID;

		if ( empty( $post_id ) ) {
			return;
		}

		$error = get_post_meta( $post_id, '_classifai_error', true );

		if ( ! empty( $error ) ) {
			delete_post_meta( $post_id, '_classifai_error' );
			$error   = (array) json_decode( $error );
			$code    = ! empty( $error['code'] ) ? $error['code'] : 500;
			$message = ! empty( $error['message'] ) ? $error['message'] : 'Unknown NLU API error';

			?>
			<div class="notice notice-error is-dismissible">
				<p>
					Error: Failed to classify content with the IBM Watson NLU API.
				</p>
				<p>
					<?php echo esc_html( $code ); ?>
					-
					<?php echo esc_html( $message ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * We need to determine if we're doing a REST call.
	 *
	 * @return bool
	 */
	public function is_rest_route() {

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		/**
		 * Filter the REST bases. Supports custom post types with a custom REST base.
		 *
		 * @since 1.5.0
		 * @hook classifai_rest_bases
		 *
		 * @param {array} rest_bases Array of REST bases.
		 *
		 * @return {array} The filtered array of REST bases.
		 */
		$rest_bases = apply_filters( 'classifai_rest_bases', array( 'posts', 'pages' ) );

		foreach ( $rest_bases as $rest_base ) {
			if ( false !== strpos( $_SERVER['REQUEST_URI'], 'wp-json/wp/v2/' . $rest_base ) || false !== strpos( $_SERVER['REQUEST_URI'], 'wp-json/classifai/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Callback for adding Generate Tags button.
	 *
	 * @param \WP_Post $post The post being classified.
	 *
	 * @return void
	 */
	public function add_generate_tags_button( $post ) {
		// Only show generate tag button for published, supported items and if features are enabled.
		if ( allow_language_processing_for_published_content( $post->ID ) ) {
			?>
			<div class="misc-pub-classifai-actions">
				<button id="classifai-generate-tags" class="button" data-id="<?php echo esc_attr( $post->ID ); ?>"
						style="margin-bottom: 15px;">
					<?php esc_html_e( 'Generate Tags', 'classifai' ); ?>
				</button>
				<span class="spinner" style="display:none;float:none;"></span>
				<span class="error" style="display:none;color:#bc0b0b;padding:5px;"></span>
			</div>
			<?php
		}
	}

	/**
	 * Create endpoints for Language Processing.
	 */
	public function register_endpoints() {
		register_rest_route(
			'classifai/v1',
			'generate-tags/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'provider_endpoint_callback' ],
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'description'       => __( 'Post ID to generate tags.', 'classifai' ),
					),
				),
				'permission_callback' => [ $this, 'can_edit_posts' ],
			]
		);
	}

	/**
	 * Check if user can edit posts to handle permission for generating tags.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return array Did the classification run?
	 */
	public function provider_endpoint_callback( $request ) {
		$current_post_id = $request->get_param( 'id' );
		$request_failed  = [
			'success' => false,
		];

		// Only process content for published, supported items and only if features are enabled.
		if ( ! allow_language_processing_for_published_content( $current_post_id ) ) {
			return $request_failed;
		}

		$result = $this->classify( $current_post_id );

		if ( is_wp_error( $result ) ) {
			return $request_failed;
		}

		// Setup result for successful processing.
		$result = [
			'success' => true,
		];

		$categories = [];
		$keywords   = [];
		$concepts   = [];
		$entities   = [];

		if ( \Classifai\get_feature_enabled( 'category' ) ) {
			$category_taxonomy = \Classifai\get_feature_taxonomy( 'category' );
			$categories_objs   = wp_get_object_terms( $current_post_id, $category_taxonomy );
			$categories_objs   = is_wp_error( $categories_objs ) ? [] : $categories_objs;
			foreach ( $categories_objs as $categories_obj ) {
				$categories[ $categories_obj->term_id ] = $categories_obj->name;
			}

			$result[ $category_taxonomy ] = $categories;
		}

		if ( \Classifai\get_feature_enabled( 'keyword' ) ) {
			$keyword_taxonomy = \Classifai\get_feature_taxonomy( 'keyword' );
			$keywords_objs    = wp_get_object_terms( $current_post_id, $keyword_taxonomy );
			$keywords_objs    = is_wp_error( $keywords_objs ) ? [] : $keywords_objs;
			foreach ( $keywords_objs as $keywords_obj ) {
				$keywords[ $keywords_obj->term_id ] = $keywords_obj->name;
			}
			$result[ $keyword_taxonomy ] = $keywords;
		}

		if ( \Classifai\get_feature_enabled( 'concept' ) ) {
			$concept_taxonomy = \Classifai\get_feature_taxonomy( 'concept' );
			$concepts_objs    = wp_get_object_terms( $current_post_id, $concept_taxonomy );
			$concepts_objs    = is_wp_error( $concepts_objs ) ? [] : $concepts_objs;
			foreach ( $concepts_objs as $concepts_obj ) {
				$concepts[ $concepts_obj->term_id ] = $concepts_obj->name;
			}
			$result[ $concept_taxonomy ] = $concepts;
		}

		if ( \Classifai\get_feature_enabled( 'entity' ) ) {
			$entity_taxonomy = \Classifai\get_feature_taxonomy( 'entity' );
			$entities_objs   = wp_get_object_terms( $current_post_id, $entity_taxonomy );
			$entities_objs   = is_wp_error( $entities_objs ) ? [] : $entities_objs;
			foreach ( $entities_objs as $entities_obj ) {
				$entities[ $entities_obj->term_id ] = $entities_obj->name;
			}
			$result[ $entity_taxonomy ] = $entities;
		}

		return $result;
	}

	/**
	 * Check if user can edit posts to handle permission for generating tags.
	 *
	 * @return bool
	 */
	public function can_edit_posts() {
		return current_user_can( 'edit_posts' );
	}
}
