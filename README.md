# dbmq
利用 MySQL 实现简单的 DB 消息队列。

## 安装

Package is available on [Packagist](https://packagist.org/packages/huangbin2018/dbmq),
使用 Composer [Composer](http://getcomposer.org).

```shell
composer require huangbin2018/dbmq
```

### 依赖

- PHP 7.0+
- PDO Extension

## 使用
### 消息发布
首先创建tag，消费者key，再绑定两者的关系；
然后就可以发布消息了。
```php
// Mysql 连接配置
$dbConfig = [
    'user' => 'wms',
    'password' => 'GtL4MBARrLsNxRZx',
    'host' => '10.10.30.211',
    'port' => '3306',
    'database' => 'wms',
];
$channel = 'test_channel';
$tag = 'test_tag';
$consumerArr = [
    'consumer_1',
    'consumer_2',
    'consumer_3',
    'consumer_4',
    'consumer_5',
] ;

// 实例化消息生产者
$publisherObj = new Publisher($channel, $dbConfig);

// 定义tag
$publisherObj->declareTag($tag, TagType::TOPIC, '测试tag');

// 定义消费者
foreach ($consumerArr as $consumerKey) {
    // 定义消费者
    $publisherObj->declareConsumer($consumerKey, '', '测试consumerKey');
    // 绑定tag
    $publisherObj->bindTag($consumerKey, $tag);
}

// 发布消息
$key = 'uid_1';
$data = [
    'user_id' => 1,
    'user_name' => 'huangbin',
];
$message = new Message($data);
$body = $message->serialize();
$rs = $publisherObj->send($tag, $key, $body);

```

### 消息消费

```php
use DBMQ\Message\Message;
use DBMQ\Consumer\Consumer;
use DBMQ\Message\Response;

// 数据库连接参数
$dbConfig = [
    'user' => 'wms',
    'password' => 'GtL4MBARrLsNxRZx',
    'host' => '10.10.30.211',
    'port' => '3306',
    'database' => 'wms',
];

$channel = 'test_channel';
$tag = 'test_tag';
$consumerKey = 'consumer_1';

$processSize = $argv[1] ?? 0; // 消费者进程数
$processIndex = $argv[2] ?? 0; // 进程索引
$consumerObj = new Consumer($consumerKey, $dbConfig, '', $processSize, $processIndex);

// 定义消息消费处理函数
$consumerObj->run(function (Message $message) {
    $timestamp = $message->getTimestamp();

    // 消息体
    $body = $message->getMessage();
    print_r($body);

    // 这里是逻辑处理...
    try {
        $ack = true;
        if ($ack == false) {
            $msg = '测试失败啦';
            return Response::isFail($msg);
        } else {
            $msg = '测试成功啦';
            return Response::isSuccess($msg);
        }
    } catch (\Exception $e) {
        // 异常
        return Response::isException($e->getMessage());
    }
});
```
只有有绑定了消费者的 tag 才能发布消息，否则消息不会被保存！！！
