<?php
/**
 * A simple and lightweight cURL library with support for multiple requests in parallel.
 *
 * @package     Curl
 * @version     2.0
 * @author      Jonas Stendahl
 * @license     MIT License
 * @copyright   2013 Jonas Stendahl
 * @link        http://github.com/jyggen/curl
 */

namespace jyggen\Curl;

use jyggen\Curl\DispatcherInterface;
use jyggen\Curl\Exception\CurlErrorException;
use jyggen\Curl\SessionInterface;

class Dispatcher implements DispatcherInterface {

	/**
	 * The cURL multi handle.
	 *
	 * @var curl_multi
	 */
	protected $handle;

	/**
	 * All added sessions.
	 *
	 * @var array
	 */
	protected $sessions = array();

	/**
	 * Create a new Dispatcher instance.
	 *
	 * @return void
	 */
	public function __construct()
	{

		$this->handle = curl_multi_init();

	}

	/**
	 * Shutdown sequence.
	 *
	 * @return void
	 */
	public function __destruct()
	{

		curl_multi_close($this->handle);

	}

	/**
	 * Add a Session.
	 *
	 * @param  SessionInterface $session
	 * @return int
	 */
	public function add(SessionInterface $session)
	{

		// Tell the $session to use this handle.
		$session->addMultiHandle($this->handle);

		// Store the session.
		$this->sessions[] = $session;

		// Return the session's key.
		return (count($this->sessions) - 1);

	}

	/**
	 * Remove all sessions.
	 *
	 * @return void
	 */
	public function clear()
	{

		// Loop through all sessions and remove
		// their relationship to our handle.
		foreach ($this->sessions as $session) {

			$session->removeMultiHandle($this->handle);

		}

		// Reset the sessions array.
		$this->sessions = array();

	}

	/**
	 * Execute all added sessions.
	 *
	 * @return void
	 */
	public function execute()
	{

		// Start the request cycle.
		do { $mrc = curl_multi_exec($this->handle, $active); } while ($mrc === CURLM_CALL_MULTI_PERFORM);

		// Keep processing requests until we're done.
		while ($active and $mrc === CURLM_OK) {

			// Workaround for PHP Bug #61141.
			if (curl_multi_select($this->handle) === -1) { usleep(100); }

			// Retrieve execution status.
			do { $mrc = curl_multi_exec($this->handle, $active); } while ($mrc === CURLM_CALL_MULTI_PERFORM);

		}

		// If everything went okay, retrieve the data from each session.
		if ($mrc === CURLM_OK) {

			foreach ($this->sessions as $key => $session) {
				$session->execute();
				$session->removeMultiHandle($this->handle);
			}

		// Otherwise, throw an exception!
		} else { throw new CurlErrorException('cURL read error #'.$mrc); }

	}

	/**
	 * Retrieve all or a specific session.
	 *
	 * @param  int   $key null
	 * @return mixed
	 */
	public function get($key = null)
	{

		// Return all sessions if no key is specified.
		if (is_null($key)) {

			return $this->sessions;

		// Otherwise check if the key exists and return that session.
		} elseif (array_key_exists($key, $this->sessions)) {

			return $this->sessions[$key];

		// Else return null.
		} else {

			return null;

		}

	}

	/**
	 * Remove a specific session.
	 *
	 * @param  int  $key
	 * @return void
	 */
	public function remove($key)
	{

		// Make sure the session exists before we try to remove it.
		if (array_key_exists($key, $this->sessions)) {

			$this->sessions[$key]->removeMultiHandle($this->handle);
			unset($this->sessions[$key]);

		}

	}

}
