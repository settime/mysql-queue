# 基于webman加mysql实现的可视化简易版消息队列

## 介绍
基于webman + mysql 实现的简易消息队列.<br>
前面使用reids消息队列部署到服务器上的时候,第一次启动的时候,由于自己配置没配好,导致启动出现异常
然后把配置改好了,再次启动后却发现某一个消息队列无法执行了,调试打印添加啥的乱搞一通都没有任何反应,后面实在找不出问题出在哪了,想着要不换个名称试试看,结果换了名称直接就正常了.<br>
正是基于这个背景下,我觉得它可能不是我想要的消息队列,于是开发了这个简易版的可视化消息队列组件,

#### 如果你想使用该组件的话,请仔细阅读并理解清楚它的特性再决定

1 从程序的逻辑上来说,基本可以保证数据不会丢失<br>
2 服务端无法支持分布式跟多进程，消费端分布式的话必须保证时间与服务端保持一致，不然会导致消息无法推送<br>
3 同一个队列只能说基本能按顺序执行，反过来听就是不保证他能按顺序执行,例如后发先至、推送失败重试等等是没法保证消息按入队顺序执行的<br>
4 不保证只推送一次,重要数据消费里必须做好幂等性.<br>
5 不管你开多少个消费者,同一类型队列,同一次推送只会有一条消息推送执行<br>
6 性能的话,取决于你服务器情况,我在自己这实测的话,2核的轻量服务器,写入1000条左右耗时1.2秒左右,加上同时进行消费,服务器占用率已经达到60%了,所以仅建议小项目用，有高并发的话，请使用专业的消息队列。


## 安装
```shell
composer require fly-cms/mysql-queue
```

## 基础配置
```

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
```

## 配置进程
打开process.php文件
```
return [
    'mysql-queue-server'  => [// 队列服务,除了监听端口,其他不能更改,分布式时,只能有一个服务器运行该进程
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

```

## 创建数据表
创建队列数据表.
```shell
 CREATE TABLE IF NOT EXISTS `cms_queue`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '队列名称',
  `uuid` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL  COMMENT 'uuid',
  `create_queue_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建队列时间',
  `dely` int(11) NOT NULL DEFAULT '0' COMMENT '延时多久',
  `data` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL  COMMENT '传递的数据',
  `start_run_time` int(11) NOT NULL DEFAULT 0 COMMENT '预计开始执行时间',
   `run_num` int(11) NOT NULL DEFAULT 0 COMMENT '已执行次数',
   `status` int(11) NOT NULL DEFAULT 0 COMMENT '	状态,0未执行,1已完成,2已放弃,这里可以是后台直接操作放弃,也可以是太久没消费放弃的	',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `queue`(`queue`) USING BTREE,
  INDEX `uuid`(`uuid`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '消息队列表' ROW_FORMAT = DYNAMIC
```
创建日志数据表
```shell
CREATE TABLE IF NOT EXISTS `cms_queue_log`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue_uuid` varchar(32) COMMENT '队列uuid',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态,0成功,1失败',
  `result` text  COMMENT '日志',
  `running_time` varchar(10) NOT NULL COMMENT '执行耗时',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `queue_uuid`(`queue_uuid`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '消息队列日志表' ROW_FORMAT = DYNAMIC
```

## 使用
创建队列  send()
* @param $queue string 队列名称
* @param $data mixed 发送的数据
* @param $dely int 延迟时间
* @return bool
```shell
   \FlyCms\MysqlQueue\Client::send('queue',[],0)
```
移除队列 removeQueue()
* @param $uuid string 消息队列uuid
* @return bool
```shell
  \FlyCms\MysqlQueue\Client::removeQueue($uuid);
```
立即执行队列 runQueue()
* @param $uuid string 消息队列uuid
* @return bool
```shell
  \FlyCms\MysqlQueue\Client::runQueue($uuid);
```

## 可视化
这个我就不写了,你自己直接调用查询数据库就行

## 常见错误
端口放行,端口放行,端口放行