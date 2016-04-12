<?php
/**
 * Curse Inc.
 * Subscription
 * Update User Subscriptions Job
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		All Rights Reserved
 * @package		Subscription
 * @link		http://www.curse.com/
 *
**/

namespace Hydra\Job;

class UpdateUserSubscriptionsJob extends \Job {
	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @param	object	Title [Unused]
	 * @param	array	Parameters
	 * @return	void
	 */
	public function __construct(\Title $title, array $params) {
		parent::__construct('UpdateUserSubscriptionsJob', $title, $params);

		$this->removeDuplicates = true;
	}

	/**
	 * Run tasks for this job.
	 *
	 * @access	public
	 * @return	boolean	Success
	 */
	public function run() {
		$globalId = intval($this->params[0]);
		if ($globalId < 1) {
			return false;
		}

		$subscription = new \Hydra\Subscription($globalId);

		foreach ($subscription->getSubscription() as $providerId => $subscriptionData) {
			if ($subscriptionData === false) {
				//There was a service error.  Continue and check it later as the service may be down.
				continue;
			}

			$subscription->updateLocalCache($providerId, $subscriptionData);
		}

		return true;
	}
}
