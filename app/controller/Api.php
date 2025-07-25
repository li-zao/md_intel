<?php
declare (strict_types=1);

namespace app\controller;

use app\common\Code;
use app\job\UrlSnapshot;
use app\model\CommonUtil;
use app\model\Jobs;
use app\model\Url;
use app\model\UrlHttpCache;
use Exception;
use think\facade\Queue;
use think\Request;
use app\BaseController;
use app\model\Dictionary;

class Api extends BaseController
{
    /**
     * 接受扫描系统推送的url
     * @return \think\response\Json
     */
    public function pushUrl()
    {
        try {
            $url      = $this->request->param('url');
            $type     = $this->request->param('type');
            $scanId   = $this->request->param('sys_scan_id', 0);
            $mailInfo = $this->request->param('sys_mail_record', '');
            $url      = CommonUtil::formatUrl($url);
            $hash     = CommonUtil::getUrlHash($url);
            $record   = Url::where('hash', $hash)->findOrEmpty();
            $save     = [];
            if (!$record->isEmpty()) {
                $save = $record->toArray();
            }
            $sourceDict   = Dictionary::getDict(Dictionary::TYPE_SOURCE);
            $categoryDict = Dictionary::getDict(Dictionary::TYPE_CATEGORY);
            // 5	source	来源	2	扫描系统	启用
            // 2	category	类型	2	恶意	启用
            $source   = 2;
            $category = 2;
            $set      = Url::SET_PREDICT;
            foreach ($sourceDict as $key => $value) {
                if ($value == '扫描系统') {
                    $source = $key;
                    break;
                }
            }
            foreach ($categoryDict as $key => $value) {
                if ($type == Url::TYPE_NORMAL) {
                    if ($value == '正常') {
                        $category = $key;
                        break;
                    }
                } else {
                    if ($value == '恶意') {
                        $category = $key;
                        break;
                    }
                }
            }
            $save['url']             = $url;
            $save['hash']            = $hash;
            $save['source']          = $source;
            $save['category']        = $category;
            $save['type']            = $type;
            $save['set']             = $set;
            $save['sys_scan_id']     = $scanId;
            $save['sys_mail_record'] = $mailInfo;
            $save['is_del']          = Code::IS_NO;
            $save                    = Url::formatSave($save);
            foreach ($save as $key => $value) {
                $record->$key = $value;
            }
            $record->save();
            Queue::later(mt_rand(1, 10), UrlSnapshot::class, $record->toArray(), Jobs::QUEUE_URL_SNAPSHOT);
            return $this->jsonAPI(['id' => $record->id]);
        } catch (Exception $e) {
            return $this->jsonAPI([], Code::API_NO, ['msg' => $e->getMessage()]);
        }
    }

    /**
     * @return \think\response\Json
     */
    public function getUrlCache()
    {
        try {
            $url    = $this->request->param('url');
            $hash   = CommonUtil::getUrlHash($url);
            $record = UrlHttpCache::where('hash', $hash)->findOrEmpty();
            if ($record->isEmpty()) {
                return $this->jsonAPI([]);
            }
            $record = $record->toArray();
            unset($record['id']);
            return $this->jsonAPI($record);
        } catch (Exception $e) {
            return $this->jsonAPI([], Code::API_NO);
        }
    }
}
