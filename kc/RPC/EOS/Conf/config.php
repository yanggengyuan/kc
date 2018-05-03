<?php
return array(

    //缓存基本配置.
    'DATA_CACHE_PREFIX' => 'Redis_',//缓存前缀
    'DATA_CACHE_TYPE'   =>'Redis',  //默认动态缓存为Redis
    'REDIS_RW_SEPARATE' => true,    //Redis读写分离 true 开启    
    'REDIS_TIMEOUT'     =>'300',    //超时时间
    'REDIS_PERSISTENT'  =>false,    //是否长连接 false=短连接    
    'DATA_CACHE_TIME'   => 0,       // 数据缓存有效期 0表示永久缓存
    'REDIS_AUTH'        =>'',       //AUTH认证密码 
        
    
    /******************* IM币 缓存********************/

    //缓存集群配置(交易成功订单).
    'REDIS_HOST_BUY_IM_TRANSACTION'     =>'127.0.0.1',
    'REDIS_PORT_BUY_IM_TRANSACTION'     =>'6379',
    'REDIS_HOST_SELL_IM_TRANSACTION'    =>'127.0.0.1',
    'REDIS_PORT_SELL_IM_TRANSACTION'    =>'6379',
    
    /******************* IM币 队列********************/
    //缓存集群配置(交易流水).
    'MQ_HOST_BUY_IM_FLOW'        =>'127.0.0.1',
    'MQ_PORT_BUY_IM_FLOW'        =>'6379',
    'MQ_HOST_SELL_IM_FLOW'       =>'127.0.0.1',
    'MQ_PORT_SELL_IM_FLOW'       =>'6379',
    //缓存集群配置(交易成功订单).
    'MQ_HOST_BUY_IM_SUCCESS'     =>'127.0.0.1',
    'MQ_PORT_BUY_IM_SUCCESS'     =>'6379',
    'MQ_HOST_SELL_IM_SUCCESS'    =>'127.0.0.1',
    'MQ_PORT_SELL_IM_SUCCESS'    =>'6379',
    //缓存集群配置(异常交易).
    'MQ_HOST_BUY_IM_EXCEPTION'   =>'127.0.0.1',
    'MQ_PORT_BUY_IM_EXCEPTION'   =>'6379',
    'MQ_HOST_SELL_IM_EXCEPTION'  =>'127.0.0.1',
    'MQ_PORT_SELL_IM_EXCEPTION'  =>'6379',
    //缓存集群配置(反馈消息).
    'MQ_HOST_BUY_IM_MSG'         =>'127.0.0.1',
    'MQ_PORT_BUY_IM_MSG'         =>'6379',
    'MQ_HOST_SELL_IM_MSG'        =>'127.0.0.1',
    'MQ_PORT_SELL_IM_MSG'        =>'6379',
    //缓存集群配置(个人余额).
    'MQ_HOST_BUY_IM_PURSE'       =>'127.0.0.1',
    'MQ_PORT_BUY_IM_PURSE'       =>'6379',
    'MQ_HOST_SELL_IM_PURSE'      =>'127.0.0.1',
    'MQ_PORT_SELL_IM_PURSE'      =>'6379',
    
    //缓存集群配置(个人余额).
    'MQ_HOST_BUY_IM_UNSEQUENCE'  =>'127.0.0.1',
    'MQ_PORT_BUY_IM_UNSEQUENCE'  =>'6379',
    'MQ_HOST_SELL_IM_UNSEQUENCE' =>'127.0.0.1',
    'MQ_PORT_SELL_IM_UNSEQUENCE' =>'6379',
 
    /******************* 数据库配置 ********************/
    'DB_TYPE'    =>  'mysql',     // 数据库类型
    'DB_HOST'    =>  '127.0.0.1', // 服务器地址
    'DB_NAME'    =>  'bourse',    // 数据库名
    'DB_USER'    =>  'root',      // 用户名
    'DB_PWD'     =>  'root',      // 密码
    'DB_PORT'    =>  '3306',      // 端口
    'DB_PREFIX'  =>  '',          // 数据库表前缀
    'DB_CHARSET' =>  'utf8',      // 数据库字符集
    
    'DB_Bourse' => 'mysql://root:root@127.0.0.1:3306/bourse#utf8',    //交易所基础库.
    'DB_IM'     => 'mysql://root:root@127.0.0.1:3306/match_imc#utf8', //IM币撮合库.    
        
	//货币类型
    'currency_type' => 'IM',
    
    /* 自动运行配置 */ 
    'CRON_CONFIG_ON' => true, // 是否开启自动运行 
    'CRON_CONFIG' => array( 
        '定时任务' => array('Match/testlog', '3', ''),
        
    ),
    
);