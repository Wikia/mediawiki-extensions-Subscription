<?php
/**
 * Import Gamepedia Pro Subscriptions
 *
 * @package   Subscription
 * @copyright (c) 2020 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
**/

namespace Hydra\Maintenance;

use HydraAuthUser;
use LoggedUpdateMaintenance;
use Wikimedia\Rdbms\IDatabase;

require_once dirname(dirname(dirname(__DIR__))) . '/maintenance/Maintenance.php';

/**
 * Maintenance script that cleans up tables that have orphaned users.
 */
class ImportProSubscriptions extends LoggedUpdateMaintenance {
	private $prefix;

	private $table;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription('Imports subscriptions from Gamepedia Pro temporary table subscription_import.');
		$this->setBatchSize(100);
	}

	/**
	 * Return an unique name to logged this maintenance as being done.
	 *
	 * @return string
	 */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/**
	 * Do database updates for all tables.
	 *
	 * @return boolean True
	 */
	protected function doDBUpdates() {
		$this->import();

		return true;
	}

	/**
	 * Import subscriptions.
	 *
	 * @return void
	 */
	protected function import() {
		$dbw = $this->getDB(DB_MASTER);

		$orderby = ['user_id'];

		if (!$dbw->tableExists('subscription_import')) {
			$this->output("Skipping due to `subscription_import` table not existing.\n");
			return;
		}

		$this->output("Importing `subscription_import` to `subscription_comp`...\n");

		$next = '1=1';
		$count = 0;
		while (true) {
			// Fetch the rows needing update
			$res = $dbw->select(
				'subscription_import',
				['user_id', 'expires'],
				array_merge(['expires > '.time()], [$next]),
				__METHOD__,
				[
					'ORDER BY' => $orderby,
					'LIMIT' => $this->mBatchSize,
				]
			);
			if (!$res->numRows()) {
				break;
			}

			// Update the existing rows
			foreach ($res as $row) {
				$userId = HydraAuthUser::userIdFromGlobalId($row->user_id);

				if ($userId > 0) {
					$result = $dbw->upsert(
						'subscription_comp',
						[
							'active' => 1,
							'expires' => $row->expires,
							'user_id' => $userId
						],
						['scid', 'user_id'],
						[
							'active' => 1,
							'expires' => $row->expires,
						],
						__METHOD__
					);
					$count += $dbw->affectedRows();
				}
			}

			list($next, $display) = $this->makeNextCond($dbw, $orderby, $row);
			$this->output("... $display\n");
			wfWaitForSlaves();
		}

		$this->output(
			"Import complete: Imported {$count} subscriptions.\n"
		);
	}

	/**
	 * Calculate a "next" condition and progress display string
	 *
	 * @param IDatabase $dbw
	 * @param string[]  $indexFields Fields in the index being ordered by
	 * @param object    $row         Database row
	 *
	 * @return string[] [ string $next, string $display ]
	 */
	private function makeNextCond($dbw, array $indexFields, $row) {
		$next = '';
		$display = [];
		for ($i = count($indexFields) - 1; $i >= 0; $i--) {
			$field = $indexFields[$i];
			$display[] = $field . '=' . $row->$field;
			$value = $dbw->addQuotes($row->$field);
			if ($next === '') {
				$next = "$field > $value";
			} else {
				$next = "$field > $value OR $field = $value AND ($next)";
			}
		}
		$display = implode(' ', array_reverse($display));
		return [$next, $display];
	}
}

$maintClass = \Hydra\Maintenance\ImportProSubscriptions::class;
require_once RUN_MAINTENANCE_IF_MAIN;
