<?php
namespace Tasks\Worker;

use Phalcon\CLI\Task;
use swoole_client;

/**
 * ClientTask
 * @package Tasks\Worker
 */
class ClientTask extends Task
{
    /**
     * 初始化链接配置
     *
     * @Author tianyunzi
     * @DateTime 2015-07-28T12:22:36+0800
     *
     * @return null
     */
    public function initialize()
    {

    }

    public function runAction()
    {
        $client = new swoole_client(SWOOLE_SOCK_TCP);
        if (!$client->connect('127.0.0.1', 9502, -1)) {
            exit("connect failed. Error: {$client->errCode}\n");
        }
        $client->send("hello world\n");
        echo $client->recv();
        $client->close();
        // $client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        // //设置事件回调函数
        // $client->on("connect", function ($cli) {
        //     $cli->send("hello world\n");
        // });
        // $client->on("receive", function ($cli, $data) {
        //     echo "Received: " . $data . "\n";
        // });
        // $client->on("error", function ($cli) {
        //     echo "Connect failed\n";
        // });
        // $client->on("close", function ($cli) {
        //     echo "Connection close\n";
        // });
        // //发起网络连接
        // $client->connect('127.0.0.1', 9501, 0.5);
    }
}
