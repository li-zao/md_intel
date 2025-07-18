<?php
namespace app\model;

use app\common\Code;
use think\db\Raw;
use think\Model;

class User extends Model
{
    // 主键
    protected $pk = 'id';

    public const STATUS_ENABLE  = 1;
    public const STATUS_DISABLE = 0;
    public const STATUS_DICT = [
        self::STATUS_ENABLE => '启用',
        self::STATUS_DISABLE => '禁用',
    ];
    // 角色
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';
    public const ROLE_GUEST = 'guest';
    public const ROLE_DICT = [
        self::ROLE_ADMIN => '管理员',
        self::ROLE_STAFF => '普通用户',
        self::ROLE_GUEST => '访客',
    ];

    public const ADMIN_ROLE_LIST = [
        self::ROLE_ADMIN
    ];
    /**
     * 需要限定数据查看范围的角色
     */
    public const LIMIT_REGION_ROLE_LIST = [
        // self::ROLE_STAFF => self::ROLE_STAFF,
    ];

    public const CACHE_KEY = 'system_user';

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
     * @return User
     */
    public static function getCommonModel($searchParam = [])
    {

        $infos = self::where(CommonUtil::DEL_FIELD, Code::IS_NO);
        foreach ($searchParam as $dbField => $searchInfo) {
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
     * @param array $searchParam
     * @param int $curPage
     * @param int $limit
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
        $order = new Raw(implode(',', $order));
        $infos = self::getCommonModel($searchParam)->field($fields);
        return $infos->order($order)->page($curPage, $limit)->select()->toArray();
    }

    /**
     * 获取用户展示字符串
     * @param $ids
     * @param $separator
     * @param $field
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getDisplayDict($ids = [], $separator = '-', $field = null)
    {
        if (empty($ids)) {
            return [];
        }
        if (empty($field)) {
            $field = ['id', 'name', 'role'];
        }
        if (!empty($field) && is_string($field)) {
            $field = explode(',', $field);
        }
        if (!in_array('id', $field)) {
            $field[] = 'id';
        }
        $userDict = User::field($field)->whereIn('id', $ids)->select();
        if (!$userDict->isEmpty()) {
            $userDict = $userDict->toArray();
            $userDict = array_column($userDict, null, 'id');
        }
        $res = [];
        foreach ($userDict as $k => $v) {
            if (!empty($v['role'])) {
                $v['role'] = self::ROLE_DICT[$v['role']] ?? $v['role'];
            }
            $res[$k] = implode($separator, $v);
        }
        return $res;
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
     * @param $userInfo
     * @return bool
     */
    public static function setSession($userInfo)
    {
        if (empty($userInfo)) {
            return false;
        }
        $isAdmin = Code::IS_NO;
        if (in_array($userInfo['role'], self::ADMIN_ROLE_LIST)) {
            $isAdmin = Code::IS_YES;
        }
        session('name', $userInfo['name']);
        session('role', $userInfo['role']);
        session('uid', $userInfo['id']);
        session('is_admin', $isAdmin);
        return true;
    }

    /**
     * @param $id
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getByID($id)
    {
        $record = self::where('id', $id)->findOrEmpty();
        list($record,) = self::formatList([$record], false);
        return $record;
    }
    /**
     * 根据用户名查询账户
     * @param $name
     * @param $params
     * @return User|null
     */
    public static function getByName($name, $params = [])
    {
        if (empty($name)) {
            return null;
        }
        $where = [
            'name' => $name,
        ];
        if (isset($params[CommonUtil::DEL_FIELD])) {
            $where[CommonUtil::DEL_FIELD] = $params[CommonUtil::DEL_FIELD];
        }
        return self::where($where)->findOrEmpty();
    }

    /**
     * @param $pass
     * @return false|string|null
     */
    public static function getPassHash($pass)
    {
        return password_hash($pass, PASSWORD_BCRYPT);
    }

    /**
     * @param $params
     * @param $page
     * @param $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getList($params = [], $page = 1, $limit = 10)
    {
        $searchParam = [];
        $equalFields = [
        ];
        $zeroFields = [
        ];
        $likeFields = [
        ];
        $timeFields = [
        ];
        $findInSetFields = [
        ];
        $typeField = [
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
        if ($searchParam === false) {
            return [0, []];
        }
        $total = self::getAllCount($searchParam);
        if (empty($total)) {
            return [0, []];
        }
        $list  = self::getAllInfos($searchParam, $page, $limit, $params);
        $list  = self::formatList($list);
        return [$total, $list];
    }

    /**
     * @param $list
     * @param $marsking
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function formatList($list = [], $marsking = true)
    {
        $formatList = [
            'role' => self::ROLE_DICT,
        ];
        foreach ($list as &$item) {
            foreach ($formatList as $col => $dict) {
                if (!isset($item[$col . '_str'])) {
                    $item[$col . '_str'] = $dict[$item[$col]] ?? '';
                }
            }
            if ($marsking) {
                $item = CommonUtil::dataMasking($item);
            }
        }
        return $list;
    }

    /**
     * @param $id
     * @return mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getUsernameById($id)
    {
        $userInfo = self::where('id', $id)->find()->toArray();

        return $userInfo['name'] ?? 'unknown';
    }

    /**
     * @param $ids
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getUsernameByIds($ids = [])
    {
        $list = self::field('id, name')->whereIn('id', $ids)->select()->toArray();
        return array_column($list, 'name', 'id');
    }
}
