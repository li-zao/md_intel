<?php

namespace app\controller;

use app\common\Code;
use app\BaseController;
use app\model\CommonUtil;
use app\model\Dictionary as DictionaryModel;
use app\model\DictionaryTypes;
use Exception;
use think\facade\View;

class Dictionary extends BaseController
{
    /**
     * 字典管理首页
     * @return string
     */
    public function index()
    {
        $types = DictionaryTypes::getDict();
        View::assign('types', $types);
        return View::fetch();
    }

    /**
     * 字典列表
     * @return \think\response\Json
     */
    public function dicList()
    {
        try {
            $params = $this->request->param();
            [$page, $limit] = $this->pagination();
            [$total, $list] = DictionaryModel::getList($params, $page, $limit);
            return self::jsonAPI($list, Code::API_YES, ['count' => $total]);
        } catch (Exception $e) {
            CommonUtil::logError($e);
            return self::jsonAPI([], Code::API_YES, ['count' => 0]);
        }
    }

    /**
     * @return \think\response\Json
     */
    public function dicUpdate()
    {
        $id     = $this->request->param('id');
        $status = $this->request->param('status');
        $type   = $this->request->param('type', DictionaryModel::TYPE_SOURCE);
        $value  = $this->request->param('value', null, 'trim');
        $key    = $this->request->param('key', '');
        $desc   = $this->request->param('desc', '');
        if (empty($type) || CommonUtil::emptyNonZero($value)) {
            return self::jsonAPI([], Code::API_NO, lang('err.params'));
        }
        try {
            if (empty($key)) {
                $key = DictionaryModel::getNextKey($type);
            }
            $data   = [
                'id'     => $id,
                'type'   => $type,
                'value'  => $value,
                'key'    => $key,
                'status' => !empty($status) ? Code::IS_YES : Code::IS_NO,
                'desc'   => $desc,
            ];
            // 数据是否已存在检测
            $record = DictionaryModel::where(['type' => $type, 'value' => $value, 'status' => $data['status']])
                ->findOrEmpty();
            if ($record->isExists() && $data['status'] != Code::IS_NO && $record->id != $id) {
                throw new Exception(lang('err.exists'));
            }
            $id = DictionaryModel::updateItem($data);
            return self::jsonAPI(['id' => $id]);
        } catch (Exception $e) {
            CommonUtil::logError($e);
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * @return \think\response\Json
     */
    public function updateType()
    {
        $id   = $this->request->param('id');
        $type = $this->request->param('type');
        $name = $this->request->param('name');
        if (empty($type) || empty($name)) {
            return self::jsonAPI([], Code::API_NO, lang('err.params'));
        }
        $data = [
            'type' => $type,
            'name' => $name,
        ];
        try {
            if (!empty($id)) {
                $data['id'] = $id;
            }
            $record = DictionaryTypes::where('type', $type)->find();
            if (!empty($record->id)) {
                unset($data['type']);
            }
            $id = DictionaryTypes::updateItem($data);
            return self::jsonAPI(['id' => $id]);
        } catch (Exception $e) {
            CommonUtil::logError($e);
            return self::jsonAPI([], Code::API_NO, lang('err.system'));
        }
    }

    /**
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getNextKey()
    {
        $type = $this->request->param('type');
        if (empty($type)) {
            return self::jsonAPI(['key' => 1]);
        }
        return self::jsonAPI(['key' => DictionaryModel::getNextKey($type)]);
    }
}
