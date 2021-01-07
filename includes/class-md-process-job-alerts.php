<?php

namespace MySiteDigital;

class ProcessJobAlerts extends \WP_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'job_alert';

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
		global $wpdb, $jr_options;
		$user_id = $user->user_id;
		$alerts = $user->potential_alerts;

		$users_keywords = get_user_meta($user_id, 'jr_alert_meta_keyword', true);
		$users_locations = get_user_meta($user_id, 'jr_alert_meta_location', true);
		$users_job_types = get_user_meta($user_id, 'jr_alert_meta_job_type', true);
		$users_job_cats = get_user_meta($user_id, 'jr_alert_meta_job_cat', true);
		$matching_jobs = [];

		foreach ($alerts as $alert) {
			$post_title = strtolower($alert->post_title);
			$post_content = strtolower($alert->post_content);
			$alert_type = strtolower($alert->alert_type);
			$alert_matches = true;

			if ($users_keywords) {
				foreach ($users_keywords as $users_keyword) {
					$users_keyword = trim($users_keyword);
					if ($users_keyword) {
						$alert_matches = strpos($post_title, $users_keyword);
						if ($alert_matches !== false) {
							$alert_matches = true;
							break;
						}
						$alert_matches = strpos($post_content, $users_keyword);
						if ($alert_matches !== false) {
							$alert_matches = true;
							break;
						}
					}
				}
			}

			if ($users_locations && $alert_matches) {
				foreach ($users_locations as $users_location) {
					$users_location = trim($users_location);
					if ($users_location) {
						$alert_matches = strpos($alert_type, $users_location);
						if ($alert_matches !== false) {
							$alert_matches = true;
							break;
						}
					}
				}
			}

			if ($users_job_types && $alert_matches) {
				foreach ($users_job_types as $users_job_type) {
					$string = 'job_type=' . $users_job_type;
					$alert_matches = strpos($alert_type, $string);
					if ($alert_matches !== false) {
						break;
					}
				}
			}

			if ($users_job_cats && $alert_matches) {
				foreach ($users_job_cats as $users_job_cat) {
					$string = 'job_cat=' . $users_job_cat;
					$alert_matches = strpos($alert_type, $string);
					if ($alert_matches !== false) {
						break;
					}
				}
			}

			if ($alert_matches) {
				$matching_jobs[] = $alert->post_id;
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE $wpdb->jr_alerts
						SET last_activity = CURRENT_TIMESTAMP, last_user_id = %d
						WHERE post_id = %d", 
						$user_id,
						$alert->post_id
					)
				);
			}
		}

		$email_sent = false;
		if( count( $matching_jobs ) ){
			$email_sent =  jr_job_alerts_send_email($user_id, $matching_jobs);
		}

		$message = '<hr>
					<dl>
						<dt><strong>User ID</strong></dt>
						<dd>'. $user_id . '</dd>
						<dt><strong>Users Keywords</strong></dt>
						<dd>' . ($users_keywords ? print_r( $users_keywords, true ) : 'None') . '</dd>
						<dt><strong>Users Locations</strong></dt>
						<dd>' . ($users_locations ? print_r( $users_locations, true ) : 'All') . '</dd>
						<dt><strong>Users Job Types</strong></dt>
						<dd>' . ($users_job_types ? print_r( $users_job_types, true ) : 'All') . '</dd>
						<dt><strong>Users Categories</strong></dt>
						<dd>' . ($users_job_cats ? print_r( $users_job_cats, true ) : 'All') . '</dd>
						<dt><strong>Users Alerts</strong></dt>
						<dd>' . print_r( $matching_jobs, true ) . '</dd>
						<dt><strong>Email Sent</strong></dt>
						<dd>' . ($email_sent ? 'Yes' : 'No') . '</dd>
					</dl>
					<hr>';

		$log = get_post($user->log_id);
		$current_content = $log->post_content;

		$log = array(
			'ID'           => $user->log_id,
			'post_content' => $current_content . $message
		);


		wp_update_post($log);


		error_log($user_id . '--------------------3sfdfsdfsdfsdf' . print_r($user->log_id, true));

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

		// delete all job alerts
	}

	public function create_db_record($user_count, $total_alert_count, $published_alert_count){
		$log_id = wp_insert_post(
			[
				'post_title'    => 'Daily Job Alerts - ' . \current_time(\get_option('date_format')),
				'post_type'  => 'job-alerts-log',
				'post_status'   => 'private',
			]
		);
		$log = get_post($log_id);
		$intial_content =
			'<table class="form-table">
				<tbody>
					<tr>
						<th><label>Total Subscribers</label></th>
						<td><p class="description">' . $user_count . '</p>
					</tr>
					<tr>
						<th><label>Alerts in DB</label></th>
						<td><p class="description">' . $total_alert_count . '</p>
					</tr>
					<tr>
						<th><label>Alerts in DB for Published Jobs</label></th>
						<td><p class="description">' . $published_alert_count . '</p>
					</tr>
					<tr>
						<th><label>Processing begun at:</label></th>
						<td><p class="description">' . $log->post_date . '</p>
					</tr>
				</tbody>
			</table>';
		
		$log = array(
			'ID'           => $log_id,
			'post_content' => $intial_content
		);

		wp_update_post($log);
		return $log_id;
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
				ORDER BY user_id ASC";

		$subscribers = $wpdb->get_results($sql);
		return $subscribers;
	}

	/**
	 * @return array
	 */
	public function get_published_job_alerts()
	{
		global $wpdb;
		$sql = " SELECT * 
				FROM $wpdb->jr_alerts alerts, $wpdb->posts posts
				WHERE post_id = id 
				AND post_status = 'publish' 
				ORDER BY last_activity ASC";

		$alerts = $wpdb->get_results($sql);

		return $alerts;
	}

	/**
	 * @return array
	 */
	public function get_job_alerts()
	{
		global $wpdb;
		$sql = " SELECT * 
				FROM $wpdb->jr_alerts alerts, $wpdb->posts posts
				WHERE post_id = id 
				ORDER BY last_activity ASC";

		$alerts = $wpdb->get_results($sql);

		return $alerts;
	}

}