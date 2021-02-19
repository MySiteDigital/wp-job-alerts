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
		include_once( MDJA_PLUGIN_PATH . 'includes/class-md-post-type-job-alerts-log.php' );
		include_once( MDJA_PLUGIN_PATH . 'includes/class-md-process-job-alerts.php' );
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks()
	{
		\add_action( 'plugins_loaded', [ $this , 'init' ] );
		\add_action( 'admin_bar_menu', [ $this, 'admin_bar' ] , 100 );
		\add_action( 'init', [ $this, 'schedule_daily_processor' ] );
		\add_action( 'init', [ $this, 'process_handler' ] );
		\add_action( 'md_daily_job_alerts', [ $this, 'handle_all' ] );
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
	public function schedule_daily_processor() {
		if ( ! wp_next_scheduled( 'md_daily_job_alerts' ) ) {
			\wp_schedule_event( strtotime('tomorrow GMT'), 'daily', 'md_daily_job_alerts' );
		}
	}

	public function process_handler() {
	
		if ( ! isset( $_GET['process-job-alerts'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		if ( ! \wp_verify_nonce( $_GET['_wpnonce'], 'process-job-alerts') ) {
			return;
		}

		if ( 'all' === $_GET['process-job-alerts'] ) {
			$this->handle_all( false );
		}
	}

	/**
	 * Handle all
	 */
	public function handle_all( $auto = true ) {
		global $wpdb;

		
		$queue_items = $this->processor->get_subscribers();
		$all_alerts = $this->processor->get_job_alerts();
		$published_alerts = $this->processor->get_published_job_alerts();
		$log_id = $this->processor->create_db_record( $auto, count($queue_items), count($all_alerts), $published_alerts );

		foreach ( $queue_items as $queue_item ) {
			$queue_item->log_id = $log_id;
			$queue_item->last_user_id = end( $queue_items )->user_id;
			$this->processor->push_to_queue( $queue_item );
		}
		
		$this->processor->save()->dispatch();
	}
}

new WPJobAlerts();