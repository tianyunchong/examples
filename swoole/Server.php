<?php
/**
 * 测试下swoole任务
 */
namespace Tasks\Worker;

use Phalcon\CLI\Task;
use Swoole_websocket_server;

/**
 * Class TestTask
 * @package Tasks\Worker
 */
class TestTask extends Task
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

    public function testAction()
    {
        //swoole1.8 例子
        $server = new swoole_websocket_server("0.0.0.0", 9501);
        $port2  = $server->listen('127.0.0.1', 9502, SWOOLE_SOCK_TCP);
        $port2->set(array(
            'open_eof_check' => true, //打开EOF检测
            'package_eof'    => "\n", //设置EOF
        ));
        $port2->on('receive', function ($serv, $fd, $from_id, $data) {
            $serv->send($fd, "Swoole:" . $data);
            $serv->close($fd);
        });
        $port2->on('connect', function ($serv, $fd, $from_id) {
            echo "[#" . posix_getpid() . "]\tClient@[$fd:$from_id]: Connect.\n";
        });
        $server->on('open', function (swoole_websocket_server $server, $request) {
            print_r($server);

            echo "server: handshake success with fd{$request->fd}\n";
        });
        $server->on('message', function (swoole_websocket_server $server, $frame) {
            echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
            $server->push($frame->fd, "this is server");
        });
        $server->on('close', function ($ser, $fd) {
            echo "client {$fd} closed\n";
        });
        $server->start();
    }
}
