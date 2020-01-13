<?php
/**
 * Curse Inc.
 * Subscription
 * Paid subscription system for Hydra Wiki Platform.
 *
 * @author Alexia E. Smith
 * @copyright (c) 2016 Curse Inc.
 * @license GNU General Public License v2.0 or later
 * @package Subscription
 * @link https://gitlab.com/hydrawiki
**/

namespace Hydra;

use Exception;
use User;

class Subscription {
	/**
	 * User ID
	 *
	 * @var integer
	 */
	private $userId;

	/**
	 * Main Config Factory
	 *
	 * @var object
	 */
	private $config;

	/**
	 * Skip using the cache
	 *
	 * @var boolean
	 */
	static private $skipCache = false;

	/**
	 * Use Local Cache Only
	 *
	 * @var boolean
	 */
	static private $useLocalCacheOnly = false;

	/**
	 * Main Constructor
	 *
	 * @param integer $userId User ID
	 *
	 * @return void
	 */
	public function __construct(int $userId) {
		$this->userId = intval($userId);

		$this->config = \ConfigFactory::getDefaultInstance()->makeConfig('main');
	}

	/**
	 * Return an initialized instance from a given User object.
	 *
	 * @param User $user User
	 * @return void
	 */
	static public function newFromUser(User $user) {
		if (!$user->getId()) {
			return false;
		}

		return new self($user->getId());
	}

