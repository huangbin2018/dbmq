<?php
namespace DBMQ\Consumer;

use DBMQ\Helper\PdoCommon;
use DBMQ\Helper\FileLock;
use DBMQ\Consumer\Status;
use DBMQ\Consumer\TagType;
use DBMQ\Message\MessageStatus;
use DBMQ\Message\Message;
use DBMQ\Message\Response;

set_time_limit(0);

/**
 * 消费者处理类
 * @author Huangbin <huangbin2018@qq.com>
 */
class Consumer
{
    private $pdoCommon = null;
    private $fileLock;
    private $lastOutSysLoadAverageTime;
    private $logDir;
    private $consumerKey;
    private $consumerData;
    private $consumerTags;
    private $noConsumerTags;
    private $exceptionTags;
    private $closeTags;
    private $onConsumeCallback;
    private $onLoggingCallback;
    private $lastRunCloseMessageTime;

    /**
     * 进程数
     * @var int
     */
    private $processSize;

    /**
     * 进程索引
     * @var int
     */
    private $processIndex;

    /**
     * 错误执行次数
     * @var int
     */
    private $_failTimesLimit = 5;

    /**
     * 异常重复次数
     * @var int
     */
    private $_exceptionTimesLimit = 15;

    /**
     * 延迟执行
     * @var int
     */
    private $_delayExec = 60;

    /**
     * 初始化
     * @param string    $consumerKey            消费者key
     * @param mixed     $pdo                    数据库操作实例
     * @param array     $logConfig              日志配置
     * @param int       $processSize            进程数
     * @param int       $processIndex           进程索引
     * @param int       $failTimesLimit         执行失败次数限制
     * @param int       $exceptionTimesLimit    执行异常次数限制
     * @param int       $delayExec              延迟执行时间
     */
    public function __construct(
        $consumerKey,
        $pdo,
        $logConfig = array('dir' => '/tmp'),
        $processSize = 0,
        $processIndex = 0,
        $failTimesLimit = 5,
        $exceptionTimesLimit = 15,
        $delayExec = 60
    ) {
        register_shutdown_function([$this, 'shutdown']);
        $this->processSize = intval($processSize);
        $this->processIndex = intval($processIndex);
        $this->consumerKey = $consumerKey;
        $this->pdoCommon = new PdoCommon($pdo ?: null);
        $customDir = isset($logConfig['dir']) && $logConfig['dir'] ? $logConfig['dir'] : __DIR__ . '/../storage';
        $this->logDir = $customDir . '/DBMQ/log';
        if ($this->processSize > 0 && $this->processIndex >= 0) {
            $this->fileLock = new FileLock($customDir . '/DBMQ/lock', $consumerKey . '_' . $processSize . '_' . $processIndex, 0);
        } else {
            $this->fileLock = new FileLock($customDir . '/DBMQ/lock', $consumerKey, 0);
        }
        $this->fileLock->mkdirs($this->logDir);

        $this->_failTimesLimit = $failTimesLimit;
        $this->_exceptionTimesLimit = $exceptionTimesLimit;
        $this->_delayExec = $delayExec;
    }

    public function __destruct()
    {
        //$this->updateConsumer($this->consumerData['consumerid'],array('status'=>ConsumerStatus::waitRun));
    }

    public function shutdown()
    {
        $error = error_get_last();
        $fatalErrorTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR);
        if (in_array($error['type'], $fatalErrorTypes)) {
            print_r($error);
            $this->log('消费者退出，异常：' . print_r($error, true), 'mq_' . $this->consumerKey . '_exit');
        }

