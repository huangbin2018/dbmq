<?php
namespace DBMQ\Consumer;

/**
 * Tag 类型定义
 * @author Huangbin <huangbin2018@qq.com>
 */
class TagType{
    /**
     * 广播
     */
    const FANOUT  = 0;

    /**
     * 订阅
     */
    const TOPIC = 1;

    /**
     * 直连
     */
    const DIRECT = 2;
}
