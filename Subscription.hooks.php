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

class SubscriptionHooks {
	/**
	 * Link cache for onLinkEnd look ups.
	 *
	 * @var		array
	 */
	static private $linkCache = [];

	/**
	 * Function Documentation
	 *
	 * @access	public
	 * @return	void
	 */
	public function init() {
		# code...
	}

	/**
	 * Handle adding premium flair.
	 *
	 * @access	public
	 * @param	object	DummyLinker
	 * @param	object	Title
	 * @param	array	Options
	 * @param	string	HTML that goes inside the anchor tag.
	 * @param	array	HTML anchor tag attributes.
	 * @param	string	Complete override of the HTML return; if needed to not use an achor tag.
	 * @return	boolean	True
	 */
	static public function onLinkEnd($dummy, $target, $options, &$html, &$attribs, &$returnOverride) {
		$isPremium = false;

		if (!empty($target) && $target->getNamespace() === NS_USER) {
			if (array_key_exists($target->getText(), self::$linkCache)) {
				$isPremium = self::$linkCache[$target->getText()];
			} else {
				$user = User::newFromName($target->getText());

				if (!empty($user) && $user->getId()) {
					$subscription = \Hydra\Subscription::newFromUser($user);
					if ($subscription !== false && $subscription->hasSubscription()) {
						$isPremium = true;
					}
				}
			}
		}

		if ($isPremium) {
			$attribs['class'] = (!empty($attribs['class']) ? $attribs['class'].' ' : '')."premium_user";
		}
		self::$linkCache[$target->getText()] = $isPremium;

		return true;
	}

	/**
	 * Setups and Modifies Database Information
	 *
	 * @access	public
	 * @param	object	[Optional] DatabaseUpdater Object
	 * @return	boolean	true
	 */
	static public function onLoadExtensionSchemaUpdates(DatabaseUpdater $updater = null) {
		$extDir = __DIR__;

		//Install
		//Tables
		$updater->addExtensionUpdate(['addTable', 'subscription', "{$extDir}/install/sql/table_subscription.sql", true]);

		return true;
	}
}
