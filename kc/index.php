<?php
if(version_compare(PHP_VERSION,'5.3.0','<'))  die('require PHP > 5.3.0 !');
define('APP_DEBUG',true);            // 开启调试模式 建议开发阶段开启 部署阶段注释或者设为false
define('APP_PATH','./RPC/');		 // 定义应用目录
define('BIND_MODULE','EOS');       // 帮定模块  否则就会运行系统设定模块
define('_PHP_FILE_',$_SERVER['SCRIPT_NAME']);
require './Core/ThinkPHP.php';       // 引入ThinkPHP入口文件
?>
