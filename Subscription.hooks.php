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
		$classes = false;

		if (!empty($target) && $target->getNamespace() === NS_USER && $target->getText() == $html) {
			if (array_key_exists($target->getText(), self::$linkCache)) {
				$classes = self::$linkCache[$target->getText()];
			} else {
				$user = User::newFromName($target->getText());

				if (!empty($user) && $user->getId()) {
					$subscription = \Hydra\Subscription::newFromUser($user);
					if ($subscription !== false) {
						$classes = $subscription->getFlairClasses();
						if (empty($classes)) {
							$classes = false; //Enforce sanity.
						}
					}
				}
			}

			if ($classes !== false) {
				$attribs['class'] = (!empty($attribs['class']) ? $attribs['class'].' ' : '').implode(' ', $classes);
			}
			self::$linkCache[$target->getText()] = $classes;
		}

		return true;
	}

	/**
	 * Handle automatically sending people back to regular HTTP if not premium.
	 *
	 * @access	public
	 * @param	object	Title
	 * @param	object	Article
	 * @param	object	Output
	 * @param	object	User
	 * @param	object	WebRequest
	 * @param	object	Mediawiki
	 * @return	boolean	True
	 */
	static public function onBeforeInitialize(&$title, &$article, &$output, &$user, $request, $mediaWiki) {
		global $wgSecureLogin;

		list($specialPage,) = SpecialPageFactory::resolveAlias($title->getDBkey());

		if (!empty($user) && $user->getId()) {
			$subscription = \Hydra\Subscription::newFromUser($user);
			if ($subscription !== false) {
				if ($subscription->hasSubscription()) {
					/*if ($wgSecureLogin === true && $request->getProtocol() !== 'https' && strpos($request->getFullRequestURL(), 'http://') === 0) {
						$redirect = substr_replace($request->getFullRequestURL(), 'https://', 0, 7);
						$output->enableClientCache(false);
						$output->redirect($redirect);
					}*/

					return true;
				}
			}
		}

		if ($wgSecureLogin === true && $specialPage != 'Userlogin' && $request->getProtocol() !== 'http' && strpos($request->getFullRequestURL(), 'https://') === 0) {
			$redirect = substr_replace($request->getFullRequestURL(), 'http://', 0, 8);
			$output->enableClientCache(false);
			$output->redirect($redirect);
		}

		//We cannot accept forced HTTPS right now.
		//TODO remove in the future when always-on HTTPS is a possibility.
		if ($request->getCookie('forceHTTPS', '')) {
			$request->response()->setcookie('forceHTTPS', '', time() - 86400, ['prefix' => '', 'secure' => false]);
		}
	}

	/**
	 * Handle automatically sending people back to regular HTTP.
	 *
	 * @access	public
	 * @param	object	OutputPage
	 * @param	string	Redirect URL
	 * @param	string	HTTP Status Code
	 * @return	boolean	True
	 */
	static public function onBeforePageRedirect(OutputPage $output, &$redirect, &$code) {
		global $wgUser, $wgServer, $wgRequest, $wgSecureLogin;

		self::init();

		if (!empty($wgUser) && $wgUser->getId()) {
			$subscription = \Hydra\Subscription::newFromUser($wgUser);
			if ($subscription !== false) {
				if ($subscription->hasSubscription()) {
					return true;
				}
			}
		}

		$server = str_ireplace(['https://', 'http://', '//'], '', $wgServer);
		if (strpos($redirect, $server) === false) {
			//Do not mess with external redirects.
			return true;
		}

		if ($wgRequest->getProtocol() !== 'http' && strpos($redirect, 'https://') === 0 && $wgSecureLogin === true) {
			$redirect = substr_replace($redirect, 'http://', 0, 8);
		}

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
		if (defined('MASTER_WIKI') && MASTER_WIKI === true) {
			$updater->addExtensionUpdate(['addTable', 'subscription', "{$extDir}/install/sql/table_subscription.sql", true]);
		}

		return true;
	}
}
