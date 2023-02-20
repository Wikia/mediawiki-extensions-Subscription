<?php

declare( strict_types=1 );

use MediaWiki\MediaWikiServices;
use Subscription\Providers\GamepediaPro;
use Subscription\Subscription;

return [
	GamepediaPro::class => static function ( MediaWikiServices $services ): GamepediaPro {
		return new GamepediaPro(
			$services->getUserIdentityLookup(),
			$services->getUserOptionsLookup(),
			$services->getUserOptionsManager()
		);
	},
	Subscription::class => static function ( MediaWikiServices $services ): Subscription {
		return new Subscription( [ $services->getService( GamepediaPro::class ) ] );
	},
];
