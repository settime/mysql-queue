<?php

namespace FlyCms\MysqlQueue;


class Client
{
    private static $token;

    private static function getClient()
    {
        $config = config('plugin.fly-cms.mysql-queue.app');
        self::$token = $config['token'];

        $register_arr =  $config['queue_register_address'];

        $client = stream_socket_client('tcp://' . $register_arr['lanip'].":{$register_arr['port']}");
        return $client;
    }

    /**
     * @param $result
     * @return bool
     * 解析响应
     */
    private static function parseResonse($result){
        $res_arr =  json_decode($result, true);
        if (($res_arr['code']??'400') == 0){
            return true;
        }
        return false;
    }

    /**
     * @param $queue string 队列名称
     * @param $data mixed 发送的数据
     * @param $dely int 延迟时间
     * @return bool
     * 创建队列
     */
    public static function send($queue, $data, $dely = 0)
    {

        $client = self::getClient();

        fwrite($client, json_encode([
                'type' => 'queue',
                'queue' => $queue,
                'time' => time(),
                'data' => $data,
                'dely' => $dely,
                'token' => self::$token,
            ]) . "\n"); // text协议末尾有个换行符"\n"
        $result = fgets($client);

        return self::parseResonse($result);
    }

    /**
     * @param $queue_uuid
     * @return bool
     * 移除队列
     */
    public static function removeQueue($queue_uuid)
    {
        $client = self::getClient();
        fwrite($client, json_encode([
                'type' => 'remove_queue',
                'token' => self::$token,
                'queue_uuid' => $queue_uuid,
            ]) . "\n");
        $result = fgets($client);
        return self::parseResonse($result);
    }

    /**
     * @param $queue_uuid
     * @return bool
     * 立即执行队列
     */
    public static function runQueue($queue_uuid)
    {
        $client = self::getClient();
        fwrite($client, json_encode([
                'type' => 'run_queue',
                'token' => self::$token,
                'queue_uuid' => $queue_uuid,
            ]) . "\n");
        $result = fgets($client);
        return self::parseResonse($result);
    }
}
