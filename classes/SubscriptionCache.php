<?php
/**
 * Curse Inc.
 * Subscription
 * Subscription Caching
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		All Rights Reserved
 * @package		Subscription
 * @link		http://www.curse.com/
 *
**/

namespace Hydra;

class SubscriptionCache {
	/**
	 * Update the local subscription cache for a global ID and provider ID.
	 *
	 * @access	public
	 * @param	integer	Global User ID
	 * @param	string	Provider ID - As defined in $wgSubscriptionProviders.
	 * @param	array	Subscription data as returned by a subscription provider.
	 * @return	boolean	Success
	 */
	static public function updateLocalCache($globalId, $providerId, $subscription) {
		$db = wfGetDB(DB_MASTER);

		if ($globalId < 1 || empty($providerId)) {
			return false;
		}

		$save = [
			'global_id'			=> $globalId,
			'provider_id'		=> $providerId,
			'active'			=> $subscription['active'],
			'begins'			=> ($subscription['begins'] !== false ? $subscription['begins']->getTimestamp(TS_MW) : '00000000000000'),
			'expires'			=> ($subscription['expires'] !== false ? $subscription['expires']->getTimestamp(TS_MW) : '00000000000000'),
			'plan_id'			=> $subscription['plan_id'],
			'plan_name'			=> $subscription['plan_name'],
			'price'				=> $subscription['price'],
			'subscription_id'	=> $subscription['subscription_id']
		];

		$result = $db->select(
			['subscription'],
			['*'],
			[
				'global_id'		=> $globalId,
				'provider_id'	=> $providerId
			],
			__METHOD__
		);
		$exists = $result->fetchObject();

		$db->begin();
		if (isset($exists->sid)) {
			$db->update(
				'subscription',
				$save,
				['sid' => $exists->sid],
				__METHOD__
			);
		} else {
			$db->insert(
				'subscription',
				$save,
				__METHOD__
			);
		}
		$db->commit();

		return true;
	}
}
