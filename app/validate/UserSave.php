<?php
declare (strict_types=1);

namespace app\validate;

use think\Validate;

class UserSave extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'    => 'require',
        'name'  => 'require',
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [
        'id.require'   => 'id不能为空',
        'name.require' => '用户名不能为空',
    ];
}
