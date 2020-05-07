<?php

namespace Omg;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 * @package Omg\Settings
 */
class Settings {
	private static $instance = null;

	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Settings();

		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'settings_init' ] );

	}

	public function add_admin_menu() {

		add_options_page( 'Offload Media Generation', 'Offload Media Generation', 'manage_options', 'omg', [
			$this,
			'options_page'
		] );

	}

	public function settings_init() {

		register_setting( 'omgPage', 'omg_settings' );

		if ( ! defined( 'S3_UPLOADS_BUCKET' ) ) {
            add_settings_section(
                'omgPage_section',
                __( 'You must define an S3_UPLOADS_BUCKET first.', 'omg' ),
                null,
                'omgPage'
            );
        } else {
            add_settings_section(
                'omgPage_section',
                __( 'Without API Key and URL, images will be generated asynchronously on this server.', 'omg' ),
                null,
                'omgPage'
            );

            add_settings_field(
                's3_bucket',
                __( 'S3 Bucket', 'omg' ),
                [ $this, 's3_bucket_render' ],
                'omgPage',
                'omgPage_section'
            );

            add_settings_field(
                'api_key',
                __( 'API Key', 'omg' ),
                [ $this, 'api_key_render' ],
                'omgPage',
                'omgPage_section'
            );

            add_settings_field(
                'api_url',
                __( 'API URL', 'omg' ),
                [ $this, 'api_url_render' ],
                'omgPage',
                'omgPage_section'
            );
		}

		add_settings_section(
			'omgPage_section2',
			__( 'Check all image sizes you want offloaded, or not ever generated.', 'omg' ),
			null,
			'omgPage'
		);

		add_settings_field(
			'remove_sizes',
			__( 'Never Generate', 'omg' ),
			[ $this, 'remove_sizes_render' ],
			'omgPage',
			'omgPage_section2'
		);

		add_settings_field(
			'offload_sizes',
			__( 'Offload Generate', 'omg' ),
			[ $this, 'offload_sizes_render' ],
			'omgPage',
			'omgPage_section2'
		);


	}

	public function enable_render() {

		$options = get_option( 'omg_settings' );
		if ( empty( $options['enable'] ) ) {
		    $options['enable'] = null;
		}
		?>
        <input type='checkbox'
               name='omg_settings[enable]' <?php checked( $options['enable'], 1 ); ?>
               value='1'/>
		<?php

	}


	public function s3_bucket_render() {

		?>
        <input type='text' value='<?php echo esc_attr( S3_UPLOADS_BUCKET ); ?>' style="width:60%" readonly/>
		<?php
	}

	public function api_key_render() {

		$options = get_option( 'omg_settings' );
		?>
        <input type='text' name='omg_settings[api_key]' value='<?php echo esc_attr( $options['api_key'] ); ?>' style="width:60%"/>
		<?php

	}


	public function api_url_render() {

		$options = get_option( 'omg_settings' );
		?>
        <input type='text' name='omg_settings[api_url]' value='<?php echo esc_attr( $options['api_url'] ); ?>' style="width:60%"/>
		<?php

	}


	public function remove_sizes_render() {

		$options     = get_option( 'omg_settings' );
		$image_sizes = get_intermediate_image_sizes();
		?>
        <select name='omg_settings[remove_sizes][]' multiple>
        <?php foreach ( $image_sizes as $image_size ): ?>
            <?php $selected = in_array( $image_size, $options['remove_sizes'] ) ? ' selected="selected" ' : ''; ?>
            <option value='<?php echo esc_attr( $image_size ); ?>' <?php echo $selected; ?>><?php echo esc_html( $image_size ); ?></option>
        <?php endforeach; ?>
		<?php

	}

	public function offload_sizes_render() {

		$options     = get_option( 'omg_settings' );
		$image_sizes = get_intermediate_image_sizes();
		?>
        <select name='omg_settings[offload_sizes][]' multiple>
        <?php foreach ( $image_sizes as $image_size ): ?>
            <?php if ( ! in_array( $image_size, $options['remove_sizes'] ) ): ?>
                <?php $selected = in_array( $image_size, $options['offload_sizes'] ) ? ' selected="selected" ' : ''; ?>
                <option value='<?php echo esc_attr( $image_size ); ?>' <?php echo $selected; ?>><?php echo esc_html( $image_size ); ?></option>
            <?php endif; ?>
        <?php endforeach; ?>
		<?php

	}


	public function options_page() {

		?>
        <form action='options.php' method='post'>

            <h2>Offload Media Generation</h2>

            <?php
            settings_fields( 'omgPage' );
            do_settings_sections( 'omgPage' );
            submit_button();
			?>

        </form>
		<?php

	}


}

Settings::init();
