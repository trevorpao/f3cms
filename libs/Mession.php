<?php

namespace F3CMS;

/**
 * Mession 類別擴展了 MHelper，
 * 提供基於資料庫的 Session 管理功能，
 * 包括開啟、關閉、讀取、寫入、銷毀與清理 Session。
 */
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

    protected $rtn; // 用於存儲查詢結果
    protected $tbl; // 資料表名稱
    protected $logger; // 日誌記錄器
    protected $debug = false; // 除錯模式

    /**
     * 開啟 Session。
     *
     * @param string $path Session 存儲路徑
     * @param string $name Session 名稱
     * @return bool 是否成功開啟
     */
    public function open($path, $name)
    {
        return true;
    }

    /**
     * 關閉 Session。
     *
     * @return bool 是否成功關閉
     */
    public function close()
    {
        $this->sid = null;

        return true;
    }

    /**
     * 讀取 Session 資料。
     *
     * @param string $id Session ID
     * @return string Session 資料
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
     * 寫入 Session 資料。
     *
     * @param string $id Session ID
     * @param string $data 要寫入的資料
     * @return bool 是否成功寫入
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
     * 銷毀指定的 Session。
     *
     * @param string $id Session ID
     * @return bool 是否成功銷毀
     */
    public function destroy($id)
    {
        $this->delete($this->tbl, [
            'session_id' => $id,
        ]);

        return true;
    }

    /**
     * 清理過期的 Session。
     *
     * @param int $max 最大存活時間（秒）
     * @return bool 是否成功清理
     */
    public function cleanup($max)
    {
        $this->delete($this->tbl, [
            'stamp[<]' => time() - 86400 * 30,
        ]);

        return true;
    }

    /**
     * 獲取當前的 Session ID。
     *
     * @return string|null Session ID 或 null
     */
    public function sid()
    {
        return $this->sid;
    }

    /**
     * 獲取 Anti-CSRF Token。
     *
     * @return string Anti-CSRF Token
     */
    public function csrf()
    {
        return $this->_csrf;
    }

    /**
     * 獲取用戶的 IP 地址。
     *
     * @return string IP 地址
     */
    public function ip()
    {
        return $this->_ip;
    }

    /**
     * 獲取 Session 的時間戳。
     *
     * @return string|false 時間戳或 false
     */
    public function stamp()
    {
        if (!$this->sid) {
            session_start();
        }

        return $this->dry() ? false : $this->rtn['stamp'];
    }

    /**
     * 獲取用戶的 HTTP User Agent。
     *
     * @return string User Agent
     */
    public function agent()
    {
        return $this->_agent;
    }

    /**
     * 判斷當前游標位置是否未映射到任何記錄。
     *
     * @return bool 是否為空
     */
    public function dry()
    {
        return empty($this->rtn) ? true : false;
    }

    /**
     * 寫入日誌。
     *
     * @param string $str 日誌內容
     */
    public function writeLog($str)
    {
        ($this->debug) ? $this->logger->write($str) : '';
    }

    /**
     * 類別建構子，初始化 Session 管理。
     *
     * @param bool $force 是否強制初始化
     * @param callable|null $onsuspect 可疑行為的回調函式
     * @param string|null $key Anti-CSRF Token 的鍵名
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
