<?php
declare (strict_types=1);

namespace app\middleware;

use app\util\Des;
use app\Request;
use think\facade\Log;

class ApiAuth extends Common
{
    /**
     * 处理请求
     * @param Request $request
     * @param \Closure $next
     * @return mixed|\think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function handle(Request $request, \Closure $next)
    {
        parent::_init($request);
        if (env('app.auth', 1) && !$this->checkAuth($request)) {
            return response()->code(403);
        }
        if ($this->controller !== 'api') {
            return $next($request);
        }
        $params = json_encode(['header' => $request->header(), 'param' => $request->param()]);
        // get token header
        $token = $request->header('Authorization');
        $flag = $this->validToken($token);
        if (empty($token) || !$flag) {
            // 返回403
            Log::error("403:" . $params);
            return response()->code(403);
        }
        return $next($request);
    }

    /**
     * @param $token
     * @return bool
     */
    private function validToken($token)
    {
        if (empty($token)) {
            return false;
        }
        $desUtil = new Des(env('des_key', 'da55151c'));
        $sign   = $desUtil->decrypt($token);
        $params = $this->request->param();
        ksort($params);
        $parameters = http_build_query($params);
        $genSign = strtoupper(md5($parameters));
        if ($genSign !== strtoupper($sign)) {
            return false;
        }
        return true;
    }
}
