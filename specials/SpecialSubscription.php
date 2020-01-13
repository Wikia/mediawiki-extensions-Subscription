<?php
/**
 * Curse Inc.
 * Subscription
 * Subscription Special Page
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		GNU General Public License v2.0 or later
 * @package Subscription
 * @link		https://gitlab.com/hydrawiki
 *
**/

class SpecialSubscription extends SpecialPage {
	/**
	 * Main Constructor
	 *
	 * @return void
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
	 * @param string Sub page passed in the URL.
	 * @return void [Outputs to screen]
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
	 * @return void [Outputs to screen]
	 */
	public function subscriptionList() {
		$start = $this->wgRequest->getInt('st');
		$itemsPerPage = 50;
		$searchTerm = null;
		$cookieExpire = time() + 900;

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

		$subscriptions = \Hydra\SubscriptionCache::filterSearch($start, $itemsPerPage, $searchTerm, $filterValues['user']);

		$total = \Hydra\SubscriptionCache::getLastSearchTotal();

		$userFilters = $this->wgRequest->getValues('list_search', 'providers', 'plans', 'min_date', 'max_date', 'min_price', 'max_price');

		$pagination = HydraCore::generatePaginationHtml($this->getFullTitle(), $total, $itemsPerPage, $start, 4, (array) $userFilters);

		$this->output->setPageTitle(wfMessage('subscriptions')->escaped());
		$this->output->addHTML($this->templates->subscriptionList($subscriptions, $pagination, $filterValues, '', 'DESC', $searchTerm));
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
}
