<?php

namespace Classifai;

use Classifai\Providers\Provider;
use Classifai\Services\Service;
use Classifai\Services\ServicesManager;

/**
 * Miscellaneous Helper functions to access different parts of the
 * ClassifAI plugin.
 */

/**
 * Returns the ClassifAI plugin's singleton instance
 *
 * @return Plugin
 */
function get_plugin() {
	return Plugin::get_instance();
}

/**
 * Returns the ClassifAI plugin's stored settings in the WP options.
 *
 * @param string $service The service to get settings from, defaults to the ServiceManager class.
 *
 * @return array The array of ClassifAi settings.
 */
function get_plugin_settings( $service = '' ) {
	$services = Plugin::$instance->services;
	if ( empty( $services ) || empty( $services['service_manager'] ) || ! $services['service_manager'] instanceof ServicesManager ) {
		return [];
	}

	/** @var ServicesManager $service_manager Instance of the services manager class. */
	$service_manager = $services['service_manager'];
	if ( empty( $service ) ) {
		return $service_manager->get_settings();
	}

	if ( ! isset( $service_manager->service_classes[ $service ] ) || ! $service_manager->service_classes[ $service ] instanceof Service ) {
		return [];
	}

	/** @var Provider $provider An instance or extension of the provider abstract class. */
	$provider = $service_manager->service_classes[ $service ]->provider_classes[0];
	return $provider->get_settings();
}

/**
 * Overwrites the ClassifAI plugin's stored settings. Expected format is,
 *
 * [
 *     'post_types' => [ <list of post type names> ]
 *     'features' => [
 *         <feature_name> => <bool>
 *         <feature_threshold> => <int>
 *     ],
 *     'credentials' => [
 *         'watson_username' => <string>
 *         'watson_password' => <string>
 *     ]
 * ]
 *
 * @param array $settings The settings we're saving.
 */
function set_plugin_settings( $settings ) {
	update_option( 'classifai_settings', $settings );
}

/**
 * Resets the plugin to factory defaults, keeping licensing information only.
 */
function reset_plugin_settings() {

	$options = get_option( 'classifai_settings' );
	if ( $options && isset( $options['registration'] ) ) {
		// This is a legacy option set, so let's update it to the new format.
		$new_settings = [
			'valid_license' => $options['valid_license'],
			'email'         => isset( $options['registration']['email'] ) ? $options['registration']['email'] : '',
			'license_key'   => isset( $options['registration']['license_key'] ) ? $options['registration']['license_key'] : '',
		];
		update_option( 'classifai_settings', $new_settings );
	}

	$services = get_plugin()->services;
	if ( ! isset( $services['service_manager'] ) || ! $services['service_manager']->service_classes ) {
		return;
	}

	$service_classes = $services['service_manager']->service_classes;
	foreach ( $service_classes as $service_class ) {
		if ( ! $service_class instanceof Service || empty( $service_class->provider_classes ) ) {
			continue;
		}

		foreach ( $service_class->provider_classes as $provider_class ) {
			if ( ! $provider_class instanceof Provider || ! method_exists( $provider_class, 'reset_settings' ) ) {
				continue;
			}

			$provider_class->reset_settings();
		}
	}
}


/**
 * Returns the currently configured Watson API URL. Lookup order is,
 *
 * - Options
 * - Constant
 *
 * @return string
 */
function get_watson_api_url() {
	$settings = get_plugin_settings( 'language_processing' );
	$creds    = ! empty( $settings['credentials'] ) ? $settings['credentials'] : [];

	if ( ! empty( $creds['watson_url'] ) ) {
		return $creds['watson_url'];
	} elseif ( defined( 'WATSON_URL' ) ) {
		return WATSON_URL;
	} else {
		return '';
	}
}


/**
 * Returns the currently configured Watson username. Lookup order is,
 *
 * - Options
 * - Constant
 *
 * @return string
 */
function get_watson_username() {
	$settings = get_plugin_settings( 'language_processing' );
	$creds    = ! empty( $settings['credentials'] ) ? $settings['credentials'] : [];

	if ( ! empty( $creds['watson_username'] ) ) {
		return $creds['watson_username'];
	} elseif ( defined( 'WATSON_USERNAME' ) ) {
		return WATSON_USERNAME;
	} else {
		return '';
	}
}

/**
 * Returns the currently configured Watson username. Lookup order is,
 *
 * - Options
 * - Constant
 *
 * @return string
 */
function get_watson_password() {
	$settings = get_plugin_settings( 'language_processing' );
	$creds    = ! empty( $settings['credentials'] ) ? $settings['credentials'] : [];

	if ( ! empty( $creds['watson_password'] ) ) {
		return $creds['watson_password'];
	} elseif ( defined( 'WATSON_PASSWORD' ) ) {
		return WATSON_PASSWORD;
	} else {
		return '';
	}
}

/**
 * Get post types we want to show in the language processing settings
 *
 * @since 1.6.0
 *
 * @return array
 */
