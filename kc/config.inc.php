<?php
return array(
    'DB_TYPE'    =>  'mysql',     // 数据库类型
    'DB_HOST'    =>  '127.0.0.1', // 服务器地址
    'DB_NAME'    =>  'kc',    // 数据库名
    'DB_USER'    =>  'root',      // 用户名
    'DB_PWD'     =>  'root',      // 密码
    'DB_PORT'    =>  '3306',      // 端口
    'DB_PREFIX'  =>  '',          // 数据库表前缀
    'DB_CHARSET' =>  'utf8',      // 数据库字符集

    'URL_ROUTER_ON'   => true, 
    'URL_MODEL'  => 2,
    'OUTPUT_ENCODE'        => true,             // 开启Gzip
        //'SHOW_PAGE_TRACE'    => true,             // 程序运行信息
	//'DEFAULT_MODULE'     => 'Admin',          // 默认模块
        //'DEFAULT_CONTROLLER' => 'Index',          // 默认控制器名称
        //'DEFAULT_ACTION'     => 'index',          // 默认操作名称
    'DEL_INDEX_RUNTIME'    => './Unicorn/Runtime',// 前台runtime
    'SHOW_PAGE_TRACE' => true,
    'LOG_RECORD' => true,
    'SHOW_ERROR_MSG' => true,

    );
?>
