<?php

namespace Omg;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Class CLI
 * @package Omg\CLI
 */
class CLI extends \WP_CLI {
	/**
	 * Update Media Attachment
	 */
	public function update( $_, $assoc_args ) {
		\WP_CLI::log( 'Updating Media Attachment.' );

		if ( ! isset( $assoc_args['aid'] ) || ! is_numeric( $assoc_args['aid'] ) ) {
			\WP_CLI::error( 'The aid is empty or invalid' );
			exit();
		}
		$attachment_id = $assoc_args['aid'];

		Attachments::update_meta( $attachment_id );

		\WP_CLI::success( 'Media Attachment Updated.' );
	}

	/**
	 * Reset Media Attachment
	 */
	public function debug_reset( $_, $assoc_args ) {
		\WP_CLI::log( 'Reset Media Attachment.' );

		if ( ! isset( $assoc_args['aid'] ) || ! is_numeric( $assoc_args['aid'] ) ) {
			\WP_CLI::error( 'The aid is empty or invalid' );
			exit();
		}
		$only_approved = Attachments::get_only_approved();
		Attachments::set_only_approved( true );

		$attachment_id = $assoc_args['aid'];
		$attachment    = get_post( $attachment_id );

		delete_transient( 'wp_generating_att_' . $attachment_id );
		delete_post_meta( $attachment_id, '_wp_attachment_metadata' );

		wp_maybe_generate_attachment_metadata( $attachment );

		Attachments::set_only_approved( $only_approved );

		\WP_CLI::success( 'Attachment Reset.' );
	}
}

\WP_CLI::add_command( 'omg', 'Omg\CLI' );
