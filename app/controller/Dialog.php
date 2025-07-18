<?php

namespace app\controller;

use app\model\CommonUtil;
use app\BaseController;
use app\model\Desc;
use app\model\DictionaryTypes;
use app\model\File;
use app\model\Dictionary as DictModel;
use app\model\Url;
use app\model\UrlTestRows;
use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use think\facade\Filesystem;
use think\facade\View;

class Dialog extends BaseController
{

    /**
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function editUrl()
    {
        $id     = $this->request->param('id', 0);
        $params = $this->request->param();
        $record = [
            'id'       => $id,
            'source'   => 0,
            'category' => 0,
        ];
        if (!empty($id)) {
            $record = Url::find($id);
        }
        View::assign([
            'source'   => DictModel::getXmSelect(DictModel::TYPE_SOURCE),
            'category' => DictModel::getXmSelect(DictModel::TYPE_CATEGORY),
            'params'   => $params,
            'record'   => $record,
        ]);
        return View::fetch('dialog/url');
    }

    /**
     * @return string
     */
    public function getUrlDesc()
    {
        $id     = $this->request->param('id', 0);
        $record = Desc::where(['r_id' => $id, 'type' => Desc::TYPE_URL])->findOrEmpty();
        if ($record->isEmpty()) {
            $record = [];
        }
        View::assign([
            'id'   => $id,
            'desc' => $record['content'] ?? '',
        ]);
        return View::fetch('dialog/url_desc');
    }

    /**
     * @return string
     */
    public function urlTrain()
    {
        $type         = $this->request->param('type', Url::TYPE_NORMAL);
        $set          = $this->request->param('type', Url::SET_TRAIN);
        $files        = [];
        $rootDir      = Filesystem::getDiskConfig('intel_file', 'root');
        $rootDir      = strval($rootDir);
        $dirPath      = sprintf('%s/vector/', $rootDir);
        $getFileLines = function ($path) {
            $count  = 0;
            $handle = fopen($path, "r");
            while (!feof($handle)) {
                $line  = fgets($handle, 4096);
                $count = $count + substr_count($line, PHP_EOL);
            }
            fclose($handle);
            return $count;
        };
        // 扫描文件，组建文件名和路径数组
        if (is_dir($dirPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDir()) {
                    continue;
                }

                $relativePath = $fileInfo->getPathName(); // 保留子目录结构
                $files[]      = [
                    'name'  => $relativePath,
                    'path'  => $fileInfo->getRealPath(),
                    'lines' => $getFileLines($fileInfo->getRealPath())
                ];
            }
        }
        View::assign([
            'files' => $files,
            'type'  => $type,
            'set'   => $set,
        ]);
        return View::fetch('dialog/url_train');
    }

    /**
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function editFile()
    {
        $id     = $this->request->param('id', 0);
        $params = $this->request->param();
        $record = [
            'id'       => $id,
            'source'   => 0,
            'category' => 0,
        ];
        if (!empty($id)) {
            $record = File::find($id);
        }
        View::assign([
            'params'   => $params,
            'record'   => $record,
            'source'   => DictModel::getXmSelect(DictModel::TYPE_SOURCE),
            'category' => DictModel::getXmSelect(DictModel::TYPE_CATEGORY),
        ]);
        return View::fetch('dialog/file');
    }

    /**
     * @return string
     */
    public function getFileDesc()
    {
        $id     = $this->request->param('id', 0);
        $record = Desc::where(['r_id' => $id, 'type' => Desc::TYPE_FILE])->findOrEmpty();
        if ($record->isEmpty()) {
            $record = [];
        }
        View::assign([
            'id'   => $id,
            'desc' => $record['content'] ?? '',
        ]);
        return View::fetch('dialog/file_desc');
    }

    /**
     * 字典
     * @return string
     */
    public function dictionary()
    {
        $info = [];
        try {
            $id = $this->request->param('id');
            if (!empty($id)) {
                $info = DictModel::findOrEmpty($id);
            }
            View::assign('info', $info);
            View::assign('types', DictionaryTypes::getDict());
        } catch (Exception $e) {
            CommonUtil::logError($e);
        }
        return View::fetch();
    }

    /**
     * 字典类型
     * @return string
     */
    public function dictionaryType()
    {
        $info = [];
        try {
            $id = $this->request->param('id');
            if (!empty($id)) {
                $info = DictionaryTypes::findOrEmpty($id);
            }
        } catch (Exception $e) {
            CommonUtil::logError($e);
        }
        View::assign('info', $info);
        return View::fetch();
    }

    /**
     * @return string|void
     */
    public function showFile()
    {
        try {
            $id       = $this->request->param('id', 0);
            $record   = File::find($id);
            $filePath = $record->path;
            if ($record->isEmpty() || !file_exists($filePath)) {
                return 'File not found';
            }
            $contentType = mime_content_type($filePath);
            // 限只读取图片
            if (strpos($contentType, 'image') === false) {
                return 'File type not support';
            }
            header('Content-type:' . $contentType);
            readfile($filePath);
        } catch (Exception $e) {
            return 'Err:' . $e->getMessage();
        }
    }

    /**
     * @return string
     */
    public function filePreview()
    {
        try {
            $id             = $this->request->param('id', 0);
            $record         = File::find($id);
            $showImageClass = $showTextClass = $showHexClass = 'layui-hide';
            $content        = '';
            $filePath       = $record->path;
            if ($record->isEmpty() || !file_exists($filePath)) {
                $content       = lang('err.file_404');
                $showTextClass = '';
            }
            // 只展示图片和纯文本文件
            $contentType = mime_content_type($filePath);
            if (strpos($contentType, 'image') !== false) {
                $showImageClass = '';
            } else if (strpos($contentType, 'text') !== false) {
                // 获取文件前200行
                $content       = CommonUtil::getFileLines($filePath, 200);
                $showTextClass = '';
            } else if (file_exists($filePath)) {
                $content      = bin2hex(CommonUtil::getFileContent($filePath, 1000));
                $showHexClass = '';
            }
            View::assign([
                'id'             => $id,
                'content'        => $content,
                'showImageClass' => $showImageClass,
                'showTextClass'  => $showTextClass,
                'showHexClass'   => $showHexClass,
            ]);
            return View::fetch();
        } catch (Exception $e) {
            return 'Err:' . $e->getMessage();
        }
    }

    /**
     * @return string
     */
    public function urlTestRows()
    {
        try {
            $id = $this->request->param('t_id', 0);
            View::assign([
                'id' => $id,
            ]);
            return View::fetch();
        } catch (Exception $e) {
            return 'Err:' . $e->getMessage();
        }
    }

    /**
     * @return string
     */
    public function urlTestRow()
    {
        try {
            $id     = $this->request->param('id', 0);
            $record = UrlTestRows::find($id);
            if ($record->isEmpty()) {
                return 'Record not found';
            }
            $record = $record->toArray();
            $record = UrlTestRows::formatCommon($record);
            View::assign([
                'record' => json_encode($record),
                'id'     => $id,
            ]);
            return View::fetch();
        } catch (Exception $e) {
            return 'Err:' . $e->getMessage();
        }
    }
}
