<?php

// https://developers.facebook.com/docs/reference/api/page/

if(!file_exists('Zend/Oauth.php')) {
	set_include_path(get_include_path() . PATH_SEPARATOR . (dirname(__FILE__)) . '/thirdparty/');
}


//copy the lines between the START AND END line to your /mysite/_config.php file and choose the right settings
//===================---------------- START social_integration MODULE ----------------===================
//SocialIntegrationControllerBaseClass::set_default_avatar("myimage.png");

#################### OLD SCHOOL ##############################
//Authenticator::unregister_authenticator('MemberAuthenticator');
//Authenticator::register_authenticator('MemberAuthenticatorWithSignup');

#################### TWITTER ##############################
//Object::add_extension('Member', 'TwitterIdentifier');
//Authenticator::register_authenticator('TwitterAuthenticator');
//TwitterCallback::set_consumer_key("KEY");
//TwitterCallback::set_consumer_secret("SECRET");

#################### FACEBOOK ##############################
//Object::add_extension('Member', 'FacebookIdentifier');
//Authenticator::register_authenticator('FacebookAuthenticator');
//FacebookCallback::set_facebook_id("FACEBOOK_ID");
//FacebookCallback::set_facebook_secret("FACEBOOK_SECRET");


#################### LINKEDIN ##############################
//Object::add_extension('Member', 'LinkedinIdentifier');
//Authenticator::register_authenticator('LinkedinAuthenticator');
//LinkedinCallback::set_consumer_key("");
//LinkedinCallback::set_consumer_secret("");



//===================---------------- END social_integration MODULE ----------------===================
