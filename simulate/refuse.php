<?php
/**
 * 考虑拒审
 * User: zyh
 * Date: 16/7/9
 * Time: 下午7:05
 */
/** 模拟登录获取下企业信息 */
$cookie_file       = "/tmp/company.cookie";
$request_headers   = array();
$request_headers[] = "Accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8";
$request_headers[] = "Cookie:_GCWGuid=E300D389-4552-8895-5A2A-B8CB8CA2E416; m_xizhi_user=SF6xdSpyI1My9tCp6zSJ4arXXz16%2BqaDlPhcJh3Sl9EDiheEbDbfeU5Gdw3giIQETYhJlILZSWpnAeegIjh8XEgt%2FeqqEOPxLcnOFknUKIkuKvGzVh7E2WvLS471IQPa";
$request_headers[] = "Accept-Language:zh-CN,zh;q=0.8";
$request_headers[] = "Connection:keep-alive";
$request_headers[] = "Host:manage.xizhi.com";
$request_headers[] = "Referer:http://manage.xizhi.com/gs/detail/?cid=1876559";
$request_headers[] = "Upgrade-Insecure-Requests:1";
$request_headers[] = "User-Agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.82 Safari/537.36 QQBrowser/4.0.4035.400";
$path              = "/www/gitwork/examples/simulate/result.log";
$f                 = fopen($path, "r") or die("无法打开文件");
while (!feof($f)) {
    $line = trim(fgets($f));
    if (empty($line)) {
        continue;
    }
    list($cid, $comname) = explode("\t", $line);
    /** 开始抓取下id */
    $content = curl_get_content("http://manage.xizhi.com/gs/detail/?cid=" . $cid, $request_headers, $cookie_file);
    preg_match('/<input type="hidden" name="id" value="(\d+)">/', $content, $matches);
    $id = $matches[1];
    echo $cid . "\n";
    $postData = array(
        "state"     => 3,
        "checkdesc" => array(
            "悉知、企查查、公信系统等确无此企业信息",
        ),
        "id"        => $id,
        "cid"       => $cid,
        "recomname" => "",
    );
    $postUrl = "http://manage.xizhi.com/gs/dodetail?isajax=1";
    $res     = curl_post($postUrl, $postData, $request_headers);
    var_dump($res);
    echo "\n";
}

function curl_post($url, $data, $request_headers)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
    $return = curl_exec($ch);
    curl_close($ch);
    return $return;
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
