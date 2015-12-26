<?php

class MedooHelper extends medoo
{
    private static $_instance = false;

    public function __construct()
    {
        $this->pdo = f3()->get('DB')->pdo();
    }

    public static function instance()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }
    /**
     *   Begin SQL transaction
     *   @return bool
     *
     */
    function begin()
    {
        $out = $this->pdo->begintransaction();
        return $out;
    }
    /**
     *   Rollback SQL transaction
     *   @return bool
     *
     */
    function rollback()
    {
        $out = $this->pdo->rollback();
        return $out;
    }
    /**
     *   Commit SQL transaction
     *   @return bool
     *
     */
    function commit()
    {
        $out = $this->pdo->commit();
        return $out;
    }

    public function exec($query)
    {
        if ($this->debug_mode) {
            echo $query;
            $this->debug_mode = false;
            return false;
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        $error = $stmt->errorinfo();
        if ($error[0] != \PDO::ERR_NONE) {
            throw new PDOException($error[2], $error[0]);
            return false;
        }
        else {
            array_push($this->logs, $query);
            return $stmt->rowcount();
        }
    }

    public function query($query)
    {
        if ($this->debug_mode) {
            echo $query;
            $this->debug_mode = false;
            return false;
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        $error = $stmt->errorinfo();
        if ($error[0] != \PDO::ERR_NONE) {
            throw new PDOException($error[2], $error[0]);
            return false;
        }
        else {
            array_push($this->logs, $query);
            return $stmt->fetchall(\PDO::FETCH_ASSOC);
        }
    }
}
