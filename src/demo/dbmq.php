<?php
require_once 'vendor/autoload.php';

use DBMQ\Publisher\Publisher;
use DBMQ\Message\Message;
use DBMQ\Consumer\TagType;

$dbConfig = [
    'user' => 'root',
    'password' => 'root',
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => 'dbmq_test',
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
    $publisherObj->declareConsumer($consumerKey, '', '测试consumerKey');
    $publisherObj->bindTag($consumerKey, $tag);
}

$key = 'uid_1';
$data = [
    'user_id' => 1,
    'user_name' => 'huangbin',
];
$message = new Message($data);
$body = $message->serialize();
$rs = $publisherObj->send($tag, $key, $body);
var_dump($rs);

