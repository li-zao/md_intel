<?php

namespace app\controller;

use app\common\Code;
use app\event\UserLogin;
use app\model\CommonUtil;
use app\model\File;
use app\model\Url;
use app\model\User;
use app\BaseController;
use Exception;
use think\db\exception\DbException;
use think\facade\Cache;
use think\facade\View;
use think\facade\Session;

class Index extends BaseController
{
    public const CHART_CACHE_KEY = 'TODAY_CHART';
    public const STATISTIC_CACHE_KEY = 'INDEX_STATISTIC';

    /**
     * @return string|\think\response\Redirect
     */
    public function login()
    {
        if (!empty(session('uid'))) {
            return redirect('/');
        }
        return View::fetch();
    }

    /**
     * @return \think\response\Json
     */
    public function doLogin()
    {
        $username = $this->request->param('username');
        $password = $this->request->param('password');
        try {
            $data = $this->request->post();
            $this->validate($data, [
                'username|用户名' => 'require',
                'password|密码'   => 'require',
            ]);
            // 用户名验证
            $userInfo = User::getByName($username);
            if (empty($userInfo->password) || !password_verify($password, $userInfo->password)) {
                return self::jsonAPI([], Code::API_NO, lang('err.user_pass'));
            }
            if (empty($userInfo->status) || !empty($userInfo->is_del)) {
                return self::jsonAPI([], Code::API_NO, lang('err.invalid_acct'));
            }
            event(new UserLogin($userInfo));
            return self::jsonAPI(['id' => $userInfo['id']]);
        } catch (Exception $e) {
            CommonUtil::logError($e);
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * @return void
     */
    public function dologout()
    {
        Session::clear();
    }

    /**
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        return View::fetch();
    }

    /**
     * @return array|mixed
     * @throws DbException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getStatistics()
    {
        $refresh = $this->request->param('refresh', 0);
        $cache   = Cache::get(self::STATISTIC_CACHE_KEY);
        if (!empty($cache) && $refresh == 0) {
            return self::jsonAPI($cache);
        }
        $data = [
            'url_normal'     => Url::getIndexCount(Url::TYPE_NORMAL),
            'url_malicious'  => Url::getIndexCount(Url::TYPE_MALICIOUS),
            'file_normal'    => File::getIndexCount(Url::TYPE_NORMAL),
            'file_malicious' => File::getIndexCount(Url::TYPE_MALICIOUS),
        ];
        Cache::set(self::STATISTIC_CACHE_KEY, $data, 6000);
        return self::jsonAPI($data);
    }

    /**
     * @return \think\response\Json
     */
    public function getChartData()
    {
        $refresh = $this->request->param('refresh', 0);
        $cache   = Cache::get(self::CHART_CACHE_KEY);
        if (!empty($cache) && $refresh == 0) {
            return self::jsonAPI($cache);
        }
        $xAxis = [];
        for ($i = 6; $i >= 0; $i--) {
            $xAxis[] = date('Y-m-d', strtotime('-' . $i . ' days'));
        }
        $series = CommonUtil::getIndexStatistics($xAxis, $refresh);
        $res    = [
            'xAxis'  => $xAxis,
            'series' => $series,
        ];
        Cache::set(self::CHART_CACHE_KEY, $res, 6000);
        return self::jsonAPI($res);
    }
}
