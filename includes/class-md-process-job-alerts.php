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
	protected function task( $queue_item ) {
		global $wpdb, $jr_options;
		$user_id = $queue_item->user_id;
		$last_user_id = $queue_item->last_user_id;
		$log_id = $queue_item->log_id;
		$alerts = $queue_item->potential_alerts;
		$current_count = \get_post_meta($log_id, 'email_count', true);
		$current_count = $current_count ? $current_count : 0;

		$users_keywords = get_user_meta($user_id, 'jr_alert_meta_keyword', true);
		$users_locations = get_user_meta($user_id, 'jr_alert_meta_location', true);
		$users_job_types = get_user_meta($user_id, 'jr_alert_meta_job_type', true);
		$users_job_cats = get_user_meta($user_id, 'jr_alert_meta_job_cat', true);
		$matching_jobs = [];
		$jobs_to_send = [];

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
				//turn off until ready to go live
				// $wpdb->query(
				// 	$wpdb->prepare(
				// 		"UPDATE $wpdb->jr_alerts
				// 		SET last_activity = CURRENT_TIMESTAMP, last_user_id = %d
				// 		WHERE post_id = %d", 
				// 		$user_id,
				// 		$alert->post_id
				// 	)
				// );
			}
		}

		$email_sent = false;
		if( count( $matching_jobs ) ){
			$jobs_to_send = array_slice( array_reverse($matching_jobs), 0, $jr_options->jr_job_alerts_jobs_limit, true );
			//all alerts will be sent to the test seeker account until ready to go live
			//$email_sent =  jr_job_alerts_send_email( $user_id, $jobs_to_send );
			$email_sent =  jr_job_alerts_send_email( 6553, $jobs_to_send );
			if( $email_sent ){
				update_post_meta( $log_id, 'email_count', $current_count + 1 );
			}
		}

		$message = '<hr>
					<dl>
						<dt><strong>User ID</strong></dt>
						<dd><a href="' . get_edit_user_link( $user_id ) . '" target="_blank">'. $user_id  . '</a></dd>
						<dt><strong>User Email</strong></dt>
						<dd>'. $queue_item->user_id . '</dd>
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
						<dt><strong>Jobs Included in Email</strong></dt>
						<dd>' . print_r( $jobs_to_send, true ) . '</dd>
						<dt><strong>Email Sent</strong></dt>
						<dd>' . ($email_sent ? 'Yes' : 'No') . '</dd>
					</dl>
					<hr>';

		$log = get_post( $log_id );

		$log = [
			'ID'           => $log_id,
			'post_content' => $log->post_content . $message
		];

		wp_update_post($log);

		if( $user_id === $last_user_id ){
			$log = get_post( $log_id );
			$summary = \get_post_meta( $log_id, 'log_summary', true) .
			'<table class="form-table">
				<tbody>
					<tr>
						<th><label>Processing Completed at:</label></th>
						<td><p class="description">' . $log->post_modified . '</p>
					</tr>
					<tr>
						<th><label>Total Emails Sent:</label></th>
						<td><p class="description">' . $current_count . '</p>
					</tr>
				</tbody>
			</table>';
			\update_post_meta( $log_id, 'log_summary', $summary );
		}

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

	public function create_db_record( $auto, $user_count, $total_alert_count, $published_alerts ){
		$titlePrefix = $auto ? 'Automatic' : 'Manual';
		$log_id = wp_insert_post(
			[
				'post_title'    => 'Daily Job Alerts (' . $titlePrefix . ') - ' . \current_time( \get_option( 'date_format' ) ),
				'post_type'  => 'job-alerts-log',
				'post_status'   => 'private',
			]
		);

		$log = get_post( $log_id );

		$published_alerts_data = '';
		if( count( $published_alerts ) ){
			foreach ($published_alerts as $published_alert) {
				$alert_data = '
					<dl>
						<dt>Job ID</dt>
						<dd>' . $published_alert->post_id . '</dd>
						<dt>Job Title</dt>
						<dd>' . $published_alert->post_title . '</dd>
						<dt>Alert Type</dt>
						<dd>' . $published_alert->alert_type . '</dd>
					</dl><hr>
				';
				$published_alerts_data .= $alert_data;
			}
		}
		

		$summary =
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
						<td><p class="description">' . count( $published_alerts ) . '</p>
					</tr>
					<tr>
						<th><label>Published Alerts Data</label></th>
						<td><p class="description">' . $published_alerts_data . '</p>
					</tr>
					<tr>
						<th><label>Processing Began at:</label></th>
						<td><p class="description">' . $log->post_date . '</p>
					</tr>
				</tbody>
			</table>
			<hr>';

		\update_post_meta( $log_id, 'log_summary', $summary );
		return $log_id;
	}

	/**
	 * @return array
	 */
	public function get_subscribers()
	{
		global $wpdb;
		$sql = "SELECT user_id, user_email 
				FROM $wpdb->usermeta
				INNER JOIN $wpdb->users
				ON $wpdb->usermeta.user_id = $wpdb->users.ID
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