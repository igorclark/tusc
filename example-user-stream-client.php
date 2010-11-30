<?php
/*
 * Define your Twitter keys & secrets.
 * Get these from the "Settings" & "My Access Token" pages
 * for your app via htp://dev.twitter.com/apps
 * These must be defined before you include
 * tusc code (below) or the init will fail.
 */
define('TWITTER_CONSUMER_KEY',			'xxxxxxxxxxxxxxxxxx');
define('TWITTER_CONSUMER_SECRET',		'xxxxxxxxxxxxxxxxxx');
define('TWITTER_OAUTH_TOKEN',			'xxxxxxxxxxxxxxxxxx');
define('TWITTER_OAUTH_TOKEN_SECRET',	'xxxxxxxxxxxxxxxxxx');

/*
 * Include tusc code
 */
require_once("tusc/setup.inc.php");

/*
 * Create our TwitterOAuth utility object & get signed stream URL
 */
$oauthutil = new TwitterOAuthUtility(
	TWITTER_CONSUMER_KEY,
	TWITTER_CONSUMER_SECRET,
	TWITTER_OAUTH_TOKEN,
	TWITTER_OAUTH_TOKEN_SECRET
);

/*
 * Use streaming API URL
 */
define("TWITTER_STREAM_API_URL", "https://userstream.twitter.com/2/");
$oauthutil->setHost(TWITTER_STREAM_API_URL);

/*
 * Construct OAuth-signed user stream URL
 */
$stream_url = $oauthutil->getOAuthUrl("user", "GET");

/*
 * Consume stream using our OAuth-signed URL
 */
$oauthPhirehose = new UserstreamPhirehose($stream_url);
$oauthPhirehose->consume();
?>