function get_post_types_for_language_settings() {
	$post_types = get_post_types( [ 'public' => true ], 'objects' );

	// Remove the attachment post type
	unset( $post_types['attachment'] );

	/**
	 * Filter post types shown in language processing settings.
	 *
	 * @since 1.6.0
	 * @hook classifai_language_settings_post_types
	 *
	 * @param {array} $post_types Array of post types to show in language processing settings.
	 *
	 * @return {array} Array of post types.
	 */
	return apply_filters( 'classifai_language_settings_post_types', $post_types );
}

/**
 * The list of post types that get the ClassifAI taxonomies. Defaults
 * to 'post'.
 *
 * return array
 */
function get_supported_post_types() {
	$classifai_settings = get_plugin_settings( 'language_processing' );

	if ( empty( $classifai_settings ) ) {
		$post_types = [];
	} else {
		$post_types = [];
		foreach ( $classifai_settings['post_types'] as $post_type => $enabled ) {
			if ( ! empty( $enabled ) ) {
				$post_types[] = $post_type;
			}
		}
	}

	/**
	 * Filter post types supported for language processing.
	 *
	 * @since 1.0.0
	 * @hook classifai_post_types
	 *
	 * @param {array} $post_types Array of post types to be classified with language processing.
	 *
	 * @return {array} Array of post types.
	 */
	$post_types = apply_filters( 'classifai_post_types', $post_types );

	return $post_types;
}

/**
 * Returns a bool based on whether the specified feature is enabled
 *
 * @param string $feature category,keyword,entity,concept
 * @return bool
 */
function get_feature_enabled( $feature ) {
	$settings = get_plugin_settings( 'language_processing' );

	if ( ! empty( $settings ) && ! empty( $settings['features'] ) ) {
		if ( ! empty( $settings['features'][ $feature ] ) ) {
			return filter_var(
				$settings['features'][ $feature ],
				FILTER_VALIDATE_BOOLEAN
			);
		}
	}

	return false;
}

/**
 * Check if any language processing features are enabled
 *
 * @since 1.6.0
 *
 * @return true
 */
