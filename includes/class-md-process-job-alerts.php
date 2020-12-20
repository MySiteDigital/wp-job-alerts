<?php

namespace MySiteDigital;

use MySiteDigital\WPSiteLogs\Logger;

class ProcessJobAlerts extends \WP_Background_Process {

	use \WP_Example_Logger_test;

	/**
	 * @var string
	 */
	protected $action = 'job_alert';

	/**
	 * @var integer
	 */
	protected $post_id = 0;

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	protected function task( $user ) {
		$user_id = $user->user_id;
		//user id, criteria matching, jobs sent, time sent, email successful?, last alert sent
		$users_criteria = \get_user_meta( $user_id, 'jr_alert', true );
		$users_keyword = \get_user_meta( $user_id, 'jr_alert_keywords', true );
		$users_alerts = '';

		$alerts = $this->get_job_alerts( $user_id );
		foreach ($alerts as $alert) {
			$users_alerts .=  $alert->post_id . ' - ' . $alert->alert_type . '<br>'; 
		}
		

		$message = '<dl>
						<dt><strong>User ID</strong></dt>
						<dd>'. $user_id . '</dd>
						<dt><strong>Users Criteria</strong></dt>
						<dd>' . $users_criteria . '</dd>
						<dt><strong>Users Keywords</strong></dt>
						<dd>' . ($users_keyword ? $users_keyword : '-') . '</dd>
						<dt><strong>Matching Jobs</strong></dt>
						<dd>' . $users_alerts . '</dd>
					</dl>';

		Logger::log(
			$this->post_id,
			'Job Alerts',
			$message
		);

		sleep( 5 );

		return false;
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();

		// Show notice to user or perform some other arbitrary task...
	}

	public function create_db_record(){
		$this->id = \wp_insert_post(
			[
				'post_title'    => 'Daily Job Alerts - ' . \current_time( \get_option('date_format') ),
				'post_type'  => 'job_alerts_processor',
				'post_status'   => 'private',
			]
		);
	}

	/**
	 * @return array
	 */
	public function get_subscribers()
	{
		global $wpdb;
		$sql = "SELECT user_id 
				FROM $wpdb->usermeta
				WHERE meta_key = 'jr_alert_status'
				AND meta_value = 'active' 
				ORDER BY user_id ASC LIMIT 5";

		$subscribers = $wpdb->get_results($sql);
		return $subscribers;
	}

	/**
	 * @return array
	 */
	public function get_job_alerts( $user_id )
	{
		global $wpdb;
		$sql = "SELECT * 
				FROM $wpdb->jr_alerts alerts, $wpdb->posts posts
				WHERE post_id = id 
				AND post_status = 'publish' 
				AND last_user_id < $user_id 
				ORDER BY last_activity ASC";

		$alerts = $wpdb->get_results($sql);
		return $alerts;
	}

}