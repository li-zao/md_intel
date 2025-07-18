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

class UrlTestStatistic
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
            $id            = $data['id'] ?? 0;
            $urlTestRecord = \app\model\UrlTest::findOrEmpty($id);
            if (!empty($urlTestRecord->statistic)) {
                return false;
            }
            $where['t_id'] = $id;
            $urlQuery      = \app\model\UrlTestRows::field('max(id) as end, min(id) as start')->where($where)->select();
            $urlQuery      = $urlQuery->toArray();
            $start         = $urlQuery[0]['start'] ?? 0;
            $end           = $urlQuery[0]['end'] ?? 0;
            $limit         = 100;
            $statistic     = [
                'ok'        => 0,
                'intercept' => 0,
            ];
            for ($i = $start; $i < $end; $i += $limit) {
                $records = UrlTestRows::field('score')->where($where)->where('id', '>=', $i)->where('id', '<', $i + $limit)->select();
                $records = $records->toArray();
                foreach ($records as $record) {
                    if (!empty($record['score']) && $record['score'] > 0) {
                        $statistic['intercept']++;
                    } else {
                        $statistic['ok']++;
                    }
                }
            }
            $urlTestRecord->save(['statistic' => json_encode($statistic)]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
        }
        return true;
    }
}
