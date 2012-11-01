This module is based on the work by http://svn.rentbox.co.nz/public/.

Thank you to Simon W and the team.

# Silverstripe Social Integration

This module allows users to easily let users sign up and login using facebook and twitter. After a user has signed up using this module, a OAuth token for that social service will be stored against the users account. This allows your application to call the facebook and twitter apis on behalf of the user and implement whatever interesting social integration features you want. The SelectFriendPage is an example of the type of functionality that can be easily implemented once you have access to the users OAuth tokens.

## How to install:

Simple copy to your Silverstripe project root directory and set config options. You need to add the following constants to your SS _config.php file.

```php
//FACEBOOK
define('FACEBOOK_APP_ID', 'AAAAAAA');
define('FACEBOOK_APP_SECRET', 'BBBBBBB');

//TWITTER
define('CONSUMER_KEY', 'CCCCCCC');
define('CONSUMER_SECRET', 'DDDDDDD');
```

**Facebook**
To get the credentials for facebook you will need to go to http://developers.facebook.com/apps and create a new app. Once you have done this it will show you a summary containing the two variables needed.

**Twitter**
To get the credentials for twitter you will need to go to https://dev.twitter.com/apps and create a new app. One you have done this it will show you a summary containing the two variables needed.

**Example page - Select Friends**
If you want to use the Select Friends page then you need to add the necessary js file in Page.php init function.

```php
//Page.php

public function init() {
	//... loading js requirements ...
	Requirements::javascript("social_integration/javascripts/select_friends.js");
}
```

## Implementation details

Once a user has signed up the following fields are added to them in the Members table.

* FacebookUserID

* FacebookOAuthToken

* TwitterUserID

* TwitterOAuthToken

* TwitterOAuthSecret
