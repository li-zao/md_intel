<?php

declare(strict_types=1);

namespace app\middleware;

use app\Request;
use Closure;
use think\response\Redirect;

class Common
{
    const DEFAULT_ACTION = 'index';
    public $request = '';
    public $controller = '';
    public $action = '';

    public function _init(Request $request)
    {
        $formatAction     = function ($action) {
            if (strpos($action, '.') !== false) {
                $action = strstr($action, '.', true);
            }
            return $action;
        };
        $this->request    = $request;
        $this->controller = $request->controller(true);
        $this->action     = $formatAction($request->action(true));
    }

    /**
     * 处理请求
     * @param Request $request
     * @param Closure $next
     * @return mixed|Redirect|void
     */
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }


    /**
     * @param Request $request
     * @return bool
     */
    protected function checkAuth(Request $request)
    {
        return true;
    }
}
