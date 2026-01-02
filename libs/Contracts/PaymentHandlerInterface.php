<?php

namespace F3CMS\Contracts;

interface PaymentHandlerInterface
{
	/**
	 * Send a command to the remote payment gateway.
	 * Mirrors the behaviour exposed by helpers like LinePayHelper and TPPhelper.
	 *
	 * @param string $command      Logical command key (e.g. checkout, start, confirm).
	 * @param array  $requestData  Payload to be dispatched.
	 * @param string $method       HTTP method, defaults to POST for gateways such as Tappay.
	 *
	 * @return mixed Gateway response payload or false on failure.
	 */
	public function call($command, array $requestData = [], $method = 'POST');

	/**
	 * Compose the concrete endpoint URI (and optionally payload) for a command.
	 * Aligns with the getURL signatures seen in LinePayHelper/TPPhelper.
	 *
	 * @param string $command     Logical command key.
	 * @param array  $requestData Request parameters.
	 * @param string $return      Either 'string' for query-string output or 'array' for [uri, data].
	 *
	 * @return mixed
	 */
	public function getURL($command, array $requestData, $return = 'string');

	/**
	 * Quick capability check so that callers can verify whether a handler exposes a command.
	 *
	 * @param string $command
	 *
	 * @return bool
	 */
	public function supportsCommand($command);

	/**
	 * Toggle verbose debug logging for gateway interactions.
	 *
	 * @param bool $enabled
	 *
	 * @return void
	 */
	public function setDebugMode($enabled);

	/**
	 * Determine whether debug logging is currently enabled.
	 *
	 * @return bool
	 */
	public function isDebugModeEnabled();
}
