<?php

return [
    'enable' => true,
    'token' => '123456', //通讯认证钥匙,请务必更改
    'queue_register_address' =>    [
        'lanip' => '127.0.0.1',
        'port' => '8773',
    ],//队列注册地址,自定义
    'consumer_register_address' => 'text://127.0.0.1:8774',//消费者注册地址,必须对应process文件 mysql-queue-server里面的监听地址
    'timeout' => 3600,//超时多久放弃
    'max_attempts'  => 6, // 消费失败后，重试次数
    'retry_seconds' => 5, // 重试间隔，单位秒
    'mysql_query' => \FlyCms\MysqlQueue\handle\Mysql::class,//mysql操作类
];
