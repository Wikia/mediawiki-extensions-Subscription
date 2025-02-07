<?php
/**
 * Subscription
 * Gamepedia Pro API and Access
 *
 * @package   Subscription
 * @author    Alexia E. Smith
 * @copyright (c) 2016 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace Subscription\Providers;

use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\User\UserOptionsManager;
use MediaWiki\Utils\MWTimestamp;
use Subscription\SubscriptionProvider;
use Wikimedia\Timestamp\TimestampException;

class GamepediaPro extends SubscriptionProvider {
	public function __construct(
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly UserOptionsManager $userOptionsManager
	) {
	}

	/**
	 * Get if a specific global user ID has an entitlement.
	 * Just a basic true or false, nothing more.
	 *
	 * @throws TimestampException
	 */
	public function hasSubscription( int $userId ): bool {
		if ( $userId < 1 ) {
			return false;
		}

		$data = $this->getSubscription( $userId );

		if ( $data !== false && isset( $data['active'] ) ) {
			return $data['active'];
		}

		return false;
	}

	/**
	 * Get the subscription information for a specific global user ID.
	 *
	 * @param int $userId User ID
	 *
	 * @return array|false|null Subscription information, null on missing subscription, false on failure.
	 * @throws TimestampException
	 */
	public function getSubscription( int $userId ): array|false|null {
		if ( $userId < 1 ) {
			return false;
		}

		$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $userId );
		if ( !$userIdentity || !$userIdentity->isRegistered() ) {
			return false;
		}
		$expires = $this->userOptionsLookup->getOption( $userIdentity, 'gpro_expires' );

		return [
			'active' => $expires > 0,
			'begins' => false,
			'expires' => new MWTimestamp( $expires ),
			'plan_id' => 'complimentary',
			'plan_name' => 'Complimentary',
			'price' => 0.00,
			'subscription_id' => 'comped_' . $userId
		];
	}

	/**
	 * Create a comped subscription for a specific global user ID for so many months.
	 *
	 * @param int $userId User ID
	 * @param int $months Number of months to compensate.
	 *
	 * @return bool Success
	 */
	public function createCompedSubscription( int $userId, int $months ): bool {
		if ( $userId < 1 || $months < 1 ) {
			return false;
		}

		$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $userId );
		if ( !$userIdentity || !$userIdentity->isRegistered() ) {
			return false;
		}
		$this->userOptionsManager->setOption( $userIdentity, 'gpro_expires', strtotime( '+' . $months . ' months' ) );
		$this->userOptionsManager->saveOptions( $userIdentity );

		return true;
	}

	/**
	 * Cancel the entirety of a global user ID's comped subscription.
	 *
	 * @param int $userId User ID
	 *
	 * @return bool Success
	 */
	public function cancelCompedSubscription( int $userId ): bool {
		if ( $userId < 1 ) {
			return false;
		}

		$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $userId );
		if ( !$userIdentity || !$userIdentity->isRegistered() ) {
			return false;
		}
		$this->userOptionsManager->setOption( $userIdentity, 'gpro_expires', 0 );
		$this->userOptionsManager->saveOptions( $userIdentity );

		return true;
	}

	/**
	 * Return a valid CSS class for flair display.
	 *
	 * @return string False for no flair, string otherwise.
	 */
	public function getFlairClass(): string {
		return 'gamepedia_pro_user';
	}
}
