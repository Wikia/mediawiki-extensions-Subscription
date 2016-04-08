<?php
/**
 * Curse Inc.
 * Subscription
 * Update User Subscriptions
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

class UpdateUserSubscriptions extends \Maintenance {
	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();

		$this->mDescription = "";
	}

	/**
	 * Adjust localization files (CDB) on cache share upon recache.
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {

	}
}

$maintClass = "Hydra\Maintenance\UpdateUserSubscriptions";
require_once(RUN_MAINTENANCE_IF_MAIN);
