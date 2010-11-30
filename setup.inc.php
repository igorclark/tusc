<?
/*
 * Root of tusc install. Modify or override this
 * if you rename it or move to subdir of include_path.
 */
define("TUSC_LOC", "tusc");
define("TUSC_LIB_LOC", TUSC_LOC . DIRECTORY_SEPARATOR . "lib");

/*
 * Convenience include methods.
 */
function tusc_require($inc) {
	require_once(TUSC_LOC . DIRECTORY_SEPARATOR . $inc);
}

function tusc_lib_require($inc) {
	tusc_require("lib" . DIRECTORY_SEPARATOR . $inc);
}

/*
 * Get twitter oauth functionality.
 * Must be on PHP include_path.
 */
require_once("twitteroauth" . DIRECTORY_SEPARATOR . "twitteroauth" . DIRECTORY_SEPARATOR . "twitteroauth.php");

/*
 * Extend TwitterOAuth with utility class
 * to generate signed URLs.
 */
tusc_lib_require("TwitterOAuthUtility.class.php");

/*
 * Include Phirehose functionality.
 * Must be on PHP include_path.
 */
require_once("phirehose" . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "Phirehose.php");

/*
 * Extend Phirehose client methods with OAuth/chunked-encoding versions
 */
tusc_lib_require("phirehose" . DIRECTORY_SEPARATOR . "UserstreamPhirehose.class.php");

/*
 * Make sure all OAuth keyss are defined.
 */
foreach(array("TWITTER_CONSUMER_KEY", "TWITTER_CONSUMER_SECRET", "TWITTER_OAUTH_TOKEN", "TWITTER_OAUTH_TOKEN_SECRET") as $constant) {
	if(!defined($constant)) {
		die("You need to define $constant to use twitter-oauth-userstream-client.\n");
	}
}
?>
