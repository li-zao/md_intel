<?php

namespace app\model;

use think\Model;

class Desc extends Model
{
    public const TYPE_URL = 1;
    public const TYPE_FILE = 2;

    /**
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getAllCount()
    {
        return self::select()->count();
    }

    /**
     * @param $curPage
     * @param $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getAllInfos($curPage, $limit)
    {
        return self::page($curPage, $limit)->select()->toArray();
    }

    /**
     * @param $id
     * @param $type
     * @return array|mixed|string
     */
    public static function get($id, $type = self::TYPE_URL)
    {
        $record = self::where('r_id', $id)->where('type', $type)->findOrEmpty();
        if ($record->isEmpty()) {
            return [];
        }
        return self::formatShow($record->toArray());
    }
    /**
     * @param $data
     * @return mixed|string
     */
    public static function formatSave($data = [])
    {
        if (!empty($data['content'])) {
            $data['content'] = htmlspecialchars($data['content']);
        }
        return $data;
    }

    /**
     * @param $data
     * @return mixed|string
     */
    public static function formatShow($data = [])
    {
        if (!empty($data['content'])) {
            $data['content'] = htmlspecialchars_decode($data['content']);
        }
        return $data;
    }

    /**
     * @param $data
     * @param $update
     * @return array
     */
    public static function saveOrUpdate($data, $update = true)
    {
        $data = self::formatSave($data);
        $record = self::where('r_id', $data['r_id'])->where('type', $data['type'])->findOrEmpty();
        if ($record->isEmpty()) {
            $record = new self();
        }
        if ($update) {
            $data['content'] = $data['content'] . " | " . $record->content;
        }
        $record->save($data);
        return $record->toArray();
    }
}