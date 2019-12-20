<?php
namespace DBMQ\Message;

/**
 * 消费响应
 *
 * @author Huangbin <huangbin2018@qq.com>
 */
class Response
{
    const FAIL = 0;
    const SUCCESS = 1;
    const EXCEPTION = 500;

    /**
     * 状态
     * @var int
     */
    public $messageConsumeStatus;

    /**
     * 消息
     * @var string
     */
    public $message;

    /**
     * 消费成功
     * @param string $message
     * @return Response
     */
    public static function isSuccess($message = '')
    {
        $mcr = new self();
        $mcr->messageConsumeStatus = self::SUCCESS;
        $mcr->message = $message;
        return $mcr;
    }

    /**
     * 消费失败（指第三方接口返回的失败）
     * @param string $message
     * @return Response
     */
    public static function isFail($message = '')
    {
        $mcr = new self();
        $mcr->messageConsumeStatus = self::FAIL;
        $mcr->message = $message;
        return $mcr;
    }

    /**
     * 消费异常（指第三方接口返回的异常）
     * @param string $message
     * @return Response
     */
    public static function isException($message)
    {
        $mcr = new self();
        $mcr->messageConsumeStatus = self::EXCEPTION;
        $mcr->message = $message;
        return $mcr;
    }
}
