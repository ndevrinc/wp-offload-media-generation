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

		add_settings_section(
			'omgPage_section',
			__( 'All the fields are required for properly implementing the plugin.', 'omg' ),
			null,
			'omgPage'
		);

		add_settings_field(
			'enable',
			__( 'Enable offloading media generation?', 'omg' ),
			[ $this, 'enable_render' ],
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

		add_settings_section(
			'omgPage_section2',
			__( 'Check all image sizes you want rendered by WordPress, checking thumbnail is highly recommended.', 'omg' ),
			null,
			'omgPage'
		);

		add_settings_field(
			'og_approved_sizes',
			__( 'Select Sizes', 'omg' ),
			[ $this, 'approved_sizes_render' ],
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
               value='1'>
		<?php

	}


	public function api_key_render() {

		$options = get_option( 'omg_settings' );
		?>
        <input type='text' name='omg_settings[api_key]' value='<?php echo $options['api_key']; ?>'>
		<?php

	}


	public function api_url_render() {

		$options = get_option( 'omg_settings' );
		?>
        <input type='text' name='omg_settings[api_url]' value='<?php echo $options['api_url']; ?>'>
		<?php

	}


	public function approved_sizes_render() {

		$options     = get_option( 'omg_settings' );
		$image_sizes = get_intermediate_image_sizes();
		?>
        <select name='omg_settings[approved_sizes][]' multiple>
        <?php foreach ( $image_sizes as $image_size ): ?>
            <?php $selected = in_array( $image_size, $options['approved_sizes'] ) ? ' selected="selected" ' : ''; ?>
            <option value='<?php echo esc_attr( $image_size ); ?>' <?php echo $selected; ?>><?php echo esc_html( $image_size ); ?></option>
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
