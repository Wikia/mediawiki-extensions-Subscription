<?php
/**
 * Curse Inc.
 * Subscription
 * Paid subscription system for Hydra Wiki Platform.
 *
 * @package   Subscription
 * @author    Alexia E. Smith
 * @copyright (c) 2016 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace Subscription;

class Subscription {
	/**
	 * @param SubscriptionProvider[] $subscriptionProviders
	 */
	public function __construct( private array $subscriptionProviders ) {
	}

	/** Does this user have a valid active subscription? */
	public function hasSubscription( int $userId ): bool {
		foreach ( $this->subscriptionProviders as $subscription ) {
			if ( $subscription !== null && $subscription->hasSubscription( $userId ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param int $userId
	 * @return array CSS classes.
	 */
	public function getFlairClasses( int $userId ): array {
		$classes = [];
		foreach ( $this->subscriptionProviders as $subscriptionProvider ) {
			if ( !empty( $subscriptionProvider?->getFlairClass() ) &&
				$subscriptionProvider->hasSubscription( $userId ) ) {
				$classes[] = $subscriptionProvider->getFlairClass();
			}
		}

		return $classes;
	}
}
