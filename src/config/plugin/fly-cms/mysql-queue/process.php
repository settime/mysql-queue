<?php

return [
    'mysql-queue-server'  => [//  队列服务,除了监听端口,其他不能更改,分布式时,只能有一个服务器运行该进程
        'handler'     => \FlyCms\MysqlQueue\QueueServer::class,
        'count'       => 1,// 必须进程1
        'listen' => 'text://0.0.0.0:8774',
    ],
    'mysql-queue-consumer-1'  => [//消费者1
        'handler'     => \FlyCms\MysqlQueue\ConsumerServer::class,
        'count'       => 1,
        'constructor' => [
            // 消费者类目录
            'consumer_dir' => app_path() . '/queue/consumer1'
        ]
    ],
    'mysql-queue-consumer-2'  => [//消费者2
        'handler'     => \FlyCms\MysqlQueue\ConsumerServer::class,
        'count'       => 1,
        'constructor' => [
            // 消费者类目录
            'consumer_dir' => app_path() . '/queue/consumer2'
        ]
    ]
];
