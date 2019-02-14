<?php
/**
 * Curse Inc.
 * Subscription
 * Queue Subscription Updates
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		GNU General Public License v2.0 or later
 * @package		Subscription
 * @link		https://gitlab.com/hydrawiki
 *
**/

namespace Hydra\Maintenance;
require_once(dirname(__DIR__, 3).'/maintenance/Maintenance.php');

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
		$config = \ConfigFactory::getDefaultInstance()->makeConfig('main');
		$masterDb = $config->get('SubscriptionMasterDB');
		if ($masterDb !== false) {
			$db = \MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getExternalLB($masterDb)->getConnection(DB_MASTER);
		} else {
			$db = wfGetDB(DB_MASTER);
		}

		$result = $db->select(
			['user'],
			['count(*) as total'],
			null,
			__METHOD__
		);
		$total = $result->fetchRow();

		for ($i = 0; $i <= $total['total']; $i += 1000) {
			$result = $db->select(
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
			while ($row = $result->fetchObject()) {
				$user = \User::newFromRow($row);

				$lookup = \CentralIdLookup::factory();
				$globalId = $lookup->centralIdFromLocalUser($user, \CentralIdLookup::AUDIENCE_RAW);
				if (!$globalId) {
					continue;
				}

				$title = \Title::newFromText($user->getTitleKey());

				$job = new \Hydra\Job\UpdateUserSubscriptionsJob($title, [$globalId]);
				\JobQueueGroup::singleton()->push($job);
			}
		}
	}
}

$maintClass = "Hydra\Maintenance\QueueSubscriptionUpdates";
require_once(RUN_MAINTENANCE_IF_MAIN);
