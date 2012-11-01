<?php

class TwitterAuthenticator extends Authenticator {
	public static function get_name() {
		return 'Twitter';
	}
	
	public static function get_login_form(Controller $controller) {
		return new TwitterLoginForm(
			$controller,
			'LoginForm'
		);
	}
	
	public static function authenticate($RAW_data, Form $form = null) {
		return singleton('TwitterCallback')->loginUser();
	}
}
