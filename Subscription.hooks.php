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
						$_cacheSetting = \Hydra\Subscription::useLocalCacheOnly(true);
						$classes = $subscription->getFlairClasses(true);
						\Hydra\Subscription::useLocalCacheOnly($_cacheSetting);
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
	 * Handle setting if the user requires HTTPS per subscription.
	 *
	 * @access	public
	 * @param	object	User
	 * @param	boolean	Requires HTTPS
	 * @return	boolean	True
	 */
	static public function onUserRequiresHTTPS($user, &$requiresHttps) {
		$requiresHttps = false;

		if (!empty($user) && $user->getId()) {
			$requiresHttps = true;
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
		if (defined('MW_API') && MW_API === true) {
			return true;
		}

		list($specialPage,) = SpecialPageFactory::resolveAlias($title->getDBkey());

		$secureSpecialPages = ['Userlogin', 'Preferences'];

		Hooks::run('SecureSpecialPages', [&$secureSpecialPages]);

		if (!empty($user) && $user->getId() && in_array($specialPage, $secureSpecialPages)) {
			if ($request->getProtocol() !== 'https' && strpos($request->getFullRequestURL(), 'http://') === 0) {
				$redirect = substr_replace($request->getFullRequestURL(), 'https://', 0, 7);
				$output->enableClientCache(false);
				$output->redirect($redirect, ($request->wasPosted() ? '307' : '302'));
			}

			return true;
		}

		if ((empty($user) || $user->isAnon()) && !in_array($specialPage, $secureSpecialPages) && $request->getProtocol() !== 'http' && strpos($request->getFullRequestURL(), 'https://') === 0) {
			$redirect = substr_replace($request->getFullRequestURL(), 'http://', 0, 8);
			$output->enableClientCache(false);
			$output->redirect($redirect, ($request->wasPosted() ? '307' : '302'));
		}

		return true;
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
		global $wgUser, $wgServer, $wgRequest;

		if (defined('MW_API') && MW_API === true) {
			return true;
		}

		if (!empty($wgUser) && $wgUser->getId()) {
			return true;
		}

		$server = str_ireplace(['https://', 'http://', '//'], '', $wgServer);
		if (strpos($redirect, $server) === false) {
			//Do not mess with external redirects.
			return true;
		}

		if ($wgRequest->getProtocol() !== 'http' && strpos($redirect, 'https://') === 0) {
			$redirect = substr_replace($redirect, 'http://', 0, 8);
			if ($output->getRequest()->wasPosted()) {
				$code = '307';
			}
		}

		return true;
	}

	/**
	 * Overloads for UserLoadAfterLoadFromSession
	 *
	 * @access	public
	 * @param	object	User Object
	 * @return	boolean	True
	 */
	static public function onUserLoggedIn(User $user) {
		if ($user->isLoggedIn()) {
			$subscription = \Hydra\Subscription::newFromUser($user);
			if ($subscription !== false) {
				$_cacheSetting = \Hydra\Subscription::skipCache(true);
				$subscription->getSubscription(); //Don't care about the return.  This just forces a recache.
				\Hydra\Subscription::skipCache($_cacheSetting);
			}
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
