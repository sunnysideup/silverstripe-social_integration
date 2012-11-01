<?php

/***
 * adds twitter functionality to Member
 *
 *
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



}