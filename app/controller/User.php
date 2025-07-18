<?php

namespace app\controller;

use app\BaseController;
use app\common\Code;
use app\model\CommonUtil;
use app\model\User as UserModel;
use app\validate\UserSave;
use Exception;
use think\facade\View;

class User extends BaseController
{
    /**
     * @return string
     */
    public function index()
    {
        View::assign('menu', 'account');
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
            [$total, $list] = UserModel::getList($params, $page, $limit);
            return $this->jsonAPI($list, Code::API_YES, ['count' => $total]);
        } catch (Exception $e) {
            CommonUtil::logError($e);
        }
        return $this->jsonAPI([], Code::API_NO);
    }

    /**
     * @return string|\think\response\Json
     */
    public function edit()
    {
        if ($this->request->isPost()) {
            return $this->save();
        }
        $record     = [
            'id'   => 0,
            'role' => UserModel::ROLE_ADMIN,
        ];
        $passHolder = lang('login.password');
        $id         = $this->request->param('id');
        if (!empty($id)) {
            $passHolder = lang('tip.left_empty');
            $record     = UserModel::getByID($id);
        }
        View::assign([
            'record'     => $record,
            'passHolder' => $passHolder,
        ]);
        View::assign('roles', UserModel::ROLE_DICT);
        return View::fetch();
    }

    /**
     * @return \think\response\Json
     */
    public function save()
    {
        $params = $this->request->param();
        try {
            validate(UserSave::class)->check($params);
            if (!empty($params['password'])) {
                $params['password'] = UserModel::getPassHash($params['password']);
            } else {
                unset($params['password']);
            }
            $record = UserModel::find($params['id']);
            if (empty($record)) {
                if (empty($params['password'])) {
                    return $this->jsonAPI([], Code::API_NO, lang('user.no_password'));
                }
                UserModel::create($params);
            } else {
                $id = $params['id'];
                unset($params['id']);
                UserModel::update($params, ['id' => $id]);
            }
            return self::jsonAPI([]);
        } catch (Exception $e) {
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * @return \think\response\Json
     */
    public function status()
    {
        if (!$this->request->isPost()) {
            return $this->jsonAPI([], Code::API_NO);
        }
        $params = $this->request->param();
        try {
            $this->validate($params, [
                'id'     => 'require',
                'status' => 'require',
            ]);
            $params['status'] = intval(filter_var($params['status'], FILTER_VALIDATE_BOOLEAN));
            $record           = UserModel::find($params['id']);
            if (!empty($record)) {
                if ($record['role'] == UserModel::ROLE_ADMIN && ($params['status'] == UserModel::STATUS_DISABLE)) {
                    return $this->jsonAPI([], Code::API_NO, lang('user.no_ban_admin'));
                }
            }
            UserModel::update(['status' => $params['status']], ['id' => $params['id']]);
            return self::jsonAPI([]);
        } catch (Exception $e) {
            return self::jsonAPI([], Code::API_NO, $e->getMessage());
        }
    }

    /**
     * @return \think\response\Json
     */
    public function del()
    {
        try {
            $id = $this->request->param('id');
            if (empty($id)) {
                throw new Exception(lang('err.params'));
            }
            $record = UserModel::find($id);
            if (empty($record)) {
                return $this->jsonAPI();
            }
            $record->update([CommonUtil::DEL_FIELD => Code::IS_YES], ['id' => $id]);
            return $this->jsonAPI();
        } catch (Exception $e) {
            CommonUtil::logError($e);
        }
        return $this->jsonAPI([], Code::API_NO);
    }
}
