<?php

class LinkedinIdentifier extends DataObjectDecorator {

	public function extraStatics() {
		return array(
			'db' => array(
				'LinkedinID' => 'Varchar',
				'LinkedinName' => 'Varchar',
				'LinkedinPicture' => 'Text'
			),
			'indexes' => array(
				'LinkedinID' => true,
				'LinkedinName' => true
			),
			'casting' => array(
				'LinkedinButton' => 'ArrayData'
			)
		);
	}


	/**
	 * connect and disconnect button
	 * @return Object (IsConnected, Link, ConnectedName, ConnectedImageURL)
	 */
	public function getLinkedinButton($backURL = "") {
		return LinkedinCallback::get_login_button($backURL, $this->owner);
	}

	/**
	 *
	 * user has logged in with facebook before?
	 * @return Boolean
	 */
	public function hasLinkedin() {
		return (bool)$this->owner->LinkedinID;
	}

	/**
	 * user is currenctly connected to Faceboo
	 *
	 *
	 */
	public function isConnectedToLinkedin() {
		return LinkedinCallback::get_current_user();
	}
}

