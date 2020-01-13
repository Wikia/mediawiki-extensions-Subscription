<?php
/**
 * Curse Inc.
 * Subscription
 * Subscription Caching
 *
 * @author    Alexia E. Smith
 * @copyright (c) 2016 Curse Inc.
 * @license   GPL-2.0-or-later
 * @package   Subscription
 * @link      https://gitlab.com/hydrawiki
**/

namespace Hydra;

use ConfigFactory;
use MediaWiki\MediaWikiServices;
use RequestContext;
use User;

class SubscriptionCache {
	/**
	 * Last serach result total.
	 *
	 * @var integer
	 */
	static private $lastSearchResultTotal = 0;

	/**
	 * Update the local subscription cache for a global ID and provider ID.
	 *
	 * @param integer $userId       User ID
	 * @param string  $providerId   Provider ID - As defined in $wgSubscriptionProviders.
	 * @param array   $subscription Subscription data as returned by a subscription provider.
	 *
	 * @return boolean Success
	 */
	public static function updateLocalCache($userId, $providerId, $subscription) {
		$db = self::getDb();

		if ($userId < 1 || empty($providerId)) {
			return false;
		}

		if ($subscription === null) {
			$success = $db->delete(
				'subscription',
				[
					'user_id'		=> $userId,
					'provider_id'	=> $providerId
				],
				__METHOD__
			);
			return $success;
		}

		$save = [
			'user_id'			=> $userId,
			'provider_id'		=> $providerId,
			'active'			=> $subscription['active'],
			'begins'			=> ($subscription['begins'] !== false ? $subscription['begins']->getTimestamp(TS_MW) : null),
			'expires'			=> ($subscription['expires'] !== false ? $subscription['expires']->getTimestamp(TS_MW) : null),
			'plan_id'			=> $subscription['plan_id'],
			'plan_name'			=> $subscription['plan_name'],
			'price'				=> $subscription['price'],
			'subscription_id'	=> $subscription['subscription_id']
		];

		$result = $db->select(
			['subscription'],
			['*'],
			[
				'user_id'		=> $userId,
				'provider_id'	=> $providerId
			],
			__METHOD__
		);
		$exists = $result->fetchObject();

		$db->startAtomic(__METHOD__);
		$success = false;
		if (isset($exists->sid)) {
			$result = $db->update(
				'subscription',
				$save,
				['sid' => $exists->sid],
				__METHOD__
			);
		} else {
			$result = $db->insert(
				'subscription',
				$save,
				__METHOD__
			);
		}
		if (!$result) {
			$db->cancelAtomic(__METHOD__);
		} else {
			$success = true;
			$db->endAtomic(__METHOD__);
		}

		return $success;
	}

	/**
	 * Return a filtered search of subscriptions.
	 *
	 * @param integer     $start        Zero based start position.
	 * @param integer     $itemsPerPage Total number of results to return.
	 * @param string|null $searchTerm   [Optional] Search term to filter by.
	 * @param array       $filters      [Optional] Filters for where statement.
	 * @param string      $sortKey      [Optional] Database field name to sort by, defaults to 'sid'.
	 * @param string      $sortDir      [Optional] Database sort direction, defaults to 'ASC'.
	 *
	 * @return array An array of resulting objects, possibly empty.
	 */
	public static function filterSearch(int $start, int $itemsPerPage, ?string $searchTerm = null, array $filters = [], string $sortKey = 'sid', string $sortDir = 'ASC') {
		$db = self::getDb();

		$searchableFields = ['user_id', 'provider_id', 'active'];
		$tables = ['subscription'];

		if (!empty($searchTerm)) {
			$searchResults = $db->select(
				['user'],
				['*'],
				['CONVERT(user_name USING utf8) LIKE "%' . $db->strencode($searchTerm) . '%"'],
				__METHOD__
			);

			$userIds = [];
			while ($row = $searchResults->fetchObject()) {
				$user = User::newFromRow($row);
				if (!$user->getId()) {
					continue;
				}

				$userIds[] = $user->getId();
			}
			if (!empty($userIds)) {
				$and[] = "user_id IN(" . $db->makeList($userIds) . ")";
			} else {
				// This is a dumb helper to produce "No results" when no valid user was foudn.
				$and[] = "user_id = -2";
			}
		}

		if (isset($filters['date']['min_date'])) {
			$and[] = "begins >= " . $db->strencode(intval($filters['date']['min_date']));
		}
		if (isset($filters['date']['max_date'])) {
			$and[] = "expires <= " . $db->strencode(intval($filters['date']['max_date']));
		}

		if (isset($filters['price']['min_price'])) {
			$and[] = "price >= " . $db->strencode(intval($filters['price']['min_price']));
		}
		if (isset($filters['price']['max_price'])) {
			$and[] = "price <= " . $db->strencode(intval($filters['price']['max_price']));
		}

		if (isset($filters['providers']) && !empty($filters['providers'])) {
			$and[] = "provider_id IN(" . $db->makeList($filters['providers']) . ")";
		}
		if (isset($filters['plans']) && !empty($filters['plans'])) {
			$and[] = "plan_name IN(" . $db->makeList($filters['plans']) . ")";
		}

		if (count($and)) {
			if (!empty($where)) {
				$where .= ' AND (' . implode(' AND ', $and) . ')';
			} else {
				$where = implode(' AND ', $and);
			}
		}

		$options['ORDER BY'] = ($db->fieldExists('subscription', $sortKey) ? $sortKey : 'sid') . ' ' . ($sortDir == 'DESC' ? 'DESC' : 'ASC');
		if ($start !== null) {
			$options['OFFSET'] = $start;
		}
		if ($itemsPerPage !== null) {
			$options['LIMIT'] = $itemsPerPage;
		}

		$wikis = [];
		$results = $db->select(
			$tables,
			['*'],
			$where,
			__METHOD__,
			$options
		);

		if (!$results) {
			self::$lastSearchResultTotal = 0;
			return [];
		}
		while ($row = $results->fetchRow()) {
			$subscriptions[] = $row;
		}

		$resultsTotal = $db->select(
			$tables,
			['count(*) as total'],
			$where,
			__METHOD__,
			null
		);
		$resultsTotal = $resultsTotal->fetchRow();
		self::$lastSearchResultTotal = intval($resultsTotal['total']);

		return $subscriptions;
	}

