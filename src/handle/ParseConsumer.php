<?php

namespace FlyCms\MysqlQueue\handle;

use Workerman\Connection\TcpConnection;
use Workerman\Lib\Timer;

/**
 * 解析消费者消息
 */
trait ParseConsumer
{

    /**
     * @var array
     * 消费者链接对象
     */
    protected $consumer_list = [
        'queue' => [],
    ];

    /**
     * @var array
     * 通讯id
     */
    private $ack_arr = [];

    /**
     * @param $queue
     * @return false|TcpConnection
     * 获取消费者
     */
    public function getConsumer($queue)
    {
        if (!isset($this->consumer_list[$queue])) {
            return false;
        }
        foreach ($this->consumer_list[$queue] as $connection) {
            if (!$connection->is_busy) {
                $connection->is_busy = true;
                return $connection;
            }
        }
        return false;
    }



    /**
     * @param $queue
     * @param $queue_data
     * @return bool
     * 推送队列
     */
    public function pushQueue($queue_data){

        $queue = $queue_data['queue'];
        $uuid = $queue_data['uuid'];

        //没有消费者的话,堆积起来
        $consumer = $this->getConsumer($queue);
        if (!$consumer) {
            $this->moveToPileQueue($uuid);
            return false;
        }

        $this->moveToRecycleBin($uuid);
        //生成一个唯一请求id
        $ack_id = md5(uniqid('ack', true) . md5(json_encode($queue_data)));

        //超时检测,如果指定时间没接收到消费者回复,那么视为推送异常,重新推送
        Timer::add(3, function () use ($uuid, $ack_id) {
            if (!isset($this->ack_arr[$ack_id])) {
                $this->moveToPileQueue($uuid);
                return;
            }
            unset($this->ack_arr[$ack_id]);
        }, [], false);

        $consumer->send(json_encode(
            [
                'ack_id' => $ack_id,
                'push_time' => time(),//推送时间,消费端做判断,超出N秒视为推送超时,作为失败处理
                'queue_data' => $queue_data,
            ]
        ));
        return true;
    }


    /**
     * @return void
     * 定时清除ack,避免无限增长
     */
    private function clearAckId()
    {
        Timer::add(60, function () {
            $now_time = time();
            foreach ($this->ack_arr as $ack_id => $time) {
                if ($now_time - $time > 60) {
                    unset($this->ack_arr[$ack_id]);
                }
            }
        });
    }


    /**
     * @param TcpConnection $connection
     * @param $json_data
     * @return void
     * 解析消费者消息
     */
    public function parseConsumerMsg(TcpConnection $connection, $json_data){

        $data_arr = json_decode($json_data, true);
        if (($data_arr['token'] ?? '') != $this->config['token']) {
            $connection->close();
            return;
        }
        $connection->is_busy = false;

        // 消息类型
        $type = $data_arr['type'] ?? '';
        switch ($type) {
            case 'report':// 上报已收
                $ack_id = $data_arr['ack_id'] ?? '';
                $this->ack_arr[$ack_id] = time();
                break;
            case 'subscribe': //订阅队列
                $queue = $data_arr['queue'];
                if (!isset($this->consumer_list[$queue])) {
                    $this->consumer_list[$queue] = [];
                }
                $this->consumer_list[$queue][] = $connection;
                break;
            case 'retry': //消费失败,重试
                $uuid = $data_arr['uuid'] ?? '';
                $this->retryQueue($uuid);
                $connection->is_busy = false;
                break;
            case 'success':
                $uuid = $data_arr['uuid'] ?? '';
                $this->removeQueue($uuid);
                $connection->is_busy = false;
                break;
            case 'ping':
                break;
        }
    }

}