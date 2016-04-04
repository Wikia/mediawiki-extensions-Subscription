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
	 * Main Constructor
	 *
	 * @access	public
	 * @param	integer	Global User ID
	 * @return	void
	 */
	public function __construct($globalId) {
		$this->globalId = intval($globalId);
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
	 * Function Documentation
	 *
	 * @access	public
	 * @return	void
	 */
	public function hasSubscription() {
		$config = \ConfigFactory::getDefaultInstance()->makeConfig('main');
		$providers = $config->get('SusbcriptionProviders');

		if (isset($providers) && is_array($providers)) {
			foreach ($providers as $providerId => $details) {
				echo "<pre>";
				var_dump($providerId);
				echo "</pre>";
				$subscription = SubscriptionProvider::factory($providerId);
				if ($subscription->hasSubscription($this->globalId)) {
					return true;
				}
			}
		}

		return false;
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
	 * @param	string	Provider ID from $$wgSusbcriptionProvider
	 * @return CentralIdLookup|null
	 */
	static public function factory($providerId = null) {
		global $wgSusbcriptionProviders, $wgSusbcriptionProvider;

		if ($providerId === null) {
			$providerId = $wgSusbcriptionProvider;
		}

		if (!array_key_exists($providerId, self::$instances)) {
			var_dump("INITIALIZE");
			self::$instances[$providerId] = null;

			if (isset($wgSusbcriptionProviders[$providerId])) {
				$provider = \ObjectFactory::getObjectFromSpec($wgSusbcriptionProviders[$providerId]);
				if ($provider instanceof SubscriptionProvider) {
					$provider->providerId = $providerId;
					self::$instances[$providerId] = $provider;
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
	 * 		'expires'			=> new \MWTimestamp(), //MWTimestamp object or oolean false if it never expires.
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
}