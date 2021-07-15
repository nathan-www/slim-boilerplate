<?php

namespace App\Database;

class Database
{
    protected $db;

    public function __construct($dbname, $setup=null)
    {
        if (!file_exists(__DIR__ . "/" . $dbname . ".db")) {
            $this->db = new \SQLite3(__DIR__ . "/" . $dbname . ".db");
            if (is_callable($setup)) {
                $setup($this->db);
            }
        } else {
            $this->db = new \SQLite3(__DIR__ . "/" . $dbname . ".db");
        }
    }
    

    public function getDB()
    {
        return $this->db;
    }



    public function insert($table, $data)
    {
        $colStr = "";
        $valStr = "";

        foreach ($data as $k=>$v) {
            $colStr .= $k . ",";
            $valStr .= ":" . $k . ",";
        }

        $q = "INSERT INTO ".$table." (".rtrim($colStr, ",").") VALUES (".rtrim($valStr, ",").");";
        $stmt = $this->db->prepare($q);

        foreach ($data as $k=>$v) {
            $stmt->bindValue(":".$k, $v);
        }
        return $stmt->execute();
    }



    public function where($table, $condition)
    {
        if (is_string($condition)) {
            $q = "SELECT * FROM " . $table . " WHERE " . $condition;
        } else {
            $conditionStr = "";
            foreach ($condition as $k=>$v) {
                $conditionStr .= $k."=:".$k." AND ";
            }
            $q = "SELECT * FROM " . $table . " WHERE " . substr($conditionStr, 0, -5);
        }


        $stmt = $this->db->prepare($q);
        if (is_array($condition)) {
            foreach ($condition as $k=>$v) {
                $stmt->bindValue(":".$k, $v);
            }
        }

        $res = $stmt->execute();
        $arr = [];
        while ($row = $res->fetchArray()) {
          $arr[] = $row;
        }

        return $arr;
    }


    public function update($table, $where, $newdata)
    {
        $setStr = "";
        foreach ($newdata as $k=>$v) {
            $setStr .= $k . "=:new".$k.",";
        }
        $setStr = rtrim($setStr, ",");

        if (is_string($where)) {
            $q = "UPDATE " . $table . " SET " . $setStr . " WHERE " . $where;
        } else {
            $conditionStr = "";
            foreach ($where as $k=>$v) {
                $conditionStr .= $k."=:".$k." AND ";
            }
            $q = "UPDATE " . $table . " SET " . $setStr . " WHERE " . substr($conditionStr, 0, -5);
        }

        $stmt = $this->db->prepare($q);
        foreach ($newdata as $k=>$v) {
            $stmt->bindValue(":new".$k, $v);
        }

        if (is_array($where)) {
            foreach ($where as $k=>$v) {
                $stmt->bindValue(":".$k, $v);
            }
        }

        return $stmt->execute();
    }


    public function delete($table, $condition)
    {
        if (is_string($condition)) {
            $q = "DELETE FROM " . $table . " WHERE " . $condition;
        } else {
            $conditionStr = "";
            foreach ($condition as $k=>$v) {
                $conditionStr .= $k."=:".$k." AND ";
            }
            $q = "DELETE FROM " . $table . " WHERE " . substr($conditionStr, 0, -5);
        }

        $stmt = $this->db->prepare($q);
        if (is_array($condition)) {
            foreach ($condition as $k=>$v) {
                $stmt->bindValue(":".$k, $v);
            }
        }

        return $stmt->execute();
    }
}
