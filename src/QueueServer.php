<?php

namespace FlyCms\MysqlQueue;

use FlyCms\MysqlQueue\handle\ParseConsumer;
use FlyCms\MysqlQueue\handle\File;
use FlyCms\MysqlQueue\handle\ParseQueue;
use FlyCms\MysqlQueue\handle\Queue;
use Workerman\Connection\TcpConnection;
use Workerman\Lib\Timer;
use Workerman\Worker;

/**
 *
 */
class QueueServer
{

    use Queue;
    use ParseQueue;
    use ParseConsumer;


    private $config = [];

    /**
     * @var BaseQuery;
     */
    private $mysql_handle;


    public function onWorkerStart(Worker $worker)
    {
        $this->config = config('plugin.fly-cms.mysql-queue.app');
        $this->mysql_handle = $this->config['mysql_query']::getInstance();

        $register_arr =  $this->config['queue_register_address'];
        //开启一个端口接收队列的消息
        $inner_text_worker = new \workerman\Worker("Text://0.0.0.0:" . $register_arr['port']);
        //监听队列消息
        $inner_text_worker->onMessage = function ($connection, $json_data) {
            $this->parseQueueMsg($connection,$json_data);
        };
        $inner_text_worker->listen();


        $this->initQueue();
        $this->listenProducer();
        $this->listenQueue();
        $this->clearAckId();
        $this->clearPileQueue();
    }

    /**
     * @return void
     * 初始化队列数据
     */
    private function initQueue()
    {
        $list =  $this->mysql_handle->getAll();
        foreach ($list as $item) {
            $this->writeQueue($item);
        }
    }


    /**
     * @return void
     * 监听队列生产
     */
    private function listenProducer()
    {

        Timer::add(1, function () {
            $list = File::getList();
            if (!$list) {
                return;
            }
            $this->mysql_handle->insertAll($list);
            File::clearList();

            foreach ($list as $item) {
                $this->writeQueue($item);
            }
        });
    }



    /**
     * @return void
     * 监听队列执行
     */
    protected function listenQueue()
    {
        Timer::add(1, function () {
            $now_time = time();
            foreach ($this->queue_list as $queue_item) {
                if ($queue_item['start_run_time'] > $now_time) {
                    continue;
                }
                $this->moveToPileQueue($queue_item['uuid']);
            }
        });

        // 定时器决定了消费速度上限
        Timer::add(0.005, function () {
            $push_queue = [];
            foreach ($this->pile_queue as $queue_item) {
                //加个判断尽量保证推送顺序
                if (!isset($push_queue[$queue_item['queue']])){
                    if ( $this->pushQueue($queue_item) ){
                        $push_queue[$queue_item['queue']] = true;
                    }
                }
            }
        });
    }


    public function onWorkerStop()
    {
    }


    /**
     * @param TcpConnection $connection
     * @param $json_data
     * @return void
     * 这里的消息是跟消费者通讯产生的
     */
    public function onMessage(TcpConnection $connection, $json_data)
    {
        $this->parseConsumerMsg($connection,$json_data);
    }


    public function onClose(TcpConnection $connection)
    {
        foreach ($this->consumer_list as $queue => $connection_list) {
            foreach ($connection_list as $key => $con) {
                if ($con->id == $connection->id) {
                    unset($this->consumer_list[$queue][$key]);
                }
            }
            $this->consumer_list[$queue] = array_values($this->consumer_list[$queue]);
        }
    }

}
