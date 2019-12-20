<?php
namespace DBMQ\Helper;

use Exception;

/**
 * 文件锁
 *
 * @author Huangbin <huangbin2018@qq.com>
 */
class FileLock
{

    /**
     * 文件全路径
     * @var string
     */
    private $file = '';

    /**
     * 文件名
     * @var string
     */
    private $fileName = '';

    /**
     * 超时时间
     * @var int
     */
    private $timeout = 0;

    public function __construct($dir = '', $filename = '', $timeout = 0)
    {
        if (empty($dir)) {
            $dir = __DIR__ . '/storage/';
        }
        if (empty($filename)) {
            $filename = $this->getlockpid() . '_' . time() . '.lock';
        }
        if (!is_dir($dir)) {
            $this->mkdirs($dir);
        }
        $this->file = trim($dir, '/') . '/' . $filename;
        $this->fileName = $filename;
        $this->timeout = $timeout;
    }

    /**
     * 检测是否有文件锁
     * @param bool $force
     * @return bool
     */
    public function isLocked($force = true){
        if (file_exists($this->file)) {
            if ($force && $this->timeout > 0 && time() - filemtime($this->file) > $this->timeout) {
                //如果锁文件存在时间过长删除锁文件
                //@unlink($this->file);
                //return false;
            }

            $lockPid = $this->getlockpid();
            if($lockPid) {
                if($lockPid == getmypid()) {
                    return true;
                }
                try {
                    //检查进程是否还存在，不存在则解锁
                    if (!file_exists("/proc/" . $lockPid)) {
                        $this->unlock();
                        return false;
                    } else {
                        $cmdline = file_get_contents("/proc/" . $lockPid . '/cmdline');
                        if ($cmdline && !preg_match('/.*dbmqConsumer|(\/)?php.*\.php/i', $cmdline)) {
                            //非PHP进程则解锁
                            $this->unlock();
                            return false;
                        }
                    }
                } catch (Exception $e) {

                }
            }
            return true;
        }

        return false;
    }

    /**
     * 加文件锁
     * @return void
     */
    public function lock(){
        if($this->isLocked(false)) {
            if ($this->timeout && time() - filemtime($this->file) > 60*60*3) { //3小时重启一次进程
                $pid = $this->getlockpid();
                $this->unlock();
                $message = "3小时到期重启进程 [{$pid}]";
                $message = date('Y-m-d H:i:s').PHP_EOL.$message.PHP_EOL.PHP_EOL;
                $dir = dirname($this->file);
                file_put_contents($dir .'/lock_restart.log', $message, FILE_APPEND);
                if($pid == getmypid()) {
                    exit(0);
                } else {
                    if (preg_match('/linux/i', PHP_OS) || preg_match('/Unix/i', PHP_OS)) {
                        $cmdline = file_get_contents("/proc/" . $pid . '/cmdline');
                        if ($cmdline && preg_match('/.*dbmqConsumer|(\/)?php.*\.php/i', $cmdline)) {
                            pclose(popen('kill -9 ' . $pid, 'r'));
                        }
                    }
                }
            }
        } else {
            //加锁,创建锁文件
            $myPid = getmypid();
            file_put_contents($this->file, $myPid);
            if (preg_match('/linux/i', PHP_OS) || preg_match('/Unix/i', PHP_OS)) {
                chmod($this->file, 0777);
            }
        }

    }

    /**
     * 解锁,删除锁文件
     * @return void
     */
    public function unlock(){
        @unlink($this->file);
    }

    /**
     * 获取进程PID
     * @return false|string
     */
    public function getlockpid(){
        return file_get_contents($this->file);
    }

    /**
     * 递归创建路径
     * @param string $path
     * @return void
     */
    public function mkdirs($path = '') {
        if ($path && !is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }
}
