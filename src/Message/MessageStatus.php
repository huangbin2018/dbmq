<?php
namespace DBMQ\Message;

/**
 * 消息状态定义
 *
 * @author Huangbin <huangbin2018@qq.com>
 */
class MessageStatus
{
    /**
     * 等待
     */
    const WAITING = 0;

    /**
     * 失败
     */
    const FAIL = 1;

    /**
     * 重试
     */
    const RETRY = 2;

    /**
     * 成功
     */
    const SUCCESS = 3;
}
