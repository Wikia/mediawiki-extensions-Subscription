<?php

namespace Subscription;

use SpecialPage;

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
 */

class TemplateSubscription {
	public function subscriptionGrant( ?array $formValues = null ): string {
		$subscriptionURL = SpecialPage::getSafeTitleFor( 'SubscriptionGrant' )->getFullURL();

		$html = "
			<div id='lookup'>
				<form method='POST' action='{$subscriptionURL}'>
					<fieldset>
						<input type='hidden' name='do' value='lookup'/>
						<input type='text' name='username' value='" . htmlentities( ( !empty( $formValues['username'] ) ? $formValues['username'] : '' ) ) . "' placeholder='" . wfMessage( 'username' )->escaped() . "'/>
						<button type='submit' class='mw-ui-button mw-ui-progressive'>" . wfMessage( 'lookup' )->escaped() . "</button>
					</fieldset>
				</form>
			</div>
			<div id='sub-grant'>
				<form method='POST' action='{$subscriptionURL}'>
					<fieldset>
						<input type='hidden' name='do' value='grant_subscription'/>
						<input type='text' name='username' value='" . htmlentities( ( !empty( $formValues['username'] ) ? $formValues['username'] : '' ) ) . "' placeholder='" . wfMessage( 'username' )->escaped() . "'/>
						<label for='duration'>" . wfMessage( 'subscription_duration' )->escaped() . "</label>
						<input type='duration' id='duration' name='duration' value='" . htmlentities( ( !empty( $formValues['duration'] ) ? $formValues['duration'] : '' ) ) . "' placeholder='" . wfMessage( 'duration_placeholder' )->escaped() . "'/>
						<label for='overwriteSub'>" . wfMessage( 'subscription_overwrite' )->escaped() . "</label>
						<input type='checkbox' id='overwriteSub' name='overwriteSub' value='checked' " . htmlentities( ( !empty( $formValues['overwriteSub'] ) ? $formValues['overwriteSub'] : '' ) ) . ">
						<button type='submit' class='mw-ui-button mw-ui-progressive'>" . wfMessage( 'grant-subscription' )->escaped() . "</button>
					</fieldset>
				</form>
			</div>";

		return $html;
	}
}