	/**
	 * Does this user have a valid active subscription?
	 *
	 * @param string|null $providerId [Optional] Provider ID, a key in 'SubscriptionProviders'.
	 *                           If a provider ID is not supplied it will loop through all the known providers short circuiting when it finds a valid subscription.
	 *
	 * @return boolean
	 */
	public function hasSubscription(?string $providerId = null) {
		foreach ($this->getSubscriptionProviders() as $subscription) {
			if ($subscription !== null && $subscription->hasSubscription($this->userId)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Does this user have a valid active subscription?
	 *
	 * @param string|null $providerId [Optional] Provider ID, a key in 'SubscriptionProviders'.
	 * 					If a provider ID is not supplied it will loop through all the known providers short circuiting when it finds a valid subscription.
	 *
	 * @return array Multidimensional array of $providerId => [...data...] OR false on fatal error for a provider.  See SubscriptionProvider::getSubscription() for data array format.
	 */
	public function getSubscription(?string $providerId = null) {
		$_subscriptions = [];
		foreach ($this->getSubscriptionProviders() as $subscription) {
			$_subscriptions[$providerId] = false;
			$subscription = SubscriptionProvider::factory($providerId);
			if ($subscription !== null) {
				$_subscriptions[$providerId] = $subscription->getSubscription($this->userId);
			}
		}

		return $_subscriptions;
	}

	/**
	 * Does this user have a valid active subscription?
	 *
	 * @param string|null $providerId [Optional] Provider ID, a key in 'SubscriptionProviders'.
	 *
	 * @return array CSS classes.
	 */
	public function getFlairClasses(?string $providerId = null) {
		$classess = [];
		foreach ($this->getSubscriptionProviders() as $subscription) {
			if ($subscription !== null && !empty($subscription->getFlairClass()) && $subscription->hasSubscription($this->userId)) {
				$classess[] = $subscription->getFlairClass();
			}
		}

		return $classess;
	}

	/**
	 * Return specified or all subscription providers.
	 *
	 * @param string $providerId [Optional] Provider ID, a key in 'SubscriptionProviders'.
	 * 					If a provider ID is not supplied it will loop through all the known providers short circuiting when it finds a valid subscription.
	 *
	 * @return array Subscription Providers.
	 */
	private function getSubscriptionProviders(?string $providerId = null) {
		$providers = $this->config->get('SubscriptionProviders');

		$subscriptionProviders = [];
		if (isset($providers) && is_array($providers)) {
			if ($providerId !== null) {
				if (!array_key_exists($providerId, $providers)) {
					throw new SubscriptionProviderException(__METHOD__.": Given subscription provider ID \"{$providerId}\" is not defined in SubscriptionProviders.");
				}
				$subscriptionProviders[] = SubscriptionProvider::factory($providerId);
			} else {
				foreach ($providers as $providerId => $details) {
					if ($details === null) {
						//Provider has been nulled out to prevent processing it.
						continue;
					}
					$subscriptionProviders[] = SubscriptionProvider::factory($providerId);
				}
			}
		}

		return $subscriptionProviders;
	}

	/**
	 * Get if local cache only should be used or to change its setting.
	 * Setting this to true will cause $useLocalCacheOnly to have no effect.
	 *
	 * @param boolean $skip [Optional] True or False to enable or disable.  Not passing this argument results in no change.
	 *
	 * @return boolean Previous value, Enabled or Disabled
	 */
	static public function skipCache(?bool $skip = null) {
		$return = self::$skipCache; //Copy so the return value is the old value if being changed.
		if (is_bool($skip)) {
			self::$skipCache = $skip;
		}
		return $return;
	}

	/**
	 * Get if local cache only should be used or to change its setting.
	 *
	 * @param boolean $local [Optional] True or False to enable or disable.  Not passing this argument results in no change.
	 *
	 * @return boolean Previous value, Enabled or Disabled
	 */
	static public function useLocalCacheOnly($local = null) {
		$return = self::$useLocalCacheOnly; //Copy so the return value is the old value if being changed.
		if (is_bool($local)) {
			self::$useLocalCacheOnly = $local;
		}
		return $return;
	}
}

abstract class SubscriptionProvider {
	/**
	 * Provider Instances
	 *
	 * @var array
	 */
	static private $instances = [];

	/**
	 * Provider ID of this instance.
	 *
	 * @var string
	 */
	private $providerId;

	/**
	 * Get a subscription provider.
	 *
	 * @param ?string $providerId Provider ID from $wgSusbcriptionProvider
	 *
	 * @return CentralIdLookup|null
	 */
	static public function factory(?string $providerId = null) {
		$config = \ConfigFactory::getDefaultInstance()->makeConfig('main');
		$wgSusbcriptionProviders = $config->get('SubscriptionProviders');

		if ($providerId === null) {
			$providerId = $config->get('SubscriptionProvider');
		}

		if (!array_key_exists($providerId, self::$instances)) {
			self::$instances[$providerId] = null;

			if (isset($wgSusbcriptionProviders[$providerId])) {
				$provider = \ObjectFactory::getObjectFromSpec($wgSusbcriptionProviders[$providerId]);
				if ($provider instanceof SubscriptionProvider) {
					$provider->providerId = $providerId;
					self::$instances[$providerId] = $provider;
				} else {
					throw new SubscriptionProviderException(__METHOD__.": Given subscription provider ID \"{$providerId}\": \"{$wgSusbcriptionProviders[$providerId]['class']}\" does not extend ".__CLASS__.".");
				}
			}
		}

		return self::$instances[$providerId];
	}

	/**
	 * Get if a specific user ID has a subscription.
	 * Just a basic true or false, nothing more.
	 *
	 * @param integer $userId User ID
	 *
	 * @return boolean Has Subscription
	 */
	abstract public function hasSubscription(int $userId);

	/**
	 * Get the subscription information for a specific global user ID.
	 * Should return an array of details:
	 * [
	 * 		'active'			=> true,
	 * 		'begins'			=> new \MWTimestamp(), //MWTimestamp object or boolean false if not applicable.
	 * 		'expires'			=> new \MWTimestamp(), //MWTimestamp object or boolean false if it never expires.
	 * 		'plan_id'			=> 'premium_pro_user', //Changing the plan ID into lower case and replacing spaces is not required.
	 * 		'plan_name'			=> 'Premium Pro User',
	 * 		'price'				=> 9.9900, //Float
	 * 		'subscription_id'	=> '123456abcdef' //Unique ID generated for the user's subscription.
	 * ]
	 *
	 * @param integer $userId User ID
	 *
	 * @return mixed Subscription information, false on API failure.
	 */
	abstract public function getSubscription(int $userId);

	/**
	 * Create a comped subscription for a specific global user ID for so many months.
	 *
	 * @param integer $userId User ID
	 * @param integer $months Number of months to compensate.
	 */
	abstract public function createCompedSubscription(int $userId, int $months);

	/**
	 * Cancel the entirety of a global user ID's comped subscription.
	 *
	 * @param integer $userId User ID
	 *
	 * @return mixed Response message such as below or false on API failure.
	 */
	abstract public function cancelCompedSubscription(int $userId);

	/**
	 * Return a valid CSS class for flair display.
	 *
	 * @return mixed False for no flair, string otherwise.
	 */
	public function getFlairClass() {
		return false;
	}

	/**
	 * Return the duration to cache API responses in seconds.
	 *
	 * @return integer Duration to cache API responses in seconds.
	 */
	public function getCacheDuration() {
		return 600; //Cache for ten minutes.
	}
}

class SubscriptionProviderException extends Exception {
}