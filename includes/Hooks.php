<?php
namespace MediaWiki\Extension\UTDRTweaks;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Html\Html;
use Skin;

class Hooks {
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
}
