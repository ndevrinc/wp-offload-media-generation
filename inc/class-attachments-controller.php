<?php

namespace Omg;

use WP_REST_Server;
use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;

/**
 * Class Attachements_Controller
 * @package Omg\Attachements_Controller
 */
class Attachments_Controller extends WP_REST_Controller {
	private static $instance = null;

	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Attachments_Controller();

		}

		return self::$instance;
	}

	public function __construct() {
		$this->namespace = 'omg/v1';
		$this->rest_base = 'attachments';

		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'update_attachment' ),
				'permission_callback' => array( $this, 'update_attachment_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Updates one attachment
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Request
	 */
	public function update_attachment( $request ) {

		$updated = false;

		if ( ! empty( $request['aid'] && ! empty( $request['key'] ) ) ) {
			$attachment_id = $request['aid'];
			$s3_key        = $request['key'];

			if ( is_numeric( $attachment_id ) && is_string( $s3_key ) ) {
				$image_path = wp_get_original_image_path( $attachment_id );

				// Simple validation that someone isn't just passing random attachment_ids
				if ( stripos( $image_path, $s3_key ) ) {
					Attachments::update_meta( $attachment_id );
					$updated = true;
				}
			}
		}

		if ( $updated ) {
			return true;
		} else {
			return new WP_Error( 'rest_invalid', __( 'Not Allowed.' ), array( 'status' => 403 ) );
		}

		return new WP_REST_Response( true, 200 );
	}


	/**
	 * Check if a given request has access to update an attachment
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function update_attachment_permissions_check( $request ) {

		// Check a API key
		$options     = get_option( 'omg_settings' );
		$stored_api_key = $options['api_key'];

		if ( empty( $request['api_key'] ) || $stored_api_key !== $request['api_key'] ) {
			return new WP_Error( 'rest_invalid', __( 'Not Allowed.' ), array( 'status' => rest_authorization_required_code() ) );;
		}

		return true;
	}

}

Attachments_Controller::init();
