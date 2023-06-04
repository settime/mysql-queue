<?php

namespace FlyCms\MysqlQueue;

/**
 * 队列查询接口
 */
interface BaseQuery
{

    public static function getInstance():static;

    /**
     * @param $uuid
     * @return array|null
     * 获取单条队列数据
     */
    public function get($uuid);

    /**
     * @return array
     * 获取全部未处理队列数据
     */
    public function getAll():array;


    /**
     * @param $uuid string
     * @param $result string
     * @param $status
     * @param $running_time
     * @return void
     * 添加日志
     */
    public function addLog($uuid,$result,$status = 0,$running_time = '');

    /**
     * @param $queue_item array
     * @return mixed
     * 插入一条队列数据
     */
    public function insert($queue_item);

    /**
     * @param $insert_data array
     * @return mixed
     * 批量插入队列数据
     */
    public function insertAll($insert_data);

    /**
     * @param $uuid
     * @param $update_data
     * @return mixed
     * 更新队列数据
     */
    public function update($uuid,$update_data);


}