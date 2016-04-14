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
	 * @param	array	Minimum and Maximum Filter Values
	 * @param	string	[Optional] Data sorting key
	 * @param	string	[Optional] Data sorting direction
	 * @param	string	[Optional] Search Term
	 * @return	string	Built HTML
	 */
	public function subscriptionList($subscriptions, $pagination, $filterValues, $sortKey = 'user_id', $sortDir = 'ASC', $searchTerm = null) {
		global $wgOut, $wgUser, $wgRequest;

		$subscriptionPage = Title::newFromText('Special:Subscription');
		$subscriptionURL = $subscriptionPage->getFullURL();

		$html = $pagination;

		$html .= "
			<div class='filter_bar'>
				<form method='get' action='{$subscriptionURL}'>
					<fieldset>
						<input id='filtervalues' type='hidden' value='".htmlspecialchars(json_encode($filterValues, JSON_UNESCAPED_SLASHES), ENT_QUOTES)."'/>
						<input type='hidden' name='section' value='list'/>
						<input type='hidden' name='do' value='search'/>
						<button type='submit' class='mw-ui-button mw-ui-progressive'>".wfMessage('list_search')->escaped()."</button>
						<button type='submit' formaction='{$subscriptionURL}?do=resetSearch' class='mw-ui-button mw-ui-destructive'>".wfMessage('list_reset')->escaped()."</button>
						<input type='text' name='list_search' value='".htmlentities($searchTerm, ENT_QUOTES)."' class='search_field' placeholder='".wfMessage('search')->escaped()."'/>
						<label for='price'>".wfMessage('price_range')->escaped()."</label>
						<div id='price'></div>
					</fieldset>
				</form>
			</div>
			<table id='subscription_list' class='with_filters'>
				<thead>
					<tr class='sortable' data-sort-dir='".($sortDir == 'desc' ? 'desc' : 'asc')."'>
						<th".($sortKey == 'user' ? " data-selected='true'" : '')."><span data-sort='user'".($sortKey == 'user' ? " data-selected='true'" : '').">".wfMessage('sub_th_user')->escaped()."</span></th>
						<th".($sortKey == 'provider_id' ? " data-selected='true'" : '')."><span data-sort='provider_id'".($sortKey == 'provider_id' ? " data-selected='true'" : '').">".wfMessage('sub_th_provider_id')->escaped()."</span></th>
						<th".($sortKey == 'active' ? " data-selected='true'" : '')."><span data-sort='active'".($sortKey == 'active' ? " data-selected='true'" : '').">".wfMessage('sub_th_active')->escaped()."</span></th>
						<th".($sortKey == 'begins' ? " data-selected='true'" : '')."><span data-sort='begins'".($sortKey == 'begins' ? " data-selected='true'" : '').">".wfMessage('sub_th_begins')->escaped()."</span></th>
						<th".($sortKey == 'expires' ? " data-selected='true'" : '')."><span data-sort='expires'".($sortKey == 'expires' ? " data-selected='true'" : '').">".wfMessage('sub_th_expires')->escaped()."</span></th>
						<th".($sortKey == 'plan_name' ? " data-selected='true'" : '')."><span data-sort='plan_name'".($sortKey == 'plan_name' ? " data-selected='true'" : '').">".wfMessage('sub_th_plan_name')->escaped()."</span></th>
						<th".($sortKey == 'price' ? " data-selected='true'" : '')."><span data-sort='price'".($sortKey == 'price' ? " data-selected='true'" : '').">".wfMessage('sub_th_price')->escaped()."</span></th>
						<th".($sortKey == 'subscription_id' ? " data-selected='true'" : '')."><span data-sort='subscription_id'".($sortKey == 'subscription_id' ? " data-selected='true'" : '').">".wfMessage('sub_th_subscription_id')->escaped()."</span></th>
					</tr>
				</thead>
				<tbody>
				";
		if (is_array($subscriptions) && count($subscriptions)) {
			foreach ($subscriptions as $subscription) {
				$lookup = \CentralIdLookup::factory();
				$user = $lookup->localUserFromCentralId($subscription['global_id'], \CentralIdLookup::AUDIENCE_RAW);
				$html .= "
					<tr>
						<td>".($user !== null ? $user->getName() : $subscription['global_id'])."</td>
						<td>{$subscription['provider_id']}</td>
						<td class='active'>".(isset($subscription['active']) && $subscription['active'] ? "✅" : "&nbsp;")."</td>
						<td>".(isset($subscription['begins']) ? wfTimestamp(TS_DB, $subscription['begins']) : "&nbsp;")."</td>
						<td>".(isset($subscription['expires']) ? wfTimestamp(TS_DB, $subscription['expires']) : "&nbsp;")."</td>
						<td>".(isset($subscription['plan_name']) && !empty($subscription['plan_name']) ? $subscription['plan_name'] : "&nbsp;").(isset($subscription['plan_id']) && !empty($subscription['plan_id']) ? " <em>({$subscription['plan_id']})</em>" : "&nbsp;")."</td>
						<td>".number_format($subscription['price'], 2)."</td>
						<td>{$subscription['subscription_id']}</td>
					</tr>
";
			}
		} else {
			$html .= "
					<tr>
						<td colspan='8'>".wfMessage('no_subscriptions_found')->escaped()."</td>
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
