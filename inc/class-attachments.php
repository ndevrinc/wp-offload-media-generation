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
		add_filter( 'intermediate_image_sizes', [ $this, 'remove_default_image_sizes' ], 999, 1 );
		add_action( 'add_attachment', [ $this, 'start_generation' ], 999 );

	}

	public function get_approved_sizes() {
		$options = get_option( 'omg_settings', [ 'approved_sizes' => 'thumbnail' ] );

		return $options['approved_sizes'];
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
	 * Optimizing the image sizes to only the largest of each aspect ratio
	 *
	 * @param $sizes
	 *
	 * @return array
	 */
	public function remove_default_image_sizes( $sizes ) {
		if ( $this->get_only_approved() ) {
			$sizes = $this->get_approved_sizes();
		}

		return $sizes;
	}

	public function start_generation( $post_ID ) {

		$post = get_post( $post_ID );
		if ( 'attachment' === $post->post_type ) {
			$guid     = $post->guid;
			$guid_arr = explode( 'uploads/', $guid );

			$key     = 'uploads/' . $guid_arr[1];
			$options = get_option( 'omg_settings' );
			$body    = [
				'bucket' => S3_UPLOADS_BUCKET,
				'key'    => $key,
				'aid'    => $post_ID,
				'api_key' => $options['api_key']
			];
			$args    = [
				'method'   => 'POST',
				'blocking' => false,
				'body'     => \GuzzleHttp\json_encode( $body )
			];

			$response = wp_remote_request( $options['api_url'], $args );

		}

	}

}

Attachments::init();
