<?php
/**
 * Curse Inc.
 * Subscription
 * Subscription Special Page
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		All Rights Reserved
 * @package		Subscription
 * @link		http://www.curse.com/
 *
**/

class SpecialSubscription extends SpecialPage {
	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct('Subscription', 'subscription', true);

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

		$this->subscriptionList();
	}

	/**
	 * Subscriptions List
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function subscriptionList() {
		$hide['deleted'] = true;
		$hide['secret'] = true;

		$searchTerm = '';
		if ($this->wgRequest->getVal('do') == 'resetSearch') {
			$this->wgRequest->response()->setcookie('subscriptionSearchTerm', '', 1);
		} else {
			$listSearch = $this->wgRequest->getVal('list_search');
			$cookieSearch = $this->wgRequest->getCookie('subscriptionSearchTerm');
			if (($this->wgRequest->getVal('do') == 'search' && !empty($listSearch)) || !empty($cookieSearch)) {
				if (!empty($cookieSearch) && empty($listSearch)) {
					$searchTerm = $this->wgRequest->getCookie('subscriptionSearchTerm');
				} else {
					$searchTerm = $this->wgRequest->getVal('list_search');
				}
				$this->wgRequest->response()->setcookie('subscriptionSearchTerm', $searchTerm, $cookieExpire);
			}
		}

		$filterValues = \Hydra\SubscriptionCache::getSearchFilterValues();

		$subscriptions = \Hydra\SubscriptionCache::filterSearch(0, 100);

		$this->output->setPageTitle(wfMessage('subscriptions')->escaped());
		$this->output->addHTML($this->templates->subscriptionList($subscriptions, $pagination, $filterValues, $sortKey, $sortDir, $searchTerm));
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @access protected
	 * @return string
	 */
	protected function getGroupName() {
		return 'user';
	}
}
