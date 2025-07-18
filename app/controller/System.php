<?php
declare (strict_types=1);

namespace app\controller;

use app\BaseController;
use app\common\Code;
use app\model\CommonUtil;
use app\model\Jobs;
use Exception;
use think\facade\View;
use think\Request;

class System extends BaseController
{
    /**
     * @return string
     */
    public function jobs()
    {
        View::assign([
            'dict' => Jobs::QUEUE_DICT
        ]);
        return View::fetch();
    }

    /**
     * @return \think\response\Json
     */
    public function jobList()
    {
        try {
            $params = $this->request->param();
            [$page, $limit] = $this->pagination();
            [$total, $list] = Jobs::getList($params, $page, $limit);
            return $this->jsonAPI($list, Code::API_YES, ['count' => $total]);
        } catch (Exception $e) {
            CommonUtil::logError($e);
            return $this->jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }
}
