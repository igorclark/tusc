<?
	tusc_lib_require("StatusQueuer.interface.php");

	/*
	 * Example status-queuing class prints
	 * stringified JSON read from the wire
	 * straight to stdout.
	 *
	 * Don't do any processing here in a real app -
	 * this class should just hand status messages
	 * over to another process. See
	 * http://dev.twitter.com/pages/streaming_api_concepts#collecting-processing
	 * for more details.
	 */
	class StatusDumper implements StatusQueuer {
		private static $instance;

		public static function defaultInstance() {
			if(!isset(self::$instance)) {
				$class = __CLASS__;
				self::$instance = new $class;
			}
			return self::$instance;
		}

		public function enqueueStatus($statusJson) {
			print "### In " . __CLASS__ . "::enqueueStatus() - got status of length " . strlen($statusJson) . "\n";
			print "---$statusJson---\n\n";
		}
	}
?>
