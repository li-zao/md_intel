<?php

declare(strict_types=1);

namespace app\job;

use app\model\CommonUtil;
use app\model\Url;
use app\util\UrlNN;
use Exception;
use think\facade\Filesystem;
use think\facade\Log;
use think\queue\Job;

class Url2Vector
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
            $type     = $data['type'] ?? Url::TYPE_NORMAL;
            $set      = $data['set'] ?? Url::SET_TRAIN;
            $fileName = sprintf('%s_%s_%s.csv', CommonUtil::getDate('m-d_His'), Url::TYPE_DICT[$type], Url::SET_DICT[$set]);
            $srcMonth = 'vector/' . date('Ym');
            $rootDir  = Filesystem::getDiskConfig('intel_file', 'root');
            $rootDir  = sprintf('%s/%s', strval($rootDir), $srcMonth);
            $filePath = sprintf('%s/%s', $rootDir, $fileName);
            if (!is_dir($rootDir)) {
                mkdir($rootDir, 0777, true);
            }
            if (file_exists($filePath)) {
                $fileName = sprintf('%s_%s', CommonUtil::getRandomString(), $fileName);
                $filePath = sprintf('%s/%s', $rootDir, $fileName);
            }
            if (!file_exists($filePath)) {
                file_put_contents($filePath, '');
            }
            $urlModel    = Url::getCommonModel();
            $totalModel  = clone $urlModel;
            $urlNN       = new UrlNN();
            $vectorModel = new \app\model\UrlVector();
            $header      = ['url'];
            $header      = array_merge($header, array_keys($vectorModel->vectorMap));
            $src         = Filesystem::disk('intel_file')->put($filePath, implode(',', $header));
            if ($src === false) {
                Log::error('url2vector put file error:' . $filePath);
                return true;
            }
            $query = $totalModel->field('max(id) as end, min(id) as start')->where(['type' => $type, 'set' => $set])->select()->toArray();
            $start = $query[0]['start'];
            $end   = $query[0]['end'];
            $step  = 10000;
            $fd    = fopen($filePath, 'a+');
            for ($i = $start; $i <= $end; $i += $step) {
                $model = clone $urlModel;
                $list  = $model->field('id,url,type,set,is_del')->where('id', '>=', $i)->where('id', '<', $i + $step)->select()->toArray();
                foreach ($list as $item) {
                    if (
                        $item[CommonUtil::DEL_FIELD] || $item['type'] != $type || $item['set'] != $set) {
                        continue;
                    }
                    $vector = $this->getUrlVector($item['url'], true);
                    if (empty($vector)) {
                        continue;
                    }
                    $vector = $urlNN->formatVector($vector);
                    $row    = [$item['url']];
                    $row    = array_merge($row, $vector);
                    fputcsv($fd, $row);
                }
            }
            fclose($fd);
            Log::queue('url2vector done:' . $filePath);
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
        return true;
    }

    /**
     * @param $url
     * @param $onlyCache
     * @return array|mixed
     * @throws Exception
     */
    public function getUrlVector($url, $onlyCache = false)
    {
        $vectorCache = $this->vectorModel->getCache($url);
        if (!empty($vectorCache['url_vector']) && json_decode($vectorCache['url_vector']) !== false) {
            return json_decode($vectorCache['url_vector'], true);
        }
        if ($onlyCache) {
            return [];
        }
        list($httpInfo, $whoisInfo, $tls) = $this->vectorModel->get2VectorInfo($url);
        if (!isset($httpInfo['code']) || $httpInfo['code'] != 200) {
            return [];
        }
        $vector = $this->vectorModel->getVectors($url, $httpInfo, $whoisInfo, $tls, []);
        if ($vector === false) {
            return [];
        }
        return $vector;
    }
}
