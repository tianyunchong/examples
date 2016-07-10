<?php
/**
 * Created by PhpStorm.
 * User: zhangyanghua
 * Date: 16/7/1
 * Time: 上午9:27
 */
namespace Tasks\Worker;

use Phalcon\CLI\Task;
use swoole_client;
use swoole_table;
use swoole_websocket_frame;
use swoole_websocket_server;

/**
 * Class StremServerTask
 * @package Tasks\Worker
 */
class StreamWebSockServerTask extends Task
{
    private $socketPort = 9502;
    private $tcpPort    = 9503;
    private $server;
    private $port2;
    private $table;
    /**
     * 环境初始化
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T13:51:12+0800
     * @return   null
     */
    public function initialize()
    {
    }

    /**
     * 模拟tcp请求
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T14:33:00+0800
     * @return   [type]                   [description]
     */
    public function testAction()
    {
        $data = array(
            "orderno" => "1254336",
            "msg"     => "当前订单1254336已经处理成功！",
        );
        $client = new swoole_client(SWOOLE_SOCK_TCP);
        if (!$client->connect('127.0.0.1', $this->tcpPort, -1)) {
            exit("connect failed. Error: {$client->errCode}\n");
        }
        $client->send(json_encode($data));
        echo $client->recv();
        $client->close();
    }

    /**
     * 开始运行
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T13:51:26+0800
     * @return   null
     */
    public function runAction()
    {
        $this->server = new swoole_websocket_server("0.0.0.0", $this->socketPort);
        $this->port2  = $this->server->listen("0.0.0.0", $this->tcpPort, SWOOLE_SOCK_TCP);
        $this->port2->set(array(
            'open_eof_check' => true, //打开EOF检测
            'package_eof'    => "\n", //设置EOF
        ));
        $this->server->set(array(
            'heartbeat_idle_time' => 360, //设置下最大允许空闲时间 单位s
        ));
        /** tcp监听 */
        $this->port2->on("connect", array($this, "onConnect"));
        $this->server->on("receive", array($this, "onReceive"));
        /** websocket监听 */
        $this->server->on("open", array($this, "onOpenWebSocket"));
        $this->server->on("message", array($this, "onMessageWebSocket"));
        $this->server->on("close", array($this, "onCloseWebSocket"));
        /** 当开启worker启动时 */
        $this->server->on('WorkerStart', array($this, "onWorkerStart"));
        /** 建立一个table，存储下传递过来的订单号 */
        $this->table = new swoole_table(10000);
        $this->table->column('fd', swoole_table::TYPE_INT, 4);
        $this->table->create();
        $this->server->start();
    }

    /**
     * 设置接收回调
     * @author tianyunchong
     * Time: 9:52 am
     * @return null
     */
    public function onReceive(swoole_websocket_server $server, $fd, $from_id, $data)
    {
        $info = $server->connection_info($fd, $from_id);
        $server->close($fd);
        if ($info["server_port"] != $this->tcpPort) {
            return;
        }
        /** 如果是tcp传递过来的信息，且传递了订单编号，则开始操作 */
        $data = json_decode($data, true);
        if (!is_array($data)) {
            return;
        }
        if (!isset($data["orderno"]) || empty($data["orderno"])) {
            return;
        }
        $tableInfo = $this->table->get($data["orderno"]);
        /** 不存在对应的订单号 */
        if (!$tableInfo) {
            return;
        }
        $fd = $tableInfo["fd"];
        /**  判断下websokcet客户端是否还链接正常 */
        $fdinfo = $this->server->connection_info($fd);
        if (!$fdinfo) {
            return;
        }
        /** 传递给客户端数据信息 */
        $this->server->push($fd, $data["msg"]);
    }

    /**
     * 设置链接回调,接受来自tcp客户端的链接
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T14:00:19+0800
     * @param    [type]                   $serv    [description]
     * @param    [type]                   $fd      [description]
     * @param    [type]                   $from_id [description]
     * @return   null
     */
    public function onConnect($serv, $fd, $from_id)
    {
        echo "[#" . posix_getpid() . "]\tClient@[$fd:$from_id]: tcp Connect.\n";
    }

    /**
     * 当WebSocket客户端关闭回调
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T14:01:44+0800
     * @param    [type]                   $serv [description]
     * @param    [type]                   $fd   [description]
     * @return   null
     */
    public function onCloseWebSocket($serv, $fd)
    {
        echo "client {$fd} closed\n";
    }
    /**
     * 当WebSocket客户端与服务器建立连接并完成握手后会回调此函数
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T14:04:21+0800
     * @param    swoole_websocket_server  $server  [description]
     * @param    [type]                   $request [description]
     * @return   null
     */
    public function onOpenWebSocket(swoole_websocket_server $server, $request)
    {
        echo "server: handshake success with fd{$request->fd}\n";
    }

    /**
     * 接收到客户端的数据时回调,发送tcp客户端传来的信息给websocket端
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T14:07:38+0800
     * @param    swoole_websocket_server  $server [description]
     * @param    [type]                   $frame  [description]
     * @return   null
     */
    public function onMessageWebSocket(swoole_websocket_server $server, swoole_websocket_frame $frame)
    {
        /** 当接收到客户端传递过来的编号 */
        //$data = json_decode($frame->data);
        // if (is_array($data) && isset($data["orderno"])) {
        //     $this->table->set($data["orderno"], array("fd" => $frame->fd));
        // }
        $this->table->set($frame->data, array("fd" => $frame->fd));
        $server->push($frame->fd, $frame->data . "和当前的fd已经绑定存入swoole_table");
    }

    /**
     * 定时器检测下链接是否超市
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T16:13:09+0800
     * @return   [type]                   [description]
     */
    public function checkConnTimeout()
    {
        /** 获取下超过约定时间的链接 */
        $closeFdArr = $this->server->heartbeat();
        if (empty($closeFdArr)) {
            return;
        }
        foreach ($closeFdArr as $fd) {
            /** 获取下链接信息 */
            $fdinfo = $this->server->connection_info($fd);
            if (!$fdinfo) {
                continue;
            }
            if ($fdinfo["server_port"] != $this->socketPort) {
                continue;
            }
            /** 超时的websocket连接,推送连接超时，关闭连接 */
            echo "client {$fd} is time out\n";
            $this->server->push($fd, "sorry, your connect is time out!");
            $this->server->close($fd);
        }
    }

    /**
     * 当worker启动时
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T10:46:17+0800
     * @param    [type]                   $serv      [description]
     * @param    [type]                   $worker_id [description]
     * @return   null
     */
    public function onWorkerStart($serv, $worker_id)
    {
        if ($this->server->taskworker) {
            return;
        }
        /** 增加个定时器每分钟检测下websocket超过6分钟的链接，主动关闭 */
        $this->server->tick(5000, array($this, "checkConnTimeout"));
    }
}
