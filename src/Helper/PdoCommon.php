<?php
namespace DBMQ\Helper;

use PDO;

/**
 * 数据库连接
 *
 * @author Huangbin <huangbin2018@qq.com>
 */
class PdoCommon
{
    /**
     * @var PDO
     */
    private static $db = null;

    /**
     * 数据库连接默认配置
     * @var array
     */
    private $config = [
        'driver' => 'mysql',
        'user' => 'root',
        'password' => '',
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => '',
        'charset' => 'UTF8',
        'options' => [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'set names utf8'
        ],
        'attributes' => [
            'ATTR_ERRMODE' => 'ERRMODE_EXCEPTION'
        ]
    ];

    /**
     * @param array $config
     */
    public function __construct($config = null)
    {
        if (empty($config)) {
            $ini = __DIR__ . "/../database.ini";
            $parse = parse_ini_file($ini, true);
            $config = [
                'driver' => $parse['driver'] ?: $this->config['driver'],
                'user' => $parse['user'] ?: $this->config['user'],
                'password' => $parse['password'] ?: $this->config['password'],
                'charset' => $parse['charset'] ?: $this->config['charset'],
                'host' => $parse["dsn"]['host'] ?: $this->config['host'],
                'port' => $parse["dsn"]['port'] ?: $this->config['port'],
                'database' => $parse["dsn"]['database'] ?: $this->config['database'],
                'options' => $parse["options"] ?: $this->config['options'],
                'attributes' => $parse["attributes"] ?: $this->config['attributes'],
            ];
            $this->config = $config;
        } elseif (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }

        $driver = $this->config['driver'];
        $user = $this->config['user'];
        $host = $this->config['host'];
        $port = $this->config['port'];
        $database = $this->config['database'];
        $password = $this->config['password'];
        $options = $this->config['options'];
        $attributes = $this->config["attributes"];
        $dsn = "{$driver}:dbname={$database};host={$host};port={$port};charset=UTF8";
        self::$db = new \PDO($dsn, $user, $password, $options);
        foreach ($attributes as $k => $v) {
            self::$db->setAttribute(constant("PDO::{$k}"), constant("PDO::{$v}"));
        }
        return self::$db;
    }

    /**
     * 获取连接实例
     * @param array $config
     * @return PdoCommon|PDO
     */
    public static function getInstance($config = [])
    {
        if (is_null(self::$db)) {
            self::$db = new self($config);
        }
        return self::$db;
    }

    /**
     * 执行SQL
     * @param $sql
     * @param $params
     * @return mixed
     */
    public function execute($sql, $params)
    {
        $stmt = self::getInstance()->prepare($sql);
        $bool = $stmt->execute($params);
        $count = $stmt->rowCount();
        $stmt->closeCursor();
        return $bool === false ? false : $count;
    }

    /**
     * 查询
     * @param $sql
     * @param array $params
     * @return array
     */
    public function fetchAll($sql, $params = array())
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        $data = array();
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $data[] = $row;
        }
        return $data;
    }

    /**
     * 查询单行
     * @param $sql
     * @param array $params
     * @return bool|mixed
     */
    public function fetchRow($sql, $params = array())
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return false;
        }
        return $row;
    }

    /**
     * 查询单个字段
     * @param $sql
     * @param array $params
     * @return bool|mixed
     */
    public function fetchOne($sql, $params = array())
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_NUM);
        if (!is_array($row)) {
            return false;
        }
        return $row[0];
    }
}
