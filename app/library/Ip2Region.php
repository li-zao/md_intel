<?php

namespace app\library;

use Exception;

/**
 * class Ip2Region
 * 为兼容老版本调度而创建
 * @author Anyon<zoujingli@qq.com>
 * @datetime 2022/07/18
 */
class Ip2Region
{
    /**
     * 查询实例对象
     * @var XdbSearcher
     */
    private $searcher;

    /**
     * 初始化构造方法
     * @throws Exception
     */
    public function __construct()
    {
        $dbFile = app_path('data') . '/ip2region.xdb';
        if (!file_exists($dbFile)) {
            throw new Exception('IP2Region 数据文件不存在');
        }
        $this->searcher = XdbSearcher::newWithFileOnly($dbFile);
    }

    /**
     * 兼容原 memorySearch 查询
     * @param string $ip
     * @return array
     * @throws Exception
     */
    public function memorySearch($ip)
    {
        return ['city_id' => 0, 'region' => $this->searcher->search($ip)];
    }

    /**
     * 兼容原 binarySearch 查询
     * @param string $ip
     * @return array
     * @throws Exception
     */
    public function binarySearch($ip)
    {
        return $this->memorySearch($ip);
    }

    /**
     * 兼容原 btreeSearch 查询
     * @param string $ip
     * @return array
     * @throws Exception
     */
    public function btreeSearch($ip)
    {
        return $this->memorySearch($ip);
    }

    /**
     * 直接查询并返回名称
     * @param string $ip
     * @return array
     * @throws \Exception
     */
    public function simple($ip)
    {
        $geo = $this->memorySearch($ip);
        $arr = explode('|', str_replace(['0|'], '|', $geo['region'] ?? ''));
        if (($last = array_pop($arr)) === '内网IP') {
            $last = '';
        }
        return [join('', $arr), (empty($last) ? '' : "$last")];
    }

    /**
     * @param $ip
     * @return string|null
     * @throws Exception
     */
    public function search($ip)
    {
        if (empty($ip)) {
            return null;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }
        return $this->searcher->search($ip);
    }

    /**
     * destruct method
     * resource destroy
     */
    public function __destruct()
    {
        $this->searcher->close();
        unset($this->searcher);
    }
}
