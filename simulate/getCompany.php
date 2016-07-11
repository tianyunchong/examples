<?php
/**
 * 从自己的后台获取到所有要处理的企业信息
 * @author tianyunchong
 * @datetime 2016/07/09
 */
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set("asia/shanghai");
$conn        = new Table("localhost");
$cookie_file = "/tmp/company.cookie";
/** 模拟登录获取下企业信息 */
//set_cookie_files("http://manage.xizhi.com/", $cookie_file);
$request_headers   = array();
$request_headers[] = "Accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8";
//$request_headers[] = "Accept-Encoding:gzip, deflate, sdch";
$request_headers[] = "Cookie:_GCWGuid=E300D389-4552-8895-5A2A-B8CB8CA2E416; m_xizhi_user=SF6xdSpyI1My9tCp6zSJ4arXXz16%2BqaDlPhcJh3Sl9EDiheEbDbfeU5Gdw3giIQETYhJlILZSWpnAeegIjh8XEgt%2FeqqEOPxLcnOFknUKIkuKvGzVh7E2WvLS471IQPa";
$request_headers[] = "Accept-Language:zh-CN,zh;q=0.8";
$request_headers[] = "Connection:keep-alive";
$request_headers[] = "Host:manage.xizhi.com";
$request_headers[] = "Referer:http://manage.xizhi.com/gs/index/";
$request_headers[] = "Upgrade-Insecure-Requests:1";
$request_headers[] = "User-Agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.82 Safari/537.36 QQBrowser/4.0.4035.400";
$page              = 1;
while (1) {
    echo $page . "=====================\n";
    $content = curl_get_content("http://manage.xizhi.com/gs/index?page=" . $page, $request_headers, $cookie_file);
    preg_match_all('/<tr>\s+<td>(.+)<\/td>\s+<td>(.+)<\/td>\s+<td>(.+)<\/td>\s+<td>(.+)<\/td>\s+<td>(.+)<\/td>\s+<\/tr>/', $content, $matches);
    if (empty($matches[0])) {
        echo "当前处理到第" . $page . "页，未获取到企业信息";
        break;
    }
    $length = count($matches[0]);
    for ($i = 0; $i < $length; $i++) {
        /** 获取下cid */
        preg_match('/\?cid=(\d+)"/', $matches[5][$i], $match);
        $insertArr = array(
            "username"   => $matches[1][$i],
            "comname"    => $matches[2][$i],
            "promain"    => addslashes($matches[3][$i]),
            "regtime"    => strtotime($matches[4][$i]),
            "regtimestr" => $matches[4][$i],
            "cid"        => $match[1],
        );
        $conn->insert($insertArr, "test.company_check");
        echo $insertArr["cid"] . "\n";
    }
    $page++;
}

/**
 *
 *      <tr>
 * <td>angelicmake</td>
 * <td>成都凡品诚信商贸有限公司</td>
 * <td>服装|鞋业</td>
 * <td>2014-04-15</td>
 * <td><a href="/gs/detail/?cid=1868880" target="_blank">去审核</a></td>
 * </tr>
 *
 */

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
    $rs = curl_exec($ch);
    curl_close($ch);
}

function curl_get_content($url, $request_headers, $cookie_file = "/tmp/cookie.txt")
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //获取页面内容不直接输出
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
    //curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
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
