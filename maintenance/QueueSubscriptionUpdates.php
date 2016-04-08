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

	}
}

$maintClass = "Hydra\Maintenance\QueueSubscriptionUpdates";
require_once(RUN_MAINTENANCE_IF_MAIN);
