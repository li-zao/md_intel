<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\common\Code;
use app\model\CommonUtil;
use app\model\UrlScreen;
use Exception;
use think\facade\Filesystem;
use think\Response;

class File extends BaseController
{
    /**
     * 文件上传
     * THINKPHP
     * @return \think\response\Json
     */
    public function docFile()
    {
        try {
            $bucket = $this->request->get('bucket', 'url');
            $file   = $this->request->file('file');
            if (empty($file)) {
                return self::jsonAPI([], Code::API_NO, lang('err.params'));
            }
            $rename   = false;
            $srcMonth = strtolower($bucket) . '/' . date('Ym');
            $fileName = htmlspecialchars_decode($file->getOriginalName());
            $filePath = sprintf(
                '%s/%s/%s',
                strval(Filesystem::getDiskConfig('public', 'root')),
                $srcMonth,
                $fileName
            );
            if (file_exists($filePath)) {
                $fileName = sprintf('%s_%s', CommonUtil::getRandomString(), $fileName);
                $rename   = true;
            }
            $src = Filesystem::disk('public')->putFileAs($srcMonth, $file, $fileName);
            if ($src === false) {
                throw new Exception(lang('err.op_fail'));
            }
            $src           = str_replace('\\', '/', $src);
            $host          = $this->request->domain();
            $store         = Filesystem::getDiskConfig('public', 'url');
            $store         = strval($store);
            $fileUrl       = $host . $store . '/' . $src;
            $res           = CommonUtil::getEditorImageData($fileUrl);
            $res['exists'] = Code::IS_YES;
            $res['rename'] = $rename;
            return self::jsonAPI($res);
        } catch (Exception $e) {
            CommonUtil::logError($e);
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * @return Response|\think\response\File
     * @throws Exception
     */
    public function download()
    {
        $path = $this->request->param('path');
        if (!empty($path)) {
            return $this->downloadPath();
        }
        $bucket          = $this->request->param('bucket');
        $id              = $this->request->param('id');
        $bucketModelDict = [
            'url' => new UrlScreen(),
        ];
        if (empty($bucket) || empty($id) || empty($bucketModelDict[$bucket])) {
            throw new Exception(lang('err.params'));
        }
        $model  = $bucketModelDict[$bucket];
        $record = $model->where('id', $id)->find();
        if (empty($record)) {
            throw new Exception(lang('err.file_404'));
        }
        $name = null;
        if ($record->rename) {
            $name = CommonUtil::getRealFileName(basename($record->src));
        }
        return $this->downloadPath($record->src, $name);
    }

    /**
     * @param $path
     * @param $name
     * @return Response|\think\response\File
     */
    public function downloadPath($path = '', $name = '')
    {
        if (empty($path)) {
            $path = $this->request->param('path');
        }
        $path = CommonUtil::formatPath($path);
        try {
            if (empty($path)) {
                throw new Exception(lang('err.params'));
            }
            $file = CommonUtil::url2Path($path);
            if (is_file($file)) {
                if (empty($name)) {
                    $fileNames = explode('/', $file);
                    $fileName  = array_pop($fileNames);
                    $name      = $fileName ?: basename($path);
                }
                return download($file, $name);
            }
            throw new Exception(lang('err.file_404'));
        } catch (Exception $e) {
            CommonUtil::logError($e);
            return Response::create($e->getMessage(), 'html', 404);
        }
    }
}
