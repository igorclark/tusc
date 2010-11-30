<?
	/*
	 * Implement StatusQueuer to plug status-queueing
	 * classes into the UserstreamPhirehose class.
	 *
	 * As per Twitter's Streaming API documentation,
	 * classes implementing this interface should not
	 * parse statuses in-process:
	 *
	 * "The collection component should efficiently handle
	 * connecting to the Streaming API and retrieving responses,
	 * as well as reconnecting in the event of network failure,
	 * and hand-off statuses via an asynchronous queueing
	 * mechanism to application specific processing and persistence
	 * components."
	 *
	 * Read all about it at
	 * http://dev.twitter.com/pages/streaming_api_concepts#collecting-processing
	 */
	interface StatusQueuer {
		public function enqueueStatus($statusJson);
	}
?>
