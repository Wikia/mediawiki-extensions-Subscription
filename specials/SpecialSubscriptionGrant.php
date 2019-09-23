<?php
/**
 * Curse Inc.
 * Subscription
 * Subscription Grant Special Page
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		GNU General Public License v2.0 or later
 * @package		Subscription
 * @link		https://gitlab.com/hydrawiki
 *
**/

class SpecialSubscriptionGrant extends SpecialPage {
	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct('Subscription_Grant', 'subscription', true);

		$this->wgRequest	= $this->getRequest();
		$this->wgUser		= $this->getUser();
		$this->output		= $this->getOutput();
	}

	/**
	 * Main Executor
	 *
	 * @access	public
	 * @param	string	Sub page passed in the URL.
	 * @return	void	[Outputs to screen]
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
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function subscriptionGrantForm() {
		$formData = null;
		$this->output->setPageTitle(wfMessage('subscription_grant')->escaped());

		if ($this->wgRequest->getVal('do') == 'grant_subscription') {

			$username = trim($this->wgRequest->getVal('username'));
			$subscriptionDuration = trim($this->wgRequest->getVal('duration'));
			$overwriteSub = $this->wgRequest->getVal('overwriteSub');
			$formData = ["username" => $username, "duration" => $subscriptionDuration, "overwriteSub" => $overwriteSub];
			$globalID = \CentralIdLookup::factory()->centralIdFromName($username);

			if($globalID != 0 && $this->isValidSubscriptionDuration($subscriptionDuration)) {
				$gamepediaPro = \Hydra\SubscriptionProvider::factory('GamepediaPro');
				\Hydra\Subscription::skipCache(true);

				// Cancel any existing subscription before applying a new one
				if($overwriteSub == "checked") {
					$cancel = $gamepediaPro->cancelCompedSubscription($globalID);
				}

				$createSubResult = $gamepediaPro->createCompedSubscription($globalID, $subscriptionDuration);

				// Something went wrong creating the subscription
				if($createSubResult === false) {
					$this->output->addHTML("<span class='error'>Error creating subscription</span><br />");

					// Usually what went wrong is the existing subscritpion wasn't cancelled first
					$subInfo = $gamepediaPro->getSubscription($globalID);
					if($subInfo !== null) {
						$expiresAt = $subInfo["expires"]->getHumanTimestamp();
						$this->output->addHTML("<span class='error'>Subscription for ".htmlspecialchars($username)."
												already exists,ending on ".$expiresAt."<br />
												You'll need to overwrite the existing subscription.</span><br />");
					}
				}
				else {
					$this->output->addHTML("<span class='success'>".$createSubResult["message"]."</span><br />");
				}
			}
			else if(!$this->isValidSubscriptionDuration($subscriptionDuration)) {
				$this->output->addHTML("<span class='error'>Invalid subscription duration</span><br />");
			}
			else {
				$this->output->addHTML("<span class='error'>Invalid username</span><br />");
				$this->output->addHTML(htmlspecialchars($username) . " GlobalID: ". $globalID);
			}
		}

		$this->output->addHTML($this->templates->subscriptionGrant($formData));
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
	 * @param	int	$duration  Subscription Duration
	 * @return	boolean
	 */
	private function isValidSubscriptionDuration($duration) {
		if(!is_numeric($duration) || $duration < 1 || $duration > 100) {
			return false;
		}
		return true;
	}
}
