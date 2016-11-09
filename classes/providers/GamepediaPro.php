<?php
/**
 * Subscription
 * Gamepedia Pro API and Access
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		All Rights Reserved
 * @package		Subscription
 * @link		http://www.curse.com/
 *
 */

namespace Hydra\Provider;

class GamepediaPro extends \Hydra\SubscriptionProvider {
	/**
	 * API configuration.
	 *
	 * @access	private
	 * @var		array
	 */
	static private $apiConfig = [];

	/**
	 * API Key
	 *
	 * @var		string
	 */
	static private $apiKey = '';

	/**
	 * Get if a specific global user ID has an entitlement.
	 * Just a basic true or false, nothing more.
	 *
	 * @access	public
	 * @param	integer	Global User ID
	 * @return	boolean	Has Subscription
	 */
	public function hasSubscription($globalId) {
		if ($globalId < 1) {
			return false;
		}

		$pieces = [
			'get-user-entitlement',
			$globalId
		];

		$data = self::callApi($pieces);

		if ($data !== false && isset($data['hasEntitlement'])) {
			return $data['hasEntitlement'];
		}

		return false;
	}

	/**
	 * Get the subscription information for a specific global user ID.
	 *
	 * @access	public
	 * @param	integer	Global User ID
	 * @return	mixed	Subscription information, null on missing subscription, false on API failure.
	 */
	public function getSubscription($globalId) {
		if ($globalId < 1) {
			return false;
		}

		$pieces = [
			'get-user-subscription',
			$globalId
		];

		$data = self::callApi($pieces);

		if ($data !== false && $data !== null) {
			if (isset($data['errorCode']) || isset($data['planId']) || isset($data['goodThru'])) {
				if (isset($data['goodThru'])) {
					$expires = new \MWTimestamp($data['goodThru']);
				} elseif (isset($data['paidThruDate'])) {
					$expires = new \MWTimestamp($data['paidThruDate']);
				} else {
					$expires = false;
				}

				$subscription = [
					'active'			=> (isset($data['status']) && $data['status'] == 1 ? true : false),
					'begins'			=> false,
					'expires'			=> $expires,
					'plan_id'			=> (isset($data['planId']) ? $data['planId'] : 'complimentary'),
					'plan_name'			=> (isset($data['planName']) ? $data['planName'] : 'Complimentary'),
					'price'				=> (isset($data['planPrice']) ? floatval($data['planPrice']) : 0.00),
					'subscription_id'	=> (isset($data['subscriptionId']) ? floatval($data['subscriptionId']) : '')
				];
				return $subscription;
			}
		} elseif ($data === null) {
			return null;
		}

		return false;
	}

	/**
	 * Create a comped subscription for a specific global user ID for so many months.
	 *
	 * @access	public
	 * @param	integer	Global User ID
	 * @param	integer	Number of months to compensate.
	 * @return	mixed	Response message such as below or false on API failure.
	 *		{"code":200,"message":"Comped subscription successfully created."}
	 *		{"code":500,"message":"Could not create comped subscription. Error message:..."}
	 *		{"errorCode":500,"errorMessage":"An unhandled exception occurred while processing the request."}
	 */
	public function createCompedSubscription($globalId, $months) {
		$globalId = intval($globalId);
		$months = intval($months);
		if ($globalId < 1 || $months < 1) {
			return false;
		}

		$pieces = [
			'create-comped-subscription',
			$globalId,
			$months
		];

		$data = self::callApi($pieces, false);

		if ($data !== false) {
			if (isset($data['code']) || isset($data['errorCode'])) {
				return $data;
			}
		}

		return false;
	}

	/**
	 * Cancel the entirety of a global user ID's comped subscription.
	 *
	 * @access	public
	 * @param	integer	Global User ID
	 * @return	mixed	Response message such as below or false on API failure.
	 *		{"code":200,"message":"Comped subscription successfully created."}
	 *		{"code":500,"message":"Could not create comped subscription. Error message:..."}
	 *		{"errorCode":500,"errorMessage":"An unhandled exception occurred while processing the request."}
	 */
	public function cancelCompedSubscription($globalId) {
		$globalId = intval($globalId);
		if ($globalId < 1) {
			return false;
		}

		$pieces = [
			'cancel-comped-subscription',
			$globalId
		];

		$data = self::callApi($pieces, false);

		if ($data !== false) {
			if (isset($data['code']) || isset($data['errorCode'])) {
				return $data;
			}
		}

		return false;
	}

	/**
	 * Make API call.
	 *
	 * @access	private
	 * @param	array	URL pieces between slashes.  Example ['get-user-subscription', 9001] would become 'https://www.exmaple.com/get-user-subscription/9001'.
	 * @return	mixed	JSON data on success, null on 404, false on a fatal error.
	 */
	private function callApi($pieces) {
		if (!\Hydra\Subscription::skipCache()) {
			$wgCache = wfGetCache(CACHE_ANYTHING);

			array_unshift($pieces, 'GamepediaPro');

			$cached = $wgCache->get(call_user_func_array('wfGlobalCacheKey', $pieces).':v1');
			if (!empty($cached)) {
				return $cached;
			}
			if (\Hydra\Subscription::useLocalCacheOnly()) {
				//Do not call out to the API if local cache only is set.
				return false;
			}
		}

		$config = \ConfigFactory::getDefaultInstance()->makeConfig('main');
		$apiConfig = $config->get('GProApiConfig');

		$endPoint = wfExpandUrl($apiConfig['endpoint'], ($apiConfig['https'] ? PROTO_HTTPS : PROTO_CURRENT));

		$url = $endPoint.implode('/', $pieces);

		$options = [
			'method'			=> 'POST',
			'postData'			=> '=',
			'timeout'			=> 'default',
			'connectTimeout'	=> 'default'
		];
		if ($apiConfig['ssl_verify'] === false) {
			$options['sslVerifyHost'] = false;
			$options['sslVerifyCert'] = false;
		}

		$request = \MWHttpRequest::factory($url, $options, __METHOD__);
		$request->setHeader('x-api-key', $apiConfig['api_key']);
		$status = $request->execute();

		if ($status->isOK()) {
			$data = @json_decode($request->getContent(), true);

			self::cacheApiResponse($pieces, $data);

			return $data;
		} elseif (!$status->isOK() && $request->getStatus() == 404) {
			return null;
		}

		return false;
	}

	/**
	 * Cache API Response into memory.
	 *
	 * @access	private
	 * @param	array	URL pieces between slashes as originally given to self::callApi().
	 * @return	boolean	Success
	 */
	private function cacheApiResponse($pieces, $response) {
		$wgCache = wfGetCache(CACHE_ANYTHING);

		array_unshift($pieces, 'GamepediaPro');

		return $wgCache->set(call_user_func_array('wfGlobalCacheKey', $pieces).':v1', $response, $this->getCacheDuration());
	}

	/**
	 * Return a valid CSS class for flair display.
	 *
	 * @access	public
	 * @return	mixed	False for no flair, string otherwise.
	 */
	public function getFlairClass() {
		return 'gamepedia_pro_user';
	}
}
