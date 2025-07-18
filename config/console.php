<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'demo'    => 'app\command\Demo',
        'queue'   => 'app\command\QueueCommand',
        'init_db' => 'app\command\DbInit',
        'fix'     => 'app\command\Fix',
    ],
];
