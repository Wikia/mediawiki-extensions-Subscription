<?php
/**
 * Migrate Gamepedia Pro Subscriptions
 *
 * @package   Subscription
 * @copyright (c) 2020 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace Hydra\Maintenance;

use LoggedUpdateMaintenance;
use MediaWiki\MediaWikiServices;

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

/**
 * Maintenance script that cleans up tables that have orphaned users.
 */
class MigrateProSubscriptions extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Imports subscriptions from tables to user preferences.' );
		$this->setBatchSize( 100 );
	}

	/** @inheritDoc */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/** @inheritDoc */
	protected function doDBUpdates() {
		return $this->import();
	}

	protected function import(): bool {
		$services = MediaWikiServices::getInstance();
		$userIdentityLookup = $services->getUserIdentityLookup();
		$optionsManager = $services->getUserOptionsManager();
		$lbFactory = $services->getDBLoadBalancerFactory();
		$dbw = $this->getDB( DB_PRIMARY );

		if ( !$dbw->tableExists( 'subscription_comp' ) ) {
			$this->output( "Skipping due to `subscription_comp` table not existing.\n" );
			return true;
		}

		$this->output( "Migrating `subscription_comp` to user preferences...\n" );

		$next = '1=1';
		$count = 0;
		while ( true ) {
			// Fetch the rows needing update
			$res = $dbw->select(
				'subscription_comp',
				[ 'user_id', 'expires',  '*' ],
				array_merge( [ 'expires > ' . time() ], [ $next ] ),
				__METHOD__,
				[
					'ORDER BY' => 'user_id',
					'LIMIT' => $this->getBatchSize(),
				]
			);
			if ( !$res->numRows() ) {
				$this->output( "Migration complete: Migrated $count subscriptions.\n" );
				return true;
			}

			// Update the existing rows
			foreach ( $res as $row ) {
				$userIdentity = $userIdentityLookup->getUserIdentityByUserId( (int)$row->user_id );
				if ( !$userIdentity || !$userIdentity->isRegistered() ) {
					return false;
				}
				$this->output( "User ID {$row->user_id} expires {$row->expires}\n" );
				$optionsManager->setOption( $userIdentity, 'gpro_expires', $row->expires );
				$optionsManager->saveOptions( $userIdentity );
				$count++;
			}

			$next = 'user_id > ' . $dbw->addQuotes( $row->user_id );
			$display = "user_id={$row->user_id}";
			$this->output( "... $display\n" );
			$lbFactory->waitForReplication();
		}
	}
}

$maintClass = MigrateProSubscriptions::class;
require_once RUN_MAINTENANCE_IF_MAIN;
