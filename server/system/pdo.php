<?php
/**
 The MIT License (MIT)

 Copyright (c) 2014 DZCP-Community
 DZCP - deV!L`z ClanPortal Steam Proxy Server
 http://www.dzcp.de

 Permission is hereby granted, free of charge, to any person obtaining a copy of
 this software and associated documentation files (the "Software"), to deal in
 the Software without restriction, including without limitation the rights to
 use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 the Software, and to permit persons to whom the Software is furnished to do so,
 subject to the following conditions:

 The above copyright notice and this permission notice shall be included in all
 copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

class db {
    protected static $dbConf = array("default" => array("driver" => "mysql","db" => "cdcol", "db_host" => "localhost", "db_user" => "root", "db_pw" => ""));
    protected static $instances = array();

    protected $active = false;
    protected $dbHandle = null;
    protected $lastInsertId = false;
    protected $rowCount = false;
    protected $queryCounter = 0;
    protected $active_driver = '';
    protected $connection_pooling = true;
    protected $connection_encrypting = true;
    protected $mysql_buffered_query = true;

    private function __clone() { }

    /**
     * Erstellt das PDO Objekt mit vorhandener Konfiguration
     * @category PDO Database
     * @param string $active = "default"
     * @throws PDOException
     */
    protected function connect($active = "default") {
        if (!isset(self::$dbConf[$active]))
            throw new PDOException("No supported connection scheme");

        $dbConf = self::$dbConf[$active];
        try {
            if(!$dsn = $this->dsn($active))
                throw new Exception("PDO driver is missing");

            $db = new PDO($dsn, $dbConf['db_user'], $dbConf['db_pw']);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->query("set character set utf8");
            $db->query("set names utf8");

            $this->dbHandle = $db;
            $this->active = $active; //mark as active
        } catch (PDOException $ex) {
            throw new PDOException("Connection Exception: " . $ex->getMessage());
        }
    }

    public static function setConfig($active = "default", array $data) {
        if(isset($data['db']) && isset($data['db_host']) && isset($data['db_host']) && isset($data['db_user']) && isset($data['db_pw'])) {
            self::$dbConf[$active] = $data;
            return true;
        }

        return false;
    }

    public static function getInstance($active = "default") {
        if (!isset(self::$dbConf[$active])) {
            throw new Exception("Unexisting db-config $active");
        }

        if (!isset(self::$instances[$active]) || !isInstanceOf('db')) {
            self::$instances[$active] = new db($active);
            self::$instances[$active]->connect($active);
        }

        return self::$instances[$active];
    }

    public function disconnect($active = "") {
        if(empty($active)) {
            unset(self::$instances[$this->active]);
        } else {
            unset(self::$instances[$active]);
        }

        $this->dbHandle = null;
    }

    public function getHandle() {
        return $this->dbHandle;
    }

    public function lastInsertId() {
        return $this->lastInsertId;
    }

    public function rowCount() {
        return $this->rowCount;
    }

    protected function run_query($qry, array $params, $type) {
        if (in_array($type, array("insert", "select", "update", "delete")) === false) {
            throw new Exception("Unsupported Query Type");
        }

        $this->lastInsertId = false;
        $this->rowCount = false;
        $stmnt = $this->active_driver == 'mysql' ? $this->dbHandle->prepare($qry, array(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => $this->mysql_buffered_query)) : $this->dbHandle->prepare($qry);

        try
        {
            $success = (count($params) !== 0) ? $stmnt->execute($params) : $stmnt->execute();
            $this->queryCounter++;

            if (!$success)
                return false;

            if ($type === "insert")
                $this->lastInsertId = $this->dbHandle->lastInsertId();

            $this->rowCount = $stmnt->rowCount();

            return ($type === "select") ? $stmnt : true;
        } catch (PDOException $ex) {
            throw new PDOException("PDO-Exception: " . $ex->getMessage());
        }
    }

    public function tablesize($table = "", $active = "default") {
        $qry = "SHOW TABLE STATUS FROM ".self::$dbConf[$active]['db']; $dbSize = 0;
        if ($stmnt = $this->run_query($qry, array(), 'select')) {
            foreach (objectToArray($stmnt->fetchAll(PDO::FETCH_ASSOC)) as $row) {
                if ( $row["Name"] == $table ) {
                    $dbSize += $row["Data_length"] + $row["Index_length"];
                }
            }

            $size = array();
            $size['db_bytes'] = $dbSize;
            $size['db_size'] = binary_multiples($dbSize,false);
            return $size;
        }
        else
            return false;
    }


    protected function check_driver($use_driver) {
        foreach(PDO::getAvailableDrivers() as $driver) {
            if($use_driver == $driver) return true;
        }

        return false;
    }

    protected function dsn($active) {
        $dbConf = self::$dbConf[$active];
        if(!$this->check_driver($dbConf['driver']))
            return false;

        $this->active_driver = $dbConf['driver'];
        $dsn= sprintf('%s:', $dbConf['driver']);
        switch($dbConf['driver']) {
            case 'mysql':
            case 'pgsql':
                $dsn .= sprintf('host=%s;dbname=%s', $dbConf['db_host'], $dbConf['db']);
                break;
            case 'sqlsrv':
                $dsn .= sprintf('Server=%s;1433;Database=%s', $dbConf['db_host'], $dbConf['db']);
                if($this->connection_pooling) $dsn .= ';ConnectionPooling=1';
                if($this->connection_encrypting) $dsn .= ';Encrypt=1';
            break;
        }

        return $dsn;
    }

    protected function getQueryType($qry) {
        list($type, ) = explode(" ", strtolower($qry), 2);
        return $type;
    }

    public function delete($qry, array $params = array()) {
        if (($type = $this->getQueryType($qry)) !== "delete") {
            throw new Exception("Incorrect Delete Query");
        }

        return $this->run_query($qry, $params, $type);
    }

    public function update($qry, array $params = array()) {
        if (($type = $this->getQueryType($qry)) !== "update") {
            throw new Exception("Incorrect Update Query");
        }

        return $this->run_query($qry, $params, $type);
    }

    public function insert($qry, array $params = array()) {
        if (($type = $this->getQueryType($qry)) !== "insert") {
            throw new Exception("Incorrect Insert Query");
        }

        return $this->run_query($qry, $params, $type);
    }

    public function select_foreach($qry, array $params = array()) {
        if (($type = $this->getQueryType($qry)) !== "select")
            throw new Exception("Incorrect Select Query");

        if ($stmnt = $this->run_query($qry, $params, $type)) {
            return objectToArray($stmnt->fetchAll(PDO::FETCH_ASSOC));
        }
        else
            return false;
    }

    public function select($qry, array $params = array()) {
        $sql = $this->select_foreach($qry,$params);
        return !$sql ? false : $sql[0];
    }

    public function db_selectSingle($qry, array $params = array(), $field = false) {
        if (($type = $this->getQueryType($qry)) !== "select")
            throw new Exception("Incorrect Select Query");

        if ($stmnt = $this->run_query($qry, $params, $type)) {
            $res = $stmnt->fetch(PDO::FETCH_ASSOC);
            return ($field === false) ? $res : $res[$field];
        }
        else
            return false;
    }

    public function query($qry) {
        $this->lastInsertId = false;
        $this->rowCount = false;
        $this->rowCount = $this->dbHandle->exec($qry);
        $this->queryCounter++;
    }

    public function getQueryCounter() {
        return $this->queryCounter;
    }

    public function quote($str) {
        return $this->dbHandle->quote($str);
    }
}