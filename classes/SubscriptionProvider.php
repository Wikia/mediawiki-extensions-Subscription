<?php
/**
 * Curse Inc.
 * Subscription Provider
 * Paid subscription system for Hydra Wiki Platform.
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
use ObjectFactory;

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
	 * @return SubscriptionProvider|null
	 */
	public static function factory(?string $providerId = null) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$wgSusbcriptionProviders = $config->get('SubscriptionProviders');

		if ($providerId === null) {
			$providerId = $config->get('SubscriptionProvider');
		}

		if (!array_key_exists($providerId, self::$instances)) {
			self::$instances[$providerId] = null;

			if (isset($wgSusbcriptionProviders[$providerId])) {
				$provider = ObjectFactory::getObjectFromSpec($wgSusbcriptionProviders[$providerId]);
				if ($provider instanceof SubscriptionProvider) {
					$provider->providerId = $providerId;
					self::$instances[$providerId] = $provider;
				} else {
					throw new SubscriptionProviderException(__METHOD__ . ": Given subscription provider ID \"{$providerId}\": \"{$wgSusbcriptionProviders[$providerId]['class']}\" does not extend " . __CLASS__ . ".");
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
	 *
	 * @return boolean Success
	 */
	abstract public function createCompedSubscription(int $userId, int $months);

	/**
	 * Cancel the entirety of a global user ID's comped subscription.
	 *
	 * @param integer $userId User ID
	 *
	 * @return boolean Success
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
		return 600; // Cache for ten minutes.
	}
}
