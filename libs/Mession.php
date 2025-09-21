<?php

namespace F3CMS;

/**
 * Mession class handles session management, including opening, closing, reading, writing,
 * destroying sessions, and garbage collection. It also provides utility methods for session-related
 * data like CSRF tokens, IP addresses, and user agents.
 */
class Mession extends MHelper
{
    // Session ID
    protected $sid;

    // Anti-CSRF token
    protected $_csrf;

    // User agent string of the client
    protected $_agent;

    // Client's IP address
    protected $_ip;

    // Callback function for handling suspicious activity
    protected $onsuspect;

    // Database table for storing session data
    protected $tbl;

    // Logger instance for debugging and logging session activities
    protected $logger;

    // Debug mode flag
    protected $debug;

    // Stores the result of the last database query
    protected $rtn;

    /**
     * Opens a session. This method is required by the session handler interface.
     *
     * @param string $path Path to the session storage (not used here).
     * @param string $name Name of the session (not used here).
     * @return bool Always returns true.
     */
    public function open($path, $name)
    {
        return true;
    }

    /**
     * Closes a session. This method is required by the session handler interface.
     *
     * @return bool Always returns true.
     */
    public function close()
    {
        $this->sid = null;
        return true;
    }

    /**
     * Reads session data from the database.
     *
     * @param string $id Session ID.
     * @return string Serialized session data or an empty string if no data is found.
     */
    public function read($id)
    {
        $this->sid = $id;
        $this->writeLog('select------::' . $id);
        $this->rtn = $this->get($this->tbl, '*', ['session_id' => $id]);
        $this->writeLog('data:' . json_encode($this->rtn));

        if ($this->dry()) {
            return '';
        }

        return $this->rtn['data'];
    }

    /**
     * Writes session data to the database.
     *
     * @param string $id Session ID.
     * @param string $data Serialized session data.
     * @return bool Always returns true.
     */
    public function write($id, $data)
    {
        $logger = new \Log('session.log');
        if ($this->dry()) {
            $this->writeLog('insert------start');
            $this->insert($this->tbl, [
                'session_id' => $id,
                'data'       => $data,
                'ip'         => $this->_ip,
                'agent'      => $this->_agent,
                'stamp'      => time(),
            ]);
            $this->writeLog('insert------::' . $this->id());
        } else {
            $this->writeLog('update------start');
            $this->writeLog('data:' . $data);

            $this->update($this->tbl, [
                'data'  => $data,
                'ip'    => $this->_ip,
                'agent' => $this->_agent,
                'stamp' => time(),
            ], [
                'session_id' => $id,
            ]);
        }

        return true;
    }

    /**
     * Destroys a session by removing its data from the database.
     *
     * @param string $id Session ID.
     * @return bool Always returns true.
     */
    public function destroy($id)
    {
        $this->delete($this->tbl, [
            'session_id' => $id,
        ]);

        return true;
    }

    /**
     * Cleans up old session data from the database.
     *
     * @param int $max Maximum lifetime of sessions in seconds (not used here).
     * @return bool Always returns true.
     */
    public function cleanup($max)
    {
        $this->delete($this->tbl, [
            'stamp[<]' => time() - 86400 * 30,
        ]);

        return true;
    }

    /**
     * Returns the current session ID.
     *
     * @return string|null Session ID or null if no session is active.
     */
    public function sid()
    {
        return $this->sid;
    }

    /**
     * Returns the anti-CSRF token.
     *
     * @return string CSRF token.
     */
    public function csrf()
    {
        return $this->_csrf;
    }

    /**
     * Returns the client's IP address.
     *
     * @return string IP address.
     */
    public function ip()
    {
        return $this->_ip;
    }

    /**
     * Returns the timestamp of the session.
     *
     * @return string|false Timestamp or false if no session data is available.
     */
    public function stamp()
    {
        if (!$this->sid) {
            session_start();
        }

        return $this->dry() ? false : $this->rtn['stamp'];
    }

    /**
     * Returns the HTTP user agent string of the client.
     *
     * @return string User agent string.
     */
    public function agent()
    {
        return $this->_agent;
    }

    /**
     * Checks if the current cursor position is not mapped to any record.
     *
     * @return bool True if no record is found, false otherwise.
     */
    public function dry()
    {
        return empty($this->rtn) ? true : false;
    }

    /**
     * Logs a message if debugging is enabled.
     *
     * @param string $str Message to log.
     */
    public function writeLog($str)
    {
        ($this->debug) ? $this->logger->write($str) : '';
    }

    /**
     * Constructor for the Mession class. Initializes session handling and sets up
     * anti-CSRF tokens, logging, and other configurations.
     *
     * @param bool $force Whether to force session initialization.
     * @param callable|null $onsuspect Callback for handling suspicious activity.
     * @param string|null $key Key for storing the CSRF token.
     */
    public function __construct($force = true, $onsuspect = null, $key = null)
    {
        // TODO: create table

        parent::__construct();

        $headers   = f3()->HEADERS;
        $agentBots = ['bot', 'crawl', 'curl', 'dataprovider', 'search', 'get', 'spider', 'find', 'java', 'majesticsEO', 'google', 'yahoo', 'teoma', 'contaxe', 'yandex', 'libwww-perl', 'facebookexternalhit'];
        $blockIps  = [];

        $this->_agent = $headers['User-Agent'] ?? '';
        $this->_ip    = f3()->IP;

        if (!preg_match('/' . implode('|', $agentBots) . '/i', $this->_agent) && !in_array($this->_ip, $blockIps)) {
            $this->onsuspect = $onsuspect;
            $this->tbl       = 'sessions';

            session_set_save_handler(
                [$this, 'open'],
                [$this, 'close'],
                [$this, 'read'],
                [$this, 'write'],
                [$this, 'destroy'],
                [$this, 'cleanup']
            );

            register_shutdown_function('session_commit');
            $this->_csrf = f3()->SEED . '.' . f3()->hash(mt_rand());

            if ($key) {
                f3()->$key = $this->_csrf;
            }

            $this->_agent = $headers['User-Agent'] ?? '';

            $this->logger = new \Log('session.log');

            $this->debug = false;

            session_start();
        }
    }
}
