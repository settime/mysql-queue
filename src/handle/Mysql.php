<?php

namespace FlyCms\MysqlQueue\handle;

use FlyCms\MysqlQueue\BaseQuery;
use think\facade\Db;

/**
 * 队列数据mysql操作
 */
class Mysql implements BaseQuery
{

    private static $instance =null;

    public static function getInstance(): static
    {
        if (!self::$instance){
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param $uuid string
     * @return array|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 获取队列
     */
    public function get($uuid){
        $find =  Db::name('queue')->where('uuid',$uuid)->find();
        return $find;
    }

    /**
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 获取全部待执行队列
     */
    public function getAll(): array
    {
        return Db::name('queue')->where('status', 0)
            ->select()
            ->toArray();
    }

    /**
     * @param $uuid string
     * @param $result string
     * @param $status int
     * @param $running_time string
     * @return void
     * 添加日志
     */
    public  function addLog($uuid,$result,$status = 0,$running_time = ''){
        Db::name('queue_log')->insert([
            'queue_uuid' => $uuid,
            'status' => $status,
            'running_time' => $running_time,
            'create_time' => time(),
            'result' => $result
        ]);
    }

    /**
     * @param $queue_ string 队列名称
     * @return int|string
     * 添加队列
     */
    public  function insert($queue_item){
        return Db::name('queue')->insertGetId($queue_item);
    }

    public  function insertAll($insert_data = []){
        Db::name('queue')->insertAll($insert_data);
    }

    /**
     * @param $uuid string
     * @param $update_data
     * @return void
     * @throws \think\db\exception\DbException
     * 更新队列
     */
    public  function update($uuid,$update_data = []){
        Db::name('queue')->where('uuid', $uuid)->update($update_data);
    }


}