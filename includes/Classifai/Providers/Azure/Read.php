<?php
/**
 * Scan PDF files to extract visible text with the Computer Vision Read service.
 *
 * @since 1.6.1
 * @package Classifai
 */

namespace Classifai\Providers\Azure;

use WP_Error;
use function Classifai\computer_vision_max_filesize;

/**
 * Read class
 *
 * Connects to Computer Vision's Read endpoint to detect text.
 *
 * @see https://docs.microsoft.com/en-us/rest/api/cognitiveservices/computervision/recognizeprintedtext/
 */
class Read {

	/**
	 * The Computer Vision API path to the Read service.
	 *
	 * @var string
	 */
	const API_PATH = 'vision/v3.2/read/';

	/**
	 * ComputerVision settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Attachment ID to process.
	 *
	 * @var boolean
	 */
	private $attachment_id;

	/**
	 * Force processing
	 *
	 * @var boolean
	 */
	private $force;

	/**
	 * Constructor
	 *
	 * @param array   $settings      Computer Vision settings.
	 * @param int     $attachment_id Attachment ID to process.
	 * @param boolean $force         Whether to force processing or not.
	 */
	public function __construct( array $settings, $attachment_id, bool $force = false ) {
		$this->settings      = $settings;
		$this->attachment_id = $attachment_id;
		$this->force         = $force;
	}

	/**
	 * Builds the API url.
	 *
	 * @param string $path Path to append to API URL.
	 *
	 * @return string
	 */
	public function get_api_url( $path = '' ) {
		return sprintf( '%s%s%s', trailingslashit( $this->settings['url'] ), static::API_PATH, $path );
	}

	/**
	 * Returns whether Read processing should be applied to the attachment
	 *
	 * @return boolean
	 */
	public function should_process() {
		// Bypass check if this is a force request
		if ( $this->force ) {
			return true;
		}

		$mime_type          = get_post_mime_type( $this->attachment_id );
		$matched_extensions = explode( '|', array_search( $mime_type, wp_get_mime_types(), true ) );
		$process            = false;

		$approved_media_types = [ 'pdf' ];

		foreach ( $matched_extensions as $ext ) {
			if ( in_array( $ext, $approved_media_types, true ) ) {
				$process = true;
			}
		}

		/**
		 * Filters whether to run Read processing on this attachment item
		 *
		 * @hook classifai_azure_read_should_process
		 *
		 * @param bool        $process       Whether to run OCR processing or not.
		 * @param int         $attachment_id The attachment ID.
		 *
		 * @return bool Whether this attachment should have OCR processing.
		 */
		return apply_filters( 'classifai_azure_read_should_process', $process, $this->attachment_id );
	}

	/**
	 * Call the Azure Read API.
	 *
	 * @return object|WP_Error
	 */
	public function read_document() {
		if ( ! $this->should_process( $this->attachment_id ) ) {
			return $this->log_error( new WP_Error( 'processError', esc_html__( 'Document does not meet processing requirements.', 'classifai' ) ) );
		}

		$filesize = filesize( get_attached_file( $this->attachment_id ) );
		if ( ! $filesize || $filesize > computer_vision_max_filesize() ) {
			return $this->log_error( new WP_Error( 'sizeError', esc_html__( 'Document does not meet size requirements. Please ensure it is smaller than the maximum threshold (default to 4MB).', 'classifai' ), $metadata ), $attachment_id );
		}

		/**
		 * Filters the request arguments sent to Read endpoint.
		 *
		 * @hook classifai_azure_read_should_process
		 *
		 * @param array $args       Whether to run OCR processing or not.
		 * @param int   $attachment_id The attachment ID.
		 *
		 * @return array Filtered request arguments.
		 */
		$request_args = apply_filters( 'classifai_azure_read_request_args', [], $this->attachment_id );

		$url = add_query_arg(
			$request_args,
			$this->get_api_url( 'analyze' )
		);

		$document_url = wp_get_attachment_url( $this->attachment_id );

		if ( ! $document_url ) {
			return $this->log_error( new WP_Error( 'invalid_attachment', esc_html__( 'Document does not exist.', 'classifai' ) ) );
		}

		$response = wp_remote_post(
			$url,
			[
				'body'    => wp_json_encode(
					[
						'url' => $document_url,
					]
				),
				'headers' => [
					'Content-Type'              => 'application/json',
					'Ocp-Apim-Subscription-Key' => $this->settings['api_key'],
				],
			]
		);

		/**
		 * Fires after the request to the read endpoint has run.
		 *
		 * @since 1.5.0
		 * @hook classifai_azure_read_after_request
		 *
		 * @param array|WP_Error Response data or a WP_Error if the request failed.
		 * @param string The request URL with query args added.
		 * @param int The document ID.
		 * @param string The document URL.
		 */
		do_action( 'classifai_azure_read_after_request', $response, $url, $this->attachment_id, $document_url );

		if ( is_wp_error( $response ) ) {
			return $this->log_error( $response );
		}

		if ( 202 === wp_remote_retrieve_response_code( $response ) ) {
			$operation_url = wp_remote_retrieve_header( $response, 'Operation-Location' );
			if ( ! filter_var( $operation_url, FILTER_VALIDATE_URL ) ) {
				return $this->log_error( new WP_Error( 'invalid_read_operation_url', esc_html__( 'Operation URL is invalid.', 'classifai' ) ) );
			}
			return $this->check_read_result( $operation_url );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['error'] ) || empty( $body['error']['code'] ) || empty( $body['error']['message'] ) ) {
			return $this->log_error( new WP_Error( 'unknown_read_error', esc_html__( 'Unknow Read error.', 'classifai' ) ) );
		}

