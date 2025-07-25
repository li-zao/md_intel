<?php
declare (strict_types=1);

namespace app\model;

use think\db\Raw;
use think\Model;

/**
 * @mixin \think\Model
 */
class UrlTestRows extends Model
{

    /**
     * @param $searchParam
     * @return int
     * @throws \think\db\exception\DbException
     */
    public static function getAllCount($searchParam = [])
    {
        $infos = self::getCommonModel($searchParam);
        return $infos->count();
    }

    /**
     * @param $searchParam
     * @param $curPage
     * @param $limit
     * @param $params
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getAllInfos($searchParam = [], $curPage = 1, $limit = 10, $params = [])
    {
        $fields = $params['field'] ?? '*';
        // 不同查询条件时对应不同排序
        $order = ['id desc'];
        foreach ($searchParam as $key => $value) {
            if (isset(Url::FIELD_ORDER_DICT[$key])) {
                array_unshift($order, Url::FIELD_ORDER_DICT[$key]);
                break;
            }
        }
        $order = new Raw(implode(',', $order));
        $infos = self::getCommonModel($searchParam)->field($fields);
        return $infos->order($order)->page($curPage, $limit)->select()->toArray();
    }

    /**
     * @param $searchParam
     * @return UrlTestRows
     */
    public static function getCommonModel($searchParam = [])
    {
        $infos = self::where(true);
        foreach ($searchParam as $dbField => $searchInfo) {
            if (strpos($dbField, CommonUtil::MULTI_SEPARATOR)) {
                list($dbField, $relation) = explode(CommonUtil::MULTI_SEPARATOR, $dbField);
                if ($relation == 'whereOr') {
                    $infos->where(function ($infos) use ($searchInfo) {
                        foreach ($searchInfo as $key => $info) {
                            [$f, $w, $v] = CommonUtil::getSearchData($key, $info);
                            if ($w == 'exp') {
                                $infos->whereOrRaw($v);
                                continue;
                            }
                            $infos->whereOr($f, $w, $v);
                        }
                    });
                    continue;
                }
            }
            [$field, $with, $value] = CommonUtil::getSearchData($dbField, $searchInfo);
            if ($with == 'exp') {
                $infos->whereRaw($value);
            } else {
                $infos->where($field, $with, $value);
            }
        }
        return $infos;
    }

    /**
     * @param $params
     * @return array|mixed
     */
    public static function getSearchParams($params = [])
    {
        $searchParam     = [];
        $equalFields     = [
            'id'   => 'id',
            't_id' => 't_id',
            // 'source'   => 'source',
            // 'category' => 'category',
        ];
        $zeroFields      = [
            // 'type' => 'type',
            // 'is_xinchuang' => 'pf.is_xinchuang',
        ];
        $likeFields      = [
            // 'name' => ['%|%', 'name'],
            // 'name'        => ['%|%', 'pf.display_name'],
            // 'main_domain' => ['%|%', 'pf.main_domain'],
        ];
        $timeFields      = [
            // 'auth_start'    => ['pf.auth_start', Code::OPERATOR_GT],
            // 'auth_end'      => ['pf.auth_end', Code::OPERATOR_LT],
            // 'service_start' => ['pf.service_start', Code::OPERATOR_GT],
            // 'service_end'   => ['pf.service_end', Code::OPERATOR_LT],
        ];
        $findInSetFields = [
        ];
        $typeField       = [
            CommonUtil::SEARCH_FIELD_TYPE_EQUAL => $equalFields,
            CommonUtil::SEARCH_FIELD_TYPE_ZERO  => $zeroFields,
            CommonUtil::SEARCH_FIELD_TYPE_LIKE  => $likeFields,
            CommonUtil::SEARCH_FIELD_TYPE_TIME  => $timeFields,
            CommonUtil::SEARCH_FIELD_TYPE_FIND  => $findInSetFields,
        ];
        foreach ($typeField as $type => $fields) {
            $searchParam = CommonUtil::getFieldsSearch(
                $type,
                $fields,
                $searchParam,
                $params
            );
        }
        return $searchParam;
    }

    /**
     * @param array $params
     * @param int $page
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getList($params = [], $page = 1, $limit = 10)
    {
        $searchParam = self::getSearchParams($params);
        $total       = self::getAllCount($searchParam);
        if (empty($total)) {
            return [0, []];
        }
        $list = self::getAllInfos($searchParam, $page, $limit, $params);
        $list = self::formatList($list);
        return [$total, $list];
    }

    /**
     * @param $data
     * @return mixed
     */
    public static function formatSave($data)
    {
        if (empty($data['hash'])) {
            $data['hash'] = '';
        }
        return $data;
    }

    /**
     * 格式化 列表 和 详情页面 共同部分 字段
     * @param $data
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function formatCommon($data)
    {
        $formatFields = [
            // 'source'   => Dictionary::getDict(Dictionary::TYPE_SOURCE),
            // 'category' => Dictionary::getDict(Dictionary::TYPE_CATEGORY),
        ];
        foreach ($formatFields as $field => $dict) {
            if (!empty($data[$field]) && is_array($data[$field])) {
                continue;
            }
            $data[$field]          = Dictionary::formatSelected(CommonUtil::explodeStr($data[$field] ?? ''), $dict);
            $data[$field . '_str'] = implode(',', array_column($data[$field], 'name'));
        }
        if (empty($data['url'])) {
            $_res = Url::field('url')->where('id', $data['url_id'])->find();
            $data = array_merge($data, $_res->toArray());
        }
        return $data;
    }

    /**
     * @param array $list
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function formatList($list = [])
    {
        $ids = array_column($list, 'url_id');
        $urls = Url::field('id, url')->where('id', 'in', $ids)->select();
        if (!$urls->isEmpty()) {
            $urls = $urls->toArray();
            $urls = array_column($urls, null, 'id');
        }
        foreach ($list as &$item) {
            $item['url'] = $urls[$item['url_id']]['url'] ?? '';
            $item = self::formatCommon($item);
        }
        unset($item);
        return $list;
    }
}
