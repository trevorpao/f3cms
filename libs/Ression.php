<?php

// https://redis.io/commands
// https://github.com/predis/predis/blob/main/src/ClientInterface.php
// https://github.com/predis/predis/blob/main/src/Session/Handler.php
// https://github.com/symfony/http-foundation/blob/5.3/Session/Storage/Handler/RedisSessionHandler.php
//

namespace F3CMS;

class Ression implements \SessionHandlerInterface
{
    // ! Session ID
    protected $sid;
    protected // ! Anti-CSRF token
    $_csrf;
    protected // ! User agent
    $_agent;
    protected // ! IP,
    $_ip;
    protected // ! Suspect callback
    $onsuspect;

    protected $prefix;
    protected $ttl;
    protected $logger;
    protected $debug;
    protected $rtn;

    /**
     *   Open session
     *
     * @param $path string
     * @param $name string
     *
     * @return true
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     *   Close session
     *
     * @return true
     */
    public function close(): bool
    {
        $this->sid = null;

        return true;
    }

    /**
     *   Return session data in serialized format
     *
     * @param $id string
     *
     * @return string
     */
    public function read(string $id): string
    {
        // check session id format
        if (!preg_match('/^[a-zA-Z0-9,-]{22,40}$/', $id)) {
            return '';
        }

        $this->sid = $id;
        $this->writeLog('select------::' . $id);
        $this->rtn = rc()::g($this->prefix . $id);
        $this->writeLog('data:' . json_encode($this->rtn));

        if ($this->dry()) {
            return '';
        }

        // IP check ?
        // if ($this->rtn['ip'] != $this->_ip || $this->rtn['agent'] != $this->_agent) {
        //     f3()->call($this->onsuspect,[$this, $id]);
        //     $this->destroy($id);
        //     $this->close();
        //     unset(f3()->{'COOKIE.'.session_name()});
        //     f3()->error(403);
        // }

        return $this->rtn['data'];
    }

    /**
     *   Write session data
     *
     * @param $id   string
     * @param $data string
     *
     * @return true
     */
    public function write(string $id, string $data): bool
    {
        $logger = new \Log('session.log');
        if ($this->dry()) {
            $this->writeLog('insert------start');
            rc()::s($this->prefix . $id, [
                'data'  => $data,
                'ip'    => $this->_ip,
                'agent' => $this->_agent,
                'stamp' => time(),
            ]);
            $this->writeLog('insert------::' . $this->sid());
        } else {
            $this->writeLog('update------start');
            $this->writeLog($_SERVER['REQUEST_URI']);
            $this->writeLog('data:' . $data);

            rc()::s($this->prefix . $id, [
                'data'  => $data,
                'ip'    => $this->_ip,
                'agent' => $this->_agent,
                'stamp' => time(),
            ]);
        }

        return true;
    }

    /**
     *   Destroy session
     *
     * @param $id string
     *
     * @return true
     */
    public function destroy(string $id): bool
    {
        rc()::del($this->prefix . $id);

        return true;
    }

    /**
     *   Garbage collector
     *
     * @param $max int
     *
     * @return true
     */
    public function cleanup(int $max)
    {
        return $this->gc($max);
    }

    /**
     * Garbage collector required by \SessionHandlerInterface.
     */
    public function gc(int $max): int|false
    {
        // Redis handles expiration via TTL; return success indicator.
        return 1;
    }

    /**
     *   Return session id (if session has started)
     *
     * @return string|null
     */
    public function sid()
    {
        return $this->sid;
    }

    /**
     *   Return anti-CSRF token
     *
     * @return string
     */
    public function csrf()
    {
        return $this->_csrf;
    }

    /**
     *   Return IP address
     *
     * @return string
     */
    public function ip()
    {
        return $this->_ip;
    }

    /**
     *   Return Unix timestamp
     *
     * @return string|false
     */
    public function stamp()
    {
        if (!$this->sid) {
            session_start();
        }

        return $this->dry() ? false : $this->rtn['stamp'];
    }

    /**
     *   Return HTTP user agent
     *
     * @return string
     */
    public function agent()
    {
        return $this->_agent;
    }

    /**
     *   Return TRUE if current cursor position is not mapped to any record
     *
     * @return bool
     **/
    public function dry()
    {
        return empty($this->rtn) ? true : false;
    }

    public function writeLog($str)
    {
        ($this->debug) ? $this->logger->write($str) : '';
    }

    /**
     *   Instantiate class
     *
     * @param $force     bool
     * @param $onsuspect callback
     * @param $key       string
     */
    public function __construct($force = true, $onsuspect = null, $key = null)
    {
        $headers   = f3()->HEADERS;
        $agentBots = ['bot', 'crawl', 'curl', 'dataprovider', 'search', 'get', 'spider', 'find', 'java', 'majesticsEO', 'google', 'yahoo', 'teoma', 'contaxe', 'yandex', 'libwww-perl', 'facebookexternalhit'];
        $blockIps  = [];

        f3()->JAR = [
            'lifetime' => (86400 * f3()->get('token_expired')),
            'samesite' => 'Strict',          // [重點] 限制跨站請求 (防範 CSRF)，可視需求改為 Lax || Strict
            'secure' => true,
        ];

        $this->_agent = $headers['User-Agent'] ?? '';
        $this->_ip    = f3()->IP;

        if (!preg_match('/' . implode('|', $agentBots) . '/i', $this->_agent) && !in_array($this->_ip, $blockIps)) {
            $this->onsuspect = $onsuspect;

            $this->prefix = 'sess_';
            $this->ttl    = ini_get('session.gc_maxlifetime');

            session_set_save_handler($this, true);

            register_shutdown_function('session_commit');
            $this->_csrf = f3()->SEED . '.' . f3()->hash(mt_rand());

            if ($key) {
                f3()->$key = $this->_csrf;
            }

            $this->logger = new \Log('session.log');

            $this->debug = false;

            session_start();
        }
    }
}
