<?php

/**
 * adds Facebook functionality to Member.
 *
 *
 *
 */

class FacebookIdentifier extends DataObjectDecorator {

	public function extraStatics() {
		return array(
			'db' => array(
				'FacebookID' => 'Varchar',
				'FacebookURL' => 'Varchar(255)',
				'FacebookPicture' => 'Text',
				'FacebookName' => 'Varchar(255)',
				'FacebookEmail' => 'Varchar(255)',
				'FacebookFirstName' => 'Varchar(255)',
				'FacebookMiddleName' => 'Varchar(255)',
				'FacebookLastName' => 'Varchar(255)',
				'FacebookUsername' => 'Varchar(255)'
			),
			'indexes' => array(
				'FacebookID' => true,
				'FacebookUsername' => true
			),
			'casting' => array(
				'FacebookButton' => 'ArrayData'
			)
		);
	}


	/**
	 * connect and disconnect button
	 * @return Object (IsConnected, Link, ConnectedName, ConnectedImageURL)
	 */
	public function getFacebookButton($backURL = "") {
		return FacebookCallback::get_login_button($backURL, $this->owner);
	}

	/**
	 *
	 * user has logged in with facebook before?
	 * @return Boolean
	 */
	public function hasFacebook() {
		return (bool)$this->owner->FacebookID;
	}

	/**
	 * user is currenctly connected to Faceboo
	 *
	 *
	 */
	public function isConnectedToFacebook() {
		return FacebookCallback::get_current_user();
	}

}
