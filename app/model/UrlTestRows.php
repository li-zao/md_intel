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

    public const CATEGORY_VIRUS               = 1;    //病毒
    public const CATEGORY_RULE_BLOCKING       = 2;    //规则阻断
    public const CATEGORY_INTERMEDIATE_THREAT = 3;    //中级威胁
    public const CATEGORY_HIGHER_THREAT       = 4;    //较高威胁
    public const CATEGORY_SERIOUS_THREAT      = 5;    //严重威胁
    public const CATEGORY_PHISH               = 6;    //钓鱼
    public const CATEGORY_PORNOGRAPHIC        = 7;    //色情
    public const CATEGORY_FRAUDULENT          = 8;    //欺诈
    public const CATEGORY_AD                  = 9;    //广告
    public const CATEGORY_SPAM                = 10;   //垃圾
    public const CATEGORY_SENSITIVE           = 11;   //敏感
    public const CATEGORY_BLACKLIST           = 12;   //黑名单
    public const CATEGORY_WHITELIST           = 18;   //白名单
    public const CATEGORY_ATTACH_ENCRYPTION   = 19;   //附件加密
    public const CATEGORY_ATTACH_CAMOUFLAGE   = 20;   //附件伪装
    public const CATEGORY_TIMEOUT_RELEASE     = 22;   //超时放行
    public const CATEGORY_TIMEOUT_INTERCEPT   = 25;   //超时拦截
    public const CATEGORY_NORMAL              = 27;   //正常
    public const CATEGORY_DYNAMIC_CLOUD       = 29;   //云分析拦截
    public const CATEGORY_BEHAVIOR_ANALYSIS   = 30;   //行为分析
    public const CATEGORY_MALICIOUS           = 32;   //恶意
    public const CATEGORY_SUBSCRIBE           = 33;   //订阅
    public const CATEGORY_BLOCK               = 34;   //阻断
    public const CATEGORY_CALUMNY             = 35;   //恶论
    public const CATEGORY_GAMBLING            = 36;   //博彩
    public const CATEGORY_ATTACK              = 40;   //攻击
    public const CATEGORY_DELETE              = 50;   //删除
    public const CATEGORY_INVALID             = 51;   //失效
    public const CATEGORY_DICT                = [
        self::CATEGORY_VIRUS               => '病毒',
        self::CATEGORY_RULE_BLOCKING       => '规则阻断',
        self::CATEGORY_INTERMEDIATE_THREAT => '中级威胁',
        self::CATEGORY_HIGHER_THREAT       => '较高威胁',
        self::CATEGORY_SERIOUS_THREAT      => '严重威胁',
        self::CATEGORY_PHISH               => '钓鱼',
        self::CATEGORY_PORNOGRAPHIC        => '色情',
        self::CATEGORY_FRAUDULENT          => '欺诈',
        self::CATEGORY_AD                  => '广告',
        self::CATEGORY_SPAM                => '垃圾',
        self::CATEGORY_SENSITIVE           => '敏感',
        self::CATEGORY_BLACKLIST           => '黑名单',
        self::CATEGORY_WHITELIST           => '白名单',
        self::CATEGORY_ATTACH_ENCRYPTION   => '附件加密',
        self::CATEGORY_ATTACH_CAMOUFLAGE   => '附件伪装',
        self::CATEGORY_TIMEOUT_RELEASE     => '超时放行',
        self::CATEGORY_TIMEOUT_INTERCEPT   => '超时拦截',
        self::CATEGORY_NORMAL              => '正常',
        self::CATEGORY_DYNAMIC_CLOUD       => '云分析拦截',
        self::CATEGORY_BEHAVIOR_ANALYSIS   => '行为分析',
        self::CATEGORY_MALICIOUS           => '恶意',
        self::CATEGORY_SUBSCRIBE           => '订阅',
        self::CATEGORY_BLOCK               => '阻断',
        self::CATEGORY_CALUMNY             => '恶论',
        self::CATEGORY_GAMBLING            => '博彩',
        self::CATEGORY_ATTACK              => '攻击',
        self::CATEGORY_DELETE              => '删除',
        self::CATEGORY_INVALID             => '失效',
    ];

    /**
     * @param array $params
     * @param int   $page
     * @param int   $limit
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
        $findInSetFields = [];
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
     * @return UrlTestRows
     */
    public static function getCommonModel($searchParam = [])
    {
        $infos = self::where(true);
        foreach ($searchParam as $dbField => $searchInfo) {
            if (strpos($dbField, CommonUtil::MULTI_SEPARATOR)) {
                [$dbField, $relation] = explode(CommonUtil::MULTI_SEPARATOR, $dbField);
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
     * @param array $list
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function formatList($list = [])
    {
        $ids  = array_column($list, 'url_id');
        $urls = Url::field('id, url')->where('id', 'in', $ids)->select();
        if (!$urls->isEmpty()) {
            $urls = $urls->toArray();
            $urls = array_column($urls, null, 'id');
        }
        foreach ($list as &$item) {
            $item['url'] = $urls[$item['url_id']]['url'] ?? '';
            $item        = self::formatCommon($item);
        }
        unset($item);
        return $list;
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
        if (!empty($data['res'])) {
            $res                  = json_decode($data['res'], true);
            $data['category']     = $res['data']['category'] ?? '';
            $data['category_str'] = self::CATEGORY_DICT[$data['category']] ?? $data['category'];
            unset($data['res']);
        }
        return $data;
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
}
