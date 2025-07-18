<?php

declare(strict_types=1);

namespace app\job;

use app\model\CommonUtil;
use app\model\Url;
use Exception;
use think\facade\Filesystem;
use think\facade\Log;
use think\queue\Job;

class UrlTrain
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
        try {
            $type     = $data['type'] ?? Url::TYPE_NORMAL;
            $set      = $data['set'] ?? Url::SET_TRAIN;
            $positive = $data['positive'] ?? '';
            $negative = $data['negative'] ?? '';
            $command  = sprintf('/usr/bin/php /opt/urlnn/pvector/train.php %s %s > /dev/null &', $positive, $negative);
            exec($command, $output);
            Log::queue('Url train done:' . json_encode($output));
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
        return true;
    }
}
