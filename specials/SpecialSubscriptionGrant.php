<?php
/**
 * Curse Inc.
 * Subscription
 * Subscription Grant Special Page
 *
 * @author    Michael Chaudhary
 * @copyright (c) 2019 Curse Inc.
 * @license   GPL-2.0-or-later
 * @package   Subscription
 * @link      https://gitlab.com/hydrawiki
**/

use Hydra\Subscription;
use Hydra\SubscriptionProvider;

class SpecialSubscriptionGrant extends SpecialPage {
	/**
	 * Main Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct('SubscriptionGrant', 'subscription', true);

		$this->wgRequest	= $this->getRequest();
		$this->wgUser		= $this->getUser();
		$this->output		= $this->getOutput();
	}

	/**
	 * Main Executor
	 *
	 * @param  string Sub page passed in the URL.
	 * @return void [Outputs to screen]
	 */
	public function execute($path) {
		$this->templates = new TemplateSubscription;

		$this->output->addModules(['ext.subscription']);

		$this->checkPermissions();

		$this->setHeaders();

		$this->subscriptionGrantForm();
	}

	/**
	 * Subscription Grant Form
	 *
	 * @return void [Outputs to screen]
	 */
	public function subscriptionGrantForm() {
		$formData = null;
		$this->output->setPageTitle(wfMessage('subscriptiongrant')->escaped());

		if ($this->wgRequest->wasPosted()) {
			switch ($this->wgRequest->getVal('do')) {
				case 'lookup':
					$this->lookup();
					break;
				case 'grant_subscription':
					$formData = $this->grantSubscription();
					break;
			}
		}

		$this->output->addHTML($this->templates->subscriptionGrant($formData));
	}

	/**
	 * Look up subscription information.
	 *
	 * @return void
	 */
	private	function lookup() {
		$username = trim($this->wgRequest->getVal('username'));
		$user = User::newFromName($username);
		if (!$user || !$user->getId()) {
			$this->output->addHTML("<span class='error'>User not found.</span><br/>");
		} else {
			$gamepediaPro = SubscriptionProvider::factory('GamepediaPro');
			Subscription::skipCache(true);
			$subInfo = $gamepediaPro->getSubscription($user->getId());
			$this->output->addHTML("<span class='success'>The subscription for {$user->getName()} expires on {$subInfo['expires']}.</span><br/>");
		}
	}

	/**
	 * Grant a Subscription
	 *
	 * @return array Form Data
	 */
	private function grantSubscription(): array {
		$username = trim($this->wgRequest->getVal('username'));
		$subscriptionDuration = trim($this->wgRequest->getVal('duration'));
		$overwriteSub = $this->wgRequest->getVal('overwriteSub');
		$formData = [
			'username' => $username,
			'duration' => $subscriptionDuration,
			'overwriteSub' => $overwriteSub
		];
		$user = User::newFromName($username);

		if ($user->getId() && $this->isValidSubscriptionDuration($subscriptionDuration)) {
			$userId = $user->getId();
			$gamepediaPro = SubscriptionProvider::factory('GamepediaPro');
			Subscription::skipCache(true);

			// Cancel any existing subscription before applying a new one
			$cancel = false;
			if ($overwriteSub === 'checked' || $subscriptionDuration == 0) {
				$cancel = $gamepediaPro->cancelCompedSubscription($userId);
				$this->output->addHTML("<span class='success'>" . $cancel ? 'Existing subscription cancelled.' : 'Failed to cancel subscription.' . "</span><br/>");
			}

			if ($subscriptionDuration > 0) {
				$createSubResult = $gamepediaPro->createCompedSubscription($userId, $subscriptionDuration);
				if ($createSubResult === false) {
					$this->output->addHTML("<span class='error'>Error creating subscription</span><br/>");

					// Usually what went wrong is the existing subscritpion wasn't cancelled first
					$subInfo = $gamepediaPro->getSubscription($userId);
					if (!$cancel = is_array($subInfo) && $subInfo['active']) {
						$expiresAt = $subInfo['expires']->getHumanTimestamp();
						$this->output->addHTML("<span class='error'>Subscription for " . htmlspecialchars($username) . "
												already exists, ending on " . $expiresAt . "<br/>
												You'll need to overwrite the existing subscription.</span><br/>");
					}
				}

				$this->output->addHTML("<span class='success'>" . $createSubResult ? 'Comped subscription successfully created.' : 'Failed to create comped subscription.' . "</span><br/>");
			}
		} elseif (!$this->isValidSubscriptionDuration($subscriptionDuration)) {
			$this->output->addHTML("<span class='error'>Invalid subscription duration</span><br/>");
		} else {
			$this->output->addHTML("<span class='error'>Invalid username</span><br/>");
			$this->output->addHTML(htmlspecialchars($username) . " User ID: " . $userId);
		}
		return $formData;
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @access protected
	 * @return string
	 */
	protected function getGroupName() {
		return 'users';
	}

	/**
	 * Ensure Subscription duration is valid
	 *
	 * @param  int $duration Subscription Duration
	 * @return boolean
	 */
	private function isValidSubscriptionDuration($duration) {
		if (!is_numeric($duration) || $duration < 0 || $duration > 100) {
			return false;
		}
		return true;
	}
}
