<?php

declare(strict_types=1);

namespace app\job;

use app\model\Jobs;
use app\model\Url;
use app\model\UrlTestRows;
use app\util\MailTas;
use Exception;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use think\queue\Job;

class UrlTest
{

    public function __construct()
    {
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
        Db::startTrans();
        try {
            $id   = $data['id'] ?? 0;
            $type = $data['type'] ?? Url::TYPE_MALICIOUS;
            $set  = $data['set'] ?? Url::SET_PREDICT;
            if (empty($id)) {
                Log::error('UrlTest: id is empty');
                return false;
            }
            $util          = new MailTas();
            $urlTestRecord = \app\model\UrlTest::findOrEmpty($id);
            $where         = [
                'type'   => $type,
                'set'    => $set,
                'is_del' => 0,
            ];
            $urlQuery      = \app\model\Url::field('max(id) as end, min(id) as start')->where($where)->select();
            $urlQuery      = $urlQuery->toArray();
            $start         = $urlQuery[0]['start'] ?? 0;
            $end           = $urlQuery[0]['end'] ?? 0;
            $limit         = 100;
            $totalInsert   = 0;
            $fiveMins      = 300;
            $sleep = 0;
            for ($i = $start; $i < $end; $i += $limit) {
                $records = Url::field('id,url')->where($where)->where('id', '>=', $i)->where('id', '<', $i + $limit)->select();
                $records = $records->toArray();
                foreach ($records as $record) {
                    $totalInsert++;
                    $testRow     = new UrlTestRows();
                    $res         = $util->urlScan($record['url']);
                    $_insertData = [
                        'url_id'   => $record['id'],
                        't_id'     => $id,
                        'tas_id'   => $res['id'] ?? 0,
                        'scan_log' => '',
                        'res'      => '',
                    ];
                    $testRow->save($_insertData);
                    if (!empty($_insertData['tas_id'])) {
                        $timeout = $fiveMins;
                        if (!empty($res['timeout'])) {
                            $timeout = $res['timeout'];
                        }
                        Queue::later($timeout + mt_rand(1, 60), UrlGetTest::class, ['id' => $_insertData['tas_id']], Jobs::QUEUE_URL_GET_TEST);
                    }
                    sleep(3);
                    $sleep += 3;
                }
            }
            $urlTestRecord->save(['total' => $totalInsert]);
            Queue::later($totalInsert * $sleep, UrlTestStatistic::class, ['id' => $id], Jobs::QUEUE_URL_TEST_STATISTIC);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
        }
        return true;
    }
}
