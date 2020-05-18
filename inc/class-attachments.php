<?php

namespace Omg;

class Attachments {
	private static $instance = null;
	private static $only_approved = false;

	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Attachments();

		}

		return self::$instance;
	}

	private function __construct() {
		add_filter( 'intermediate_image_sizes', [ $this, 'refine_image_sizes' ], 999, 1 );
		add_action( 'add_attachment', [ $this, 'start_generation' ], 999 );

	}

	/**
	 * Get all the registered image sizes along with their dimensions
	 *
	 * @global array $_wp_additional_image_sizes
	 *
	 * @link http://core.trac.wordpress.org/ticket/18947 Reference ticket
	 *
	 * @return array $image_sizes The image sizes
	 */
	private function get_all_image_sizes() {
		global $_wp_additional_image_sizes;

		$default_sizes = array( 'thumbnail', 'medium', 'medium_large', 'large' );
		$image_sizes   = [];

		foreach ( $default_sizes as $size ) {
			$image_sizes[ $size ]['width']  = intval( get_option( "{$size}_size_w" ) );
			$image_sizes[ $size ]['height'] = intval( get_option( "{$size}_size_h" ) );
			$image_sizes[ $size ]['crop']   = get_option( "{$size}_crop" ) ? get_option( "{$size}_crop" ) : false;
		}

		if ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) ) {
			$image_sizes = array_merge( $image_sizes, $_wp_additional_image_sizes );
		}

		return $image_sizes;
	}

	public function get_offload_sizes() {
		$options       = get_option( 'omg_settings' );
		$offload_sizes = [];

		if ( isset( $options['offload_sizes'] ) ) {
			$offload_sizes = $options['offload_sizes'];
		}

		global $pagenow;
		if ( self::$only_approved ) {
			// proceed
		} else if ( 'options-general.php' === $pagenow ) {
			$offload_sizes = [];
		} else if ( is_admin() ) {
			// proceed
		} else {
			$offload_sizes = [];
		}

		return $offload_sizes;

	}

	public function get_remove_sizes() {
		global $pagenow;
		$remove_sizes = [];

		if ( 'options-general.php' !== $pagenow ) {
			$options      = get_option( 'omg_settings' );
			$remove_sizes = [];

			if ( isset( $options['remove_sizes'] ) ) {
				$remove_sizes = $options['remove_sizes'];
			}
		}

		return $remove_sizes;
	}

	public static function set_only_approved( $value ) {
		self::$only_approved = $value;
	}

	public static function get_only_approved() {

		global $pagenow;
		if ( self::$only_approved ) {
			return true;
		} else if ( 'options-general.php' === $pagenow ) {
			return false;
		} else if ( is_admin() ) {
			return true;
		}

		return false;
	}

	public static function update_meta( $attachment_id ) {
		// Update only approved variable to get all defined subsizes
		$only_approved = Attachments::get_only_approved();
		Attachments::set_only_approved( false );

		// Get any missing subsizes from the meta data
		include_once( ABSPATH . 'wp-admin/includes/image.php' ); // Need to include for WP_REST API
		$missing = wp_get_missing_image_subsizes( $attachment_id );

		// Get meta data from missing subsizes that exist
		$image_file    = wp_get_original_image_path( $attachment_id );
		$image_meta    = wp_get_attachment_metadata( $attachment_id );
		$editor        = wp_get_image_editor( $image_file );
		$missing_files = [];

		foreach ( $missing as $name => $subsize ) {
			$filename  = $editor->generate_filename( $subsize['width'] . 'x' . $subsize['height'] );
			$imagesize = @\getimagesize( $filename );

			if ( false !== $imagesize ) {
				// Update attachment meta data
				$meta                         = [
					'file'      => str_replace( wp_get_upload_dir(), '', $filename ),
					'width'     => $imagesize[0],
					'height'    => $imagesize[1],
					'mime-type' => $imagesize['mime']
				];
				$image_meta['sizes'][ $name ] = $meta;

			} else {
				$missing_files[ $name ] = $subsize;
			}
		}

		// Update meta data
		wp_update_attachment_metadata( $attachment_id, $image_meta );

		// Generate any missing image files
		$image_meta = _wp_make_subsizes( $missing_files, $image_file, $image_meta, $attachment_id );

		// Reset only approved variable
		Attachments::set_only_approved( $only_approved );
	}

	/**
	 * Refine the image sizes to only necessary ones
	 *
	 * @param $sizes
	 *
	 * @return array
	 */
	public function refine_image_sizes( $sizes ) {

		$offload_sizes = $this->get_offload_sizes();
		$sizes         = array_diff( $sizes, $offload_sizes );

		$remove_sizes = $this->get_remove_sizes();
		$sizes        = array_diff( $sizes, $remove_sizes );

		return $sizes;
	}

	public function start_generation( $post_ID ) {

		// No need to fire request if no images are set to offload
		$options = get_option( 'omg_settings' );
		if ( isset( $options['offload_sizes'] ) && ! empty( $options['offload_sizes'] ) ) {
			$post = get_post( $post_ID );
			if ( 'attachment' === $post->post_type ) {
				$hostname = $_SERVER['HTTP_HOST'];
				$path     = '/wp-json/omg/v1/attachments';

				$sizes         = [];
				$all_sizes     = $this->get_all_image_sizes();
				$offload_sizes = $this->get_offload_sizes();
				foreach ( $offload_sizes as $size ) {
					if ( isset( $all_sizes[ $size ] ) ) {
						$sizes[] = [
							$all_sizes[ $size ]['width'],
							$all_sizes[ $size ]['height'],
							$all_sizes[ $size ]['crop'],
						];
					}
				}

				$guid     = $post->guid;
				$guid_arr = explode( 'uploads/', $guid );

				$key     = 'uploads/' . $guid_arr[1];
				$options = get_option( 'omg_settings' );
				if ( isset( $options['api_key'] ) && isset( $options['api_url'] ) ) {
					$body = [
						'bucket'   => S3_UPLOADS_BUCKET,
						'key'      => $key,
						'aid'      => $post_ID,
						'api_key'  => $options['api_key'],
						'sizes'    => \GuzzleHttp\json_encode( $sizes ),
						'hostname' => $hostname,
						'path'     => $path
					];
					$args = [
						'method'   => 'POST',
						'blocking' => false,
						'body'     => \GuzzleHttp\json_encode( $body )
					];

					$response = wp_remote_request( $options['api_url'], $args );
				} else {
					$body = [
						'key'     => $key,
						'aid'     => $post_ID,
						'api_key' => $options['api_key']
					];
					$args = [
						'method'   => 'GET',
						'blocking' => false,
						'body'     => \GuzzleHttp\json_encode( $body )
					];

					$response = wp_remote_request( 'https://' . $_SERVER['HTTP_HOST'] . $path, $args );
				}

			}
		}

	}

}

Attachments::init();
