<?php
/**
 * Subscription
 * Gamepedia Pro API and Access
 *
 * @author    Alexia E. Smith
 * @copyright (c) 2016 Curse Inc.
 * @license   GPL-2.0-or-later
 * @package   Subscription
 * @link      https://gitlab.com/hydrawiki
 */

namespace Hydra\SubscriptionProvider;

use Hydra\Subscription;
use Hydra\SubscriptionProvider;
use MWTimestamp;

class GamepediaPro extends SubscriptionProvider {
	/**
	 * Get if a specific global user ID has an entitlement.
	 * Just a basic true or false, nothing more.
	 *
	 * @param integer $userId User ID
	 *
	 * @return boolean Has Subscription
	 */
	public function hasSubscription(int $userId) {
		if ($userId < 1) {
			return false;
		}

		$data = $this->getSubscription($userId);

		if ($data !== false && isset($data['active'])) {
			return $data['active'];
		}

		return false;
	}

	/**
	 * Get the subscription information for a specific global user ID.
	 *
	 * @param integer $userId User ID
	 *
	 * @return array|boolean Subscription information, null on missing subscription, false on failure.
	 */
	public function getSubscription(int $userId) {
		if ($userId < 1) {
			return false;
		}

		$db = Subscription::getSharedDb(DB_REPLICA);
		$result = $db->select(
			['subscription_comp'],
			['*'],
			[
				'user_id'		=> $userId
			],
			__METHOD__
		);
		$data = $result->fetchRow();

		if (!empty($data)) {
			$subscription = [
				'active'			=> boolval($data),
				'begins'			=> false,
				'expires'			=> new MWTimestamp($data['expires']),
				'plan_id'			=> 'complimentary',
				'plan_name'			=> 'Complimentary',
				'price'				=> 0.00,
				'subscription_id'	=> 'comped_' . $userId
			];
			return $subscription;
		}

		return false;
	}

	/**
	 * Create a comped subscription for a specific global user ID for so many months.
	 *
	 * @param integer $userId User ID
	 * @param integer $months Number of months to compensate.
	 *
	 * @return boolean Success
	 */
	public function createCompedSubscription(int $userId, int $months) {
		if ($userId < 1 || $months < 1) {
			return false;
		}

		$db = Subscription::getSharedDb(DB_MASTER);
		$result = $db->upsert(
			'subscription_comp',
			[
				'active' => 1,
				'expires' => strtotime('+' . $months . ' months'),
				'user_id' => $userId
			],
			['scid', 'user_id'],
			[
				'active' => 1,
				'expires' => strtotime('+' . $months . ' months')
			],
			__METHOD__
		);

		return $result;
	}

	/**
	 * Cancel the entirety of a global user ID's comped subscription.
	 *
	 * @param integer $userId User ID
	 *
	 * @return boolean Success
	 */
	public function cancelCompedSubscription(int $userId) {
		if ($userId < 1) {
			return false;
		}

		$db = Subscription::getSharedDb(DB_MASTER);
		$result = $db->update(
			'subscription',
			[
				'active' => 0
			],
			['user_id' => $userId],
			__METHOD__
		);

		return $result;
	}

	/**
	 * Return a valid CSS class for flair display.
	 *
	 * @return mixed False for no flair, string otherwise.
	 */
	public function getFlairClass() {
		return 'gamepedia_pro_user';
	}
}
