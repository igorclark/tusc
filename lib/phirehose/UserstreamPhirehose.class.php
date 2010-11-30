<?
	/*
	 * Ensure we have Phirehose to inherit from.
	 * Phirehose must be in the include_path.
	 */
	require_once("phirehose/lib/Phirehose.php");

	/*
	 * Custom class to talk to OAuth-locked
	 * user stream API using Phirehose
	 */
	class UserstreamPhirehose extends Phirehose {

		protected $oauth_url;
		protected $status_queuer;

		/*
		 * Set up OAuth URL and (at least default) status dumper
		 */
		public function __construct($url, $queuer = null) {
			$this->oauth_url = $url;

			if($queuer == null) {
				tusc_lib_require("StatusDumper.class.php");
				$queuer = StatusDumper::defaultInstance();
			}

			$this->status_queuer = $queuer;
		}

		/*
		 * Custom class log messages.
		 */
		protected function log($message) {
			@error_log('UserstreamPhirehose: ' . $message, 0);
		}
		
		/*
		 * Have to implement abstract method from
		 * Phirehose parent. No point using a
		 * wrapper which just creates an extra
		 * function call; just use a method on
		 * a StatusQueuer-implementing class directly.
		 */
		public function enqueueStatus($status) {
			die(__CLASS__ . " uses a custom status queuer, but you're trying to use the public enqueueStatus method abstracted in Phirehose. To use " . __CLASS__ . ", implement the StatusQueuer interface and use that.\n");
		}

		/*
		 * reconnect function copied from parent 'cos it's private
		 */
		private function reconnect() {
			$reconnect = $this->reconnect;
			$this->disconnect(); // Implicitly sets reconnect to FALSE
			$this->reconnect = $reconnect; // Restore state to prev
			$this->connect(); 
		}

		/*
		 * Consumes chunked-encoding user stream.
		 *
		 * Code largely lifted from Phirehose,
		 * just modified to read chunked encoding
		 * rather than "delimited=length" as per
		 * filter streams etc, because it doesn't
		 * seem supported in GET requests to user
		 * stream.
		 */
		public function consume($reconnect = TRUE) {

			// Persist connection?
			$this->reconnect = $reconnect;
			
			// Loop indefinitely based on reconnect
			do {
				
				// (Re)connect
				$this->reconnect();

				// Init state
				$statusCount = $enqueueSpent = $idlePeriod = $maxIdlePeriod = 0;
				$lastAverage = $lastFilterCheck = $lastFilterUpd = $lastStreamActivity = time();
				$fdw = $fde = NULL; // Placeholder write/error file descriptors for stream_select

				/*
				 * We use a blocking-select with timeout, to
				 * allow us to continue processing on idle streams.
				 */
				while (
					$this->conn !== NULL
					&& !feof($this->conn)
					&& ($numChanged = stream_select($this->fdrPool, $fdw, $fde, $this->readTimeout)
				) !== FALSE) {
					/* Unfortunately, we need to do a safety check
					 * for dead twitter streams - This seems to be
					 * able to happen where you end up with a valid
					 * connection, but NO tweets coming along the
					 * wire (or keep alives). The below guards
					 * against this.
					 */
					if ((time() - $lastStreamActivity) > $this->idleReconnectTimeout) {
						$this->log('Idle timeout: No stream activity for > ' . $this->idleReconnectTimeout . ' seconds. Reconnecting.');
						$this->reconnect();
						$lastStreamActivity = time();
						continue;
					}
	
					// Process stream/buffer
					$this->fdrPool = array($this->conn); // Must reassign for stream_select()

					// read till we get a CRLF at the end of a chunk-size line
					while(substr($this->buff, strlen($this->buff) -2) != "\r\n") {
						$this->buff .= fread($this->conn, 1);
					}

					// where in the buffer is the CRLF?
					$eol = strpos($this->buff, "\r\n");

					if($eol==0) {
						// just chaff from previous 
						$this->buff = '';
						continue;
					}

					// Track maximum idle period
					$idlePeriod = (time() - $lastStreamActivity);
					$maxIdlePeriod = ($idlePeriod > $maxIdlePeriod) ? $idlePeriod : $maxIdlePeriod;

					// We got a newline, this is stream activity
					$lastStreamActivity = time();

					// Take hex chunk size line & CRLF off top of buffer
					list($chunkSize, $this->buff) = explode("\r\n", $this->buff);

					// chunked encoding 'chunk-size' lines are hex encoded
					$statusLength = hexdec($chunkSize);

					// Discard chunked-encoding keep-alive/pad activity
					if($statusLength <= 2) {
						$this->buff = '';
						continue;
					}

					// Still here? This is a status message.
					if ($statusLength > 0) {
						// Read status bytes and enqueue
						$bytesLeft = $statusLength - strlen($this->buff);

						while ($bytesLeft > 0
								&& $this->conn !== NULL
								&& !feof($this->conn)
								&& ($numChanged = stream_select($this->fdrPool, $fdw, $fde, 0, 20000)
						) !== FALSE) {
							$this->fdrPool = array($this->conn); // Reassign
							$this->buff .= fread($this->conn, $bytesLeft); // Read until all bytes are read into buffer
							$bytesLeft = ($statusLength - strlen($this->buff));
						}

						// Accrue/enqueue and track time spent enqueing
						$statusCount ++;

						$enqueueStart = microtime(TRUE);
						$this->status_queuer->enqueueStatus(trim($this->buff));
						$enqueueSpent += (microtime(TRUE) - $enqueueStart);

						$this->buff = '';
					} else {
						// Timeout/no data after readTimeout seconds
					}

					// Calc counter averages 
					$avgElapsed = time() - $lastAverage;
					if ($avgElapsed >= $this->avgPeriod) {
						// Calc tweets-per-second
						$this->statusRate = round($statusCount / $avgElapsed, 0);
						// Calc time spent per enqueue in ms
						$enqueueTimeMS = ($statusCount > 0) ? round($enqueueSpent / $statusCount * 1000, 2) : 0;
						$this->log('Consume rate: ' . $this->statusRate
									. ' status/sec (' . $statusCount . ' total), avg enqueueStatus(): '
									. $enqueueTimeMS . 'ms, over '
									. $this->avgPeriod . ' seconds, max stream idle period: '
									.  $maxIdlePeriod . ' seconds.'
						);
						// Reset
						$statusCount = $enqueueSpent = $idlePeriod = $maxIdlePeriod = 0;
						$lastAverage = time();
					}
				
				} // End while-stream-activity
		
				// Some sort of socket error has occured
				$this->lastErrorNo = is_resource($this->conn) ? @socket_last_error($this->conn) : NULL;
				$this->lastErrorMsg = ($this->lastErrorNo > 0) ? @socket_strerror($this->lastErrorNo) : 'Socket disconnected';
				$this->log('Phirehose connection error occured: ' . $this->lastErrorMsg);
				
				// Reconnect 
			} while ($this->reconnect);
		
			// Exit
			$this->log('Exiting.');

		}

		/**
		 * Connects to the stream URL using the configured method.
		 * @throws ErrorException
		 *
		 * Again, largely lifted from Phirehose, this time because
		 * the URL construction is built into the connect method,
		 * and port 80 is hardcoded, so we can't just override.
		 */
		protected function connect() {
		
			// Init state
			$connectFailures = 0;
			$tcpRetry = self::TCP_BACKOFF / 2;
			$httpRetry = self::HTTP_BACKOFF / 2;
		
			// Keep trying until connected (or max connect failures exceeded)
			do {
		
				// Construct URL/HTTP bits
				$url = $this->oauth_url;
				$urlParts = parse_url($url);

				// Debugging is useful
				$this->log('Connecting to twitter stream: ...');
				
				/**
				* Open socket connection to make POST request.
				* It'd be nice to use stream_context_create
				* with the native HTTP transport but it
				* hides/abstracts too many required bits
				* (like HTTP error responses).
				*/
				$errNo = $errStr = NULL;
				$scheme = ($urlParts['scheme'] == 'https') ? 'ssl://' : 'tcp://';
				$port = ($urlParts['scheme'] == 'https') ? 443 : 80;
				
				/**
				* We must perform manual host resolution
				* here as Twitter's IP regularly rotates
				* (ie: DNS TTL of 60 seconds) and PHP
				* appears to cache it the result if in
				* a long running process (as per Phirehose).
				*/
				$streamIPs = gethostbynamel($urlParts['host']);
				if (count($streamIPs) == 0) {
					throw new ErrorException("Unable to resolve hostname: '" . $urlParts['host'] . '"');
				}
				
				// Choose one randomly (if more than one)
				$this->log('Resolved host ' . $urlParts['host'] . ' to ' . implode(', ', $streamIPs));
				$streamIP = $streamIPs[rand(0, (count($streamIPs) - 1))];
				$this->log('Connecting to ' . $streamIP . ' on port ' . $port);
				
				@$this->conn = fsockopen($scheme . $streamIP, $port, $errNo, $errStr, $this->connectTimeout);
			
				// No go - handle errors/backoff
				if (!$this->conn || !is_resource($this->conn)) {
					$this->lastErrorMsg = $errStr;
					$this->lastErrorNo = $errNo;
					$connectFailures ++;
					if ($connectFailures > $this->connectFailuresMax) {
						$msg = 'TCP failure limit exceeded with ' . $connectFailures . ' failures. Last error: ' . $errStr;
						$this->log($msg);
						throw new ErrorException($msg, $errNo); // Throw an exception for other code to handle
					}
					// Increase retry/backoff up to max
					$tcpRetry = ($tcpRetry < self::TCP_BACKOFF_MAX) ? $tcpRetry * 2 : self::TCP_BACKOFF_MAX;
					$this->log('TCP failure ' . $connectFailures . ' of ' . $this->connectFailuresMax . ' connecting to stream: ' .
						$errStr . ' (' . $errNo . '). Sleeping for ' . $tcpRetry . ' seconds.');
					sleep($tcpRetry);
					continue;
				}
				
				// TCP connect OK, clear last error (if present)
				$this->log('Connection established to ' . $streamIP);
				$this->lastErrorMsg = NULL;
				$this->lastErrorNo = NULL;
				
				// If we have a socket connection, we can attempt a HTTP request - Ensure blocking read for the moment
				stream_set_blocking($this->conn, 1);
			
				/* GET version */
				fwrite($this->conn,
						"GET "
						. $urlParts['path']	. "?"
						. $urlParts['query']
						. " HTTP/1.1\r\n"
				);
				fwrite($this->conn, "Host: " . $urlParts['host'] . "\r\n");
				fwrite($this->conn, "Accept: *//*\r\n");
				fwrite($this->conn, "User-Agent: " . self::USER_AGENT . "\r\n");
				fwrite($this->conn, "\r\n");

				$this->log('Stream request sent');

				// First line is response
				list($httpVer, $httpCode, $httpMessage) = preg_split('/\s+/', trim(fgets($this->conn, 1024)), 3);

				// Response buffers
				$respHeaders = $respBody = '';
		
				// Consume each header response line until we get to body
				while ($hLine = trim(fgets($this->conn, 4096))) {
					$respHeaders .= $hLine . ' ';
				}
				
				// If we got a non-200 response, we need to backoff and retry
				if ($httpCode != 200) {
					$connectFailures ++;
				
					// Twitter will disconnect on error, but we want to consume the rest of the response body (which is useful)
					while ($bLine = trim(fgets($this->conn, 4096))) {
						$respBody .= $bLine;
					}
				
					// Construct error
					$errStr = 'HTTP ERROR ' . $httpCode . ': ' . $httpMessage . ' (' . $respBody . ')'; 
				
					// Set last error state
					$this->lastErrorMsg = $errStr;
					$this->lastErrorNo = $httpCode;
				
					// Have we exceeded maximum failures?
					if ($connectFailures > $this->connectFailuresMax) {
						$msg = 'Connection failure limit exceeded with ' . $connectFailures . ' failures. Last error: ' . $errStr;
						$this->log($msg);
						throw new ErrorException($msg); // We eventually throw an exception for other code to handle			
					}
					// Increase retry/backoff up to max
					$httpRetry = ($httpRetry < self::HTTP_BACKOFF_MAX) ? $httpRetry * 2 : self::HTTP_BACKOFF_MAX;
					$this->log('HTTP failure ' . $connectFailures . ' of ' . $this->connectFailuresMax . ' connecting to stream: ' . 
					$errStr . '. Sleeping for ' . $httpRetry . ' seconds.');
					sleep($httpRetry);
					continue;
				
				} // End if not http 200
				
				// Loop until connected OK
			} while (!is_resource($this->conn) || $httpCode != 200);

			$this->log('Stream established.');

			// Connected OK, reset connect failures
			$connectFailures = 0;
			$this->lastErrorMsg = NULL;
			$this->lastErrorNo = NULL;
			
			// Switch to non-blocking to consume the stream (important) 
			stream_set_blocking($this->conn, 0);

			// Flush stream buffer & (re)assign fdrPool (for reconnect)
			$this->fdrPool = array($this->conn);
			$this->buff = '';
		}

	}
?>
