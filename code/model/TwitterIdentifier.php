<?php

/***
 * adds twitter functionality to Member
 *
 * email issue: https://dev.twitter.com/discussions/4019
 *
 *
 */


class TwitterIdentifier extends DataObjectDecorator {

	//TwitterHandle
	//TwitterAccessToken
	public function extraStatics() {
		return array(
			'db' => array(
				'TwitterID' => 'Varchar',
				'TwitterToken' => 'Varchar(100)',
				'TwitterSecret' => 'Varchar(100)',
				'TwitterPicture' => 'Text',
				'TwitterName' => 'Varchar(255)',
				'TwitterScreenName' => 'Varchar(255)'
			),
			'indexes' => array(
				'TwitterID' => true,
				'TwitterScreenName' => true
			)
		);
	}

	/**
	 * connect and disconnect button
	 * @return Object (IsConnected, Link, ConnectedName, ConnectedImageURL)
	 */
	public function getTwitterButton($backURL = "") {
		return TwitterCallback::get_login_button($backURL, $this->owner);
	}

	/**
	 * Does the user have (or had) a connection with twitter?
	 * @return Boolean
	 */
	public function hasTwitter() {
		return (bool)($this->owner->TwitterID);
	}

	/**
	 * link to profile
	 * @return String
	 */
	public function TwitterLink(){
		if($this->owner->TwitterID) {
			return "https://twitter.com/account/redirect_by_id?id=".$this->owner->TwitterID;
		}
		return "";
	}

}
