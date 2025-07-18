<?php

namespace app\controller;

use app\common\Code;
use app\job\UrlTestStatistic;
use app\model\CommonUtil;
use app\BaseController;
use app\model\Jobs;
use Exception;
use think\App;
use think\facade\Queue;
use think\facade\View;

class UrlTest extends BaseController
{
    /**
     * @param App $app
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
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
     * @return \think\response\Json
     */
    public function list()
    {
        try {
            $params = $this->request->param();
            [$page, $limit] = $this->pagination();
            [$total, $list] = \app\model\UrlTest::getList($params, $page, $limit);
            return $this->jsonAPI($list, Code::API_YES, ['count' => $total]);
        } catch (Exception $e) {
            CommonUtil::logError($e);
        }
        return $this->jsonAPI([], Code::API_NO);
    }

    /**
     * @return \think\response\Json
     */
    public function rows()
    {
        try {
            $params = $this->request->param();
            [$page, $limit] = $this->pagination();
            [$total, $list] = \app\model\UrlTestRows::getList($params, $page, $limit);
            return $this->jsonAPI($list, Code::API_YES, ['count' => $total]);
        } catch (Exception $e) {
            CommonUtil::logError($e);
        }
        return $this->jsonAPI([], Code::API_NO);
    }

    /**
     * @return \think\response\Json
     */
    public function statistic()
    {
        try {
            $id = $this->request->param('id');
            Queue::later(1, UrlTestStatistic::class, ['id' => $id], Jobs::QUEUE_URL_TEST_STATISTIC);
            return $this->jsonAPI();
        } catch (Exception $e) {
            CommonUtil::logError($e);
        }
        return $this->jsonAPI([], Code::API_NO);
    }

}
