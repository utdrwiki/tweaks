<?php

namespace MediaWiki\Extension\UTDRTweaks\Preferences;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Preferences\DefaultPreferencesFactory;
use MediaWiki\User\User;

class UTDRPreferencesFactory extends DefaultPreferencesFactory {
	public function __construct(
		private readonly ?array $supportedLanguages,
		...$args
	) {
		parent::__construct( ...$args );
	}

	/** @inheritDoc */
	protected function profilePreferences(
		User $user, IContextSource $context, &$defaultPreferences
	) {
		parent::profilePreferences( $user, $context, $defaultPreferences );
		if ( $this->supportedLanguages !== null ) {
			$defaultPreferences['language']['options'] = array_filter(
				$defaultPreferences['language']['options'],
				fn ( $code ) => in_array( $code, $this->supportedLanguages, true )
			);
		}
	}
}
