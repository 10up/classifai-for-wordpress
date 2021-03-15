<?php
/**
 * IBM Watson NLU
 */

namespace Classifai\Providers\Watson;

use Classifai\Admin\SavePostHandler;
use Classifai\Providers\Provider;
use Classifai\Taxonomy\TaxonomyFactory;
use function Classifai\get_post_types_for_language_settings;
use function Classifai\allow_language_processing_for_published_content;

class NLU extends Provider {

	/**
	 * @var $taxonomy_factory TaxonomyFactory Watson taxonomy factory
	 */
	public $taxonomy_factory;

	/**
	 * @var $save_post_handler SavePostHandler Triggers a classification with Watson
	 */
	public $save_post_handler;

	/**
	 * Watson NLU constructor.
	 *
	 * @param string $service The service this class belongs to.
	 */
	public function __construct( $service ) {
		parent::__construct(
			'IBM Watson',
			'Natural Language Understanding',
			'watson_nlu',
			$service
		);

		$this->nlu_features = [
			'category' => [
				'feature'           => __( 'Category', 'classifai' ),
				'threshold'         => __( 'Category Threshold (%)', 'classifai' ),
				'taxonomy'          => __( 'Category Taxonomy', 'classifai' ),
				'threshold_default' => WATSON_CATEGORY_THRESHOLD,
				'taxonomy_default'  => WATSON_CATEGORY_TAXONOMY,
			],
			'keyword'  => [
				'feature'           => __( 'Keyword', 'classifai' ),
				'threshold'         => __( 'Keyword Threshold (%)', 'classifai' ),
				'taxonomy'          => __( 'Keyword Taxonomy', 'classifai' ),
				'threshold_default' => WATSON_KEYWORD_THRESHOLD,
				'taxonomy_default'  => WATSON_KEYWORD_TAXONOMY,
			],
			'entity'   => [
				'feature'           => __( 'Entity', 'classifai' ),
				'threshold'         => __( 'Entity Threshold (%)', 'classifai' ),
				'taxonomy'          => __( 'Entity Taxonomy', 'classifai' ),
				'threshold_default' => WATSON_ENTITY_THRESHOLD,
				'taxonomy_default'  => WATSON_ENTITY_TAXONOMY,
			],
			'concept'  => [
				'feature'           => __( 'Concept', 'classifai' ),
				'threshold'         => __( 'Concept Threshold (%)', 'classifai' ),
				'taxonomy'          => __( 'Concept Taxonomy', 'classifai' ),
				'threshold_default' => WATSON_CONCEPT_THRESHOLD,
				'taxonomy_default'  => WATSON_CONCEPT_TAXONOMY,
			],
		];
	}

	/**
	 * Resets the settings for the NLU provider.
	 */
	public function reset_settings() {
		$settings = [
			'post_types' => [
				'post',
				'page',
			],
			'features'   => [
				'category'           => true,
				'category_threshold' => WATSON_CATEGORY_THRESHOLD,
				'category_taxonomy'  => WATSON_CATEGORY_TAXONOMY,

				'keyword'            => true,
				'keyword_threshold'  => WATSON_KEYWORD_THRESHOLD,
				'keyword_taxonomy'   => WATSON_KEYWORD_TAXONOMY,

				'concept'            => false,
				'concept_threshold'  => WATSON_CONCEPT_THRESHOLD,
				'concept_taxonomy'   => WATSON_CONCEPT_TAXONOMY,

				'entity'             => false,
				'entity_threshold'   => WATSON_ENTITY_THRESHOLD,
				'entity_taxonomy'    => WATSON_ENTITY_TAXONOMY,
			],
		];

		update_option( $this->get_option_name(), $settings );
	}

	/**
	 * Can the functionality be initialized?
	 *
	 * @return bool
	 */
	public function can_register() {
		// TODO: Implement can_register() method.
		return true;
	}

	/**
	 * Register what we need for the plugin.
	 */
	public function register() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		$this->taxonomy_factory = new TaxonomyFactory();
		$this->taxonomy_factory->build_all();

		$this->save_post_handler = new SavePostHandler();

