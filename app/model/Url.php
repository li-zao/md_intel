<?php
declare (strict_types=1);

namespace app\model;

use app\common\Code;
use app\util\UrlNN;
use think\db\Raw;
use think\facade\Db;
use think\Model;

/**
 * @mixin \think\Model
 */
class Url extends Model
{
    public const TYPE_NORMAL = 0;
    public const TYPE_MALICIOUS = 1;
    public const TYPE_DICT = [
        self::TYPE_NORMAL    => '正常',
        self::TYPE_MALICIOUS => '恶意',
    ];
    public const SET_TRAIN = 1;
    public const SET_PREDICT = 2;
    public const SET_DICT = [
        self::SET_TRAIN   => '训练',
        self::SET_PREDICT => '预测',
    ];

    // 搜索条件对应排序规则
    public const FIELD_ORDER_DICT = [];

    public const TYPE_WHERE_DICT = [
        self::TYPE_NORMAL    => ' type = ' . self::TYPE_NORMAL,
        self::TYPE_MALICIOUS => ' type = ' . self::TYPE_MALICIOUS,
    ];

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
            if (isset(self::FIELD_ORDER_DICT[$key])) {
                array_unshift($order, self::FIELD_ORDER_DICT[$key]);
                break;
            }
        }
        $order = new Raw(implode(',', $order));
        $infos = self::getCommonModel($searchParam)->field($fields);
        return $infos->order($order)->page($curPage, $limit)->select()->toArray();
    }

    /**
     * @param $searchParam
     * @return Url
     */
    public static function getCommonModel($searchParam = [])
    {
        $infos = self::where(CommonUtil::DEL_FIELD, Code::IS_NO);
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
            'id'       => 'id',
            'domain'   => 'domain',
            'hash'     => 'hash',
            'source'   => 'source',
            'category' => 'category',
            'set'      => 'set',
        ];
        $zeroFields      = [
            'type'    => 'type',
            'predict' => 'predict',
            // 'is_xinchuang' => 'pf.is_xinchuang',
        ];
        $likeFields      = [
            'url' => ['|%', 'url'],
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
        if (empty($data['domain'])) {
            $data['domain'] = CommonUtil::get1stDomain($data['url']);
        }
        if (empty($data['hash'])) {
            $data['hash'] = CommonUtil::getUrlHash($data['url']);
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
        $multipleFields = [
            'source'   => Dictionary::getDict(Dictionary::TYPE_SOURCE),
            'category' => Dictionary::getDict(Dictionary::TYPE_CATEGORY),
        ];
        $dictFields     = [
            'set'     => self::SET_DICT,
            'predict' => UrlNN::URL_PREDICT_DICT,
        ];
        foreach ($dictFields as $field => $dict) {
            $data[$field . '_str'] = $dict[$data[$field]] ?? $data[$field];
        }
        foreach ($multipleFields as $field => $dict) {
            if (!empty($data[$field]) && is_array($data[$field])) {
                continue;
            }
            $data[$field . '_d']   = Dictionary::formatSelected(CommonUtil::explodeStr($data[$field] ?? ''), $dict);
            $data[$field . '_str'] = implode(',', array_column($data[$field . "_d"], 'name'));
        }
        $data['clean_url'] = CommonUtil::formatUrl($data['url'], ['clean' => Code::IS_YES, 'decode' => Code::IS_YES]);
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
        // 匹配对应url评论
        $rIds = array_column($list, 'id');
        $desc = Desc::where('type', Desc::TYPE_URL)->where('r_id', 'in', $rIds)->select();
        if (!$desc->isEmpty()) {
            $desc = array_column($desc->toArray(), null, 'r_id');
        } else {
            $desc = [];
        }
        foreach ($list as &$item) {
            $item         = self::formatCommon($item);
            $item['desc'] = '';
            if (isset($desc[$item['id']])) {
                $item['desc'] = $desc[$item['id']]['content'];
            }
        }
        return $list;
    }

    /**
     * @param $type
     * @param $searchParam
     * @return int
     * @throws \think\db\exception\DbException
     */
    public static function getIndexCount($type, $searchParam = [])
    {
        if (!isset(self::TYPE_WHERE_DICT[$type])) {
            return 0;
        }
        $where = self::TYPE_WHERE_DICT[$type];
        $where .= sprintf(' AND %s=%s', CommonUtil::DEL_FIELD, Code::IS_NO);
        $infos = Db::table('url')->alias('u');
        $infos = $infos->whereRaw($where);
        foreach ($searchParam as $dbField => $searchInfo) {
            if (strpos($dbField, CommonUtil::MULTI_SEPARATOR)) {
                list($dbField,) = explode(CommonUtil::MULTI_SEPARATOR, $dbField);
            }
            [$field, $with, $value] = CommonUtil::getSearchData($dbField, $searchInfo);
            if ($with == 'exp') {
                $infos->whereRaw($value);
            } else {
                $infos->where($field, $with, $value);
            }
        }
        return $infos->count();
    }
}
