<?php

declare(strict_types=1);

namespace app\job;

use app\model\Url;
use app\model\UrlTestRows;
use app\util\MailTas;
use Exception;
use think\facade\Db;
use think\facade\Log;
use think\queue\Job;

class UrlGetTest
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
            $id = $data['id'] ?? 0;
            if (empty($id)) {
                return false;
            }
            $util       = new MailTas();
            $urlTestRow = \app\model\UrlTestRows::where('tas_id', $id)->findOrEmpty();
            $res        = $util->urlGetScan($id);
            $update = [
                'res'      => json_encode($res),
                'score'    => $res['data']['score'] ?? -1,
                'scan_log' => $res['data']['url_scanlog'] ?? '',
            ];
            $urlTestRow->save($update);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
        }
        return true;
    }
}
