<?php
/**
 * Curse Inc.
 * Subscription
 * Subscription Template
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		GNU General Public License v2.0 or later
 * @package Subscription
 * @link		https://gitlab.com/hydrawiki
 *
**/

class TemplateSubscription {
	/**
	 * Wiki Sites
	 *
	 * @param array Array of subscription information.
	 * @param array Pagination
	 * @param array Minimum and Maximum Filter Values
	 * @param string [Optional] Data sorting key
	 * @param string [Optional] Data sorting direction
	 * @param string [Optional] Search Term
	 * @return string Built HTML
	 */
	public function subscriptionList($subscriptions, $pagination, $filterValues, $sortKey = 'user_id', $sortDir = 'ASC', $searchTerm = null) {
		global $wgOut, $wgUser, $wgRequest;

		$subscriptionPage = Title::newFromText('Special:Subscription');
		$subscriptionURL = $subscriptionPage->getFullURL();

		$html = "
			<div class='filter_bar'>
				<form method='get' action='{$subscriptionURL}'>
					<fieldset>
						<input id='filtervalues' type='hidden' value='".htmlspecialchars(json_encode($filterValues, JSON_UNESCAPED_SLASHES), ENT_QUOTES)."'/>
						<input type='hidden' name='section' value='list'/>
						<input type='hidden' name='do' value='search'/>
						<button type='submit' class='mw-ui-button mw-ui-progressive'>".wfMessage('filter')->escaped()."</button>
						<a href='{$subscriptionURL}?do=resetSearch' class='mw-ui-button mw-ui-destructive'>".wfMessage('list_reset')->escaped()."</a>
						<input type='text' name='list_search' value='".htmlentities($searchTerm, ENT_QUOTES)."' class='search_field' placeholder='".wfMessage('search_users')->escaped()."'/>
						<label for='price'>".wfMessage('price_range')->escaped()."</label>
						<div id='price'>
							<input type='hidden' name='min_price' value=''/>
							<input type='hidden' name='max_price' value=''/>
						</div>
						<label for='begins_after' class='label_above'>".wfMessage('begins_after')->escaped()."</label>
						<input id='begins_after' data-input='min_date' type='text' value=''/>
						<input id='min_date' name='min_date' type='hidden' value='".htmlentities((!empty($filterValues['user']['date']['min_date']) ? wfTimestamp(TS_UNIX, $filterValues['user']['date']['min_date']) : ''))."'/>

						<label for='expires_before' class='label_above'>".wfMessage('expires_before')->escaped()."</label>
						<input id='expires_before' data-input='max_date' type='text' value=''/>
						<input id='max_date' name='max_date' type='hidden' value='".htmlentities((!empty($filterValues['user']['date']['max_date']) ? wfTimestamp(TS_UNIX, $filterValues['user']['date']['max_date']) : ''))."'/>
					</fieldset>
				</form>
			</div>
			<div id='subscription_list'>
				<div>{$pagination}<span id='subscription_statistics'>".wfMessage("subscriber_statistics")->params(\Hydra\SubscriptionCache::getStatistics())->escaped()."</span></div>
				<table class='with_filters'>
					<thead>
						<tr class='sortable' data-sort-dir='".($sortDir == 'desc' ? 'desc' : 'asc')."'>
							<th".($sortKey == 'user' ? " data-selected='true'" : '')."><span data-sort='user'".($sortKey == 'user' ? " data-selected='true'" : '').">".wfMessage('sub_th_user')->escaped()."</span></th>
							<th".($sortKey == 'provider_id' ? " data-selected='true'" : '')."><span data-sort='provider_id'".($sortKey == 'provider_id' ? " data-selected='true'" : '').">".wfMessage('sub_th_provider_id')->escaped()."</span></th>
							<th".($sortKey == 'active' ? " data-selected='true'" : '')."><span data-sort='active'".($sortKey == 'active' ? " data-selected='true'" : '').">".wfMessage('sub_th_active')->escaped()."</span></th>
							<th".($sortKey == 'begins' ? " data-selected='true'" : '')."><span data-sort='begins'".($sortKey == 'begins' ? " data-selected='true'" : '').">".wfMessage('sub_th_begins')->escaped()."</span></th>
							<th".($sortKey == 'expires' ? " data-selected='true'" : '')."><span data-sort='expires'".($sortKey == 'expires' ? " data-selected='true'" : '').">".wfMessage('sub_th_expires')->escaped()."</span></th>
							<th".($sortKey == 'plan_name' ? " data-selected='true'" : '')."><span data-sort='plan_name'".($sortKey == 'plan_name' ? " data-selected='true'" : '').">".wfMessage('sub_th_plan_name')->escaped()."</span></th>
							<th".($sortKey == 'price' ? " data-selected='true'" : '')." class='collapse'><span data-sort='price'".($sortKey == 'price' ? " data-selected='true'" : '').">".wfMessage('sub_th_price')->escaped()."</span></th>
							<th".($sortKey == 'subscription_id' ? " data-selected='true'" : '')."><span data-sort='subscription_id'".($sortKey == 'subscription_id' ? " data-selected='true'" : '').">".wfMessage('sub_th_subscription_id')->escaped()."</span></th>
						</tr>
					</thead>
					<tbody>
				";
		if (is_array($subscriptions) && count($subscriptions)) {
			foreach ($subscriptions as $subscription) {
				$lookup = CentralIdLookup::factory();
				$user = User::newFromId($subscription['user_id']);
				$html .= "
						<tr>
							<td>".($user !== null ? $user->getName() : $subscription['user_id'])."</td>
							<td>{$subscription['provider_id']}</td>
							<td class='active'>".(isset($subscription['active']) && $subscription['active'] ? "✅" : "&nbsp;")."</td>
							<td>".(isset($subscription['begins']) ? wfTimestamp(TS_DB, $subscription['begins']) : "&nbsp;")."</td>
							<td>".(isset($subscription['expires']) ? wfTimestamp(TS_DB, $subscription['expires']) : "&nbsp;")."</td>
							<td>".(isset($subscription['plan_name']) && !empty($subscription['plan_name']) ? $subscription['plan_name'] : "&nbsp;").(isset($subscription['plan_id']) && !empty($subscription['plan_id']) ? " <em>({$subscription['plan_id']})</em>" : "&nbsp;")."</td>
							<td class='collapse'>".number_format($subscription['price'], 2)."</td>
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
				</table>
				{$pagination}
			</div>";

		return $html;
	}

	/**
	 * Wiki Sites
	 *
	 * @param array
	 * @return string Built HTML
	 */
	public function subscriptionGrant($formValues = null) {

		$subscriptionPage = Title::newFromText('Special:Subscription_Grant');
		$subscriptionURL = $subscriptionPage->getFullURL();

		$html = "
			<div id='sub-grant'>
				<form method='get' action='{$subscriptionURL}'>
					<fieldset>
						<input type='hidden' name='do' value='grant_subscription'/>
						<input type='text' name='username' value='".htmlentities((!empty($formValues['username']) ? $formValues['username'] : ''))."' placeholder='".wfMessage('username')->escaped()."'/>
						<label for='duration'>".wfMessage('subscription_duration')->escaped()."</label>
						<input type='duration' id='duration' name='duration' value='".htmlentities((!empty($formValues['duration']) ? $formValues['duration'] : ''))."' placeholder='".wfMessage('duration_placeholder')->escaped()."'/>
						<label for='overwriteSub'>".wfMessage('subscription_overwrite')->escaped()."</label>
						<input type='checkbox' id='overwriteSub' name='overwriteSub' value='checked' ".htmlentities((!empty($formValues['overwriteSub']) ? $formValues['overwriteSub'] : '')).">
						<button type='submit' class='mw-ui-button mw-ui-progressive'>".wfMessage('grant-subscription')->escaped()."</button>
					</fieldset>
				</form>
			</div>
				";

		return $html;
	}
}
