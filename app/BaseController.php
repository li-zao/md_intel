<?php
declare (strict_types=1);

namespace app;

use app\common\Code;
use app\model\CommonUtil;
use think\App;
use think\exception\ValidateException;
use think\facade\View;
use think\Validate;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];
    public $controller;
    public $action;

    public $breadcrumb = [
        'queue/all'          => '全部邮件',
        'queue/ad'           => '广告营销',
        'queue/bad'          => '垃圾邮件',
        'queue/blank'        => '空文邮件',
        'queue/isolation'    => '隔离邮件',
        'queue/malicious'    => '恶意邮件',
        'queue/normal'       => '正常邮件',
        'queue/phishing'     => '钓鱼邮件',
        'queue/subscription' => '学术订阅',
        'queue/todo'         => '待办队列',
        'queue/virus'        => '病毒邮件',
        'queue/waiting'      => '待审队列',
        'sample/index'       => '数据列表',
        'sample/top'         => 'TOP统计',
        'user/index'         => '账户管理',
        'urltest/index'      => 'URL验证列表',
    ];

    /**
     * 构造方法
     * @access public
     * @param App $app 应用对象
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;

        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {
        $this->controller = $this->request->controller(true);
        $this->action     = $this->request->action(true);
        $breadcrumb       = '';
        $key              = $this->controller . '/' . $this->action;
        if (isset($this->breadcrumb[$key])) {
            $breadcrumb = $this->breadcrumb[$key];
        }
        View::assign('breadcrumb', $breadcrumb);
        View::assign('menu', $this->controller . '_' . $this->action);
        View::assign('li', $this->controller);
        View::assign('v', env('app.version', '1'));
    }

    /**
     * 验证数据
     * @access protected
     * @param array $data 数据
     * @param string|array $validate 验证器名或者验证规则数组
     * @param array $message 提示信息
     * @param bool $batch 是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v     = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }

    /**
     * @param array $data
     * @param int $code
     * @param ...$arg
     * @return \think\response\Json
     */
    public static function jsonAPI(array $data = [], int $code = Code::API_YES, ...$arg)
    {
        return CommonUtil::jsonRes($data, $code, ...$arg);
    }

    /**
     * 获取分页数据 page & limit
     * @return array
     */
    public function pagination()
    {
        return CommonUtil::pagination($this->request->param());
    }
}
