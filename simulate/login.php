<?php
/**
 * 模拟登陆查询
 */
header('Content-Type: text/html; charset=utf-8');

$url = "http://www.qichacha.com/";
/** 要查询的企业名称 */
$comname = "小米科技有限责任公司";
/** 存储下cookie */
set_cookie_files($url);
/** 设置下http 头 */
$userAgent       = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.82 Safari/537.36 QQBrowser/4.0.4035.400";
$request_headers = array();

$request_headers[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8";
//$request_headers[] = "Accept-Encoding:gzip, deflate, sdch";
$request_headers[] = "Accept-Language:zh-CN,zh;q=0.8";
$request_headers[] = "Cache-Control:max-age=0";
$request_headers[] = "Connection:keep-alive";
$request_headers[] = "Host:www.qichacha.com";
$request_headers[] = "Referer:http://www.qichacha.com/search?key=" . urlencode($comname) . "&index=2";
$request_headers[] = "Upgrade-Insecure-Requests:1";
$request_headers[] = 'User-Agent: ' . $userAgent;
/** 读取数据库开始排查信息 */
$conn = new Table("localhost");
$id   = 0;
while (1) {
    $result = $conn->findAll("select * from test.company_check where id > '" . $id . "' order by id asc limit 100");
    if (empty($result)) {
        break;
    }
    foreach ($result as $value) {
        $id      = $value["id"];
        $comname = trim($value["comname"]);
        if ($value["ischeck"]) {
            continue;
        }
        /** 开始排查下结果 */
        $content = curl_get_content("http://www.qichacha.com/search?key=" . urlencode($comname) . "&index=2", $request_headers);
        preg_match('/小查为您找到\s+<span class="text-danger">\s+(\d+)\s+<\/span>\s+家符合条件的企业, 用时/', $content, $matches);
        if (empty($matches)) {
            echo $comname;
            echo "\n";
            echo "当前检索系统未查到结果，请人工访问下页面，确认下是否超时限制\n";
            exit;
        }
        /** 开始更新下数据 */
        $conn->update("update test.company_check set qccnum = '" . $matches[1] . "', ischeck = '1' where id = '" . $value["id"] . "'");
        echo $value["cid"] . "\n";
    }
}

//preg_match("")
/**
 * 设置下cookie文件
 *
 * @Author   tianyunzi
 * @DateTime 2016-07-09T09:18:03+0800
 * @param    null
 */
function set_cookie_files($url, $file = "/tmp/cookie.txt")
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, 0); //不返回header部分
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //返回字符串，而非直接输出
    curl_setopt($ch, CURLOPT_COOKIEJAR, $file); //存储cookies
    curl_exec($ch);
    curl_close($ch);
}

function curl_get_content($url, $request_headers, $cookie_file = "/tmp/cookie.txt")
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //获取页面内容不直接输出
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
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