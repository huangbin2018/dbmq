<?php
include 'vendor/autoload.php';

use DBMQ\Message\Message;
use DBMQ\Consumer\Consumer;
use DBMQ\Message\Response;

// 数据库连接参数
$dbConfig = [
    'user' => 'root',
    'password' => 'root',
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => 'dbmq_test',
];

$channel = 'test_channel';
$tag = 'test_tag';
$consumerKey = 'consumer_1';

$processSize = $argv[1] ?? 0;
$processIndex = $argv[2] ?? 0;
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

