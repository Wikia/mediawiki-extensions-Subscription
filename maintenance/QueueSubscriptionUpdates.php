<?php
/**
 * Curse Inc.
 * Subscription
 * Queue Subscription Updates
 *
 * @author    Alexia E. Smith
 * @copyright (c) 2016 Curse Inc.
 * @license   GPL-2.0-or-later
 * @package   Subscription
 * @link      https://gitlab.com/hydrawiki
**/

require_once dirname(__DIR__, 3) . '/maintenance/Maintenance.php';

namespace Hydra\Maintenance;

use ConfigFactory;
use JobQueueGroup;
use Maintenance;
use MediaWiki\MediaWikiServices;
use Title;
use User;

class QueueSubscriptionUpdates extends Maintenance {
	/**
	 * Main Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();

		$this->mDescription = "Update subscriptions from providers for given list of user IDs.";
	}

	/**
	 * Update subscriptions from providers for given list of global user IDs.
	 *
	 * @return void
	 */
	public function execute() {
		$config = ConfigFactory::getDefaultInstance()->makeConfig('main');
		$masterDb = $config->get('SubscriptionMasterDB');
		if ($masterDb !== false) {
			$db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getExternalLB($masterDb)->getConnection(DB_MASTER);
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

			$userIds = [];
			while ($row = $result->fetchObject()) {
				$user = User::newFromRow($row);
				if (!$user->getId()) {
					continue;
				}

				$title = Title::newFromText($user->getTitleKey());

				$job = new \Hydra\Job\UpdateUserSubscriptionsJob($title, [$user->getId()]);
				JobQueueGroup::singleton()->push($job);
			}
		}
	}
}

$maintClass = "Hydra\Maintenance\QueueSubscriptionUpdates";
require_once RUN_MAINTENANCE_IF_MAIN;
