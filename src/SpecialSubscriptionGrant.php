<?php
/**
 * Curse Inc.
 * Subscription
 * Subscription Grant Special Page
 *
 * @package   Subscription
 * @author    Michael Chaudhary
 * @copyright (c) 2019 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace Subscription;

use MediaWiki\User\UserFactory;
use OutputPage;
use SpecialPage;
use Subscription\Providers\GamepediaPro;
use WebRequest;

class SpecialSubscriptionGrant extends SpecialPage {
	public function __construct( private UserFactory $userFactory, private GamepediaPro $gamepediaPro ) {
		parent::__construct( 'SubscriptionGrant', 'subscription', true );
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		$this->checkPermissions();
		$this->setHeaders();
		$output = $this->getOutput();
		$request = $this->getRequest();

		$output->setPageTitle( $this->msg( 'subscriptiongrant' )->escaped() );
		$output->addModules( [ 'ext.subscription' ] );

		$template = new TemplateSubscription();

		if ( !$request->wasPosted() ) {
			$output->addHTML( $template->subscriptionGrant() );
			return;
		}

		switch ( $request->getVal( 'do' ) ) {
			case 'lookup':
				$this->lookupSubscriptionInfo( $request, $output );
				$output->addHTML( $template->subscriptionGrant() );
				return;
			case 'grant_subscription':
				$formData = $this->grantSubscription( $request, $output );
				$output->addHTML( $template->subscriptionGrant( $formData ) );
				return;
		}
	}

	private	function lookupSubscriptionInfo( WebRequest $request, OutputPage $output ): void {
		$username = trim( $request->getVal( 'username' ) );
		$user = $this->userFactory->newFromName( $username );
		if ( !$user || !$user->getId() ) {
			$output->addHTML( "<span class='error'>User not found.</span><br/>" );
			return;
		}

		$subInfo = $this->gamepediaPro->getSubscription( $user->getId() );

		$message = $subInfo['active'] ?
			"The subscription for {$user->getName()} expires on {$subInfo['expires']->getHumanTimestamp()}." :
			"{$user->getName()} does not have an active subscription.";

		$output->addHTML( "<span class='success'>$message</span><br/>" );
	}

	private function grantSubscription( WebRequest $request, OutputPage $output ): array {
		$username = trim( $request->getVal( 'username' ) );
		$subscriptionDuration = trim( $request->getVal( 'duration' ) );
		$overwriteSub = $request->getVal( 'overwriteSub' );
		$formData = [
			'username' => $username,
			'duration' => $subscriptionDuration,
			'overwriteSub' => $overwriteSub
		];

		if ( !$this->isValidSubscriptionDuration( $subscriptionDuration ) ) {
			$output->addHTML( "<span class='error'>Invalid subscription duration</span><br/>" );
			return $formData;
		}

		$user = $this->userFactory->newFromName( $username );
		if ( !$user || !$user->isRegistered() ) {
			$output->addHTML( "<span class='error'>Invalid username</span><br/>" );
			$output->addHTML( htmlspecialchars( $username ) . " User ID: 0" );
			return $formData;
		}

		$userId = $user->getId();
		// Cancel any existing subscription before applying a new one
		if ( $overwriteSub === 'checked' || $subscriptionDuration == 0 ) {
			$cancel = $this->gamepediaPro->cancelCompedSubscription( $userId );
			$message = $cancel ? 'Existing subscription cancelled.' : 'Failed to cancel subscription.';
			$output->addHTML( "<span class='success'>$message</span><br/>" );
			return $formData;
		}

		$createSubResult = $this->gamepediaPro->createCompedSubscription( $userId, $subscriptionDuration );
		if ( $createSubResult === false ) {
			$output->addHTML( "<span class='error'>Error creating subscription</span><br/>" );

			// Usually what went wrong is the existing subscritpion wasn't cancelled first
			$subInfo = $this->gamepediaPro->getSubscription( $userId );
			if ( is_array( $subInfo ) && $subInfo['active'] ) {
				$expiresAt = $subInfo['expires']->getHumanTimestamp();
				$output->addHTML( "<span class='error'>Subscription for " . htmlspecialchars( $username ) . "
										already exists, ending on " . $expiresAt . "<br/>
										You'll need to overwrite the existing subscription.</span><br/>" );
			}
		}

		$message = $createSubResult ?
			'Comped subscription successfully created.' :
			'Failed to create comped subscription.';
		$output->addHTML( "<span class='success'>$message</span><br/>" );
		return $formData;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'users';
	}

	private function isValidSubscriptionDuration( $duration ): bool {
		if ( !is_numeric( $duration ) || $duration < 0 || $duration > 100 ) {
			return false;
		}
		return true;
	}
}
