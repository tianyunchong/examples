<?php
/**
 * 不包含公司或厂的
 * 包含个体的，去掉小括号内容字数小于6的
 */
$conn = new Table("localhost");
$id   = 0;
while (1) {
    $resultArr = $conn->findAll("select * from test.company_check_sybak where id > '{$id}' order by id asc limit 100");
    if (empty($resultArr)) {
        break;
    }
    foreach ($resultArr as $value) {
        $id = $value["id"];
        /** 去掉下括号 */
        if (strstr($value["comname"], "(")) {
            $pos              = stripos($value["comname"], "(");
            $value["comname"] = substr($value["comname"], 0, $pos);
        } elseif (strstr($value["comname"], "（")) {
            $pos              = stripos($value["comname"], "（");
            $value["comname"] = substr($value["comname"], 0, $pos);
        }
        if (strstr($value["comname"], "个体")) {
            file_put_contents("result.log", $value["cid"] . "\t" . $value["comname"] . "\n", FILE_APPEND);
            echo $id . "\n";
            continue;
        }
        if (strstr($value["comname"], "公司")) {
            continue;
        }
        if (strstr($value["comname"], "厂")) {
            continue;
        }
        if (mb_strlen($value["comname"], "utf8") >= 6) {
            continue;
        }
        file_put_contents("result.log", $value["cid"] . "\t" . $value["comname"] . "\n", FILE_APPEND);
        echo $id . "\n";
    }
}
class Config
{
    public static $dbArr = array(
        'localhost' => array(
            '127.0.0.1', "root", "123456", "test",
        ),
    );
    public static $cateApiUrl = 'http://cate.ch.gongchang.com/cate_json/'; //本地接口无法使用，暂时调取线上的
}

class Table
{
    public $conn = '';
    public $config;
    public function __construct($connName)
    {
        $db           = Config::$dbArr;
        $this->config = $db[$connName];
        try {
            $this->getConnect();
        } catch (Exception $e) {
            sleep(2);
            $this->getConnect();
        }
    }

    public function getConnect()
    {
        $config = $this->config;
        $i      = 0;
        do {
            $this->conn = new mysqli($config[0], $config[1], $config[2], $config[3], isset($config[4]) ? $config[4] : 3306);
            if ($this->conn) {
                break;
            }
            $i++;
            sleep(2);
        } while ($i <= 3);
        $this->conn->query("set names utf8");
        if (!$this->conn) {
            throw new Exception("connect error@" . $config[0]);
        }
    }

    public function findOne($sql)
    {
        if (empty($sql)) {
            return $sql;
        }
        $query = $this->query($sql);
        return $query->fetch_assoc();
    }

    public function findAll($sql, $primary = "")
    {
        if (empty($sql)) {
            return array();
        }
        $result = array();
        $query  = $this->query($sql);
        while ($item = $query->fetch_assoc()) {
            if ($primary && isset($item[$primary])) {
                $result[$item[$primary]] = $item;
            } else {
                $result[] = $item;
            }
        }
        return $result;
    }

    public function update($sql)
    {
        $rs = $this->query($sql);
        return $this->conn->affected_rows;
    }

    public function insert($data, $table)
    {
        $sql = $this->getInsertSql($data, $table);
        $this->query($sql);
        return $this->conn->insert_id;
    }

    public function getInsertSql($data, $table)
    {
        $sql = "insert into " . $table . " (`" .
        implode("`,`", array_keys($data)) . "`) values" .
        " ('" . implode("','", array_values($data)) . "')";
        return $sql;
    }

    public function query($sql)
    {
        try {
            $query = $this->conn->query($sql);
        } catch (Exception $e) {
            echo $this->conn->error();
            echo "\n";
            echo $e->getMessage();exit();
        }
        if (!$query && in_array($this->conn->errno, array(2006, 2013))) {
            $this->conn - close();
            $this->getConnect();
            return $this->query($sql);
        } elseif (!$query) {
            echo $this->conn->errno . "\t" . $this->conn->error . "\n";
            echo $sql;
            echo "\n";
            exit;
        }
        return $query;
    }

    public function close()
    {
        mysqli_close($this->conn);
    }

    public function logSql($sql)
    {
        $path = "/tmp/init_" . date("Ymd") . ".log";
        file_put_contents($path, $sql . "\t" . date("H:i:s") . "\n", FILE_APPEND);
    }
}
