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
**/

use Hydra\Subscription;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;

class SubscriptionHooks {
	/**
	 * Link cache for onLinkEnd look ups.
	 *
	 * @var array
	 */
	static private $linkCache = [];

	/**
	 * Handle adding premium flair
	 *
	 * @param LinkRenderer  $linkRenderer
	 * @param LinkTarget    $target
	 * @param bool          $isKnown
	 * @param string|object $text
	 * @param array         $attribs
	 * @param string        $ret
	 *
	 * @return void
	 */
	public static function onHtmlPageLinkRendererEnd(
		LinkRenderer $linkRenderer,
		LinkTarget $target,
		$isKnown,
		&$text,
		&$attribs,
		&$ret
	) {
		$classes = false;
		$defaultText = trim(strip_tags(HtmlArmor::getHtml($text)));
		if (!empty($target) && !empty($target->getText()) && $target->getNamespace() === NS_USER
			&& mb_strpos($defaultText, $target->getText()) === 0) {
			if (array_key_exists($target->getText(), self::$linkCache)) {
				$classes = self::$linkCache[$target->getText()];
			} else {
				$user = User::newFromName($target->getText());

				if (!empty($user) && $user->getId()) {
					$subscription = Subscription::newFromUser($user);
					if ($subscription !== false) {
						$_cacheSetting = Subscription::useLocalCacheOnly(true);
						$classes = $subscription->getFlairClasses(true);
						Subscription::useLocalCacheOnly($_cacheSetting);
						if (empty($classes)) {
							// Enforce sanity.
							$classes = false;
						}
					}
				}
			}

			if ($classes !== false) {
				$attribs['class'] = (!empty($attribs['class']) ? $attribs['class'] . ' ' : '') . implode(' ', $classes);
			}
			self::$linkCache[$target->getText()] = $classes;
		}

		return true;
	}

	/**
	 * Handle setting if the user requires HTTPS per subscription.
	 *
	 * @param object  $user          User
	 * @param boolean $requiresHttps Requires HTTPS
	 *
	 * @return boolean True
	 */
	public static function onUserRequiresHTTPS($user, &$requiresHttps) {
		global $wgFullHTTPSExperiment;

		if ($wgFullHTTPSExperiment) {
			$requiresHttps = true;
			return true;
		}

		$requiresHttps = false;

		if (!empty($user) && $user->getId()) {
			$requiresHttps = true;
		}
		return true;
	}

	/**
	 * Handle automatically sending people back to regular HTTP if not premium.
	 *
	 * @param object $title     Title
	 * @param object $article   Article
	 * @param object $output    Output
	 * @param object $user      User
	 * @param object $request   WebRequest
	 * @param object $mediaWiki Mediawiki
	 *
	 * @return boolean True
	 */
	public static function onBeforeInitialize(&$title, &$article, &$output, &$user, $request, $mediaWiki) {
		global $wgFullHTTPSExperiment;

		if (defined('MW_API') && MW_API === true) {
			return true;
		}

		list($specialPage,) = SpecialPageFactory::resolveAlias($title->getDBkey());

		$secureSpecialPages = ['Userlogin', 'Preferences'];

		Hooks::run('SecureSpecialPages', [&$secureSpecialPages]);
		if ($wgFullHTTPSExperiment || (!empty($user) && $user->getId() && in_array($specialPage, $secureSpecialPages))) {
			if ($request->getProtocol() !== 'https') {
				$redirect = $request->getFullRequestURL();
				if (strpos($request->getFullRequestURL(), 'http://') === 0) {
					$redirect = substr_replace($redirect, 'https://', 0, 7);
				}
				$output->enableClientCache(false);
				$output->redirect($redirect, ($request->wasPosted() ? '307' : '302'));
			}

			return true;
		}

		if ((empty($user) || $user->isAnon()) && !in_array($specialPage, $secureSpecialPages) && $request->getProtocol() !== 'http') {
			$response = $request->response();
			$config = ConfigFactory::getDefaultInstance()->makeConfig('main');
			$response->clearCookie(
				'forceHTTPS',
				[
					'prefix' => '',
					'secure' => false,
					'path' => $config->get('CookiePath'),
					'domain' => $config->get('CookieDomain'),
					'httpOnly' => $config->get('CookieHttpOnly')
				]
			);

			$redirect = $request->getFullRequestURL();
			if (strpos($request->getFullRequestURL(), 'https://') === 0) {
				$redirect = substr_replace($redirect, 'http://', 0, 8);
			}
			$output->enableClientCache(false);
			$output->redirect($redirect, ($request->wasPosted() ? '307' : '302'));
		}

		return true;
	}

	/**
	 * Handle automatically sending people back to regular HTTP.
	 *
	 * @param object $output   OutputPage
	 * @param string $redirect Redirect URL
	 * @param string $code     HTTP Status Code
	 *
	 * @return boolean True
	 */
	public static function onBeforePageRedirect(OutputPage $output, &$redirect, &$code) {
		global $wgUser, $wgServer, $wgRequest, $wgFullHTTPSExperiment;

		if ($wgFullHTTPSExperiment || (defined('MW_API') && MW_API === true)) {
			return true;
		}

		if (!empty($wgUser) && $wgUser->getId()) {
			return true;
		}

		$server = str_ireplace(['https://', 'http://', '//'], '', $wgServer);
		if (strpos($redirect, $server) === false) {
			// Do not mess with external redirects.
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
	 * @param object $user User Object
	 *
	 * @return boolean true
	 */
	public static function onUserLoggedIn(User $user) {
		if ($user->isLoggedIn()) {
			$subscription = Subscription::newFromUser($user);
			if ($subscription !== false) {
				$_cacheSetting = Subscription::skipCache(true);
				$subscription->getSubscription(); // Don't care about the return.  This just forces a recache.
				Subscription::skipCache($_cacheSetting);
			}
		}

		return true;
	}
}