		return $this->log_error( new WP_Error( $body['error']['code'], $body['error']['message'] ) );
	}

	/**
	 * Use WP Cron to periodically check the status of the read operation.
	 *
	 * @param string $operation_url Operation URL for checking the read status.
	 *
	 * @return WP_Error|null|array
	 */
	public function check_read_result( $operation_url ) {
		if ( function_exists( 'vip_safe_wp_remote_get' ) ) {
			$response = vip_safe_wp_remote_get( $operation_url );
		} else {
			$response = wp_remote_get(
				$operation_url,
				[
					'headers' => [
						'Ocp-Apim-Subscription-Key' => $this->settings['api_key'],
					],
				]
			);
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( empty( $body['status'] ) ) {
				return $this->log_error( new WP_Error( 'invalid_read_result', esc_html__( 'Invalid read result.', 'classifai' ) ) );
			}

			switch ( $body['status'] ) {
				case 'notStarted':
				case 'running':
					$this->update_status( $body );
					$retry_interval = apply_filters( 'classifai_azure_read_retry_interval', MINUTE_IN_SECONDS );
					wp_schedule_single_event( time() + $retry_interval, 'classifai_retry_get_read_result', [ $operation_url, $this->attachment_id ] );
					break;
				case 'failed':
					return $this->log_error( new WP_Error( 'failed_read_request', esc_html__( 'The read operation has failed.', 'classifai' ) ) );
					break;
				case 'succeeded':
					return $this->update_document_description( $body );
					break;
				default:
					return $this->log_error( new WP_Error( 'invalid_read_result_status', esc_html__( 'Invalid result status.', 'classifai' ) ) );
					break;
			}
		}
	}

	/**
	 * Update document desctiption using text received from Read API.
	 *
	 * @param array $data          Read result.
	 *
	 * @return WP_Error|array
	 */
	public function update_document_description( $data ) {
		if ( empty( $data['analyzeResult'] ) || empty( $data['analyzeResult']['readResults'] ) ) {
			return $this->log_error( new WP_Error( 'invalid_read_result', esc_html__( 'The Read result is invalid.', 'classifai' ) ) );
		}

		/**
		 * Filter the text max pages can be processed.
		 *
		 * @hook classifai_azure_read_result_max_page
		 *
		 * @param int $max_page The attachment ID.
		 *
		 * @return int
		 */
		$max_page = min( apply_filters( 'classifai_azure_read_result_max_page', 2 ), count( $data['analyzeResult']['readResults'] ) );

		$lines_of_text = [];

		for ( $page = 0; $page < $max_page; $page++ ) {
			foreach ( $data['analyzeResult']['readResults'][ $page ]['lines'] as $line ) {
				$lines_of_text[] = $line['text'];
			}
		}

		/**
		 * Filter the text result returned from Read API.
		 *
		 * @hook classifai_azure_read_text_result
		 *
		 * @param array       $lines_of_text Array of text extracted from the response.
		 * @param int         $attachment_id The attachment ID.
		 * @param array       $data          Read result.
		 *
		 * @return array
		 */
		$lines_of_text = apply_filters( 'classifai_azure_read_text_result', $lines_of_text, $this->attachment_id, $data );

		$update = wp_update_post(
			[
				'ID'           => $this->attachment_id,
				'post_content' => implode( ' ', $lines_of_text ),
			]
		);

		if ( is_wp_error( $update ) ) {
			return $this->log_error( $update );
		}

		$this->update_status( $data );
	}

	/**
	 * Log error to metadata for troubleshooting.
	 *
	 * @param WP_Error $error WP_Error object.
	 */
	private function log_error( $error ) {
		update_post_meta( $this->attachment_id, '_classifai_azure_read_error', $error->get_error_message() );

		return $error;
	}

	/**
	 * Log the status of read process to database.
	 *
	 * @param array $data Response body of the read result.
	 *
	 * @see https://centraluseuap.dev.cognitive.microsoft.com/docs/services/computer-vision-v3-2/operations/5d9869604be85dee480c8750
	 */
	private function update_status( $data ) {
		update_post_meta( $this->attachment_id, '_classifai_azure_read_status', $data );

		return $data;
	}
}
