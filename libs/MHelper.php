<?php
namespace F3CMS;

use \Medoo\Medoo as Medoo;

class MHelper extends Medoo
{
    /**
     * @var mixed
     */
    private static $_instance = false;

    public function __construct()
    {
        // $this->pdo = f3()->get('DB')->pdo();
        parent::__construct(array(
            'database_type' => 'mysql',
            'database_name' => f3()->get('db_name'),
            'server'        => f3()->get('db_host'),
            'username'      => f3()->get('db_account'),
            'password'      => f3()->get('db_password'),
            'charset'       => 'utf8'
        ));
    }

    public static function init()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     *   Begin SQL transaction
     * @return bool
     */
    public function begin()
    {
        $out = $this->pdo->begintransaction();
        return $out;
    }

    /**
     *   Rollback SQL transaction
     * @return bool
     */
    public function rollback()
    {
        $out = $this->pdo->rollback();
        return $out;
    }

    /**
     *   Commit SQL transaction
     * @return bool
     */
    public function commit()
    {
        $out = $this->pdo->commit();
        return $out;
    }
}
