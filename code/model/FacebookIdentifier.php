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


	/**
	 * link to profile
	 * @return String
	 */
	public function FacebookLink(){
		if($this->owner->FacebookURL) {
			return $this->owner->FacebookURL;
		}
		if($this->owner->FacebookID) {
			return "http://www.facebook.com/people/@/".$this->owner->FacebookID;
		}
		return "";
	}

	function onBeforeWrite(){
		if(!$this->owner->Email) {
			if($this->owner->FacebookEmail) {
				$id = $this->owner->ID;
				if(!$id) {
					$id = 0;
				}
				if(!DataObject::get_one("Member", "\"Email\" = '".$this->owner->FacebookEmail."' AND \"Member\".\"ID\" <> ".$id."")) {
					$this->owner->Email = $this->owner->FacebookEmail;
				}
			}
		}
	}

}
