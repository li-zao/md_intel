<?php

namespace app\model;

use think\db\Raw;
use think\facade\Cache;
use think\Model;

class Dictionary extends Model
{
    // 主键
    protected $pk = 'id';
    public const FIELD_STATUS = 'status';
    public const STATUS_ENABLE  = 1;
    public const STATUS_DISABLE = 0;
    public const STATUS_DICT    = [
        self::STATUS_ENABLE  => '启用',
        self::STATUS_DISABLE => '禁用'
    ];

    const TYPE_SOURCE = 'source';
    const TYPE_CATEGORY = 'category';
    public const TYPE_ORDER_DICT = [];

    /**
     * @param $params
     * @param int $page
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getList($params, $page = 1, $limit = 10)
    {
        $model = new self();
        $type  = $params['type'] ?? '';
        $val   = $params['value'] ?? '';
        $order = $params['order'] ?? '';
        $orderType = $params['order_type'] ?? '';
        $val = trim($val);
        $defaultOrder = ['type' => 'desc', 'id' => 'desc'];
        if (!empty($order)) {
            $defaultOrder = [$order => $orderType ?: 'asc'];
        }
        if (!empty($type)) {
            $model = $model->where('type', $type);
        }
        if (!empty($val)) {
            $model = $model->where('value', 'like', '%' . $val . '%');
        }
        $total    = $model->count();
        $list     = $model->order($defaultOrder)->page($page, $limit)->select()->toArray();
        $typeDict = DictionaryTypes::getDict();
        foreach ($list as &$item) {
            $item['status_str'] = self::STATUS_DICT[$item['status']] ?? '';
            $item['type_str'] = $typeDict[$item['type']] ?? '';
        }
        return [$total, $list];
    }

    /**
     * @param $type
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getXmSelect($type = '')
    {
        $dict = self::getDict($type);
        return self::formatSelect($dict);
    }
    /**
     * @param string $type
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getDict($type = '')
    {
        $cache = self::getCache($type);
        if (!empty($cache)) {
            return $cache;
        }
        $model = self::where(self::FIELD_STATUS, self::STATUS_ENABLE);
        if (!empty($type)) {
            $model = $model->where('type', $type);
        }
        // 支持自定义字典列表排序
        $order = 'id desc';
        if (isset(self::TYPE_ORDER_DICT[$type])) {
            $order = self::TYPE_ORDER_DICT[$type];
        }
        $list = $model->order(new Raw($order))
            ->select()
            ->toArray();
        $res = array_column($list, 'value', 'key');
        self::setCache($type, $res);
        return $res;
    }

    /**
     * @param null $type
     * @return array|mixed
     */
    public static function getCache($type = null)
    {
        if (empty($type)) {
            return [];
        }
        return Cache::get($type);
    }

    /**
     * @param $type
     * @param $data
     * @return bool
     */
    public static function setCache($type, $data)
    {
        if (empty($type) || empty($data)) {
            return false;
        }
        return Cache::set($type, $data);
    }

    /**
     * @param $type
     * @return bool
     */
    public static function delCache($type)
    {
        if (empty($type)) {
            return false;
        }
        return Cache::delete($type);
    }

    /**
     * @param $data
     * @return int|string
     */
    public static function add($data)
    {
        return self::insertGetId($data);
    }

    /**
     * @param array $data
     * @return Dictionary|int|string
     */
    public static function updateItem($data = [])
    {
        if (!empty($data['type'])) {
            self::delCache($data['type']);
        }
        if (!empty($data['id'])) {
            self::update($data, ['id' => $data['id']]);
            return $data['id'];
        } else {
            return self::add($data);
        }
    }

    /**
     * 格式化多选框数据
     * @param array $data
     * @return array
     */
    public static function formatSelect($data = [])
    {
        $res = [];
        foreach ($data as $key => $value) {
            $res[] = ['name' => $value, 'value' => $key];
        }
        return $res;
    }

    /**
     * @param array $data
     * @param array $dict
     * @return array
     */
    public static function formatSelected($data = [], $dict = [])
    {
        $mSelect = [];
        foreach ($data as $key) {
            $mSelect[] = ['name' => $dict[$key] ?? $key, 'value' => $key, 'selected' => true];
        }
        return $mSelect;
    }

    /**
     * @param $type
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getTypeSelect($type = '')
    {
        if (empty($type)) {
            return [];
        }
        $dict = self::getDict($type);
        return self::formatSelect($dict);
    }

    /**
     * @param $select
     * @param $default
     * @return array|mixed
     */
    public static function setSelectDefault($select = [], $default = null)
    {
        if (is_null($default)) {
            return $select;
        }
        foreach ($select as &$item) {
            if ($item['value'] == $default) {
                $item['selected'] = true;
                break;
            }
        }
        return $select;
    }

    /**
     * @param $type
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getNextKey($type)
    {
        $record = self::where('type', $type)->order('id', 'desc')->find();
        return intval($record['key'] ?? 0) + 1;
    }
}
