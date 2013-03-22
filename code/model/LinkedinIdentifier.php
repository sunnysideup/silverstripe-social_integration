<?php

class LinkedinIdentifier extends DataObjectDecorator {

	public function extraStatics() {
		return array(
			'db' => array(
				'LinkedinID' => 'Varchar',
				'LinkedinFirstName' => 'Varchar(255)',
				'LinkedinLastName' => 'Varchar(255)',
				'LinkedinPicture' => 'Text',
				'LinkedinEmail' => 'Varchar(255)'
			),
			'indexes' => array(
				'LinkedinID' => true,
				'LinkedinEmail' => true
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

	/**
	 * link to profile
	 * @return String
	 */
	public function LinkedinLink(){
		if($this->owner->LinkedinID) {
			return "http://www.linkedin.com/profile/view?id=".$this->owner->LinkedinID;
		}
		return "";
	}

	function onBeforeWrite(){
		if(!$this->owner->Email) {
			if($this->owner->LinkedinEmail) {
				$id = $this->owner->ID;
				if(!$id) {
					$id = 0;
				}
				if(!DataObject::get_one("Member", "\"Email\" = '".$this->owner->LinkedinEmail."' AND \"Member\".\"ID\" <> ".$id."")) {
					$this->owner->Email = $this->owner->LinkedinEmail;
				}
			}
		}
	}

}

