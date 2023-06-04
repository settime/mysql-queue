<?php

namespace FlyCms\MysqlQueue;

use think\facade\Db;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Lib\Timer;
use Workerman\Worker;

/**
 * 消费服务
 */
class ConsumerServer
{
    /**
     * @var array
     * 配置信息
     */
    private $config = [];

    /**
     * @var AsyncTcpConnection
     */
    private $connection = '';

    /**
     * @var int
     * 心跳定时器id
     */
    private $time_id;

    /**
     * @var array
     * 消费者操作
     */
    private $handle = [];

    /**
     * @var BaseQuery
     */
    private $mysql_handle;

    /**
     * @var string
     * 队列任务目录
     */
    private $consumer_dir;


    public function __construct($consumer_dir = '')
    {
        $this->consumer_dir = $consumer_dir;
    }


    public function onWorkerStart(Worker $worker){
        $this->config = config('plugin.fly-cms.mysql-queue.app');

        $this->mysql_handle = $this->config['mysql_query']::getInstance();

        //延时一下再初始化,避免服务未启动
        Timer::add(0.2,function (){
            $this->tryInit();
        },[],false);
    }

    /**
     * @param $queue string 队列名称
     * @return void
     * 订阅队列
     */
    public function subscribe($queue){
        $this->connection->send(json_encode([
            'token' => $this->config['token'],
            'type' => 'subscribe',
            'queue' => $queue,
        ]));
    }

    /**
     * @return void
     * 上报消息已接收
     */
    public function report($ack_id){
        $this->connection->send(json_encode([
            'token' => $this->config['token'],
            'type' => 'report',
            'ack_id' => $ack_id,
        ]));
    }

    /**
     * @param $queue_data
     * @return void
     * 上报重试
     */
    public function retry($queue_data){
        $this->connection->send(json_encode([
            'token' => $this->config['token'],
            'type' => 'retry',
            'uuid' => $queue_data['uuid'],
        ]));
    }

    /**
     * @param $queue_data
     * @return void
     * 上报已执行成功
     */
    public function success($queue_data){
        $this->connection->send(json_encode([
            'token' => $this->config['token'],
            'type' => 'success',
            'uuid' => $queue_data['uuid'],
        ]));
    }

    /**
     * @return void
     * @throws \Exception
     * 解析消费者
     */
    private function parseConsumer(){

        $path = $this->consumer_dir;
        if (!$path){
            throw new \Exception("Consumer directory {$path} not exists\r\n");
        }
        if (!is_dir($path)) {
            throw new \Exception("Consumer directory {$path} not exists\r\n");
        }

        foreach (glob($path."/*.php") as $filename) {
            $class = str_replace('/', "\\", substr(substr($filename, strlen(base_path())), 0, -4));
            $consumer = new $class();
            $queue = $consumer->queue ?? '';
            $this->handle[$queue] = [$consumer, 'consume'];

            $this->subscribe($queue);
        }
    }

    /**
     * @return void
     * @throws \Exception
     * 初始化
     */
    private function tryInit(){

        //读取监听地址
        $listen_address = config('plugin.fly-cms.mysql-queue.app.consumer_register_address');

        $this->connection = new AsyncTcpConnection($listen_address);
        //server_address
        $this->connection->onConnect = function (){

            $this->time_id = Timer::add(20,function (){
                $this->connection->send(json_encode([
                    'token' => $this->config['token'],
                    'type' => 'ping',
                ]));
            });
            $this->parseConsumer();
        };
        $this->connection->onClose = function () {
            Timer::del($this->time_id);
            //重新初始化
            $this->tryInit();;
        };
        $this->connection->onMessage = function ($connection,$message){
            $this->parseMsg($message);
        };
        $this->connection->connect();
    }

    /**
     * @return void
     * 解析消息
     */
    private function parseMsg($json_data){
        $data_arr = json_decode($json_data,true);
        if (!$data_arr){
            return;
        }

        $ack_id = $data_arr['ack_id'] ?? '';
        $push_time = $data_arr['push_time'] ?? '';
        $queue_data = $data_arr['queue_data'] ?? '';
        $now_time = time();
//        dump('消费者开始消费:'.$queue_data['id']);

        //消息接收时间过长,舍弃
        if ($now_time - $push_time > 2){
            return;
        }
        $this->report($ack_id);

        $this->runConsumer($queue_data);
    }

    /**
     * @param $queue_data
     * @return void
     * @throws \think\db\exception\DbException
     * 执行消费
     */
    private function runConsumer($queue_data){
        $queue = $queue_data['queue'];
        $param_json = $queue_data['data'];
        $param_arr =(array) json_decode($param_json,true);
        $uuid = $queue_data['uuid'] ?? '';

        if (!isset($this->handle[$queue])){
            return;
        }
        $start_time = microtime(true);
        $status = 0;
        try{
           $result = call_user_func($this->handle[$queue],$param_arr);
            if (json_encode($result)){
                $result = json_encode($result);
            }

            $this->mysql_handle->update($uuid,[
                'status' => 1,
                'run_num' => Db::raw(" run_num + 1"),
            ]);

            $this->success($queue_data);

        }catch (\Throwable $e){

            $this->mysql_handle->update($uuid,[
                'run_num' => Db::raw(" run_num + 1"),
            ]);

            $this->retry($queue_data);
            $status = 1;
            $result = json_encode([
               'file' => $e->getFile(),
               'line' => $e->getLine(),
               'code' => $e->getCode(),
               'msg' => $e->getMessage(),
               'trace' => $e->getTraceAsString(),
            ]);
        }
        $end_time = microtime(true);
        $running_time = round($end_time - $start_time, 6);

        $this->mysql_handle->addLog($queue_data['uuid'],$result,$status,$running_time);
    }

}
