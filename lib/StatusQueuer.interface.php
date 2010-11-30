<?
	/*
	 * Implement StatusQueuer to plug status-queueing
	 * classes into the UserstreamPhirehose class.
	 */
	interface StatusQueuer {
		public function enqueueStatus($statusJson);
	}
?>
