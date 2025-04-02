<?php

namespace MediaWiki\Extension\UTDRTweaks\Special;

use MediaWiki\SpecialPage\FormSpecialPage;

class AnalyticsOptOut extends FormSpecialPage {
	public function __construct() {
		parent::__construct( 'AnalyticsOptOut' );
	}
	protected function getDisplayFormat() {
		return 'codex';
	}
	protected function preHtml() {
		$this->getOutput()->addHtml( $this->msg( 'utdr-optout-message' )->parse() );
	}
	protected function getFormFields() {
		return [
			'optout' => [
				'type' => 'check',
				'label-message' => 'utdr-optout',
				'name' => 'wpOptout',
				'id' => 'wpOptout',
			],
		];
	}
	protected function postHtml() {
		$this->getOutput()->addModules( [ 'ext.utdrtweaks.optout.scripts' ] );
	}
	public function onSubmit( array $data ) {
		return;
	}
}
