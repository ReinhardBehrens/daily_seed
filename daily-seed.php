<?php
/*
Plugin Name: Daily Seed - Bible Verse of the Day
Description: Displays a random Bible verse of the day, supporting specific or random versions, and selecting verses from the Old Testament, New Testament, or both.
Version: 1.0
Author: WP Plugin Architect
Author URI: https://chatgpt.com/g/g-6cqBCrKTn-wp-plugin-architect
Text Domain: daily-seed
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class DailySeed {
	private $option_name = 'daily_seed_options';

	public function __construct() {
		// Register settings and menu
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Register shortcode
		add_shortcode( 'daily_seed', array( $this, 'verse_shortcode' ) );
	}

	// Adds the settings page under "Settings" menu in WordPress Admin
	public function add_settings_page() {
		add_options_page(
			__('Daily Seed Settings', 'daily-seed'),
			__('Daily Seed', 'daily-seed'),
			'manage_options',
			'daily-seed',
			array( $this, 'settings_page_content' )
		);
	}

	// Settings page content
	public function settings_page_content() {
		?>
		<div class="wrap">
			<h1><?php _e( 'Daily Seed - Bible Verse of the Day Settings', 'daily-seed' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'daily_seed_settings_group' );
				do_settings_sections( 'daily-seed' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	// Register plugin settings and fields
	public function register_settings() {
		register_setting( 'daily_seed_settings_group', $this->option_name, array( 'sanitize_callback' => array( $this, 'sanitize_options' ) ) );

		add_settings_section(
			'daily_seed_main_section',
			__('Configure Daily Seed Settings', 'daily-seed'),
			null,
			'daily-seed'
		);

		add_settings_field(
			'api_key',
			__('Bible API Key', 'daily-seed'),
			array( $this, 'api_key_field' ),
			'daily-seed',
			'daily_seed_main_section'
		);

		add_settings_field(
			'default_version',
			__('Default Bible Version', 'daily-seed'),
			array( $this, 'default_version_field' ),
			'daily-seed',
			'daily_seed_main_section'
		);

		add_settings_field(
			'use_random_version',
			__('Use Random Bible Version?', 'daily-seed'),
			array( $this, 'random_version_field' ),
			'daily-seed',
			'daily_seed_main_section'
		);

		add_settings_field(
			'verse_scope',
			__('Verse Scope (OT, NT, Both)', 'daily-seed'),
			array( $this, 'verse_scope_field' ),
			'daily-seed',
			'daily_seed_main_section'
		);
	}

	public function api_key_field() {
		$options = get_option( $this->option_name );
		$api_key = isset( $options['api_key'] ) ? esc_attr( $options['api_key'] ) : '';
		echo "<input type='text' name='{$this->option_name}[api_key]' value='$api_key' placeholder='Enter API Key'>";
	}

	public function default_version_field() {
		$options = get_option( $this->option_name );
		$default_version = isset( $options['default_version'] ) ? esc_attr( $options['default_version'] ) : '';
		echo "<input type='text' name='{$this->option_name}[default_version]' value='$default_version' placeholder='e.g., NIV'>";
	}

	public function random_version_field() {
		$options = get_option( $this->option_name );
		$use_random = isset( $options['use_random_version'] ) ? (bool) $options['use_random_version'] : false;
		echo "<input type='checkbox' name='{$this->option_name}[use_random_version]' value='1' " . checked( 1, $use_random, false ) . ">";
	}

	public function verse_scope_field() {
		$options = get_option( $this->option_name );
		$verse_scope = isset( $options['verse_scope'] ) ? esc_attr( $options['verse_scope'] ) : 'both';
		echo "<select name='{$this->option_name}[verse_scope]'>
				<option value='ot' " . selected( 'ot', $verse_scope, false ) . ">Old Testament</option>
				<option value='nt' " . selected( 'nt', $verse_scope, false ) . ">New Testament</option>
				<option value='both' " . selected( 'both', $verse_scope, false ) . ">Both</option>
			  </select>";
	}

	public function sanitize_options( $input ) {
		$sanitized = array();
		$sanitized['api_key'] = sanitize_text_field( $input['api_key'] );
		$sanitized['default_version'] = sanitize_text_field( $input['default_version'] );
		$sanitized['use_random_version'] = isset( $input['use_random_version'] ) ? (bool) $input['use_random_version'] : false;
		$sanitized['verse_scope'] = in_array( $input['verse_scope'], array( 'ot', 'nt', 'both' ) ) ? $input['verse_scope'] : 'both';
		return $sanitized;
	}

	public function verse_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'config_id' => 0 ), $atts, 'daily_seed' );

		$options = get_option( $this->option_name );
		$api_key = $options['api_key'];
		$version = $options['default_version'];
		$use_random = $options['use_random_version'];
		$scope = $options['verse_scope'];

		if ( $use_random ) {
			$version = $this->get_random_version();
		}

		$verse = $this->fetch_random_verse( $api_key, $version, $scope );

		if ( is_wp_error( $verse ) ) {
			return __( 'Error retrieving the verse. Please check API key and settings.', 'daily-seed' );
		}

		return sprintf( '<p><strong>%s</strong>: %s</p>', esc_html( $verse->reference ), esc_html( $verse->text ) );
	}

	private function fetch_random_verse( $api_key, $version, $scope ) {
		$api_url = 'https://api.scripture.api.bible/v1/bibles/' . urlencode($version) . '/verses';
		$params = array(
			'scope' => $scope,
			'random' => 'true',
		);
		$response = wp_remote_get( $api_url, array(
			'headers' => array(
				'api-key' => $api_key
			),
			'body' => $params
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );
		if ( isset( $data->error ) ) {
			return new WP_Error( 'api_error', __( 'API Error: ' . $data->error, 'daily-seed' ) );
		}

		return $data;
	}

	private function get_random_version() {
		$versions = array( 'NIV', 'KJV', 'ESV', 'NASB' );
		return $versions[ array_rand( $versions ) ];
	}
}

new DailySeed();
