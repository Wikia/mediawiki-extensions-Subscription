<?php
/**
 * Curse Inc.
 * Subscription Provider
 * Paid subscription system for Hydra Wiki Platform.
 *
 * @package   Subscription
 * @author    Alexia E. Smith
 * @copyright (c) 2016 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace Subscription;

use MediaWiki\MediaWikiServices;
use Subscription\Providers\GamepediaPro;

abstract class SubscriptionProvider {
	/**
	 * @deprecated Instead grab GamepediaPro instance directly via
	 * MediaWikiServices::getInstance()->getService( GamepediaPro::class );
	 */
	public static function factory( ?string $providerId = null ): ?SubscriptionProvider {
		if ( $providerId === 'GamepediaPro' || $providerId === null ) {
			return MediaWikiServices::getInstance()->getService( GamepediaPro::class );
		}

		return null;
	}

	/**
	 * Get if a specific user ID has a subscription.
	 * Just a basic true or false, nothing more.
	 *
	 * @param int $userId User ID
	 *
	 * @return bool Has Subscription
	 */
	abstract public function hasSubscription( int $userId );

	/**
	 * Get the subscription information for a specific global user ID.
	 * Should return an array of details:
	 * [
	 * 'active' => true,
	 * 'begins' => new \MWTimestamp(), //MWTimestamp object or boolean false if not applicable.
	 * 'expires' => new \MWTimestamp(), //MWTimestamp object or boolean false if it never expires.
	 * 'plan_id' => 'premium_pro_user', //Changing the plan ID into lower case and replacing spaces is not required.
	 * 'plan_name' => 'Premium Pro User',
	 * 'price' => 9.9900, //Float
	 * 'subscription_id' => '123456abcdef' //Unique ID generated for the user's subscription.
	 * ]
	 *
	 * @param int $userId User ID
	 *
	 * @return mixed Subscription information, false on API failure.
	 */
	abstract public function getSubscription( int $userId );

	/**
	 * Create a comped subscription for a specific global user ID for so many months.
	 *
	 * @param int $userId User ID
	 * @param int $months Number of months to compensate.
	 *
	 * @return bool Success
	 */
	abstract public function createCompedSubscription( int $userId, int $months );

	/**
	 * Cancel the entirety of a global user ID's comped subscription.
	 *
	 * @param int $userId User ID
	 *
	 * @return bool Success
	 */
	abstract public function cancelCompedSubscription( int $userId );

	/**
	 * Return a valid CSS class for flair display.
	 *
	 * @return mixed False for no flair, string otherwise.
	 */
	public function getFlairClass() {
		return false;
	}
}
