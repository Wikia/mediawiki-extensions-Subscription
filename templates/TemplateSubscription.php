<?php
/**
 * Curse Inc.
 * Subscription
 * Subscription Template
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		All Rights Reserved
 * @package		Subscription
 * @link		http://www.curse.com/
 *
**/

class TemplateSubscription {
	/**
	 * Wiki Sites
	 *
	 * @access	public
	 * @param	array	Array of subscription information.
	 * @param	array	Pagination
	 * @param	string	[Optional] Data sorting key
	 * @param	string	[Optional] Data sorting direction
	 * @param	string	[Optional] Search Term
	 * @return	string	Built HTML
	 */
	public function wikiSites($subscriptions, $pagination, $sortKey = 'user_id', $sortDir = 'ASC', $searchTerm = null) {
		global $wgOut, $wgUser, $wgRequest;

		$subscriptionPage = Title::newFromText('Special:Subscription');
		$subscriptionURL = $wikiSitesPage->getFullURL();

		$html = $pagination;

		$html .= "
			<div class='search_bar'>
				<form method='get' action='{$subscriptionURL}'>
					<fieldset>
						<input type='hidden' name='section' value='list'/>
						<input type='hidden' name='do' value='search'/>
						<input type='text' name='list_search' value='".htmlentities($searchTerm, ENT_QUOTES)."' class='search_field' placeholder='".wfMessage('search')->escaped()."' title='".wfMessage('wiki_sites_search_tooltip')->escaped()."'/>
						<input type='submit' value='".wfMessage('list_search')->escaped()."' class='button'/>
						<a href='{$subscriptionURL}?do=resetSearch' class='button'>".wfMessage('list_reset')->escaped()."</a>
					</fieldset>
				</form>
			</div>
			<table id='wikilist'>
				<thead>
					<tr class='sortable' data-sort-dir='".($sortDir == 'desc' ? 'desc' : 'asc')."'>
						<th".($sortKey == 'user' ? " data-selected='true'" : '')."><span data-sort='user'".($sortKey == 'user' ? " data-selected='true'" : '').">".wfMessage('sub_th_user')->escaped()."</span></th>
						<th".($sortKey == 'provider_id' ? " data-selected='true'" : '')."><span data-sort='provider_id'".($sortKey == 'provider_id' ? " data-selected='true'" : '').">".wfMessage('sub_th_provider_id')->escaped()."</span></th>
						<th".($sortKey == 'active' ? " data-selected='true'" : '')."><span data-sort='active'".($sortKey == 'active' ? " data-selected='true'" : '').">".wfMessage('sub_th_active')->escaped()."</span></th>
						<th".($sortKey == 'begins' ? " data-selected='true'" : '')."><span data-sort='begins'".($sortKey == 'begins' ? " data-selected='true'" : '').">".wfMessage('sub_th_begins')->escaped()."</span></th>
						<th".($sortKey == 'expires' ? " data-selected='true'" : '')."><span data-sort='expires'".($sortKey == 'expires' ? " data-selected='true'" : '').">".wfMessage('sub_th_expires')->escaped()."</span></th>
						<th".($sortKey == 'plan_id' ? " data-selected='true'" : '')."><span data-sort='plan_id'".($sortKey == 'plan_id' ? " data-selected='true'" : '').">".wfMessage('sub_th_plan_id')->escaped()."</span></th>
						<th".($sortKey == 'plan_name' ? " data-selected='true'" : '')."><span data-sort='plan_name'".($sortKey == 'plan_name' ? " data-selected='true'" : '').">".wfMessage('sub_th_plan_name')->escaped()."</span></th>
						<th".($sortKey == 'price' ? " data-selected='true'" : '')."><span data-sort='price'".($sortKey == 'price' ? " data-selected='true'" : '').">".wfMessage('sub_th_price')->escaped()."</span></th>
						<th".($sortKey == 'subscription_id' ? " data-selected='true'" : '')."><span data-sort='subscription_id'".($sortKey == 'subscription_id' ? " data-selected='true'" : '').">".wfMessage('sub_th_subscription_id')->escaped()."</span></th>
					</tr>
				</thead>
				<tbody>
				";
		if (is_array($subscriptions) && count($subscriptions)) {
			$_wikis = [];
			foreach ($subscriptions as $subscription) {
				$html .= "
					<tr>
						<td>{$subscription['global_id']}</td>
						<td>{$subscription['provider_id']}</td>
						<td>{$subscription['active']}</td>
						<td>{$subscription['begins']}</td>
						<td>{$subscription['expires']}</td>
						<td>{$subscription['plan_id']}</td>
						<td>{$subscription['plan_name']}</td>
						<td>{$subscription['price']}</td>
						<td>{$subscription['subscription_id']}</td>
					</tr>
";
			}
		} else {
			$html .= "
					<tr>
						<td colspan='".1."'>".wfMessage('no_subscriptions_found')->escaped()."</td>
					</tr>
			";
		}
		$html .= "
				</tbody>
			</table>";

		$html .= $pagination;

		return $html;
	}
}
