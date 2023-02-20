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

use HtmlArmor;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererEndHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\UserIdentityLookup;

class SubscriptionHooks implements GetPreferencesHook, HtmlPageLinkRendererEndHook {
	/**
	 * Link cache for onLinkEnd look ups.
	 * @see onHtmlPageLinkRendererEnd
	 *
	 * @var array
	 */
	private static $linkCache = [];

	public function __construct(
		private UserIdentityLookup $userIdentityLookup,
		private Subscription $subscription
	) {
	}

	/**
	 * Handle adding premium flair
	 * @inheritDoc
	 */
	public function onHtmlPageLinkRendererEnd( $linkRenderer, $target, $isKnown, &$text, &$attribs, &$ret ) {
		$defaultText = trim( strip_tags( HtmlArmor::getHtml( $text ) ) );
		if ( empty( $target ) ||
			empty( $target->getText() ) ||
			$target->getNamespace() !== NS_USER ||
			!str_starts_with( $defaultText, $target->getText() ) ) {
			return true;
		}

		$flairClasses = false;
		if ( array_key_exists( $target->getText(), self::$linkCache ) ) {
			$flairClasses = self::$linkCache[$target->getText()];
		} else {
			$userIdentity = $this->userIdentityLookup->getUserIdentityByName( $target->getText() );

			if ( $userIdentity && $userIdentity->isRegistered() ) {
				$flairClasses = $this->subscription->getFlairClasses( $userIdentity->getId() );
				if ( empty( $flairClasses ) ) {
					// Enforce sanity.
					$flairClasses = false;
				}
			}
		}

		if ( $flairClasses !== false ) {
			$classes = !empty( $attribs['class'] ) ? $attribs['class'] . ' ' : '';
			$attribs['class'] = $classes . implode( ' ', $flairClasses );
		}
		self::$linkCache[$target->getText()] = $flairClasses;

		return true;
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences['gpro_expires'] = [
			'type' => 'api',
			'default' => 0,
		];
	}
}
