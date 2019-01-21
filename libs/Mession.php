<?php
namespace F3CMS;

class Mession extends MHelper
{
    /**
     * @var Session ID
     */
    protected $sid;

    /**
     * @var Anti-CSRF token
     */
    protected $_csrf;

    /**
     * @var User agent
     */
    protected $_agent;

    /**
     * @var IP
     */
    protected $_ip;

    /**
     * @var Suspect callback
     */
    protected $onsuspect;

    /**
     *   Open session
     * @param  $path  string
     * @param  $name  string
     * @return TRUE
     */
    public function open($path, $name)
    {
        return true;
    }

    /**
     *   Close session
     * @return TRUE
     */
    public function close()
    {
        $this->sid = null;
        return true;
    }

    /**
     *   Return session data in serialized format
     * @param  $id      string
     * @return string
     */
    public function read($id)
    {
        $this->sid = $id;

        $this->writeLog('select------::' . $id);
        $this->rtn = $this->get($this->tbl, '*', array('session_id' => $id));
        $this->writeLog('data:' . json_encode($this->rtn));

        if ($this->dry()) {
            return '';
        }

        return $this->rtn['data'];
    }

    /**
     *   Write session data
     * @param  $id    string
     * @param  $data  string
     * @return TRUE
     */
    public function write($id, $data)
    {
        $logger = new \Log('session.log');
        if ($this->dry()) {
            $this->writeLog('insert------start');
            $this->insert($this->tbl, array(
                'session_id' => $id,
                'data'       => $data,
                'ip'         => $this->_ip,
                'agent'      => $this->_agent,
                'stamp'      => time()
            ));
            $this->writeLog('insert------::' . $this->id());
        } else {
            $this->writeLog('update------start');
            $this->writeLog('data:' . $data);

            $this->update($this->tbl, array(
                'data'  => $data,
                'ip'    => $this->_ip,
                'agent' => $this->_agent,
                'stamp' => time()
            ), array(
                'session_id' => $id
            ));
        }

        return true;
    }

    /**
     *   Destroy session
     * @param  $id    string
     * @return TRUE
     */
    public function destroy($id)
    {
        $this->delete($this->tbl, array(
            'session_id' => $id
        ));

        return true;
    }

    /**
     *   Garbage collector
     * @param  $max   int
     * @return TRUE
     */
    public function cleanup($max)
    {
        $this->delete($this->tbl, array(
            'stamp[<]' => time() - 86400 * 30
        ));

        return true;
    }

    /**
     *   Return session id (if session has started)
     * @return string|NULL
     */
    public function sid()
    {
        return $this->sid;
    }

    /**
     *   Return anti-CSRF token
     * @return string
     */
    public function csrf()
    {
        return $this->_csrf;
    }

    /**
     *   Return IP address
     * @return string
     */
    public function ip()
    {
        return $this->_ip;
    }

    /**
     *   Return Unix timestamp
     * @return string|FALSE
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
     * @return string
     */
    public function agent()
    {
        return $this->_agent;
    }

    /**
     *   Return TRUE if current cursor position is not mapped to any record
     * @return bool
     */
    public function dry()
    {
        return empty($this->rtn) ? true : false;
    }

    /**
     * @param $str
     */
    public function writeLog($str)
    {
        ($this->debug) ? $this->logger->write($str) : '';
    }

    /**
     *   Instantiate class
     * @param $force     bool
     * @param $onsuspect callback
     * @param $key       string
     */
    public function __construct($force = true, $onsuspect = null, $key = null)
    {
        // TODO: create table

        parent::__construct();
        $this->onsuspect = $onsuspect;
        $this->tbl = 'sessions';

        session_set_save_handler(
            array($this, 'open'),
            array($this, 'close'),
            array($this, 'read'),
            array($this, 'write'),
            array($this, 'destroy'),
            array($this, 'cleanup')
        );

        register_shutdown_function('session_commit');

        $headers = f3()->HEADERS;
        $this->_csrf = f3()->SEED . '.' . f3()->hash(mt_rand());

        if ($key) {
            f3()->$key = $this->_csrf;
        }

        $this->_agent = isset($headers['User-Agent']) ? $headers['User-Agent'] : '';
        $this->_ip = f3()->IP;

        $this->logger = new \Log('session.log');

        $this->debug = false;

        session_start();
    }

}
