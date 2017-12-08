<?php

namespace app\core;

class Model
{
    private static $obj;
    private $db;

    public static function object()
    {
        if (empty(self::$obj)) self::$obj = new self();
        return self::$obj;
    }

    public function __construct()
    {
        $this->db = new \mysqli('127.0.0.1','dev_corp','corp1723','db_corp');
        $this->db->query('SET NAMES utf8');
    }

    public function query($query)
    {
        return $this->db->query($query);
    }

    public function Get($table, $params = '*', $who = 1, $val = 1)
    {
        return $this->db->query("SELECT $params FROM $table WHERE $who = '$val'");
    }

    public function Set($table, $field, $val, $who = 1, $by = 1)
    {
        return $this->db->query("UPDATE $table SET $field = '$val' WHERE $who = '$by'");
    }

    public function Delete($table, $who, $by)
    {
        return $this->db->query("DELETE FROM $table WHERE $who = '$by'");
    }

    public function Add($table, $val)
    {
        return $this->db->query("INSERT INTO $table VALUES ('$val')");
    }

    public function __destruct()
    {
        $this->db->close();
        unset($this->db);
    }
}
