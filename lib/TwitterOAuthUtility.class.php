<?
	/*
	 * Ensure we have TwitterOAuth class to inherit from.
	 * TwitterOAuth must be in the include_path.
	 */
	require_once("twitteroauth/twitteroauth/twitteroauth.php");

	/*
	 * Utility class to construct OAuth-signed URLs.
	 */
	class TwitterOAuthUtility extends TwitterOAuth {

		protected $buffer;

		/*
		 * Allow overriding API host URL.
		 * This is actually the URL root - e.g.
		 * http://api.twitter.com/1/, or
		 * https://userstream.twitter.com/2/
		 * as in the user stream.
		 */
		function setHost($newHost) {
			$this->host = $newHost;
		}

		function getOAuthUrl($url, $method, $parameters = array()) {
			$request = $this->createOAuthRequest($url, $method, $parameters);
			return $request->to_url();
		}

		function createOAuthRequest($url, $method, $parameters) {
			if (strrpos($url, 'https://') !== 0 && strrpos($url, 'http://') !== 0) {
				$url = "{$this->host}{$url}.{$this->format}";
			}
			$request = OAuthRequest::from_consumer_and_token($this->consumer, $this->token, $method, $url, $parameters);
			$request->sign_request($this->sha1_method, $this->consumer, $this->token);
			return $request;
		}

	}
?>
