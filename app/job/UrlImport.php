<?php

declare(strict_types=1);

namespace app\job;

use app\common\Code;
use app\model\CommonUtil;
use app\model\Dictionary;
use app\model\Jobs;
use app\model\Url;
use app\util\UrlNN;
use Exception;
use think\facade\Log;
use think\facade\Queue;
use think\queue\Job;

class UrlImport
{
    public const COL_URL = 'url';
    public const COL_SOURCE = 'source|来源';
    public const COL_CATEGORY = 'category|类型';
    public const COL_SET = 'set|集合';

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
            $source   = $data['source'] ?? 1;
            $category = $data['category'] ?? 1;
            $filePath = $data['src'] ?? '';
            if (!isset(Url::TYPE_DICT[$type])) {
                Log::queue('TYPE ERR:' . json_encode($data));
                return false;
            }
            if (!file_exists($filePath)) {
                Log::queue('PATH ERR:' . json_encode($data));
                return false;
            }
            $colPos          = [
                self::COL_URL      => 0,
                self::COL_SOURCE   => null,
                self::COL_CATEGORY => null,
                self::COL_SET      => null,
            ];
            $sourceDict      = Dictionary::getDict(Dictionary::TYPE_SOURCE);
            $sourceReverse   = array_flip($sourceDict);
            $categoryDict    = Dictionary::getDict(Dictionary::TYPE_CATEGORY);
            $categoryReverse = array_flip($categoryDict);
            $setDict         = Url::SET_DICT;
            $setReverse      = array_flip($setDict);
            $fd              = fopen($filePath, 'r');
            $firstLine       = fgets($fd);
            $colPos          = $this->calcColPos($firstLine, $colPos);
            // if (empty(array_sum($colPos))) {
            //     Log::queue('COL POS ERR:' . $firstLine . "\t" . json_encode($colPos));
            //     return false;
            // }
            while ($line = fgetcsv($fd)) {
                $url = $line[$colPos[self::COL_URL]] ?? '';
                $url = CommonUtil::formatUrl($url);
                $url = filter_var($url, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    continue;
                }
                $hash     = CommonUtil::getUrlHash($url);
                $record   = Url::where('hash', $hash)->findOrEmpty();
                $source   = is_null($colPos[self::COL_SOURCE]) ? $source : $line[$colPos[self::COL_SOURCE]];
                $category = is_null($colPos[self::COL_CATEGORY]) ? $category : $line[$colPos[self::COL_CATEGORY]];
                $setData  = is_null($colPos[self::COL_SET]) ? $set : $line[$colPos[self::COL_SET]];
                if (isset($sourceReverse[$source])) {
                    $source = $sourceReverse[$source];
                }
                if (isset($categoryReverse[$category])) {
                    $category = $categoryReverse[$category];
                }
                if (isset($setReverse[$setData])) {
                    $setData = $setReverse[$setData];
                }
                $insertData = [
                    'url'                 => $url,
                    'hash'                => $hash,
                    'source'              => $source,
                    'category'            => $category,
                    'type'                => $type,
                    'set'                 => is_null($setData) ? $set : $setData,
                    CommonUtil::DEL_FIELD => Code::IS_NO,
                ];
                $insertData = Url::formatSave($insertData);
                if ($record->isEmpty()) {
                    Url::create($insertData);
                    Queue::later(mt_rand(10, 100), UrlVector::class, $insertData, Jobs::QUEUE_VECTOR);
                } else {
                    Url::update($insertData, ['hash' => $hash]);
                }
            }
            fclose($fd);
            return true;
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
        return true;
    }

    /**
     * @param $line
     * @param $colPos
     * @return mixed
     */
    public function calcColPos($line, $colPos)
    {
        $cols = explode(',', trim($line));
        foreach ($cols as $k => $v) {
            if (strtolower($v) == self::COL_URL) {
                $colPos[self::COL_URL] = $k;
            }
            [$s1, $s2] = explode('|', self::COL_SOURCE);
            if (strtolower($v) == strtolower($s1) || strtolower($v) == strtolower($s2)) {
                $colPos[self::COL_SOURCE] = $k;
            }
            [$s1, $s2] = explode('|', self::COL_CATEGORY);
            if (strtolower($v) == strtolower($s1) || strtolower($v) == strtolower($s2)) {
                $colPos[self::COL_CATEGORY] = $k;
            }
            [$s1, $s2] = explode('|', self::COL_SET);
            if (strtolower($v) == strtolower($s1) || strtolower($v) == strtolower($s2)) {
                $colPos[self::COL_SET] = $k;
            }
        }
        return $colPos;
    }
}
