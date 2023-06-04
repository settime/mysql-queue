<?php

namespace FlyCms\MysqlQueue\handle;

/**
 * 队列数据临时存储操作
 */
class File
{

    private static function getFileAdd(){
        return __DIR__.'/../temp/queue.json';
    }


    /**
     * @param $list array 二维数组
     * @return false|int
     * 写入队列
     */
    public static function writeList($queue_item){
        $data = self::getList();
        $data[] = $queue_item;
       return file_put_contents(self::getFileAdd(),json_encode($data));
    }

    /**
     * @return array
     * 获取队列
     */
    public static function getList(){

        $json = file_get_contents(self::getFileAdd());

        $data = (array) json_decode($json,true);
        return $data;
    }

    /**
     * @return void
     * 清除队列
     */
    public static function clearList(){
        file_put_contents(self::getFileAdd(),json_encode([]));
    }

}