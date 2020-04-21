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
	 * Return a filtered search of subscriptions.
	 *
	 * @param integer     $start        Zero based start position.
	 * @param integer     $itemsPerPage Total number of results to return.
	 * @param string|null $searchTerm   [Optional] Search term to filter by.
	 * @param array       $filters      [Optional] Filters for where statement.
	 * @param string      $sortKey      [Optional] Database field name to sort by, defaults to 'scid'.
	 * @param string      $sortDir      [Optional] Database sort direction, defaults to 'ASC'.
	 *
	 * @return array An array of resulting objects, possibly empty.
	 */
	public static function filterSearch(int $start, int $itemsPerPage, ?string $searchTerm = null, array $filters = [], string $sortKey = 'scid', string $sortDir = 'ASC') {
		$db = self::getDb();

		$searchableFields = ['user_id', 'active'];
		$tables = ['subscription_comp'];

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
				// This is a dumb helper to produce "No results" when no valid user was found.
				$and[] = "user_id = -2";
			}
		}

		if (isset($filters['date']['max_date'])) {
			$and[] = "expires <= " . $db->strencode(intval($filters['date']['max_date']));
		}

		if (count($and)) {
			if (!empty($where)) {
				$where .= ' AND (' . implode(' AND ', $and) . ')';
			} else {
				$where = implode(' AND ', $and);
			}
		}

		$options['ORDER BY'] = ($db->fieldExists('subscription_comp', $sortKey) ? $sortKey : 'scid') . ' ' . ($sortDir == 'DESC' ? 'DESC' : 'ASC');
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

		$subscriptions = [];
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
			'date'	=> ['max_date']
		];

		$filters = [
			'date' => [
				'max_date'	=> false
			]
		];

		$db = self::getDb();

		$result = $db->select(
			['subscription_comp'],
			[
				'MAX(expires) AS max_date'
			],
			null,
			__METHOD__
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
		}

		$request = RequestContext::getMain()->getRequest();

		$userFilters = $request->getValues('list_search', 'max_date');

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
			['subscription_comp'],
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
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$masterDb = $config->get('SubscriptionMasterDB');
		if ($masterDb !== false) {
			$db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getExternalLB($masterDb)->getConnection(DB_MASTER);
		} else {
			$db = wfGetDB(DB_MASTER);
		}

		return $db;
	}
}
