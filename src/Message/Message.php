<?php
namespace DBMQ\Message;

/**
 * 消息定义
 *
 * @author Huangbin <huangbin2018@qq.com>
 */
class Message implements \Serializable
{
    private $message = [];

    /**
     * @param mixed     $message    消息
     * @param string    $source     消息来源
     */
    public function __construct($message = [], $source = '')
    {
        if (is_string($message) && $this->isJson($message)) {
            $this->unserialize($message);
        } else {
            $this->message = [
                'body' => $message,
                '__id' => uniqid(date('YmdHis-', time())),
                '__timestamp' => time(),
                '__source' => $source ? $source : $this->getSelfIp(),
            ];
        }
    }

    public function isJson($string = '')
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * 获取消息
     * @return array
     */
    public function getMessage()
    {
        return isset($this->message['body']) ? $this->message['body'] : null;
    }

    /**
     * 获取消息ID
     *
     * @return string
     */
    public function getID()
    {
        return isset($this->message['__id']) ? $this->message['__id'] : null;
    }

    /**
     * 获取消息来源
     *
     * @return string
     */
    public function getSource()
    {
        return isset($this->message['__source']) ? $this->message['__source'] : null;
    }

    /**
     * 获取消息时间戳
     *
     * @return string
     */
    public function getTimestamp()
    {
        return isset($this->message['__timestamp']) ? $this->message['__timestamp'] : null;
    }

    /**
     * 系列化消息(对象的字符串表示形式)
     *
     * @link  http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        return json_encode($this->message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function unserialize($serialized)
    {
        $this->message = json_decode($serialized, true);
    }

    function getSelfIp()
    {
        if (isset($_SERVER)) {
            if (isset($_SERVER['SERVER_ADDR'])) {
                $server_ip = $_SERVER['SERVER_ADDR'];
            } else {
                $server_ip = $_SERVER['LOCAL_ADDR'] ?? '';
            }
        } else {
            $server_ip = getenv('SERVER_ADDR');
        }
        return $server_ip ?: '127.0.0.1';
    }
}
