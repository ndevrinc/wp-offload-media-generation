<?php
/*
Plugin Name: Offload Media Generation
Description: Allows for generating media assets on AWS S3
Version: 1.0
Author: Ndevr
Author URI: https://ndevr.io
*/

require_once dirname( __FILE__ ) . '/inc/class-settings.php';

// Nothing to do if no image sizes were selected
$options = get_option( 'omg_settings' );
if ( isset( $options['remove_sizes'] ) && isset( $options['offload_sizes'] ) && ! empty( $options['remove_sizes'] ) && ! empty( $options['offload_sizes'] ) ) {
	require_once dirname( __FILE__ ) . '/inc/class-attachments.php';
	require_once dirname( __FILE__ ) . '/inc/class-attachments-controller.php';

	if ( defined( 'WP_CLI' ) && WP_CLI && ! class_exists( 'Omg/CLI' ) ) {
		require_once dirname( __FILE__ ) . '/inc/class-cli.php';
	}
}

