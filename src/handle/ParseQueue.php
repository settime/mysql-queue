<?php

namespace FlyCms\MysqlQueue\handle;

use think\facade\Db;

/**
 * 解析队列的消息
 */
trait ParseQueue
{

    /**
     * @param $connection
     * @param $json_data
     * @return void
     * @throws \think\db\exception\DbException
     * 解析并处理队列消息
     */
    public function parseQueueMsg($connection, $json_data)
    {
        $data_arr = json_decode($json_data, true);
        if (($data_arr['token'] ?? '') != $this->config['token']) {
            $connection->send(json_encode(['code' => 400, 'msg' => 'Missing token']));
            $connection->close();
            return;
        }

        $type = $data_arr['type'] ?? '';
        switch ($type) {
            case 'queue': //生产队列
                $queue = $data_arr['queue'] ?? '';
                $create_queue_time = $data_arr['time'] ?? '';
                $dely = $data_arr['dely'] ?? 0;
                $data = $data_arr['data'] ?? '';
                //直接插数据库性能太慢,写入文件,定时读取批量写入数据库
                File::writeList([
                    'queue' => $queue,
                    'uuid' =>  $this->createQueueUuid(),
                    'create_queue_time' => $create_queue_time,
                    'dely' => $dely,
                    'run_num' => 0,
                    'data' => json_encode($data),
                    'start_run_time' => $create_queue_time + $dely,
                    'create_time' => time(),
                ]);
                break;
            case 'remove_queue'://移除队列
                $queue_uuid = $data_arr['queue_uuid'] ?? '';

                $this->mysql_handle->update($queue_uuid,[
                   'status' => 2,
                ]);
                $this->mysql_handle->addLog($queue_uuid, 'api调用放弃队列');
                $this->removeQueue($queue_uuid);

                break;
            case 'run_queue'://执行队列
                $uuid = $data_arr['queue_uuid'] ?? '';
                $find = $this->mysql_handle->get($uuid);
                if ($find) {
                    $this->removeQueue($uuid);
                    $this->writeQueue([
                        'queue' => $find['queue'],
                        'uuid' => $find['uuid'],
                        'create_queue_time' => $find['create_queue_time'],
                        'dely' => 0,
                        'run_num' => $this->config['max_attempts'] - 1,
                        'data' => $find['data'],
                        'start_run_time' => time(),
                        'create_time' => $find['create_time'],
                    ]);

                    $this->mysql_handle->addLog($uuid, 'api调用立即执行');
                }
                break;
        }
        $connection->send(json_encode([
            'code' => 0, 'msg' => 'ok'
        ]));
        $connection->close();
    }

}