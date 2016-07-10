<?php
/**
 * 任务发放回调给后端业务接口
 * @author tianyunchong
 * @datetime 2016/07/07
 */
namespace Tasks\Worker;

use Phalcon\CLI\Task;
use swoole_server;
use Xz\Func\ReqHelp;
use Xz\Lib\SocketBeanstalk;

/**
 * SteamServerTask
 * @package Tasks\Worker
 */
class StreamServerTask extends Task
{
    private $server;
    private $num = 8;
    /**
     * 初始化下swoole环境
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T09:56:41+0800
     * @return   [type]                   [description]
     */
    public function initialize()
    {
        $this->di->setShared('beanstalkd', function () {
            $queue = new SocketBeanstalk(
                $this->di['config']->Beanstalk->toArray()
            );
            return $queue;
        });
    }

    /**
     * 预热打入数据
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T11:20:40+0800
     * @return   [type]                   [description]
     */
    public function prepareAction()
    {
        $params = array(
            "url" => "http://finance.gongchang.net/stream/index/request",
        );
        $i = 1000;
        while ($i) {
            try {
                $this->di->get("beanstalkd")->choose("finance_callback");
                $this->di->get("beanstalkd")->put(1024, 0, 1, json_encode($params));
            } catch (Exception $e) {
            }
            $i--;
        }
    }

    /**
     * 开始处理
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T09:57:00+0800
     * @return   null
     */
    public function runAction()
    {
        $this->server = new swoole_server("0.0.0.0", 9501, SWOOLE_BASE, SWOOLE_SOCK_TCP);
        $this->server->set(array('worker_num' => 1, 'task_worker_num' => 8));
        /** 监听下客户端 */
        $this->server->on("connect", array($this, "onConnect"));
        $this->server->on("receive", array($this, "onReceive"));
        /** 开启下task回调 */
        $this->server->on("task", array($this, "onTask"));
        $this->server->on("finish", array($this, "onFinish"));
        /** 当开启worker启动时 */
        $this->server->on('WorkerStart', array($this, "onWorkerStart"));
        $this->server->start();
    }

    /**
     * 当客户端连接时
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T09:57:14+0800
     * @return   null
     */
    public function onConnect($serv, $fd)
    {
        echo "Client:Connect.\n";
    }

    /**
     * 当接受到客户端的请求时
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T10:09:33+0800
     * @param    [type]                   $serv    [description]
     * @param    [type]                   $fd      [description]
     * @param    [type]                   $from_id [description]
     * @param    [type]                   $data    [description]
     * @return   null
     */
    public function onReceive($serv, $fd, $from_id, $data)
    {
        echo "get message from client:" . $fd . "\n";
        $this->server->close($fd);
        $params       = json_decode($data, true);
        $params["fd"] = $fd;
        $this->server->task(json_encode($params), -1);
    }

    /**
     * 当接收到task任务请求
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T10:16:35+0800
     * @param    swoole_server            $serv    [description]
     * @param    int                      $task_id [description]
     * @param    int                      $from_id [description]
     * @param    string                   $data    [description]
     * @return   null
     */
    public function onTask($serv, $task_id, $from_id, $data)
    {
        $params = json_decode($data, true);
        $url    = isset($params["url"]) ? $params["url"] : "";
        if (empty($url)) {
            return false;
        }
        $time = rand(1, 10);
        sleep($time);
        ReqHelp::get($url);
        return true;
    }

    /**
     * task任务处理完毕
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T10:29:45+0800
     * @param    [type]                   $serv    [description]
     * @param    [type]                   $task_id [description]
     * @param    [type]                   $data    [description]
     * @return   null
     */
    public function onFinish($serv, $task_id, $data)
    {
        echo $task_id . "完成=========\n";
        $this->num++;
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
        /** 定时每500毫秒触发一个读取队列的任务 */
        $this->server->tick(500, array($this, "buildTasks"));
    }

    /**
     * 读取队列触发任务
     *
     * @Author   tianyunzi
     * @DateTime 2016-07-07T10:51:55+0800
     * @return   [type]                   [description]
     */
    public function buildTasks()
    {
        echo "begin build task \t" . $this->num . "\n";
        if ($this->num < 1) {
            return;
        }
        while ($this->num) {
            $this->di->get("beanstalkd")->watch("finance_callback");
            $job = $this->di->get("beanstalkd")->reserve(1);
            if (empty($job)) {
                return;
            }
            $result = "";
            $result = !empty($job['body']) ? $job['body'] : array();
            if (empty($result)) {
                isset($job["id"]) && $this->di->get("beanstalkd")->delete($job['id']);
                continue;
            }
            /** 开启task任务 */
            $this->server->task($result, -1);
            $this->num--;
            isset($job["id"]) && $this->di->get("beanstalkd")->delete($job['id']);
        }
    }
}
