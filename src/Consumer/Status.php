<?php
namespace DBMQ\Consumer;

/**
 * 消费者状态
 * @author Huangbin <huangbin2018@qq.com>
 */
class Status
{
    /**
     * 等待运行
     */
    const WAITING = 0;

    /**
     * 运行中
     */
    const RUNNING = 1;

    /**
     * 停止
     */
    const STOP = 2;
}
