<?
	tusc_lib_require("StatusQueuer.interface.php");

	/*
	 * Example status-queuing class prints
	 * stringified JSON read from the wire
	 * straight to stdout.
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
