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
	 * Last serach result total.
	 *
	 * @var		integer
	 */
	static private $lastSearchResultTotal = 0;

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

	/**
	 * Return a filtered search of subscriptions.
	 *
	 * @access	public
	 * @param	integer	Zero based start position.
	 * @param	integer	Total number of results to return.
	 * @param	string	[Optional] Search term to filter by.
	 * @param	string	[Optional] Database field name to sort by, defaults to 'wiki_name'.
	 * @param	string	[Optional] Database sort direction, defaults to 'ASC'.
	 * @return	array	an array of resulting objects, possibly empty.
	 */
	static public function filterSearch($start, $itemsPerPage, $searchTerm = null, $sortKey = 'sid', $sortDir = 'ASC') {
		$db = wfGetDB(DB_MASTER);
		$searchableFields = ['global_id', 'provider_id', 'active'];
		$tables = ['subscription'];

		/*if (!empty($searchTerm)) {
			//Normal table search.
			foreach ($searchableFields as $field) {
				$where[] = "`".$field."` LIKE '%".$db->strencode($searchTerm)."%'";
			}
			$where = "(".implode(' OR ', $where).")";
		}

		$joins['wiki_advertisements'] = [
			'LEFT JOIN', 'wiki_sites.md5_key = wiki_advertisements.site_key'
		];
		$joins['wiki_domains'] = [
			'LEFT JOIN', 'wiki_domains.site_key = wiki_sites.md5_key'
		];
		$and[] = "wiki_sites.deleted = 0";
		$and[] = "wiki_domains.type = ".Domains::getDomainEnvironment();
		$and[] = "(wiki_advertisements.site_key != -1 OR wiki_advertisements.site_key IS NULL OR CHAR_LENGTH(wiki_advertisements.site_key) >= 32)";

		if (count($and)) {
			if (!empty($where)) {
				$where .= ' AND ('.implode(' AND ', $and).')';
			} else {
				$where = implode(' AND ', $and);
			}
		}

		$options['ORDER BY'] = ($db->fieldExists('wiki_advertisements', $sortKey) || $sortKey == 'domain' ? $sortKey : 'wiki_name').' '.($sortDir == 'DESC' ? 'DESC' : 'ASC');
		if ($start !== null) {
			$options['OFFSET'] = $start;
		}
		if ($itemsPerPage !== null) {
			$options['LIMIT'] = $itemsPerPage;
		}*/

		$wikis = [];
		$results = $db->select(
			$tables,
			['*'],
			$where,
			__METHOD__,
			$options,
			$joins
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
			null,
			$joins
		);
		$resultsTotal = $resultsTotal->fetchRow();
		self::$lastSearchResultTotal = intval($resultsTotal['total']);

		return $subscriptions;
	}

	/**
	 * Return the last search result total.
	 *
	 * @access	public
	 * @return	integer	Total
	 */
	static public function getLastSearchTotal() {
		return intval(self::$lastSearchResultTotal);
	}

	/**
	 * Return values for use in search filters.
	 *
	 * @access	public
	 * @return	array	Filter Values
	 */
	static public function getSearchFilterValues() {
		//Filter types that need to be strictly clamped and checked.
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

		$db = wfGetDB(DB_MASTER);

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
					if ($row[$value] !== null) {
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

			if (!empty($row['provider_id'])) {
				$filters['providers'][] = $row['provider_id'];
			}

			if (!empty($row['plan_name'])) {
				$filters['plans'][] = $row['plan_name'];
			}
		}
		$filters['providers'] = array_unique($filters['providers']);
		$filters['plans'] = array_unique($filters['plans']);

		$request = \RequestContext::getMain()->getRequest();

		$userFilters = $request->getValues('list_search', 'providers', 'plans', 'min_date', 'max_date', 'min_price', 'max_price');
		if (!isset($userFilters['providers'])) {
			$userFilters['providers'] = [];
		}
		if (!isset($userFilters['plans'])) {
			$userFilters['plans'] = [];
		}

		foreach ($_clamps as $type => $_values) {
			foreach ($_values as $value) {
				if (!isset($userFilters[$value])) {
					$userFilters[$type][$value] = $filters[$type][$value];
				} else {
					//These are intentionally backwards so that users can not set out of bound values.
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
}
