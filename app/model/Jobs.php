<?php
declare (strict_types=1);

namespace app\model;

use app\common\Code;
use think\db\Raw;
use think\Model;

/**
 * @mixin \think\Model
 */
class Jobs extends Model
{
    public const QUEUE_VECTOR = 'url_vector';
    public const QUEUE_URL_IMPORT = 'url_import';
    public const QUEUE_URL_2_VECTOR = 'url_2_vector';
    public const QUEUE_URL_2_TRAIN = 'url_train';
    public const QUEUE_URL_TEST = 'url_test';
    public const QUEUE_URL_GET_TEST = 'url_get_test';
    public const QUEUE_URL_TEST_STATISTIC = 'url_test_statistic';
    public const QUEUE_URL_SNAPSHOT = 'url_snapshot';
    public const ALL_QUEUE = [
        // self::QUEUE_VECTOR,
        // self::QUEUE_URL_IMPORT,
        // self::QUEUE_URL_2_VECTOR,
        // self::QUEUE_URL_2_TRAIN,
        self::QUEUE_URL_SNAPSHOT,
        self::QUEUE_URL_TEST,
        self::QUEUE_URL_GET_TEST,
        self::QUEUE_URL_TEST_STATISTIC,
    ];
    public const QUEUE_DICT = [
        self::QUEUE_VECTOR       => '计算URL向量',
        self::QUEUE_URL_IMPORT   => 'URL导入',
        self::QUEUE_URL_2_VECTOR => '生成URL向量文件',
        self::QUEUE_URL_2_TRAIN  => '训练新模型',
        self::QUEUE_URL_SNAPSHOT  => '拉取网关沙箱URL',
    ];
    // 搜索条件对应排序规则
    public const FIELD_ORDER_DICT = [];

    private const VECTOR_QUEUE_THREAT = 10;

    /**
     * 获取向量队列阈值
     * @return mixed
     */
    public static function getVectorQueueThreat()
    {
        return intval(env('queue.VECTOR_MAX', self::VECTOR_QUEUE_THREAT));
    }
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
     * @return Jobs
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
            'id' => 'id',
            'queue' => 'queue',
        ];
        $zeroFields      = [
        ];
        $likeFields      = [
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
        $formatFields = [
        ];
        foreach ($formatFields as $field => $dict) {
            if (!empty($data[$field]) && is_array($data[$field])) {
                continue;
            }
            $data[$field]          = Dictionary::formatSelected(CommonUtil::explodeStr($data[$field] ?? ''), $dict);
            $data[$field . '_str'] = implode(',', array_column($data[$field], 'name'));
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
        foreach ($list as &$item) {
            $item = self::formatCommon($item);
        }
        return $list;
    }
}
