<?php

namespace FlyCms\MysqlQueue\handle;

use Workerman\Lib\Timer;

trait Queue
{
    /**
     * @var array
     * 消息队列数据
     */
    private $queue_list = [];

    /**
     * @var array
     * 堆积队列,这里的队列都是到了执行时间的
     */
    private $pile_queue = [];

    /**
     * @var array
     * 回收站队列,下发消费者后放这里临时存储，根据结果判断是重新执行还是删除
     */
    private $recycle_queue = [];


    /**
     * @param $queue_id
     * @return string
     * 单进程的,不需要考虑重复问题
     */
    public function createQueueUuid()
    {
        return  md5(uniqid('queue_'.rand(),true));
    }

    /**
     * @param $id
     * @param $queue_item
     * @return void
     * 写入队列
     */
    protected function writeQueue( $queue_item)
    {
        $uuid = $queue_item['uuid'];
        //立即调用的话放到堆积队列里去
        if ($queue_item['dely'] == 0) {
            $this->pile_queue[$uuid] = $queue_item;
        } else {
            $this->queue_list[$uuid] = $queue_item;
        }
    }

    /**
     * @param $uuid string
     * @param bool $is_check 是否检查最大次数
     * @return void
     * 重试队列
     */
    protected function retryQueue($uuid, $is_check = true)
    {

        if (!isset($this->recycle_queue[$uuid])) {
            return;
        }
        $queue_item = $this->recycle_queue[$uuid];

        $max_attempts = $this->config['max_attempts'];
        $retry_seconds = $this->config['retry_seconds'];
        $queue_item['run_num'] += 1;
        $queue_item['start_run_time'] = time() + $retry_seconds;

        if ($is_check) {
            if ($queue_item['run_num'] > $max_attempts) {
                return;
            }
        }
        $this->removeQueue($uuid);
        $this->writeQueue($queue_item);
    }

    /**
     * @param $uuid
     * @return void
     * 移入回收站
     */
    protected function moveToRecycleBin($uuid)
    {
        if (isset($this->recycle_queue[$uuid])) {
            return;
        }

        $queue_item = [];
        if (isset($this->queue_list[$uuid])) {
            $queue_item = $this->queue_list[$uuid];
            unset($this->queue_list[$uuid]);
        }
        if (isset($this->pile_queue[$uuid])) {
            $queue_item = $this->pile_queue[$uuid];
            unset($this->pile_queue[$uuid]);
        }
        if ($queue_item) {
            $this->recycle_queue[$uuid] = $queue_item;
        }
    }

    /**
     * @param $queue_item
     * @return void
     * 移入堆积队列
     */
    protected function moveToPileQueue($uuid)
    {

        $queue_item = [];
        if (isset($this->pile_queue[$uuid])) {
            return;
        }
        if (isset($this->queue_list[$uuid])) {
            $queue_item = $this->queue_list[$uuid];
            unset($this->queue_list[$uuid]);
        }
        if (isset($this->recycle_queue[$uuid])) {
            $queue_item = $this->recycle_queue[$uuid];
            unset($this->recycle_queue[$uuid]);
        }
        if ($queue_item) {
            $this->pile_queue[$uuid] = $queue_item;
        }

    }

    /**
     * @param $uuid
     * @return void
     * 移除队列
     */
    protected function removeQueue($uuid)
    {

        if (isset($this->queue_list[$uuid])) {
            unset($this->queue_list[$uuid]);
        }
        if (isset($this->pile_queue[$uuid])) {
            unset($this->pile_queue[$uuid]);
        }
        if (isset($this->recycle_queue[$uuid])) {
            unset($this->recycle_queue[$uuid]);
        }
    }



    /**
     * @return void
     * 定时清除堆积的队列
     */
    private function clearPileQueue()
    {
        $timeout = $this->config['timeout'] ?? 3600;

        Timer::add(60, function () use ($timeout) {
            $now_time = time();
            foreach ($this->pile_queue as $queue => $queue_item) {
                if ($now_time - $queue_item['start_run_time'] > $timeout) {

                    $this->mysql_handle->addLog($queue_item['uuid'], '超时未消费放弃');
                    $this->mysql_handle->update($queue_item['uuid'], [
                        'status' => 2,
                    ]);
                    $this->removeQueue($queue_item['uuid']);
                }
            }
        });
    }

}