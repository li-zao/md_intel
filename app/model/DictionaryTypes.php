<?php

namespace app\model;

use think\Model;

class DictionaryTypes extends Model
{
    // 主键
    protected $pk = 'id';

    /**
     * @param string $type
     * @return array
     */
    public static function getDict($type = '')
    {
        $key = self::getCacheKey($type);
        $cache = Dictionary::getCache($key);
        if (!empty($cache)) {
            return $cache;
        }
        $model = new self();
        if (!empty($type)) {
            $model = $model->where('type', $type);
        }
        $res = $model->order('id', 'desc')->column('name', 'type');
        Dictionary::setCache($key, $res);
        return $res;
    }

    /**
     * @param $type
     * @return string
     */
    public static function getCacheKey($type = '')
    {
        return 'types_' . $type;
    }
    /**
     * @param $type
     * @return bool
     */
    public static function delCache($type = '')
    {
        return Dictionary::delCache($type);
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
        self::delCache(self::getCacheKey());
        if (!empty($data['id'])) {
            self::update($data, ['id' => $data['id']]);
            return $data['id'];
        } else {
            return self::add($data);
        }
    }
}
