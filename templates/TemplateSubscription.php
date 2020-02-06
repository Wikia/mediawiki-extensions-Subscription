<?php
/**
 * Curse Inc.
 * Subscription
 * Subscription Template
 *
 * @author    Alexia E. Smith
 * @copyright (c) 2016 Curse Inc.
 * @license   GPL-2.0-or-later
 * @package   Subscription
 * @link      https://gitlab.com/hydrawiki
**/

class TemplateSubscription {
	/**
	 * Wiki Sites
	 *
	 * @param  array Array of subscription information.
	 * @param  array Pagination
	 * @param  array Minimum and Maximum Filter Values
	 * @param  string [Optional] Data sorting key
	 * @param  string [Optional] Data sorting direction
	 * @param  string [Optional] Search Term
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
						<input id='filtervalues' type='hidden' value='" . htmlspecialchars(json_encode($filterValues, JSON_UNESCAPED_SLASHES), ENT_QUOTES) . "'/>
						<input type='hidden' name='section' value='list'/>
						<input type='hidden' name='do' value='search'/>
						<button type='submit' class='mw-ui-button mw-ui-progressive'>" . wfMessage('filter')->escaped() . "</button>
						<a href='{$subscriptionURL}?do=resetSearch' class='mw-ui-button mw-ui-destructive'>" . wfMessage('list_reset')->escaped() . "</a>
						<input type='text' name='list_search' value='" . htmlentities($searchTerm, ENT_QUOTES) . "' class='search_field' placeholder='" . wfMessage('search_users')->escaped() . "'/>
						<label for='expires_before' class='label_above'>" . wfMessage('expires_before')->escaped() . "</label>
						<input id='expires_before' data-input='max_date' type='text' value=''/>
						<input id='max_date' name='max_date' type='hidden' value='" . htmlentities((!empty($filterValues['user']['date']['max_date']) ? wfTimestamp(TS_UNIX, $filterValues['user']['date']['max_date']) : '')) . "'/>
					</fieldset>
				</form>
			</div>
			<div id='subscription_list'>
				<div>{$pagination}<span id='subscription_statistics'>" . wfMessage("subscriber_statistics")->params(array_values(\Hydra\SubscriptionCache::getStatistics()))->escaped() . "<a href='".SpecialPage::getTitleFor('SubscriptionGrant')->getFullUrl()."' class='mw-ui-button'>" . wfMessage('give_grant')->escaped() . "</a></span></div>
				<table class='with_filters'>
					<thead>
						<tr class='sortable' data-sort-dir='" . ($sortDir == 'desc' ? 'desc' : 'asc') . "'>
							<th" . ($sortKey == 'user' ? " data-selected='true'" : '') . "><span data-sort='user'" . ($sortKey == 'user' ? " data-selected='true'" : '') . ">" . wfMessage('sub_th_user')->escaped() . "</span></th>
							<th" . ($sortKey == 'active' ? " data-selected='true'" : '') . "><span data-sort='active'" . ($sortKey == 'active' ? " data-selected='true'" : '') . ">" . wfMessage('sub_th_active')->escaped() . "</span></th>
							<th" . ($sortKey == 'expires' ? " data-selected='true'" : '') . "><span data-sort='expires'" . ($sortKey == 'expires' ? " data-selected='true'" : '') . ">" . wfMessage('sub_th_expires')->escaped() . "</span></th>
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
							<td>" . ($user !== null ? $user->getName() : $subscription['user_id']) . "</td>
							<td class='active'>" . (isset($subscription['active']) && $subscription['active'] ? "✅" : "&nbsp;") . "</td>
							<td>" . (isset($subscription['expires']) ? wfTimestamp(TS_DB, $subscription['expires']) : "&nbsp;") . "</td>
						</tr>
";
			}
		} else {
			$html .= "
						<tr>
							<td colspan='8'>" . wfMessage('no_subscriptions_found')->escaped() . "</td>
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
	 * Grant Subscriptions
	 *
	 * @param  array
	 * @return string Built HTML
	 */
	public function subscriptionGrant($formValues = null) {
		$subscriptionPage = Title::newFromText('Special:SubscriptionGrant');
		$subscriptionURL = $subscriptionPage->getFullURL();

		$html = "
			<div id='sub-grant'>
				<form method='POST' action='{$subscriptionURL}'>
					<fieldset>
						<input type='hidden' name='do' value='grant_subscription'/>
						<input type='text' name='username' value='" . htmlentities((!empty($formValues['username']) ? $formValues['username'] : '')) . "' placeholder='" . wfMessage('username')->escaped() . "'/>
						<label for='duration'>" . wfMessage('subscription_duration')->escaped() . "</label>
						<input type='duration' id='duration' name='duration' value='" . htmlentities((!empty($formValues['duration']) ? $formValues['duration'] : '')) . "' placeholder='" . wfMessage('duration_placeholder')->escaped() . "'/>
						<label for='overwriteSub'>" . wfMessage('subscription_overwrite')->escaped() . "</label>
						<input type='checkbox' id='overwriteSub' name='overwriteSub' value='checked' " . htmlentities((!empty($formValues['overwriteSub']) ? $formValues['overwriteSub'] : '')) . ">
						<button type='submit' class='mw-ui-button mw-ui-progressive'>" . wfMessage('grant-subscription')->escaped() . "</button>
					</fieldset>
				</form>
			</div>
				";

		return $html;
	}
}
