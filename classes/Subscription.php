<?php
/**
 * Curse Inc.
 * Subscription
 * Paid subscription system for Hydra Wiki Platform.
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		All Rights Reserved
 * @package		Subscription
 * @link		http://www.curse.com/
 *
**/

namespace Hydra;

class Subscription {
	/**
	 * Global User ID
	 *
	 * @var		integer
	 */
	private $globalId;

	/**
	 * Main Config Factory
	 *
	 * @var		object
	 */
	private $config;

	/**
	 * Use Local Cache Only
	 *
	 * @var		boolean
	 */
	static private $useLocalCacheOnly = false;

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @param	integer	Global User ID
	 * @return	void
	 */
	public function __construct($globalId) {
		$this->globalId = intval($globalId);

		$this->config = \ConfigFactory::getDefaultInstance()->makeConfig('main');
	}

	/**
	 * Return an initialized instance from a given User object.
	 *
	 * @access	public
	 * @param	object	User
	 * @return	void
	 */
	static public function newFromUser($user) {
		$lookup = \CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, \CentralIdLookup::AUDIENCE_RAW);
		if (!$globalId) {
			return false;
		}

		return new self($globalId);
	}

	/**
	 * Does this user have a valid active subscription?
	 *
	 * @access	public
	 * @param	string	[Optional] Provider ID, a key in 'SubscriptionProviders'.
	 * 					If a provider ID is not supplied it will loop through all the known providers short circuiting when it finds a valid subscription.
	 * @return	boolean
	 */
	public function hasSubscription($providerId = null) {
		foreach ($this->getSubscriptionProviders() as $subscription) {
			if ($subscription !== null && $subscription->hasSubscription($this->globalId)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Does this user have a valid active subscription?
	 *
	 * @access	public
	 * @param	string	[Optional] Provider ID, a key in 'SubscriptionProviders'.
	 * 					If a provider ID is not supplied it will loop through all the known providers short circuiting when it finds a valid subscription.
	 * @return	array	Multidimensional array of $providerId => [...data...] OR false on fatal error for a provider.  See SubscriptionProvider::getSubscription() for data array format.
	 */
	public function getSubscription($providerId = null) {
		$_subscriptions = [];
		foreach ($this->getSubscriptionProviders() as $subscription) {
			$_subscriptions[$providerId] = false;
			$subscription = SubscriptionProvider::factory($providerId);
			if ($subscription !== null) {
				$_subscriptions[$providerId] = $subscription->getSubscription($this->globalId);
			}
		}

		return $_subscriptions;
	}

	/**
	 * Does this user have a valid active subscription?
	 *
	 * @access	public
	 * @param	string	[Optional] Provider ID, a key in 'SubscriptionProviders'.
	 * @return	array	CSS classes.
	 */
	public function getFlairClasses($providerId = null) {
		$classess = [];
		foreach ($this->getSubscriptionProviders() as $subscription) {
			if ($subscription !== null && !empty($subscription->getFlairClass()) && $subscription->hasSubscription($this->globalId)) {
				$classess[] = $subscription->getFlairClass();
			}
		}

		return $classess;
	}

	/**
	 * Return specified or all subscription providers.
	 *
	 * @access	private
	 * @param	string	[Optional] Provider ID, a key in 'SubscriptionProviders'.
	 * 					If a provider ID is not supplied it will loop through all the known providers short circuiting when it finds a valid subscription.
	 * @return	array	Subscription Providers.
	 */
	private function getSubscriptionProviders($providerId = null) {
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
	 *
	 * @access	public
	 * @param	boolean	[Optional] True or False to enable or disable.  Not passing this argument results in no change.
	 * @return	boolean	Enabled or Disabled
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
	 * @var		array
	 */
	static private $instances = [];

	/**
	 * Provider ID of this instance.
	 *
	 * @var		string
	 */
	private $providerId;

	/**
	 * Get a subscription provider.
	 *
	 * @param	string	Provider ID from $wgSusbcriptionProvider
	 * @return CentralIdLookup|null
	 */
	static public function factory($providerId = null) {
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
	 * Get if a specific global user ID has a subscription.
	 * Just a basic true or false, nothing more.
	 *
	 * @access	public
	 * @param	integer	Global User ID
	 * @return	boolean	Has Subscription
	 */
	abstract public function hasSubscription($globalId);

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
	 * @access	public
	 * @param	integer	Global User ID
	 * @return	mixed	Subscription information, false on API failure.
	 */
	abstract public function getSubscription($globalId);

	/**
	 * Create a comped subscription for a specific global user ID for so many months.
	 *
	 * @access	public
	 * @param	integer	Global User ID
	 * @param	integer	Number of months to compensate.
	 */
	abstract public function createCompedSubscription($globalId, $months);

	/**
	 * Cancel the entirety of a global user ID's comped subscription.
	 *
	 * @access	public
	 * @param	integer	Global User ID
	 * @return	mixed	Response message such as below or false on API failure.
	 */
	abstract public function cancelCompedSubscription($globalId);

	/**
	 * Return a valid CSS class for flair display.
	 *
	 * @access	public
	 * @return	mixed	False for no flair, string otherwise.
	 */
	public function getFlairClass() {
		return false;
	}

	/**
	 * Return the duration to cache API responses in seconds.
	 *
	 * @access	public
	 * @return	integer	Duration to cache API responses in seconds.
	 */
	public function getCacheDuration() {
		return 1209600; //Cache for two weeks.
	}
}

class SubscriptionProviderException extends \Exception {
}