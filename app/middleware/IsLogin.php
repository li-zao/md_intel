<?php
declare (strict_types=1);

namespace app\middleware;

class IsLogin
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        $requestUrl = $request->pathinfo();
        // Api 无需登录验证
        if (strpos($requestUrl, 'api/') === 0) {
            return $next($request);
        }
        $allowIP = [
            '192.168.',
            '127.0.0.',
        ];
        $ipFlag  = false;
        foreach ($allowIP as $ip) {
            if (strpos($_SERVER['REMOTE_ADDR'], $ip) !== false) {
                $ipFlag = true;
            }
        }
        if (!$ipFlag) {
            header('HTTP/1.1 403 Forbidden');
            die;
        }

        if (strpos($requestUrl, 'dialog') === false) {
            if (!preg_match('/login/', $requestUrl)) {
                //没有登录 跳转到登录页面
                if (!$this->checkStatus()) {
                    return redirect((string)url('/index/login'));
                }
            }
        }

        return $next($request);
    }

    // 检查是否登录
    public function checkStatus()
    {
        if (!empty(session('uid'))) {
            return true;
        } else {
            return false;
        }
    }
}
