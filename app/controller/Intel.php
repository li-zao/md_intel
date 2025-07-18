<?php

namespace app\controller;

use app\common\Code;
use app\job\Url2Vector;
use app\job\UrlImport;
use app\job\UrlSnapshot;
use app\job\UrlTest;
use app\job\UrlTrain;
use app\job\UrlVector;
use app\library\XLSXWriter;
use app\model\CommonUtil;
use app\model\File;
use app\model\Jobs;
use app\model\Url;
use app\model\Desc;
use app\model\Dictionary as DictModel;
use app\BaseController;
use app\model\UrlHttpCache;
use app\model\UrlScreen;
use app\util\UrlNN;
use app\validate\FileSave;
use app\validate\UrlSave;
use Exception;
use think\App;
use think\facade\Db;
use think\facade\Filesystem;
use think\facade\Queue;
use think\facade\View;
use ZipArchive;

class Intel extends BaseController
{
    /**
     * @param App $app
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    /**
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function url()
    {
        $type = $this->request->param('type', Url::TYPE_NORMAL);
        $set  = $this->request->param('set', Url::SET_TRAIN);
        View::assign([
            'source'   => DictModel::getXmSelect(DictModel::TYPE_SOURCE),
            'category' => DictModel::getXmSelect(DictModel::TYPE_CATEGORY),
            'predict'  => DictModel::formatSelect(UrlNN::URL_PREDICT_DICT),
            'type'     => $type,
            'set'      => $set,
            'menu'     => $this->controller . '_' . $this->action . '_type_' . $type,
        ]);
        return View::fetch();
    }

    /**
     * @return \think\response\Json
     */
    public function urlList()
    {
        try {
            $params = $this->request->param();
            if (!isset($params[CommonUtil::DEL_FIELD])) {
                $params[CommonUtil::DEL_FIELD] = Code::IS_NO;
            }
            [$page, $limit] = $this->pagination();
            [$total, $list] = Url::getList($params, $page, $limit);
            return $this->jsonAPI($list, Code::API_YES, ['count' => $total]);
        } catch (Exception $e) {
            CommonUtil::logError($e);
        }
        return $this->jsonAPI([], Code::API_NO);
    }

