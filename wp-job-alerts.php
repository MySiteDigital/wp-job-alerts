<?php
/*
Plugin Name: WP Job Alerts
Plugin URI: https://github.com/MySiteDigital/wp-job-alerts
Description: Job Alerts sent using background processing
Author: MySite Digital
Version: 0.1
Author URI: https://mysite.digital
Text Domain: example-plugin
Domain Path: /languages/
*/

namespace MySiteDigital;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

final class WPJobAlerts {

	/**
	 * @var $id
	 */
	protected $id = 'wp-job-alerts';

	/**
	 * @var JobAlertRequest
	 */
	protected $processor;

	/**
	 * WPJobAlerts Constructor.
	 */
	public function __construct()
	{
		$this->define_constants();
		$this->includes();
		$this->init_hooks();

		
	}

	/*
	 * Define WPJobAlerts Constants.
	 */
	private function define_constants()
	{
		if (!defined('MDJA_PLUGIN_PATH')) {
			define('MDJA_PLUGIN_PATH', plugin_dir_path(__FILE__));
		}
		if (!defined('MDJA_PLUGIN_URL')) {
			define('MDJA_PLUGIN_URL', plugin_dir_url(__FILE__));
		}
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 */
	public function includes()
	{
		include_once(MDJA_PLUGIN_PATH . 'class-logger.php');
		include_once( MDJA_PLUGIN_PATH . 'includes/class-md-process-job-alerts.php' );

		
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks()
	{
		\add_action('plugins_loaded', array($this, 'init'));
		\add_action('admin_bar_menu', array($this, 'admin_bar'), 100);
		\add_action('init', array($this, 'process_handler'));
	}

	/**
	 * Example_Background_Processing constructor.
	 */

	/**
	 * Init
	 */
	public function init() {
		$this->processor    = new ProcessJobAlerts();
	}

	/**
	 * Admin bar
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	public function admin_bar( $wp_admin_bar ) {
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		$wp_admin_bar->add_menu( array(
			'id'    =>  $this->id,
			'title' => __( 'Process Job Alerts', $this->id ),
			'href'  => \wp_nonce_url(\admin_url('?process-job-alerts=all'), 'process-job-alerts'),
		) );
	}

	/**
	 * Process handler
	 */
	public function process_handler() {
		if ( ! isset( $_GET['process-job-alerts'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		if ( ! \wp_verify_nonce( $_GET['_wpnonce'], 'process-job-alerts') ) {
			return;
		}

		if ( 'all' === $_GET['process-job-alerts'] ) {
			
			$this->handle_all();
		}
	}

	/**
	 * Handle all
	 */
	protected function handle_all() {
		$users = $this->processor->get_subscribers();

		foreach ($users as $user ) {
			$this->processor->push_to_queue( $user );
		}

		$this->processor->create_db_record();
		$this->processor->save()->dispatch();
	}
}

new WPJobAlerts();