        $this->updateConsumer($this->consumerData['consumerid'], ['processid' => 0, 'status' => Status::WAITING]);
    }

    /**
     * $onConsumeCallback 消费回调函数
     * $processSize 进程数量（如果只开一个线程，填写0）
     * $processIndex 进程索引
     * @param $onConsumeCallback
     */
    public function run($onConsumeCallback)
    {
        if ($this->fileLock->isLocked()) {
            $errorMsg = $this->consumerKey . ' is running.';
            $this->log($errorMsg, 'consumer_runtime_error');
            echo $errorMsg;
            return;
        }

        $this->consumerData = $this->getConsumer();
        if (empty($this->consumerData)) {
            $errorMsg = '消费者key:' . $this->consumerKey . ',不存在.';
            echo $errorMsg;
            $this->log($errorMsg, 'consumer_runtime_error');
            return;
        } elseif ($this->consumerData['status'] == Status::STOP) {
            $errorMsg = '消费者key:' . $this->consumerKey . ',状态为停止运行.';
            echo $errorMsg;
            $this->log($errorMsg, 'consumer_stop_log');
            return;
        }

        $this->onConsumeCallback = $onConsumeCallback;

        $errorMsg = '消费者key:' . $this->consumerKey . ',状态为启动中.';
        echo $errorMsg;
        $this->log($errorMsg, 'consumer_running_log');

        /*
        if ($this->consumerData['tag_type'] != TagType::ALL) {
            // consumer_key 对应的所有tag
            $this->consumerTags = $this->getConsumerTags();
            // 同个channel非 consumer_key 对应的所有tag
            $noConsumerTags = $this->getNoConsumerTags();
            if (!empty($noConsumerTags)) {
                foreach ($noConsumerTags as $noTag) {
                    if (in_array($noTag, $this->consumerTags)) {
                        $errorMsg = '消费者key:' . $this->consumerKey . '的tag:' . $noTag . '与其他消费者冲突.';
                        echo $errorMsg;
                        $this->log($errorMsg, 'consumer_error_log');
                        return;
                    }
                }
            }
        } else {
            $this->noConsumerTags = $this->getNoConsumerTags();
        }
        */

        // 更新消费者为运行中
        $pid = getmypid();
        $this->updateConsumer($this->consumerData['consumerid'], array('processid' => $pid, 'status' => Status::RUNNING));

        while (true) {
            $this->fileLock->lock();
            if ($this->checkSysLoadAverageOut()) {
                sleep(60);
                $lockPid = $this->fileLock->getlockpid();
                if ($lockPid != getmypid()) {
                    break;
                }

                continue;
            } else {
                $this->consumeMessage();
                usleep(1000 * 100);
                $lockPid = $this->fileLock->getlockpid();
                if ($lockPid != getmypid()) {
                    break;
                }
            }
        }
    }

    /**
     * 记录日志事件
     * @param $onLoggingCallback
     * @return void
     */
    public function onLogging($onLoggingCallback)
    {
        $this->onLoggingCallback = $onLoggingCallback;
    }

    /**
     * 记录日志
     * @param $message
     * @param $messageKey
     * @return void
     */
    private function log($message, $messageKey)
    {
        $message = date('Y-m-d H:i:s') . PHP_EOL . $message . PHP_EOL . PHP_EOL;
        error_log($message, 3, $this->logDir . '/' . date('Y-m-d') . $messageKey . '.log');
        if (empty($this->onLoggingCallback) == false) {
            $onLoggingCallback = $this->onLoggingCallback;
            $onLoggingCallback($message, $messageKey);
        }
    }

    /**
     * 消息消费
     * @return void
     */
    private function consumeMessage()
    {
        // 去数据库获取数据
        while ($rows = $this->getMessage()) {
            foreach ($rows as $data) {
                $consumer = $this->getConsumer();
                if ($consumer['status'] == Status::STOP) {
                    die('消费者正常停止退出.');
                }

                // 消费消息
                try {
                    $onConsumeCallback = $this->onConsumeCallback;
                    $result = $onConsumeCallback(new Message($data['body']));
                    if (!($result instanceof Response)) {
                        $result = Response::isException('dbmq消费响应结果未知.');
                    }
                } catch (\Exception $e) {
                    $result = Response::isException($e->getMessage() . PHP_EOL . $e->getTraceAsString());
                }

                $this->processConsumedMessage($data, $result);
                $lockPid = $this->fileLock->getlockpid();
                if ($lockPid != getmypid()) {
                    break;
                }
            }

            if ($this->checkSysLoadAverageOut()) {
                break;
            }

            usleep(1000 * 10);
        }

        /*
        if (!empty($this->closeTags) && (time() - $this->lastRunCloseMessageTime) > 60) {
            $this->lastRunCloseMessageTime = time();
            $closeTags = $this->closeTags;
            foreach ($closeTags as $closeTag) {
                $this->fileLock->lock();
                $consumer = $this->getConsumer();
                if ($consumer['status'] == Status::STOP) {
                    die('消费者正常停止退出.');
                }

                $data = $this->getCloseMessage($closeTag);
                if (empty($data)) {
                    unset($this->closeTags[$closeTag]);
                } else {
                    // 消费消息
                    try {
                        $onConsumeCallback = $this->onConsumeCallback;
                        $result = $onConsumeCallback($data);
                        if (!($result instanceof Response)) {
                            $result = Response::isException('dbmq消费响应结果未知.');
                        }
                    } catch (\Exception $e) {
                        $result = Response::isException($e->getMessage() . PHP_EOL . $e->getTraceAsString());
                    }

                    $this->processConsumedMessage($data, $result);
                    $lockPid = $this->fileLock->getlockpid();
                    if ($lockPid != getmypid()) {
                        return;
                    }
                }
            }
        }
        */
    }

    /**
     * 检查系统负载
     * @return bool
     */
    private function checkSysLoadAverageOut()
    {
        $cpuCores = $this->getCPUCores();
        $sysLoadAverage = $this->getSysLoadAverage();
        if ($sysLoadAverage > $cpuCores * $this->consumerData['max_sys_load_average']) {
            if ((time() - $this->lastOutSysLoadAverageTime) > 1 * 60) {
                $this->log('系统负载率超出，当前负载率为：' . $sysLoadAverage, 'outSysLoadAverage');
                $this->lastOutSysLoadAverageTime = time();
            }

            return true;
        }

        return false;
    }

    /**
     * 处理消息消费结果
     * @param array $message
     * @param Response $result
     */
    private function processConsumedMessage(array $message, Response $result)
    {
        $tag = $message['tag'];
        switch ($result->messageConsumeStatus) {
            case Response::SUCCESS:
                // 处理成功消息
                $this->exceptionTags[$tag] = 0;
                unset($this->closeTags[$tag]);
                $this->deleteMessage($message['messageid']);
                $this->insertMessageLog($tag, $message['key'], $message['body']);
                break;
            case Response::FAIL;
                // 处理失败消息
                $this->exceptionTags[$tag] = 0;
                unset($this->closeTags[$tag]);

                if ($message['fail_times']+1 >= $this->_failTimesLimit) {
                    $updateRow = array('status' => MessageStatus::FAIL, 'note' => $result->message, 'timestamp' => '');
                } else {
                    $updateRow = array('fail_times' => $message['fail_times'] + 1, 'note' => $result->message);
                }

                $this->updateMessage($message['messageid'], $updateRow, -1 * $this->_delayExec);
                break;
            case Response::EXCEPTION:
                // 处理异常消息
                $this->exceptionTags[$tag]++;

                // 超过十次异常，加入closeTag
                if ($this->exceptionTags[$tag] > 10) {
                    $this->closeTags[$tag] = $tag;
                }

                if ($message['exception_times'] >= $this->_exceptionTimesLimit) {
                    $updateRow = array('status' => MessageStatus::FAIL, 'note' => $result->message);
                } else {
                    $updateRow = array('exception_times' => $message['exception_times'] + 1, 'note' => $result->message);
                }

                $this->updateMessage($message['messageid'], $updateRow, -1 * $this->_delayExec);
                break;
        }
    }

    /**
     * 获取待消费的消息
     * @return array
     */
    private function getMessage()
    {
        $condition = '`status`=0 and channel=? and `consumer_key`=? and `timestamp`<=now()';
        $bindParams = array($this->consumerData['channel'], $this->consumerData['consumer_key']);
        if ($this->processSize > 0 && $this->processIndex >= 0) {
            $bindParams[] = $this->processSize;
            $bindParams[] = $this->processIndex;
            $condition .= " and MOD(messageid,?) = ?";
        }

        $sql = 'select * from `mq_message` where ' . $condition . ' order by `timestamp` limit 10;';
        return $this->pdoCommon->fetchAll($sql, $bindParams);
    }

    /**
     * @param $bindParams
     * @param $data
     * @return string
     * @uses $this->bindParamsForInOperator($bindParams,$this->consumerTags)
     */
    private function bindParamsForInOperator(& $bindParams, $data)
    {
        $params = array();
        foreach ($data as $val) {
            $params[] = '?';
            $bindParams[] = $val;
        }
        return join(',', $params);
    }

    /**
     * @return bool|mixed
     */
    private function getConsumer()
    {
        $sql = 'select * from `mq_consumer` where `consumer_key` = ?;';
        $bindParams = array($this->consumerKey);
        return $this->pdoCommon->fetchRow($sql, $bindParams);
    }

    /**
     * 更新消息
     * @param int   $messageid
     * @param array $row
     * @param int   $timestamp
     */
    private function updateMessage($messageid, $row, $timestamp = 0)
    {
        $col = array();
        $bindParams = array();
        foreach ($row as $k => $v) {
            $col[] = $k . '=?';
            $bindParams[] = $v;
        }
        if ($timestamp != 0) {
            $timestamp = intval($timestamp);
            $col[] = 'timestamp=DATE_SUB(NOW(),INTERVAL ' . $timestamp . ' SECOND)';
        }
        $bindParams[] = $messageid;
        $colstr = implode(',', $col);
        $sql = 'update `mq_message` set ' . $colstr . ' where `messageid`=?';
        $this->pdoCommon->execute($sql, $bindParams);
    }

    /**
     * 更新消费者
     * @param int   $consumerid
     * @param array $row
     * @return void
     */
    private function updateConsumer($consumerid, $row)
    {
        $col = array();
        $bindParams = array();
        foreach ($row as $k => $v) {
            $col[] = $k . '=?';
            $bindParams[] = $v;
        }

        $bindParams[] = $consumerid;
        $colstr = implode(',', $col);
        $sql = 'update mq_consumer set ' . $colstr . ' where consumerid=?';
        $this->pdoCommon->execute($sql, $bindParams);
    }

    /**
     * 删除消息
     * @param int $messageid
     * @return void
     */
    private function deleteMessage($messageid)
    {
        $sql = 'delete from `mq_message` where `messageid`=?;';
        $bindParams = array($messageid);
        $this->pdoCommon->execute($sql, $bindParams);
    }

    /**
     * 插入消息消费日志
     * @param $tag
     * @param $key
     * @param $body
     * @return void
     */
    private function insertMessageLog($tag, $key, $body)
    {
        $sql = 'insert into `mq_message_log` (`channel`,`tag`,`key`,`consumer_key`,`body`) values(?,?,?,?,?);';
        $bindParams = array($this->consumerData['channel'], $tag, $key, $this->consumerData['consumer_key'], $body);
        $this->pdoCommon->execute($sql, $bindParams);
    }

    /**
     * 获取CPU核心数
     * @return int
     */
    private function getCPUCores()
    {
        if (preg_match('/linux/i', PHP_OS) || preg_match('/Unix/i', PHP_OS)) {
            if ($str = @file("/proc/cpuinfo")) {
                $str = implode("", $str);
                @preg_match_all("/model\s+name\s{0,}\:+\s{0,}([\w\s\)\(\@.-]+)([\r\n]+)/s", $str, $model);
                if (false !== is_array($model[1])) {
                    return sizeof($model[1]);
                }
            }
        }

        return 1;
    }

    /**
     * @return int|mixed
     */
    private function getSysLoadAverage()
    {
        if (preg_match('/linux/i', PHP_OS) || preg_match('/Unix/i', PHP_OS)) {
            exec('uptime', $out);
            if (empty($out) == false && count($out) > 0) {
                $arr = explode('load average:', $out[0]);
                $arr = explode(',', end($arr));
                return end($arr);
            }
        }

        return 0;
    }
}