    /**
     * @return \think\response\Json
     */
    public function urlSave()
    {
        try {
            $params = $this->request->param();
            validate(UrlSave::class)->check($params);
            $hash   = CommonUtil::getUrlHash($params['url']);
            $record = Url::where('hash', $hash)->find();
            $params = Url::formatSave($params);
            if (empty($record)) {
                Url::create($params);
                Queue::later(10, UrlVector::class, $params, Jobs::QUEUE_VECTOR);
            } else {
                unset($params['id']);
                Url::update($params, ['hash' => $hash]);
            }
            return self::jsonAPI([]);
        } catch (Exception $e) {
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * 保存【评论，分类，来源，类型，集合】
     * @return \think\response\Json
     */
    public function urlDesc()
    {
        Db::startTrans();
        try {
            $id     = $this->request->param('id', 0);
            $desc   = $this->request->param('desc', '');
            $params = $this->request->param();
            $where  = [
                'r_id' => $id,
                'type' => Desc::TYPE_URL
            ];
            if (empty($id)) {
                return self::jsonAPI([], Code::API_NO, lang('err.params'));
            }
            $record = Url::where('id', $id)->findOrEmpty();
            if (!$record->isEmpty()) {
                $update  = [];
                $setList = ['type', 'set', 'source', 'category'];
                foreach ($setList as $item) {
                    if (isset($params[$item])) {
                        $update[$item] = $params[$item];
                    }
                }
                if (!empty($update)) {
                    Url::update($update, ['id' => $id]);
                }
            }
            $data   = ['r_id' => $id, 'type' => Desc::TYPE_URL, 'content' => $desc];
            $data   = Desc::formatSave($data);
            $record = Desc::where($where)->findOrEmpty();
            if ($record->isEmpty()) {
                Desc::create($data);
            } else {
                Desc::update($data, ['id' => $record->id]);
            }
            Db::commit();
            return self::jsonAPI(['desc_time' => CommonUtil::getDate(), 'desc' => $desc]);
        } catch (Exception $e) {
            Db::rollback();
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * @return \think\response\Json
     */
    public function urlDel()
    {
        try {
            $ids = [];
            $id  = $this->request->param('id', 0);
            if (!empty($id)) {
                $ids[] = $id;
            }
            $batches = $this->request->param('ids', '');
            if (!empty($batches)) {
                $ids = array_merge($ids, explode(',', $batches));
            }
            $ids = array_unique($ids);
            if (empty($ids)) {
                return self::jsonAPI([], Code::API_NO, lang('need.ids'));
            }
            $res = Url::where('id', 'in', $ids)->save([CommonUtil::DEL_FIELD => Code::IS_YES]);
            return self::jsonAPI([$res]);
        } catch (Exception $e) {
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * @return string|\think\response\Json
     */
    public function urlDetail()
    {
        try {
            $urlResponse = $body = '';
            $id          = $this->request->param('id', 0);
            $record      = Url::findOrEmpty($id);
            $record      = Url::formatCommon($record);
            $url         = $record['url'] ?? '';
            $url         = CommonUtil::formatUrl($url);
            $_vector     = \app\model\UrlVector::getCache($url);
            $vectorLog   = json_decode($_vector['log'], true) ?: [];
            $vectors     = json_decode($_vector['url_vector'], true);
            $desc        = Desc::get($id);
            $urlHash     = CommonUtil::getUrlHash($url);
            $http        = UrlHttpCache::field('id, url_http, url_whois, updated_at')->where('url_hash', $urlHash)->findOrEmpty();
            $realUrl = $url;
            if (!$http->isEmpty()) {
                $http = $http->toArray();
            } else {
                $http = ['id' => 0, 'updated_at' => ''];
            }
            $vector = $vectors;
            // if (!empty($vectors)) {
            //     foreach ($vectors as $key => $value) {
            //         if (isset($vectorLog[$key])) {
            //             $vector[$key] = $vectorLog[$key];
            //         } else {
            //             $vector[$key] = $value;
            //         }
            //     }
            // }
            // ksort($vector);
            if (!empty($http['url_http'])) {
                // $_format = function ($str) {
                //     $str = str_replace([PHP_EOL . PHP_EOL, "\n\n", ',"'], ["\n", "", ",\n\""], $str);
                //     $str = htmlspecialchars_decode($str);
                //     $str = html_entity_decode($str);
                //     return preg_replace('/^[ \t]*[\r\n]+/m', '', $str);
                // };
                // $urlHttp = json_decode($http['url_http'], true);
                // if (json_last_error() !== JSON_ERROR_NONE) {
                //     $urlHtmlText .= $_format(strip_tags($http['url_http']));
                // } elseif (!empty($urlHttp['body'])) {
                //     $urlHtmlText .= $_format(strip_tags($urlHttp['body']));
                // }
                [$json, $body] = UrlHttpCache::reformatJson($http['url_http']);
                $decodeJson = json_decode($json, true);
                if (!empty($decodeJson['http']['real_url'])) {
                    $realUrl = $decodeJson['http']['real_url'];
                }
                $urlResponse = json_encode(json_decode($json, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
            if (!empty($http['url_whois'])) {
                $record['whois'] = json_encode(json_decode($http['url_whois'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
            $record['real_url'] = $realUrl;
            View::assign([
                'id'          => $id,
                'hash'        => $urlHash,
                'record'      => $record,
                'vectorLog'   => empty($vector) ? json_encode($vectorLog) : json_encode($vector),
                'collapsed'   => 0, // empty($vector) ? 0 : 1,
                'urlResponse' => $urlResponse,
                'urlHtml'     => $body,
                'vector'      => $_vector,
                'desc'        => $desc['content'] ?? '',
                'descTime'    => $desc['updated_at'] ?? '',
                'rowWidth'    => empty($_vector['url_vector']) ? 12 : 6,
                'source'      => DictModel::getXmSelect(DictModel::TYPE_SOURCE),
                'category'    => DictModel::getXmSelect(DictModel::TYPE_CATEGORY),
                'predict'     => DictModel::formatSelect(UrlNN::URL_PREDICT_DICT),
                'set'         => DictModel::formatSelect(Url::SET_DICT),
                'type'        => DictModel::formatSelect(Url::TYPE_DICT),
                'http'        => $http,
            ]);
            return View::fetch();
        } catch (Exception $e) {
            return json([$e->getMessage(), $e->getTrace()]);
        }
    }

    /**
     * @return \think\response\File | string
     */
    public function urlExport()
    {
        try {
            $params = $this->request->param();
            if (!isset($params[CommonUtil::DEL_FIELD])) {
                $params[CommonUtil::DEL_FIELD] = Code::IS_NO;
            }
            $ids = $this->request->param('ids', '');
            if (!empty($ids)) {
                $ids   = explode(',', $ids);
                $model = Url::getCommonModel();
                $model = $model->where('id', 'in', $ids);
            } else {
                $searchParam = Url::getSearchParams($params);
                $model       = Url::getCommonModel($searchParam);
            }
            $totalModel = clone $model;
            $query      = $totalModel->field('max(id) as end, min(id) as start')->select()->toArray();
            if (empty($query)) {
                View::assign(['error' => lang('err.no_data')]);
                return View::fetch('/error/error');
            }
            $headers     = [[
                lang('common.id'),
                lang('common.url'),
                lang('common.domain'),
                lang('common.hash'),
                lang('common.created_at'),
                lang('common.source'),
                lang('common.category'),
            ]];
            $fileName    = 'url_' . date('YmdHis') . '.xlsx';
            $filePath    = runtime_path('temp') . $fileName;
            $writer      = new XLSXWriter(['col_width' => [], 'merge_cells' => []]);
            $counter     = 0;
            $step        = 10000;
            $numPerSheet = 1000000;
            $getSheet    = function ($n) use ($numPerSheet) {
                $p = ceil($n / $numPerSheet);
                if (empty($p)) {
                    $p = 1;
                }
                return 'Sheet' . $p;
            };
            $writeHeader = function ($sheet, $headers) use ($writer) {
                foreach ($headers as $key => $row) {
                    $writer->writeSheetRow($sheet, $row);
                }
            };
            $start       = $query[0]['start'];
            $end         = $query[0]['end'];
            $sheet       = $getSheet($counter);
            $writeHeader($sheet, $headers);
            for ($i = $start; $i <= $end; $i += $step) {
                $m    = clone $model;
                $list = $m->where('id', 'between', [$i, $i + $step])->select()->toArray();
                foreach ($list as $item) {
                    if ($item[CommonUtil::DEL_FIELD]) {
                        continue;
                    }
                    $item = Url::formatCommon($item);
                    unset($item['type'], $item[CommonUtil::DEL_FIELD], $item['source'], $item['category']);
                    $counter++;
                    if (fmod($counter, $numPerSheet) == 0) {
                        $sheet = $getSheet($counter);
                        $writeHeader($sheet, $headers);
                    }
                    $writer->writeSheetRow($sheet, $item);
                }
            }
            $writer->writeToFile($filePath);
            return download($filePath, $fileName)->force(true);
        } catch (Exception $e) {
            View::assign(['error' => $e->getMessage()]);
            return View::fetch('/error/error');
        }
    }

    /**
     * @return string|\think\response\Json
     */
    public function urlImport()
    {
        try {
            $type     = $this->request->param('type', Url::TYPE_NORMAL);
            $set      = $this->request->param('set', Url::SET_TRAIN);
            $file     = $this->request->file('file');
            $fileName = $file->getOriginalName();
            $srcMonth = 'import/' . date('Ym');
            $rootDir  = Filesystem::getDiskConfig('intel_file', 'root');
            $rootDir  = strval($rootDir);
            $dirPath  = sprintf('%s', $srcMonth);
            $filePath = sprintf('%s/%s/%s', $rootDir, $dirPath, $fileName);
            if (file_exists($filePath)) {
                $fileName = sprintf('%s_%s', CommonUtil::getRandomString(), $fileName);
                $filePath = sprintf('%s/%s/%s', $rootDir, $dirPath, $fileName);
            }
            $src = Filesystem::disk('intel_file')->putFileAs($dirPath, $file, $fileName);
            if ($src === false) {
                return self::jsonAPI([], Code::API_NO, lang('err.upload'));
            }
            $data = [
                'type' => $type,
                'set'  => $set,
                'src'  => $filePath,
            ];
            Queue::push(UrlImport::class, $data, Jobs::QUEUE_URL_IMPORT);
            return self::jsonAPI($data);
        } catch (Exception $e) {
            View::assign(['error' => $e->getMessage()]);
            return View::fetch('/error/error');
        }
    }

    /**
     * 移动url
     * @return \think\response\Json
     */
    public function urlMove()
    {
        try {
            $params = $this->request->param();
            $set    = $this->request->param('set', Url::SET_TRAIN);
            if ($set == Url::SET_TRAIN) {
                $set = $set << 1;
            } else {
                $set = $set >> 1;
            }
            if (!isset($params[CommonUtil::DEL_FIELD])) {
                $params[CommonUtil::DEL_FIELD] = Code::IS_NO;
            }
            $ids = $this->request->param('ids', '');
            if (!empty($ids)) {
                $ids   = explode(',', $ids);
                $model = Url::getCommonModel();
                $model = $model->where('id', 'in', $ids);
            } else {
                $searchParam = Url::getSearchParams($params);
                $model       = Url::getCommonModel($searchParam);
            }
            $model->update(['set' => $set]);
            return self::jsonAPI([], Code::API_YES, lang('common.move_success'));
        } catch (Exception $e) {
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * url2vector 生成当前集合训练所需向量文件
     * @return \think\response\Json
     */
    public function url2Vector()
    {
        try {
            $type = $this->request->param('type', Url::TYPE_NORMAL);
            $set  = $this->request->param('set', Url::SET_TRAIN);
            $data = [
                'type' => $type,
                'set'  => $set,
            ];
            Queue::push(Url2Vector::class, $data, Jobs::QUEUE_URL_2_VECTOR);
            return self::jsonAPI($data);
        } catch (Exception $e) {
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * @return \think\response\Json
     */
    public function urlTrain()
    {
        try {
            $data = [
                'type'     => $this->request->param('type', Url::TYPE_NORMAL),
                'set'      => $this->request->param('set', Url::SET_TRAIN),
                'positive' => $this->request->param('positive', ''),
                'negative' => $this->request->param('negative', ''),
            ];
            Queue::push(UrlTrain::class, $data, Jobs::QUEUE_URL_2_TRAIN);
            return self::jsonAPI($data);
        } catch (Exception $e) {
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * url测试
     * @return \think\response\Json
     */
    public function urlTest()
    {
        Db::startTrans();
        try {
            $type = $this->request->param('type', Url::TYPE_MALICIOUS);
            $set  = $this->request->param('set', Url::SET_PREDICT);
            $record = new \app\model\UrlTest();
            $record->name = sprintf('测试集-%s-%s', Url::TYPE_DICT[$type], Url::SET_DICT[$set]);
            $record->statistic = '[]';
            $record->save();
            $data = [
                'type' => $type,
                'set'  => $set,
                'id'   => $record->id,
            ];
            Queue::push(UrlTest::class, $data, Jobs::QUEUE_URL_TEST);
            Db::commit();
            return self::jsonAPI($data);
        } catch (Exception $e) {
            Db::rollback();
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * @return \think\response\Json
     */
    public function urlPredict()
    {
        try {
            $id     = $this->request->param('id', 0);
            $record = Url::where('id', $id)->findOrEmpty();
            if ($record->isEmpty()) {
                return self::jsonAPI([], Code::API_NO, '记录不存在');
            }
            $util            = new UrlNN();
            $predict         = $util->urlPredict($record->url);
            $record->predict = $predict;
            $record->save();
            return self::jsonAPI([
                'predict'     => $predict,
                'predict_str' => UrlNN::URL_PREDICT_DICT[$predict] ?? $predict,
                'err'         => $util->errInfo,
            ]);
        } catch (Exception $e) {
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * @return \think\response\Json
     */
    public function urlGenVector()
    {
        try {
            $id     = $this->request->param('id', 0);
            $record = Url::where('id', $id)->findOrEmpty();
            if ($record->isEmpty()) {
                return self::jsonAPI([], Code::API_NO, '记录不存在');
            }
            $data              = $record->toArray();
            $data['force']     = Code::IS_YES;
            $data['skipCache'] = Code::IS_YES;
            // $data['rerun'] = Code::IS_YES;
            // $res = Queue::push(UrlVector::class, $data, Jobs::QUEUE_VECTOR);
            $res = Queue::later(mt_rand(1, 10), UrlSnapshot::class, $data, Jobs::QUEUE_URL_SNAPSHOT);
            return self::jsonAPI([$res]);
        } catch (Exception $e) {
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * @return \think\response\Json
     */
    public function urlScreen()
    {
        try {
            $hash       = $this->request->param('hash');
            $src        = $this->request->param('src');
            $src        = parse_url($src, PHP_URL_PATH);
            $record     = UrlScreen::where('hash', $hash)->findOrEmpty();
            $storageDir = root_path('public');
            $sourceFile = $storageDir . $src;
            $getTarget  = function ($hash) use ($storageDir, $src) {
                $ext      = pathinfo($src, PATHINFO_EXTENSION);
                $bucket   = $this->request->get('bucket', 'url');
                $srcMonth = strtolower($bucket) . '/' . date('Ym');
                $filePath = sprintf('%s/%s/', strval(Filesystem::getDiskConfig('public', 'root')), $srcMonth);
                for ($i = 1; $i <= 10; $i++) {
                    $path = $filePath . sprintf('%s_%s.%s', $hash, $i, $ext);
                    if (!file_exists($path)) {
                        return $path;
                    }
                }
                return null;
            };
            $targetFile = $getTarget($hash);
            if (empty($targetFile)) {
                return self::jsonAPI([], Code::API_NO, '文件不存在');
            }
            $command = sprintf('mv "%s" "%s"', $sourceFile, $targetFile);
            exec($command);
            $targetFile = str_replace($storageDir, '/', $targetFile);
            if ($record->isEmpty()) {
                $record         = new UrlScreen();
                $record->hash   = $hash;
                $record->screen = json_encode([$targetFile]);
                $record->save();
                return self::jsonAPI();
            }
            $screen = json_decode($record->screen, true);
            if (!in_array($targetFile, $screen)) {
                $screen[]       = $targetFile;
                $record->screen = json_encode($screen);
                $record->save();
            }
            return $this->jsonAPI();
        } catch (Exception $e) {
            CommonUtil::logError($e);
            return $this->jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * @return \think\response\Json
     */
    public function urlScreenList()
    {
        try {
            $hash   = $this->request->param('hash');
            $record = UrlScreen::where('hash', $hash)->findOrEmpty();
            if ($record->isEmpty()) {
                $total = 0;
                $list  = [];
            } else {
                $list  = json_decode($record->screen, true);
                $total = count($list);
            }
            $list = UrlScreen::formatList($list);
            return $this->jsonAPI($list, Code::API_YES, ['count' => $total]);
        } catch (Exception $e) {
            CommonUtil::logError($e);
        }
        return $this->jsonAPI([], Code::API_NO);
    }

    /**
     * @return \think\response\Json
     */
    public function urlScreenDel()
    {
        try {
            $hash   = $this->request->param('hash');
            $src    = $this->request->param('src');
            $record = UrlScreen::where('hash', $hash)->findOrEmpty();
            if ($record->isEmpty()) {
                return self::jsonAPI();
            }
            $screen = json_decode($record->screen, true);
            if (($key = array_search($src, $screen)) !== false) {
                unset($screen[$key]);
                $record->screen = json_encode($screen);
                $record->save();
            }
            return $this->jsonAPI();
        } catch (Exception $e) {
            CommonUtil::logError($e);
        }
        return $this->jsonAPI([], Code::API_NO);
    }

    /**
     * @return string
     */
    public function urlCacheView()
    {
        try {
            $id     = $this->request->param('id', 0);
            $record = Url::where('id', $id)->findOrEmpty();
            $http   = '';
            if ($record->isEmpty()) {
                $http = '404';
            }
            $cache = (new UrlHttpCache())->getByHash($record->hash, ['field' => 'url_http']);
            if (!empty($cache['url_http'])) {
                $http = json_decode($cache['url_http'], true);
                $http = $http['body'] ?? '';
            }
            if (empty($http)) {
                $http = 'Empty body';
            }
        } catch (Exception $e) {
            $http = $e->getMessage();
        }
        View::assign([
            'http' => $http,
        ]);
        return View::fetch();
    }

    /**
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function file()
    {
        $type = $this->request->param('type', Url::TYPE_NORMAL);
        View::assign([
            'source'   => DictModel::getXmSelect(DictModel::TYPE_SOURCE),
            'category' => DictModel::getXmSelect(DictModel::TYPE_CATEGORY),
            'type'     => $type,
            'menu'     => $this->controller . '_' . $this->action . '_type_' . $type,
        ]);
        return View::fetch();
    }


    /**
     * @return \think\response\Json
     */
    public function fileList()
    {
        try {
            $params = $this->request->param();
            if (!isset($params[CommonUtil::DEL_FIELD])) {
                $params[CommonUtil::DEL_FIELD] = Code::IS_NO;
            }
            [$page, $limit] = $this->pagination();
            [$total, $list] = File::getList($params, $page, $limit);
            return $this->jsonAPI($list, Code::API_YES, ['count' => $total]);
        } catch (Exception $e) {
            CommonUtil::logError($e);
        }
        return $this->jsonAPI([], Code::API_NO);
    }

    /**
     * @return \think\response\Json
     */
    public function fileSave()
    {
        try {
            $params = $this->request->param();
            $file   = $this->request->file('file');
            validate(FileSave::class)->check($params);
            $srcMonth = 'file/' . date('Ym');
            $fileName = $file->getOriginalName();
            $fileName = str_replace([' '], ['_'], $fileName);
            $rootDir  = Filesystem::getDiskConfig('intel_file', 'root');
            $rootDir  = strval($rootDir);
            $dirPath  = sprintf('%s', $srcMonth);
            $filePath = sprintf('%s/%s/%s', $rootDir, $dirPath, $fileName);
            $isRename = false;
            if (file_exists($filePath)) {
                $fileName = sprintf('%s_%s', CommonUtil::getRandomString(), $fileName);
                $filePath = sprintf('%s/%s/%s', $rootDir, $dirPath, $fileName);
                $isRename = true;
            }
            $src = Filesystem::disk('intel_file')->putFileAs($dirPath, $file, $fileName);
            if ($src === false) {
                return self::jsonAPI([], Code::API_NO, lang('err.upload'));
            }
            $record              = File::find($params['id']);
            $params              = File::formatSave($params);
            $params['hash']      = $file->hash('md5');
            $params['path']      = $filePath;
            $params['name']      = $fileName;
            $params['is_rename'] = intval($isRename);
            if (empty($record)) {
                File::create($params);
            } else {
                $id = $params['id'];
                unset($params['id']);
                File::update($params, ['id' => $id]);
            }
            return self::jsonAPI($params);
        } catch (Exception $e) {
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * @return \think\response\Json
     */
    public function fileDesc()
    {
        try {
            $id    = $this->request->param('id', 0);
            $msg   = $this->request->param('msg', '');
            $where = [
                'r_id' => $id,
                'type' => Desc::TYPE_FILE
            ];
            if (empty($id)) {
                return self::jsonAPI([], Code::API_NO, lang('err.params'));
            }
            $data   = ['r_id' => $id, 'type' => Desc::TYPE_FILE, 'content' => $msg];
            $record = Desc::where($where)->findOrEmpty();
            if ($record->isEmpty()) {
                Desc::create($data);
            } else {
                Desc::update($data, ['id' => $record->id]);
            }
            return self::jsonAPI([]);
        } catch (Exception $e) {
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * @return \think\response\Json
     */
    public function fileDel()
    {
        try {
            $ids = [];
            $id  = $this->request->param('id', 0);
            if (!empty($id)) {
                $ids[] = $id;
            }
            $batches = $this->request->param('ids', '');
            if (!empty($batches)) {
                $ids = array_merge($ids, explode(',', $batches));
            }
            $ids = array_unique($ids);
            if (empty($ids)) {
                return self::jsonAPI([], Code::API_NO, lang('need.ids'));
            }
            $res = File::where('id', 'in', $ids)->save([CommonUtil::DEL_FIELD => Code::IS_YES]);
            return self::jsonAPI([$res]);
        } catch (Exception $e) {
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * @return string
     */
    public function fileDetail()
    {
        try {
            $id     = $this->request->param('id', 0);
            $record = File::findOrEmpty($id);
            $record = File::formatCommon($record);
            $desc   = Desc::get($id, Desc::TYPE_FILE);
            View::assign([
                'id'     => $id,
                'record' => $record,
                'desc'   => $desc['content'] ?? '',
            ]);
        } catch (Exception $e) {
            View::assign('err', $e->getMessage());
        }
        return View::fetch();
    }

    /**
     * @return string|\think\response\File|\think\response\Json
     */
    public function fileExport()
    {
        try {
            $params = $this->request->param();
            if (!isset($params[CommonUtil::DEL_FIELD])) {
                $params[CommonUtil::DEL_FIELD] = Code::IS_NO;
            }
            $ids         = $this->request->param('ids', '');
            $checkParams = $params;
            unset($checkParams['type']);
            unset($checkParams[CommonUtil::DEL_FIELD]);
            if (empty($checkParams) && empty($ids)) {
                return self::jsonAPI([], Code::API_NO, lang('need.ids'));
            }
            if (!empty($ids)) {
                $ids   = explode(',', $ids);
                $model = File::getCommonModel();
                $model = $model->where('id', 'in', $ids);
            } else {
                $searchParam = File::getSearchParams($params);
                $model       = File::getCommonModel($searchParam);
            }
            $totalModel = clone $model;
            $query      = $totalModel->field('max(id) as end, min(id) as start')->select()->toArray();
            if (empty($query)) {
                View::assign(['error' => lang('err.no_data')]);
                return View::fetch('/error/error');
            }
            $start = $query[0]['start'];
            $end   = $query[0]['end'];
            $step  = 1000;
            $files = [];
            for ($i = $start; $i <= $end; $i += $step) {
                $m    = clone $model;
                $list = $m->where('id', 'between', [$i, $i + $step])->select()->toArray();
                foreach ($list as $item) {
                    if ($item[CommonUtil::DEL_FIELD]) {
                        continue;
                    }
                    $files[] = $item;
                }
            }
            if (count($files) == 1) {
                $item = reset($files);
                $name = $item['name'];
                if ($item['is_rename']) {
                    $name = CommonUtil::getRealFileName($name);
                }
                return download($item['path'], $name)->force(true);
            }
            $fileName = sprintf('%s.zip', date('YmdHis'));
            $filePath = runtime_path('temp') . $fileName;
            $zip      = new ZipArchive();
            if ($zip->open($filePath, ZipArchive::CREATE) !== true) {
                View::assign(['error' => lang('err.create_zip')]);
                return View::fetch('/error/error');
            }
            foreach ($files as $item) {
                $name = $item['name'];
                if ($item['is_rename']) {
                    $name = CommonUtil::getRealFileName($name);
                }
                $zip->addFile($item['path'], $name);
            }
            $zip->close();
            unset($files);
            return download($filePath, $fileName)->force(true);
        } catch (Exception $e) {
            View::assign(['error' => $e->getMessage()]);
            return View::fetch('/error/error');
        }
    }
}