		if ( $this->save_post_handler->can_register() ) {
			$this->save_post_handler->register();
		}
	}

	/**
	 * Helper to get the settings and allow for settings default values.
	 *
	 * Overridden from parent to polyfill older settings storage schema.
	 *
	 * @param string|bool|mixed $index Optional. Name of the settings option index.
	 *
	 * @return array
	 */
	public function get_settings( $index = false ) {
		$defaults = [];
		$settings = get_option( $this->get_option_name(), [] );

		// If no settings have been saved, check for older storage to polyfill
		// These are pre-1.3 settings
		if ( empty( $settings ) ) {
			$old_settings = get_option( 'classifai_settings' );

			if ( isset( $old_settings['credentials'] ) ) {
				$defaults['credentials'] = $old_settings['credentials'];
			}

			if ( isset( $old_settings['post_types'] ) ) {
				$defaults['post_types'] = $old_settings['post_types'];
			}

			if ( isset( $old_settings['features'] ) ) {
				$defaults['features'] = $old_settings['features'];
			}
		}

		$settings = wp_parse_args( $settings, $defaults );

		if ( $index && isset( $settings[ $index ] ) ) {
			return $settings[ $index ];
		}

		return $settings;
	}

	/**
	 * Enqueue the editor scripts.
	 */
	public function enqueue_editor_assets() {
		global $post;

		wp_enqueue_script(
			'classifai-editor', // Handle.
			CLASSIFAI_PLUGIN_URL . 'dist/js/editor.min.js',
			array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor', 'wp-edit-post', 'wp-components', 'wp-plugins' ),
			CLASSIFAI_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'classifai-editor',
			'classifyObj',
			[
				'show_generate_button' => allow_language_processing_for_published_content( $post->ID ) ? 'true' : 'false',
			]
		);
	}

	/**
	 * Enqueue the admin scripts.
	 */
	public function enqueue_admin_assets() {
		wp_enqueue_script(
			'classifai-admin',
			CLASSIFAI_PLUGIN_URL . 'dist/js/admin.min.js',
			[],
			CLASSIFAI_PLUGIN_VERSION,
			true
		);
		wp_localize_script(
			'classifai-admin',
			'ClassifAI',
			[
				'api_password' => __( 'API Password', 'classifai' ),
				'api_key'      => __( 'API Key', 'classifai' ),
				'use_key'      => __( 'Use an API Key instead?', 'classifai' ),
				'use_password' => __( 'Use a username/password instead?', 'classifai' ),
			]
		);
	}

	/**
	 * Setup fields
	 */
	public function setup_fields_sections() {
		// Add the settings section.
		add_settings_section(
			$this->get_option_name(),
			$this->provider_service_name,
			function() {
				printf(
					wp_kses(
						__( 'Don\'t have an IBM Cloud account yet? <a title="Register for an IBM Cloud account" href="%1$s">Register for one</a> and set up a <a href="%2$s">Natural Language Understanding</a> Resource to get your API key.', 'classifai' ),
						[
							'a' => [
								'href'  => [],
								'title' => [],
							],
						]
					),
					esc_url( 'https://cloud.ibm.com/registration' ),
					esc_url( 'https://cloud.ibm.com/catalog/services/natural-language-understanding' )
				);
			},
			$this->get_option_name()
		);

		// Create the Credentials Section.
		$this->do_credentials_section();

		// Create content tagging section
		$this->do_nlu_features_sections();
	}

	/**
	 * Helper method to create the credentials section
	 */
	protected function do_credentials_section() {
		add_settings_field(
			'url',
			esc_html__( 'API URL', 'classifai' ),
			[ $this, 'render_input' ],
			$this->get_option_name(),
			$this->get_option_name(),
			[
				'label_for'    => 'watson_url',
				'option_index' => 'credentials',
				'input_type'   => 'text',
				'large'        => true,
			]
		);
		add_settings_field(
			'username',
			esc_html__( 'API Username', 'classifai' ),
			[ $this, 'render_input' ],
			$this->get_option_name(),
			$this->get_option_name(),
			[
				'label_for'     => 'watson_username',
				'option_index'  => 'credentials',
				'input_type'    => 'text',
				'default_value' => 'apikey',
				'large'         => true,
				'class'         => $this->use_username_password() ? 'hidden' : '',
			]
		);
		add_settings_field(
			'password',
			esc_html__( 'API Key', 'classifai' ),
			[ $this, 'render_input' ],
			$this->get_option_name(),
			$this->get_option_name(),
			[
				'label_for'    => 'watson_password',
				'option_index' => 'credentials',
				'input_type'   => 'password',
				'large'        => true,
			]
		);
		add_settings_field(
			'toggle',
			'',
			function() {
				printf(
					'<a id="classifai-waston-cred-toggle" href="#">%s</a>',
					$this->use_username_password()
						? esc_html__( 'Use a username/password instead?', 'classifai' )
						: esc_html__( 'Use an API Key instead?', 'classifai' )
				);
			},
			$this->get_option_name(),
			$this->get_option_name()
		);
	}

	/**
	 * Check if a username/password is used instead of API key.
	 *
	 * @return bool
	 */
	protected function use_username_password() {
		$settings = $this->get_settings( 'credentials' );

		if ( empty( $settings['watson_username'] ) ) {
			return false;
		}

		return 'apikey' === $settings['watson_username'];
	}

	/**
	 * Helper method to create the watson features section
	 */
	protected function do_nlu_features_sections() {
		add_settings_field(
			'post-types',
			esc_html__( 'Post Types to Classify', 'classifai' ),
			[ $this, 'render_post_types_checkboxes' ],
			$this->get_option_name(),
			$this->get_option_name(),
			[
				'option_index' => 'post_types',
			]
		);

		foreach ( $this->nlu_features as $feature => $labels ) {
			add_settings_field(
				$feature,
				esc_html( $labels['feature'] ),
				[ $this, 'render_nlu_feature_settings' ],
				$this->get_option_name(),
				$this->get_option_name(),
				[
					'option_index' => 'features',
					'feature'      => $feature,
					'labels'       => $labels,
				]
			);
		}
	}

	/**
	 * Generic text input field callback
	 *
	 * @param array $args The args passed to add_settings_field.
	 */
	public function render_input( $args ) {
		$setting_index = $this->get_settings( $args['option_index'] );
		$type          = $args['input_type'] ?? 'text';
		$value         = ( isset( $setting_index[ $args['label_for'] ] ) ) ? $setting_index[ $args['label_for'] ] : '';

		// Check for a default value
		$value = ( empty( $value ) && isset( $args['default_value'] ) ) ? $args['default_value'] : $value;
		$attrs = '';
		$class = '';

		switch ( $type ) {
			case 'text':
			case 'password':
				$attrs = ' value="' . esc_attr( $value ) . '"';
				$class = empty( $args['large'] ) ? 'regular-text' : 'large-text';
				break;
			case 'number':
				$attrs = ' value="' . esc_attr( $value ) . '"';
				$class = 'small-text';
				break;
			case 'checkbox':
				$attrs = ' value="1"' . checked( '1', $value, false );
				break;
		}
		?>
		<input
			type="<?php echo esc_attr( $type ); ?>"
			id="classifai-settings-<?php echo esc_attr( $args['label_for'] ); ?>"
			class="<?php echo esc_attr( $class ); ?>"
			name="classifai_<?php echo esc_attr( $this->option_name ); ?>[<?php echo esc_attr( $args['option_index'] ); ?>][<?php echo esc_attr( $args['label_for'] ); ?>]"
			<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
		<?php
		if ( ! empty( $args['description'] ) ) {
			echo '<br /><span class="description">' . wp_kses_post( $args['description'] ) . '</span>';
		}
	}

	/**
	 * Render the post types checkbox array.
	 *
	 * @param array $args Settings for the input
	 *
	 * @return void
	 */
	public function render_post_types_checkboxes( $args ) {
		echo '<ul>';
		$post_types = get_post_types_for_language_settings();
		foreach ( $post_types as $post_type ) {
			$args = [
				'label_for'    => $post_type->name,
				'option_index' => 'post_types',
				'input_type'   => 'checkbox',
			];

			echo '<li>';
			$this->render_input( $args );
			echo '<label for="classifai-settings-' . esc_attr( $post_type->name ) . '">' . esc_html( $post_type->label ) . '</label>';
			echo '</li>';
		}

		echo '</ul>';
	}

	/**
	 * Render the NLU features settings.
	 *
	 * @param array $args Settings for the inputs
	 *
	 * @return void
	 */
	public function render_nlu_feature_settings( $args ) {
		$feature = $args['feature'];
		$labels  = $args['labels'];

		$taxonomies = $this->get_supported_taxonomies();
		$features   = $this->get_settings( 'features' );
		$taxonomy   = isset( $features[ "{$feature}_taxonomy" ] ) ? $features[ "{$feature}_taxonomy" ] : $labels['taxonomy_default'];

		// Enable classification type
		$feature_args = [
			'label_for'    => $feature,
			'option_index' => 'features',
			'input_type'   => 'checkbox',
		];

		$threshold_args = [
			'label_for'     => "{$feature}_threshold",
			'option_index'  => 'features',
			'input_type'    => 'number',
			'default_value' => $labels['threshold_default'],
		];
		?>

		<fieldset>
		<legend class="screen-reader-text"><?php esc_html_e( 'Watson Category Settings', 'classifai' ); ?></legend>

		<p>
			<?php $this->render_input( $feature_args ); ?>
			<label
				for="classifai-settings-<?php echo esc_attr( $feature ); ?>"><?php esc_html_e( 'Enable', 'classifai' ); ?></label>
		</p>

		<p>
			<label
				for="classifai-settings-<?php echo esc_attr( "{$feature}_threshold" ); ?>"><?php echo esc_html( $labels['threshold'] ); ?></label><br/>
			<?php $this->render_input( $threshold_args ); ?>
		</p>

		<p>
			<label
				for="classifai-settings-<?php echo esc_attr( "{$feature}_taxonomy" ); ?>"><?php echo esc_html( $labels['taxonomy'] ); ?></label><br/>
			<select id="classifai-settings-<?php echo esc_attr( "{$feature}_taxonomy" ); ?>"
				name="classifai_<?php echo esc_attr( $this->option_name ); ?>[features][<?php echo esc_attr( "{$feature}_taxonomy" ); ?>]">
				<?php foreach ( $taxonomies as $name => $singular_name ) : ?>
					<option
						value="<?php echo esc_attr( $name ); ?>" <?php selected( $taxonomy, esc_attr( $name ) ); ?> ><?php echo esc_html( $singular_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Return the list of supported taxonomies
	 *
	 * @return array
	 */
	public function get_supported_taxonomies() {
		$taxonomies = \get_taxonomies( [], 'objects' );
		$supported  = [];

		foreach ( $taxonomies as $taxonomy ) {
			$supported[ $taxonomy->name ] = $taxonomy->labels->singular_name;
		}

		return $supported;
	}


	/**
	 * Helper to ensure the authentication works.
	 *
	 * @param array $settings The list of settings to be saved
	 *
	 * @return bool
	 */
	protected function nlu_authentication_check_failed( $settings ) {

		// Check that we have credentials before hitting the API.
		if ( ! isset( $settings['credentials'] )
			|| empty( $settings['credentials']['watson_username'] )
			|| empty( $settings['credentials']['watson_password'] )
			|| empty( $settings['credentials']['watson_url'] )
		) {
			return true;
		}

		$request           = new \Classifai\Watson\APIRequest();
		$request->username = $settings['credentials']['watson_username'];
		$request->password = $settings['credentials']['watson_password'];
		$base_url          = trailingslashit( $settings['credentials']['watson_url'] ) . 'v1/analyze';
		$url               = esc_url( add_query_arg( [ 'version' => WATSON_NLU_VERSION ], $base_url ) );
		$options           = [
			'body' => wp_json_encode(
				[
					'text'     => 'Lorem ipsum dolor sit amet.',
					'language' => 'en',
					'features' => [
						'keywords' => [
							'emotion' => false,
							'limit'   => 1,
						],
					],
				]
			),
		];
		$response          = $request->post( $url, $options );

		$is_error = is_wp_error( $response );
		if ( ! $is_error ) {
			update_option( 'classifai_configured', true );
		} else {
			delete_option( 'classifai_configured' );
		}

		return $is_error;

	}


	/**
	 * Sanitization for the options being saved.
	 *
	 * @param array $settings Array of settings about to be saved.
	 *
	 * @return array The sanitized settings to be saved.
	 */
	public function sanitize_settings( $settings ) {
		$new_settings = $this->get_settings();
		if ( $this->nlu_authentication_check_failed( $settings ) ) {
			add_settings_error(
				'credentials',
				'classifai-auth',
				esc_html__( 'IBM Watson NLU Authentication Failed. Please check credentials.', 'classifai' ),
				'error'
			);
		}

		if ( isset( $settings['credentials']['watson_url'] ) ) {
			$new_settings['credentials']['watson_url'] = esc_url_raw( $settings['credentials']['watson_url'] );
		}

		if ( isset( $settings['credentials']['watson_username'] ) ) {
			$new_settings['credentials']['watson_username'] = sanitize_text_field( $settings['credentials']['watson_username'] );
		}

		if ( isset( $settings['credentials']['watson_password'] ) ) {
			$new_settings['credentials']['watson_password'] = sanitize_text_field( $settings['credentials']['watson_password'] );
		}

		// Sanitize the post type checkboxes
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		foreach ( $post_types as $post_type ) {
			if ( isset( $settings['post_types'][ $post_type->name ] ) ) {
				$new_settings['post_types'][ $post_type->name ] = absint( $settings['post_types'][ $post_type->name ] );
			} else {
				$new_settings['post_types'][ $post_type->name ] = null;
			}
		}

		foreach ( $this->nlu_features as $feature => $labels ) {
			// Set the enabled flag.
			if ( isset( $settings['features'][ $feature ] ) ) {
				$new_settings['features'][ $feature ] = absint( $settings['features'][ $feature ] );
			} else {
				$new_settings['features'][ $feature ] = null;
			}

			// Set the threshold
			if ( isset( $settings['features'][ "{$feature}_threshold" ] ) ) {
				$new_settings['features'][ "{$feature}_threshold" ] = min( absint( $settings['features'][ "{$feature}_threshold" ] ), 100 );
			}

			if ( isset( $settings['features'][ "{$feature}_taxonomy" ] ) ) {
				$new_settings['features'][ "{$feature}_taxonomy" ] = sanitize_text_field( $settings['features'][ "{$feature}_taxonomy" ] );
			}
		}

		return $new_settings;
	}

	/**
	 * Hit license API to see if key/email is valid
	 *
	 * @param string $email Email address.
	 * @param string $license_key License key.
	 *
	 * @return bool
	 * @since  1.2
	 *
	 * @todo Is this function supposed to be here?
	 */
	public function check_license_key( $email, $license_key ) {

		$request = wp_remote_post(
			'https://classifaiplugin.com/wp-json/classifai-theme/v1/validate-license',
			[
				'timeout' => 10,
				'body'    => [
					'license_key' => $license_key,
					'email'       => $email,
				],
			]
		);

		if ( is_wp_error( $request ) ) {
			return false;
		}

		if ( 200 === wp_remote_retrieve_response_code( $request ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Provides debug information related to the provider.
	 *
	 * @param array|null $settings Settings array. If empty, settings will be retrieved.
	 * @param boolean    $configured Whether the provider is correctly configured. If null, the option will be retrieved.
	 * @return string|array
	 * @since 1.4.0
	 */
	public function get_provider_debug_information( $settings = null, $configured = null ) {
		if ( is_null( $settings ) ) {
			$settings = $this->sanitize_settings( $this->get_settings() );
		}

		if ( is_null( $configured ) ) {
			$configured = get_option( 'classifai_configured' );
		}

		$settings_post_types = $settings['post_types'] ?? [];
		$post_types          = array_filter(
			array_keys( $settings_post_types ),
			function( $post_type ) use ( $settings_post_types ) {
				return 1 === intval( $settings_post_types[ $post_type ] );
			}
		);

		$credentials = $settings['credentials'] ?? [];

		return [
			__( 'Configured', 'classifai' )      => $configured ? __( 'yes', 'classifai' ) : __( 'no', 'classifai' ),
			__( 'API URL', 'classifai' )         => $credentials['watson_url'] ?? '',
			__( 'API username', 'classifai' )    => $credentials['watson_username'] ?? '',
			__( 'Post types', 'classifai' )      => implode( ', ', $post_types ),
			__( 'Features', 'classifai' )        => preg_replace( '/,"/', ', "', wp_json_encode( $settings['features'] ?? '' ) ),
			__( 'Latest response', 'classifai' ) => $this->get_formatted_latest_response(),
		];
	}

	/**
	 * Format the result of most recent request.
	 *
	 * @return string
	 */
	private function get_formatted_latest_response() {
		$data = get_transient( 'classifai_watson_nlu_latest_response' );

		if ( ! $data ) {
			return __( 'N/A', 'classifai' );
		}

		if ( is_wp_error( $data ) ) {
			return $data->get_error_message();
		}

		$formatted_data = array_intersect_key(
			$data,
			[
				'usage'    => 1,
				'language' => 1,
			]
		);

		foreach ( array_diff_key( $data, $formatted_data ) as $key => $value ) {
			$formatted_data[ $key ] = count( $value );
		}

		return preg_replace( '/,"/', ', "', wp_json_encode( $formatted_data ) );
	}
}
