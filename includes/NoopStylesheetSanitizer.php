<?php

namespace MediaWiki\Extension\UTDRTweaks;

use Wikimedia\CSS\Objects\CSSObject;
use Wikimedia\CSS\Sanitizer\StylesheetSanitizer;

/**
 * A no-operation stylesheet sanitizer that does not modify the CSS and reports
 * no errors.
 */
class NoopStylesheetSanitizer extends StylesheetSanitizer {
	/** @inheritDoc */
	public function sanitize( CSSObject $css ): CSSObject {
		return $css;
	}
	/** @inheritDoc */
	public function getSanitizationErrors(): array {
		return [];
	}
	/** @inheritDoc */
	public function clearSanitizationErrors(): void {}
}
