<?php

namespace F3CMS;

class Mession extends MHelper
{
    //! Session ID
    protected $sid;
    //! Anti-CSRF token
        protected $_csrf;
    //! User agent
        protected $_agent;
    //! IP,
        protected $_ip;
    //! Suspect callback
        protected $onsuspect;

    /**
     *   Open session
     *
     * @param $path string
     * @param $name string
     *
     * @return true
     */
    public function open($path, $name)
    {
        return true;
    }

    /**
     *   Close session
     *
     * @return true
     */
    public function close()
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
    public function read($id)
    {
        $this->sid = $id;

        $this->writeLog('select------::' . $id);
        $this->rtn = $this->get($this->tbl, '*', ['session_id' => $id]);
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
     *   Destroy session
     *
     * @param $id string
     *
     * @return true
     */
    public function destroy($id)
    {
        $this->delete($this->tbl, [
            'session_id' => $id,
        ]);

        return true;
    }

    /**
     *   Garbage collector
     *
     * @param $max int
     *
     * @return true
     */
    public function cleanup($max)
    {
        $this->delete($this->tbl, [
            'stamp[<]' => time() - 86400 * 30,
        ]);

        return true;
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
     *   @return bool
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
