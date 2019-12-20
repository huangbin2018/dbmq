<?php

namespace DBMQ\Publisher;

use DBMQ\Consumer\TagType;
use DBMQ\Helper\PdoCommon;
use Exception;

/**
 * 消息生产者
 * @author Huangbin <huangbin2018@qq.com>
 */
class Publisher
{
    private $pdoCommon;

    private $channel;

    public function __construct($channel, $pdo = null)
    {
        $this->pdoCommon = new PdoCommon($pdo);
        $this->channel = $channel;
    }

    /**
     * 定义tag
     * @param string $tag tag名称
     * @param string $tagType tag 类型，0-广播，1-订阅，2-直连
     * @param string $note
     * @return bool|mixed
     */
    public function declareTag($tag = '', $tagType = '', $note = '')
    {
        $pdoCommon = $this->pdoCommon;
        $condition = 'channel=? and tag=?';
        $bindParams = array($this->channel, $tag);
        $row = $pdoCommon->fetchRow('select t_id from `mq_tags` where ' . $condition . ' limit 1;', $bindParams);
        if ($row) {
            return true;
        }

        $sql = 'insert into `mq_tags` (`channel`,`tag`,`tag_type`,`note`,`create_date`) values(?,?,?,?,?);';
        $bindParams = array($this->channel, $tag, $tagType, $note, date('Y-m-d H:i:s'));
        return $pdoCommon->execute($sql, $bindParams);
    }

    /**
     * 定义消费者
     * @param string $consumerKey 名称
     * @param string $channel 渠道
     * @param string $note
     * @param float  $maxSysLoad
     * @return bool|mixed
     */
    public function declareConsumer($consumerKey = '', $channel = '', $note = '', $maxSysLoad = 1.0)
    {
        $pdoCommon = $this->pdoCommon;
        $channel = $channel ?: $this->channel;
        $condition = 'channel=? and consumer_key=?';
        $bindParams = array($channel, $consumerKey);
        $row = $pdoCommon->fetchRow('select `consumerid` from `mq_consumer` where ' . $condition . ' limit 1;', $bindParams);
        if ($row) {
            return true;
        }
        $sql = 'insert into `mq_consumer` (`channel`,`consumer_key`,`note`,`create_date`,`max_sys_load_average`) values(?,?,?,?,?);';
        $bindParams = array($channel, $consumerKey, $note, date('Y-m-d H:i:s'), $maxSysLoad);
        return $pdoCommon->execute($sql, $bindParams);
    }

    /**
     * 绑定tag
     * @param string $consumerKey 消费者key
     * @param string $tag tag名称
     * @param string $channel 渠道
     * @return bool|mixed
     * @throws \Exception
     */
    public function bindTag($consumerKey = '', $tag = '', $channel = '')
    {
        $channel = $channel ?: $this->channel;
        $pdoCommon = $this->pdoCommon;
        $condition = 'channel=? and tag=?';
        $bindParams = array($channel, $tag);
        $tagRow = $pdoCommon->fetchRow('select * from `mq_tags` where ' . $condition . ' limit 1;', $bindParams);
        if (!$tagRow) {
            throw new \Exception('tag 未定义');
        }

        $condition = 'channel=? and consumer_key=?';
        $bindParams = array($channel, $consumerKey);
        $consumerRow = $pdoCommon->fetchRow('select * from `mq_consumer` where ' . $condition . ' limit 1;', $bindParams);
        if (!$consumerRow) {
            throw new \Exception('consumer 未定义');
        }

        $condition = 'channel=? and consumer_key=? and tag=?';
        $bindParams = array($channel, $consumerKey, $tag);
        $consumerTagRow = $pdoCommon->fetchRow('select * from `mq_consumer_tag` where ' . $condition . ' limit 1;', $bindParams);
        if ($consumerTagRow) {
            return true;
        }

        $sql = 'insert into `mq_consumer_tag` (`consumer_key`,`channel`,`tag`,`create_date`) values(?,?,?,?);';
        $bindParams = array($consumerKey, $channel, $tag, date('Y-m-d H:i:s'));
        return $pdoCommon->execute($sql, $bindParams);
    }

    /**
     * 添加消息
     * @param string $tag 类型
     * @param string $key 参考号
     * @param string $body 消息体
     * @return mixed
     * @throws Exception
     */
    public function send($tag, $key, $body = '')
    {
        if (empty($tag)) {
            throw new Exception('发布消息异常，tag不能为空.');
        }
        if (empty($key)) {
            throw new Exception('发布消息异常，key不能为空.');
        }
        if (empty($body)) {
            $body = '';
        }

        return $this->insertMessage($tag, $key, $body);
    }

    private function insertMessage($tag, $key, $body)
    {
        $condition = 'channel=? and tag=?';
        $bindParams = array($this->channel, $tag);
        $tagRow = $this->pdoCommon->fetchRow('select * from `mq_tags` where ' . $condition . ' limit 1;', $bindParams);
        if (!$tagRow) {
            return false;
        }

        $consumerRows = [];
        switch ($tagRow['tag_type']) {
            case TagType::FANOUT:
                $consumerRows = $this->pdoCommon->fetchAll('select consumer_key from mq_consumer_tag where `channel`=? and tag=?', [$this->channel, $tag]);
                break;
            case TagType::TOPIC:
                $consumerRows = $this->pdoCommon->fetchAll('select consumer_key from mq_consumer_tag where `channel`=? and tag=?', [$this->channel, $tag]);
                break;
            case TagType::DIRECT:
                $consumerRows = $this->pdoCommon->fetchAll('select consumer_key from mq_consumer_tag where `channel`=? and tag=?', [$this->channel, $tag]);
                break;
            default:
                break;
        }
        if (empty($consumerRows)) {
            return false;
        }

        $params = [];
        foreach ($consumerRows as $c) {
            $params[] = [
                  'tag' => $tag,
                  'key' => $key,
                  'consumer_key' => $c['consumer_key'],
                  'body' => $body,
            ];
        }
        return $this->batchInsertMessage($params);
    }

    private function batchInsertMessage($params)
    {
        $sql = 'insert into `mq_message` (`channel`,`tag`,`key`,`consumer_key`,`body`,`create_date`) values ';
        $val = '(?,?,?,?,?,now())';
        $values = array();
        $bindParams = array();
        foreach ($params as $item) {
            $values[] = $val;

            if (empty($item['tag'])) {
                throw new Exception('发布消息异常，tag不能为空.');
            }
            if (empty($item['key'])) {
                throw new Exception('发布消息异常，key不能为空.');
            }
            if (empty($item['consumer_key'])) {
                throw new Exception('发布消息异常，consumer_key不能为空.');
            }

            $bindParams[] = $this->channel;
            $bindParams[] = $item['tag'];
            $bindParams[] = $item['key'];
            $bindParams[] = $item['consumer_key'];
            $bindParams[] = empty($item['body']) ? '' : $item['body'];
        }

        $sql .= implode(",", $values);
        $sql .= ";";
        return $this->pdoCommon->execute($sql, $bindParams);
    }
}