function language_processing_features_enabled() {
	$features = [
		'category',
		'concept',
		'entity',
		'keyword',
	];

	foreach ( $features as $feature ) {
		if ( get_feature_enabled( $feature ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns the feature threshold based on current configuration. Lookup
 * order is.
 *
 * - Option
 * - Constant
 *
 * Any results below the threshold will be ignored.
 *
 * @param string $feature The feature whose threshold to lookup
 * @return int
 */
function get_feature_threshold( $feature ) {
	$settings  = get_plugin_settings( 'language_processing' );
	$threshold = 0;

	if ( ! empty( $settings ) && ! empty( $settings['features'] ) ) {
		if ( ! empty( $settings['features'][ $feature . '_threshold' ] ) ) {
			$threshold = filter_var(
				$settings['features'][ $feature . '_threshold' ],
				FILTER_VALIDATE_INT
			);
		}
	}

	if ( empty( $threshold ) ) {
		$constant = 'WATSON_' . strtoupper( $feature ) . '_THRESHOLD';

		if ( defined( $constant ) ) {
			$threshold = intval( constant( $constant ) );
		}
	}

	$threshold = empty( $threshold ) ? 0.7 : $threshold / 100;
	/**
	 * Filter the threshold for a specific feature. Any results below the
	 * threshold will be ignored.
	 *
	 * @since 1.0.0
	 * @hook classifai_feature_threshold
	 *
	 * @param {string} $threshold The threshold to use, expressed as a decimal between 0 and 1 inclusive.
	 * @param {string} $feature   The feature in question.
	 *
	 * @return {string} The filtered threshold.
	 */
	return apply_filters( 'classifai_feature_threshold', $threshold, $feature );
}

/**
 * Returns the Taxonomy for the specified NLU feature. Returns defaults
 * in config.php if options have not been configured.
 *
 * @param string $feature NLU feature name
 * @return string Taxonomy mapped to the feature
 */
function get_feature_taxonomy( $feature ) {
	$settings = get_plugin_settings( 'language_processing' );
	$taxonomy = 0;

	if ( ! empty( $settings ) && ! empty( $settings['features'] ) ) {
		if ( ! empty( $settings['features'][ $feature . '_taxonomy' ] ) ) {
			$taxonomy = $settings['features'][ $feature . '_taxonomy' ];
		}
	}

	if ( empty( $taxonomy ) ) {
		$constant = 'WATSON_' . strtoupper( $feature ) . '_TAXONOMY';

		if ( defined( $constant ) ) {
			$taxonomy = constant( $constant );
		}
	}

	/**
	 * Filter the Taxonomy for the specified NLU feature.
	 *
	 * @since 1.1.0
	 * @hook classifai_taxonomy_for_feature
	 *
	 * @param {string} $taxonomy The slug of the taxonomy to use.
	 * @param {string} $feature  The NLU feature this taxonomy is for.
	 *
	 * @return {string} The filtered taxonomy slug.
	 */
	return apply_filters( 'classifai_taxonomy_for_feature', $taxonomy, $feature );
}

/**
 * Provides the max filesize for the Computer Vision service.
 *
 * @since 1.4.0
 *
 * @return int
 */
function computer_vision_max_filesize() {
	/**
	 * Filters the Computer Vision maximum allowed filesize.
	 *
	 * @since 1.5.0
	 * @hook classifai_computer_vision_max_filesize
	 *
	 * @param {int} file_size The maximum allowed filesize for Computer Vision in bytes. Default `4 * MB_IN_BYTES`.
	 *
	 * @return {int} Filtered filesize in bytes.
	 */
	return apply_filters( 'classifai_computer_vision_max_filesize', 4 * MB_IN_BYTES ); // 4MB default.
}

/**
 * Callback for sorting images by width plus height, descending.
 *
 * @since 1.5.0
 *
 * @param array $size_1 Associative array containing width and height values.
 * @param array $size_2 Associative array containing width and height values.
 * @return int Returns -1 if $size_1 is larger, 1 if $size_2 is larger, and 0 if they are equal.
 */
function sort_images_by_size_cb( $size_1, $size_2 ) {
	$size_1_total = $size_1['width'] + $size_1['height'];
	$size_2_total = $size_2['width'] + $size_2['height'];

	if ( $size_1_total === $size_2_total ) {
		return 0;
	}

	return $size_1_total > $size_2_total ? -1 : 1;
}

/**
 * Retrieves the URL of the largest version of an attachment image lower than a specified max size.
 *
 * @since 1.4.0
 *
 * @param string $full_image The path to the full-sized image source file.
 * @param string $full_url   The URL of the full-sized image.
 * @param array  $sizes      Intermediate size data from attachment meta.
 * @param int    $max        The maximum acceptable size.
 * @return string|null The image URL, or null if no acceptable image found.
 */
function get_largest_acceptable_image_url( $full_image, $full_url, $sizes, $max = MB_IN_BYTES ) {
	$file_size = @filesize( $full_image ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( $file_size && $max >= $file_size ) {
		return $full_url;
	}

	usort( $sizes, __NAMESPACE__ . '\sort_images_by_size_cb' );

	foreach ( $sizes as $size ) {
		$sized_file = str_replace( basename( $full_image ), $size['file'], $full_image );
		$file_size  = @filesize( $sized_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( $file_size && $max >= $file_size ) {
			return str_replace( basename( $full_url ), $size['file'], $full_url );
		}
	}

	return null;
}

/**
 * Retrieves the URL of the largest image that matches filesize and dimensions.
 *
 * This will check that the filesize of an image matches our requirements and
 * if so, will then check the dimensions match our requirements as well. If
 * neither match, will move on to the next largest image size.
 *
 * @param string $full_image The path to the full-sized image source file.
 * @param string $full_url   The URL of the full-sized image.
 * @param array  $metadata   Attachment metadata, including intermediate sizes.
 * @param array  $width      Array of minimimum and maximum width values. Default 0, 4200.
 * @param array  $height     Array of minimimum and maximum height values. Default 0, 4200.
 * @param int    $max_size   The maximum acceptable filesize. Default 1MB.
 * @return string|null The image URL, or null if no acceptable image found.
 */
function get_largest_size_and_dimensions_image_url( $full_image, $full_url, $metadata, $width = [ 0, 4200 ], $height = [ 0, 4200 ], $max_size = MB_IN_BYTES ) {
	// Check if the full size image meets our filesize and dimension requirements
	$file_size = @filesize( $full_image ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if (
		( $file_size && $max_size >= $file_size )
		&& ( $metadata['width'] >= $width[0] && $metadata['width'] <= $width[1] )
		&& ( $metadata['height'] >= $height[0] && $metadata['height'] <= $height[1] )
	) {
		return $full_url;
	}

	// If the full size doesn't match, run the same checks on our resized images
	if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
		usort( $metadata['sizes'], __NAMESPACE__ . '\sort_images_by_size_cb' );

		foreach ( $metadata['sizes'] as $size ) {
			$sized_file = str_replace( basename( $full_image ), $size['file'], $full_image );
			$file_size  = @filesize( $sized_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if (
				( $file_size && $max_size >= $file_size )
				&& ( $size['width'] >= $width[0] && $size['width'] <= $width[1] )
				&& ( $size['height'] >= $height[0] && $size['height'] <= $height[1] )
			) {
				return str_replace( basename( $full_url ), $size['file'], $full_url );
			}
		}
	}

	return null;
}

/**
 * Helper function to determine if post content should be processed for published posts.
 *
 * @param int $post_id Post ID.
 *
 * @return bool
 */
function allow_language_processing_for_published_content( $post_id ) {
	if ( empty( $post_id ) ) {
		return false;
	}

	$supported   = \Classifai\get_supported_post_types();
	$post_type   = get_post_type( $post_id );
	$post_status = get_post_status( $post_id );

	// Only process content for published, supported items and if features are enabled.
	if ( 'publish' === $post_status && in_array( $post_type, $supported, true ) && \Classifai\language_processing_features_enabled() ) {
		return true;
	}

	return false;
}
