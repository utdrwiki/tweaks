<?php
namespace MediaWiki\Extension\UTDRTweaks;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Hook\ImageBeforeProduceHTMLHook;
use MediaWiki\Html\Html;
use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;
use Skin;
use File;

class Hooks implements ImageBeforeProduceHTMLHook {
	private const CAPTION_REQUIRED_MEDIA_TYPES = [
		MEDIATYPE_BITMAP,
		MEDIATYPE_DRAWING,
		MEDIATYPE_VIDEO,
	];

	/**
	 * Updates the WebP media handler to allow animated WebP images.
	 * @return void
	 */
	public static function initTweaks(): void {
		global $wgMediaHandlers;
		$wgMediaHandlers[ 'image/webp' ] = 'MediaWiki\\Extension\\UTDRTweaks\\MediaHandler\\UTDRWebPHandler';
	}

	/**
	 * Moves all notifications to the 'alert' section, because our skin only
	 * displays that section.
	 * @param array $notifications Value of $wgEchoNotifications
	 * @param array $notificationCategories Value of $wgEchoNotificationCategories
	 * @param array $notificationIcons Value of $wgEchoNotificationIcons
	 * @return void
	 */
	public static function onBeforeCreateEchoEvent(
		array &$notifications,
		array &$notificationCategories,
		array &$notificationIcons
	) {
		foreach ( $notifications as &$notificationConfig ) {
			$notificationConfig['section'] = 'alert';
		}
	}

	/**
	 * Adds a checkbox to the user registration form to accept the Terms of Service.
	 * @param \MediaWiki\Auth\AuthenticationRequest[] $requests Array of AuthenticationRequests the fields are created from
	 * @param array $fieldInfo Field information array
	 * @param array $formDescriptor HTMLForm descriptor. The special key 'weight' can be set to change the order of the fields.
	 * @param string $action One of the AuthManager::ACTION_* constants
	 * @return void
	 */
	public static function onAuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ): void {
		if ( $action !== AuthManager::ACTION_CREATE ) {
			return;
		}
		$formDescriptor['tos'] = [
			'type' => 'check',
			'label-message' => 'utdr-accept-tos',
			'name' => 'wpTos',
			'id' => 'wpTos',
			'required' => true,
		];
	}

	/**
	 * Replace footer with only Terms of Service and Privacy Policy links.
	 * @param Skin $skin Skin object
	 * @param string $key Key of the footer link
	 * @param array $footerlinks Array of footer links
	 */
	public static function onSkinAddFooterLinks( Skin $skin, string $key, array &$footerlinks ): void {
		if ( $key === 'places' ) {
			$footerlinks = [
				'terms-of-service' => Html::element( 'a', [
					'href' => 'https://undertale.wiki/w/Undertale_Wiki:Terms_of_Service',
				], $skin->msg( 'utdr-terms-of-service' )->text() ),
				'privacy-policy' => Html::element( 'a', [
					'href' => 'https://undertale.wiki/w/Undertale_Wiki:Privacy_Policy',
				], $skin->msg( 'utdr-privacy-policy' )->text() ),
			];
		}
	}

	/**
	 * Fall back to a file's page title whenever an alt text is missing.
	 * @param null $unused Will always be null
	 * @param Title &$title Title object of the image
	 * @param File|false &$file File object, or false if it doesn't exist
	 * @param array &$frameParams Various parameters with special meanings; see documentation in
	 *   includes/Linker.php for Linker::makeImageLink
	 * @param array &$handlerParams Various parameters with special meanings; see documentation in
	 *   includes/Linker.php for Linker::makeImageLink
	 * @param string|bool &$time Timestamp of file in 'YYYYMMDDHHIISS' string
	 *   form, or false for current
	 * @param string &$res Final HTML output, used if you return false
	 * @param Parser $parser
	 * @param string &$query Query params for desc URL
	 * @param string &$widthOption Used by the parser to remember the user preference thumbnailsize
	 */
	public function onImageBeforeProduceHTML( $unused, &$title, &$file,
		&$frameParams, &$handlerParams, &$time, &$res, $parser, &$query, &$widthOption ): void {
		if ( empty( $frameParams['alt'] ) && $file ) {
			$title = $file->getTitle();
			$frameParams['alt'] = $title ? $title->getText() : "";
		}
		if (
			empty( $frameParams['caption'] ) &&
			$file &&
			in_array( $file->getMediaType(), self::CAPTION_REQUIRED_MEDIA_TYPES )
		) {
			$parser->addTrackingCategory( 'utdr-category-pages-without-captions' );
		}
	}

	  /**
     * Changes the selflink to a span instead of a link, since it's not actually
     * a link.
     * @param \MediaWiki\Title\Title $nt Title object that the link leads to
     * @param mixed $html HTML output of the link
     * @param mixed $trail Trailing HTML
     * @param mixed $prefix Prefix HTML
     * @param mixed $ret Return value
     * @return bool False to stop processing
     */
    public static function onSelfLinkBegin( Title $nt, &$html, &$trail, &$prefix, &$ret ): bool {
        $ret = Html::rawElement( 'span', [
            'class' => 'mw-selflink selflink'
        ], $prefix . $html ) . $trail;
        return false;
    }


	/**
	 * Marks user pages and talk pages as always known, so that they show up
	 * as blue links instead of red links.
	 * @param \MediaWiki\Title\Title $title Title object that is being checked
	 * @param bool|null &$isKnown Whether MediaWiki currently thinks this page is known
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public static function onTitleIsAlwaysKnown( $title, &$isKnown ) {
		if ( $title->isTalkPage() || $title->inNamespace( NS_USER ) ) {
			$isKnown = true;
		}
	}

	/**
	 * Don't count talk pages as wanted.
	 * @param \MediaWiki\Specials\SpecialWantedPages $wantedPages
	 * @param array &$query Query array. See QueryPage::getQueryInfo() for format documentation.
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public static function onWantedPages__getQueryInfo( &$wantedPages, &$query ) {
		$query['conds'][] = 'lt_namespace % 2 = 0';
	}

	/**
	 * Turn more URLs into fancy URLs.
	 * Taken from GloopTweaks, by TehKittyCat and Jayden.
	 */
	public static function onGetLocalURL( $title, &$url, $query ) {
		global $wgArticlePath, $wgScript, $wgScriptPath;
		$dbkey = wfUrlencode( $title->getPrefixedDBkey() );
		if ( $title->isMainPage() ) {
			$url = wfAppendQuery( $wgScriptPath . '/', $query );
		} elseif ( $url == "{$wgScript}?title={$dbkey}&{$query}" ) {
			$url = wfAppendQuery( str_replace( '$1', $dbkey, $wgArticlePath ), $query );
		}
	}
}
