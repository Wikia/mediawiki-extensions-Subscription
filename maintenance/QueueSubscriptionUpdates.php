<?php
/**
 * Curse Inc.
 * Subscription
 * Queue Subscription Updates
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		All Rights Reserved
 * @package		Subscription
 * @link		http://www.curse.com/
 *
**/

namespace Hydra\Maintenance;
use Hydra\Job;
require_once(dirname(dirname(dirname(__DIR__))).'/maintenance/Maintenance.php');

class QueueSubscriptionUpdates extends \Maintenance {
	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();

		$this->mDescription = "Update subscriptions from providers for given list of global user IDs.";
	}

	/**
	 * Update subscriptions from providers for given list of global user IDs.
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		$result = $this->DB->select(
			['user'],
			['count(*) as total'],
			null,
			__METHOD__
		);
		$total = $result->fetchRow();

		for ($i = 0; $i <= $total['total']; $i += 1000) {
			$result = $this->DB->select(
				['user'],
				['*'],
				null,
				__METHOD__,
				[
					'OFFSET'	=> $i,
					'LIMIT'		=> 1000
				]
			);

			$globalIds = [];
			while ($row = $result->fetch()) {
				$user = User::newFromRow($row);

				$lookup = \CentralIdLookup::factory();
				$globalId = $lookup->centralIdFromLocalUser($user, \CentralIdLookup::AUDIENCE_RAW);
				if (!$globalId) {
					continue;
				}

				$globalIds[] = $globalId;
			}
			//$job = new UpdateUserSubscriptionsJob($globalIds);
			//JobQueueGroup::singleton()->push($job);
		}
	}
}

$maintClass = "Hydra\Maintenance\QueueSubscriptionUpdates";
require_once(RUN_MAINTENANCE_IF_MAIN);
