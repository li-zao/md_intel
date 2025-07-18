<?php

declare(strict_types=1);

namespace app\job;

use app\model\CommonUtil;
use app\model\UrlScreen;
use app\util\UrlNN;
use Exception;
use think\facade\Filesystem;
use think\facade\Log;
use think\queue\Job;

class UrlSnapshot
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
            $url = $data['url'] ?? '';
            $hash = $data['hash'] ?? '';
            $url = CommonUtil::formatUrl($url);
            // if (!filter_var($url, FILTER_VALIDATE_URL)) {
            //     Log::queue(sprintf("url invalid:%s", $url));
            //     return false;
            // }
            $skipCache = $data['skipCache'] ?? false;
            if (empty($url)) {
                return true;
            }
            $this->vectorModel->get2VectorInfo($url, $skipCache, true);
            $bucket     = 'url';
            $srcMonth   = strtolower($bucket) . '/' . date('Ym');
            $fileName   = md5($url) . '.png';
            $screenFile = sprintf(
                '%s/%s/%s',
                strval(Filesystem::getDiskConfig('public', 'root')),
                $srcMonth,
                $fileName
            );
            if (!file_exists($screenFile)) {
                // $resize = sprintf('%s -crop x900 +repage %s', $screenFile, str_replace('.png', '_%02d.png', $screenFile));
                CommonUtil::makeScreenshot($url, $screenFile);
            }
            $record     = UrlScreen::where('hash', $hash)->findOrEmpty();
            $targetFile = sprintf('/storage/%s/%s', $srcMonth, $fileName);
            if ($record->isEmpty()) {
                $record         = new UrlScreen();
                $record->hash   = $hash;
                $record->screen = json_encode([$targetFile]);
                $record->save();
            } else {
                $screen = json_decode($record->screen, true);
                if (!in_array($targetFile, $screen)) {
                    $screen[]       = $targetFile;
                    $record->screen = json_encode($screen);
                    $record->save();
                }
            }
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
        return true;
    }
}
