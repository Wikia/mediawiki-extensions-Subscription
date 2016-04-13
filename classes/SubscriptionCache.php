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
			if ($row['min_date'] !== null) {
				if ($filters['date']['min_date'] === false) {
					$filters['date']['min_date'] = $row['min_date'];
				} else {
					$filters['date']['min_date'] = min($filters['date']['min_date'], $row['min_date']);
				}
			}
			if ($row['max_date'] !== null) {
				if ($filters['date']['max_date'] === false) {
					$filters['date']['max_date'] = $row['max_date'];
				} else {
					$filters['date']['max_date'] = max($filters['date']['max_date'], $row['max_date']);
				}
			}

			if ($filters['price']['min_price'] === false) {
				$filters['price']['min_price'] = $row['min_price'];
			} else {
				$filters['price']['min_price'] = min($filters['price']['min_price'], $row['min_price']);
			}
			if ($filters['price']['max_price'] === false) {
				$filters['price']['max_price'] = $row['max_price'];
			} else {
				$filters['price']['max_price'] = max($filters['price']['max_price'], $row['max_price']);
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

		return $filters;
	}
}
