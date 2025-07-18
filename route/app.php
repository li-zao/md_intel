<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use app\middleware\ApiAuth;
use think\facade\Route;

// 项目相关
// Route::get('/queue', 'queue/normal');
Route::group('api', function () {
    Route::post('pushUrl', 'api/pushUrl');
})->middleware(ApiAuth::class);