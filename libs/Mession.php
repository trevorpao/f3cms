<?php

namespace F3CMS;

/**
 * Mession class handles session management, including opening, closing, reading, writing,
 * destroying sessions, and garbage collection. It also provides utility methods for session-related
 * data like CSRF tokens, IP addresses, and user agents.
 */
class Mession extends MHelper implements \SessionHandlerInterface
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
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * Closes a session. This method is required by the session handler interface.
     *
     * @return bool Always returns true.
     */
    public function close(): bool
    {
        $this->sid = null;
        return true;
    }

    /**
     * Validates the session ID format accepted by this handler.
     *
     * @param string $id Session ID.
     * @return bool True when the ID format is acceptable.
     */
    protected function isValidSessionId(string $id): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9,-]{22,40}$/', $id);
    }

    /**
     * Normalizes the user agent string for fingerprint comparison.
     *
     * @param string $agent User agent string.
     * @return string Normalized user agent.
     */
    protected function normalizeAgent(string $agent): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $agent)));
    }

    /**
     * Reduces the IP address to a coarse fingerprint to avoid over-binding.
     *
     * @param string $ip IP address.
     * @return string Fingerprint string.
     */
    protected function ipFingerprint(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return implode('.', array_slice($parts, 0, 3));
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', strtolower($ip));
            return implode(':', array_slice($parts, 0, 4));
        }

        return '';
    }

    /**
     * Detects staff/backend sessions from serialized session data.
     *
     * @param array $session Session record.
     * @return bool True when the session represents a logged-in staff user.
     */
    protected function isStrictFingerprintSession(array $session): bool
    {
        $data = (string) ($session['data'] ?? '');

        return strpos($data, 'cu_staff') !== false && strpos($data, 'has_login') !== false;
    }

    /**
     * Compares the current request fingerprint against the stored session fingerprint.
     *
     * @param array $session Session record.
     * @return bool True when the request matches the stored fingerprint.
     */
    protected function matchesFingerprint(array $session): bool
    {
        $storedAgent = $this->normalizeAgent((string) ($session['agent'] ?? ''));
        $currentAgent = $this->normalizeAgent($this->_agent);

        if ($storedAgent === '' || $currentAgent === '') {
            return false;
        }

        if (!hash_equals($storedAgent, $currentAgent)) {
            return false;
        }

        if (!$this->isStrictFingerprintSession($session)) {
            return true;
        }

        $storedIp = $this->ipFingerprint((string) ($session['ip'] ?? ''));
        $currentIp = $this->ipFingerprint($this->_ip);

        if ($storedIp === '' || $currentIp === '') {
            return false;
        }

        return hash_equals($storedIp, $currentIp);
    }

    /**
     * Clears the current session cookie from the request and response.
     */
    protected function clearSessionCookie(): void
    {
        $cookieName = session_name();

        unset($_COOKIE[$cookieName]);
        unset(f3()->{'COOKIE.' . $cookieName});

        if (!headers_sent()) {
            setcookie($cookieName, '', time() - 3600, '/', '', true, true);
        }
    }

    /**
     * Rejects suspicious sessions and terminates the current request.
     *
     * @param string $id Session ID.
     * @param string $reason Reject reason.
     */
    protected function rejectSuspiciousSession(string $id, string $reason): void
    {
        $this->writeLog('suspect------::' . $reason . '::' . $id);

        if ($this->onsuspect) {
            f3()->call($this->onsuspect, [$this, $id, $reason]);
        }

        $this->destroy($id);
        $this->rtn = null;
        $this->close();
        $this->clearSessionCookie();
        f3()->error(403);
    }

    /**
     * Rejects incoming cookie session IDs that do not already exist.
     */
    protected function rejectUnknownIncomingSessionId(): void
    {
        $cookieName = session_name();
        $incomingId = $_COOKIE[$cookieName] ?? '';

        if ($incomingId === '') {
            return;
        }

        if (!$this->isValidSessionId($incomingId)) {
            $this->writeLog('reject-invalid-session-id::' . $incomingId);
            $this->clearSessionCookie();
            session_id('');
            return;
        }

        $currentResult = $this->rtn;
        $existingSession = $this->get($this->tbl, '*', ['session_id' => $incomingId]);
        $this->rtn = $currentResult;

        if (empty($existingSession)) {
            $this->writeLog('reject-unknown-session-id::' . $incomingId);
            $this->clearSessionCookie();
            session_id('');
        }
    }

    /**
     * Reads session data from the database.
     *
     * @param string $id Session ID.
     * @return string Serialized session data or an empty string if no data is found.
     */
    public function read(string $id): string
    {
        // check session id format
        if (!$this->isValidSessionId($id)) {
            return '';
        }
        $this->sid = $id;
        $this->writeLog('select------::' . $id);
        $this->rtn = $this->get($this->tbl, '*', ['session_id' => $id]);
        $this->writeLog('data:' . json_encode($this->rtn));

        if ($this->dry()) {
            return '';
        }

        if (!$this->matchesFingerprint($this->rtn)) {
            $this->rejectSuspiciousSession($id, 'fingerprint-mismatch');
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
    public function write(string $id, string $data): bool
    {

        // check session id format
        if (!$this->isValidSessionId($id)) {
            $this->writeLog('reject-write-invalid-session-id::' . $id);
            return false;
        }

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
    public function destroy(string $id): bool
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
    public function cleanup(int $max)
    {
        return $this->gc($max);
    }

    /**
     * Garbage collector required by \SessionHandlerInterface.
     *
     * @param int $max Maximum lifetime of sessions in seconds.
     * @return bool Always returns true.
     */
    public function gc(int $max): int|false
    {
        $this->delete($this->tbl, [
            'stamp[<]' => time() - 86400 * 30,
        ]);

        return 1;
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

        f3()->JAR = [
            'lifetime' => (86400 * f3()->get('token_expired')),
            'samesite' => 'Strict',          // [重點] 限制跨站請求 (防範 CSRF)，可視需求改為 Lax || Strict
            'secure' => true,
            'httponly' => true,
        ];

        $this->_agent = $headers['User-Agent'] ?? '';
        $this->_ip    = f3()->IP;

        if (!preg_match('/' . implode('|', $agentBots) . '/i', $this->_agent) && !in_array($this->_ip, $blockIps)) {
            $this->onsuspect = $onsuspect;
            $this->tbl       = 'sessions';

            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_trans_sid', '0');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', '1');

            if (PHP_VERSION_ID >= 70300) {
                ini_set('session.cookie_samesite', 'Strict');
            }

            session_set_save_handler($this, true);
            $this->rejectUnknownIncomingSessionId();

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