	/**
	 * Return the last search result total.
	 *
	 * @return integer Total
	 */
	public static function getLastSearchTotal() {
		return intval(self::$lastSearchResultTotal);
	}

	/**
	 * Return values for use in search filters.
	 *
	 * @return array Filter Values
	 */
	public static function getSearchFilterValues() {
		// Filter types that need to be strictly clamped and checked.
		$_clamps = [
			'date'	=> ['min_date', 'max_date'],
			'price'	=> ['min_price', 'max_price']
		];

		$filters = [
			'providers'	=> [],
			'plans'	=> [],
			'date' => [
				'min_date'	=> false,
				'max_date'	=> false
			],
			'price'	=> [
				'min_price'	=> false,
				'max_price'	=> false
			]
		];

		$db = self::getDb();

		$result = $db->select(
			['subscription'],
			[
				'MIN(begins) AS min_date',
				'MAX(expires) AS max_date',
				'MIN(price) AS min_price',
				'MAX(price) AS max_price',
				'provider_id',
				'plan_name'
			],
			null,
			__METHOD__,
			['GROUP BY' => 'provider_id, plan_name']
		);

		while ($row = $result->fetchRow()) {
			foreach ($_clamps as $type => $_values) {
				foreach ($_values as $value) {
					if ($filters[$type][$value] === false) {
						$filters[$type][$value] = $row[$value];
					} else {
						if (strpos($value, 'min_') === 0) {
							$filters[$type][$value] = min($filters[$type][$value], $row[$value]);
						} else {
							$filters[$type][$value] = max($filters[$type][$value], $row[$value]);
						}
					}
				}
			}

			if (!empty($row['provider_id'])) {
				$filters['providers'][] = $row['provider_id'];
			}

			if (!empty($row['plan_name'])) {
				$filters['plans'][] = $row['plan_name'];
			}
		}
		$filters['providers'] = array_unique($filters['providers']);
		$filters['plans'] = array_unique($filters['plans']);

		$request = RequestContext::getMain()->getRequest();

		$userFilters = $request->getValues('list_search', 'providers', 'plans', 'min_date', 'max_date', 'min_price', 'max_price');

		if (!isset($userFilters['providers'])) {
			$userFilters['providers'] = [];
		}
		if (!isset($userFilters['plans'])) {
			$userFilters['plans'] = [];
		}

		foreach ($_clamps['date'] as $type) {
			if (isset($userFilters[$type])) {
				if (empty($userFilters[$type])) {
					unset($userFilters[$type]);
				} else {
					$userFilters[$type] = wfTimestamp(TS_MW, $userFilters[$type]);
				}
				if ($userFilters[$type] === false) {
					unset($userFilters[$type]);
				}
			}
		}

		foreach ($_clamps as $type => $_values) {
			foreach ($_values as $value) {
				if (!isset($userFilters[$value])) {
					$userFilters[$type][$value] = $filters[$type][$value];
				} else {
					// These are intentionally backwards so that users can not set out of bound values.
					if (strpos($value, 'min_') === 0) {
						$userFilters[$type][$value] = max($filters[$type][$value], $userFilters[$value]);
					} else {
						$userFilters[$type][$value] = min($filters[$type][$value], $userFilters[$value]);
					}
					unset($userFilters[$value]);
				}
			}
		}

		return [
			'default'	=> $filters,
			'user'		=> $userFilters
		];
	}

	/**
	 * Get basic subscription statistics.
	 *
	 * @return array Active subscriptions, total users, and percentage.
	 */
	public static function getStatistics() {
		$db = self::getDb();

		$result = $db->select(
			['subscription'],
			['count(*) as total'],
			['active' => 1],
			__METHOD__
		);
		$statistics['active'] = $result->fetchRow()['total'];

		$result = $db->select(
			['user'],
			['count(*) as total'],
			null,
			__METHOD__
		);
		$statistics['total'] = $result->fetchRow()['total'];

		$statistics['precentage'] = 0;
		if ($statistics['total'] > 0) {
			$statistics['precentage'] = number_format($statistics['active'] / $statistics['total'] * 100, 2);
		}

		return $statistics;
	}

	/**
	 * Get the database connection.
	 *
	 * @return object Database
	 */
	private static function getDb() {
		$config = ConfigFactory::getDefaultInstance()->makeConfig('main');
		$masterDb = $config->get('SubscriptionMasterDB');
		if ($masterDb !== false) {
			$db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getExternalLB($masterDb)->getConnection(DB_MASTER);
		} else {
			$db = wfGetDB(DB_MASTER);
		}

		return $db;
	}
}
