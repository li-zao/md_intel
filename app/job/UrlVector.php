<?php

declare(strict_types=1);

namespace app\job;

use app\model\CommonUtil;
use app\util\UrlNN;
use Exception;
use think\facade\Log;
use think\queue\Job;

class UrlVector
{

    /**
     * @var \app\model\UrlVector
     */
    private $vectorModel;

    public function __construct()
    {
        $this->vectorModel = new \app\model\UrlVector();
    }

    /**
     * @param Job $job
     * @param $data
     * @return void
     */
    public function fire(Job $job, $data)
    {
        if (!$job->isDeleted()) {
            $job->delete();
            $this->run($data);
        }
    }

    /**
     * @param $data
     * @return bool
     */
    public function run($data)
    {
        try {
            $url  = $data['url'] ?? '';
            $url  = CommonUtil::formatUrl($url);
            // if (!filter_var($url, FILTER_VALIDATE_URL)) {
            //     Log::queue(sprintf("url invalid:%s", $url));
            //     return false;
            // }
            $skipCache = $data['skipCache'] ?? false;
            $force     = $data['force'] ?? false;
            $rerun     = $data['rerun'] ?? false;
            if (empty($url)) {
                return true;
            }
            $vectorCache = $this->vectorModel->getCache($url);
            $urlVector   = json_decode($vectorCache['url_vector'], true);
            $cache       = [];
            if (!empty($urlVector) && !filter_var($vectorCache['url_vector'], FILTER_VALIDATE_BOOLEAN) && !$skipCache && !$force && !$rerun) {
                return true;
            }
            if (!empty($vectorCache['log']) && stripos($vectorCache['log'], 'http no 200') !== false && !$force) {
                return true;
            }
            if (!empty($urlVector) && json_decode($vectorCache['url_vector']) !== false && !$skipCache && !$force) {
                $cache = $urlVector;
            }
            if ($rerun) {
                $cache = [];
            }
            list($httpInfo, $whoisInfo, $tls) = $this->vectorModel->get2VectorInfo($url, $skipCache || $force);
            $logs   = [];
            $vector = [];
            if (!isset($httpInfo['code']) || $httpInfo['code'] != 200) {
                $logs[] = sprintf("http no 200:%s", $url);
                if (!$force) {
                    return false;
                }
            } else {
                $vector = $this->vectorModel->getVectors($url, $httpInfo, $whoisInfo, $tls, $cache, true);
                $logs   = $this->vectorModel->vectorLogs;
                if ($vector === false) {
                    $logs[] = sprintf("vector error:%s - %s", $this->vectorModel->errorFlag, $url);
                    if (!$force) {
                        Log::queue(sprintf("vector error:%s - %s", $this->vectorModel->errorFlag, $url));
                        return false;
                    }
                }
            }
            $vectorData = [
                'url'        => $url,
                'url_hash'   => CommonUtil::getUrlHash($url),
                'url_vector' => json_encode($vector),
                'log'        => json_encode($logs),
                'url_date'   => CommonUtil::getDate('Y-m-d')
            ];
            $this->vectorModel->addUrlVector($vectorData, $vectorCache['id'] ?? 0);
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
        return true;
    }
}
