<?php

namespace EOS\Controller;

use EOS\Common\http;

/**
  * 账户控制器.
  * @author Henry
  * @version 1.0 time 2018-03-26.
  */
class AccountController extends BaseController
{

    public function get_info()
    {
        $id   = $_GET['id'];
        $data = array(
            "block_num_or_id" => $id,
        );
        $url    = "http://127.0.0.1:8888/v1/chain/get_block";

        $result = http::post($url, json_encode($data));

        var_dump($result);
    }

    /**
     * @summary      : 下单.    
     * @order        : 订单数据.
     * @type         : 订单类型.
    */
    public function add()
    {
        $orderType = $_GET['ot'];
       
        //订单数据 接口Post传入.
        $order = [      
        'user_id'       => 10008939,
        'currency_id'   => 1,
        'currency_name' => 'IM',
        'price_entrust' => 8290,
        'count_entrust' => 1,
        'create_time'   => date('Y-m-d H:i:s', time()),
        'transaction_type' => (bool)ETransactionType::$LimitPrice,
        ];
        
        return OrderModel::getInstance("IM")->add(($orderType ? EOrderType::$SELL : EOrderType::$BUY), $order);      
        
    }   
    
    //撤单.
    public function undo()
    {
        $orderType = $_GET['ot'];
        //$orderType = 0;
        $id = $_GET['id'];
       
        return OrderModel::getInstance("IM")->undo(($orderType ? EOrderType::$SELL : EOrderType::$BUY), $id);
    } 
    
    //订单查询
    public function get()
    {
        //$orderType = $_POST['ot'];
        $orderType = 0;
        $id = $_GET['id'];
        
        $order = OrderModel::getInstance("IM")->getOrder(($orderType ? EOrderType::$SELL : EOrderType::$BUY), $id);
        var_dump($order);
    }  
    
    //获取内存交易行情
    public function getlist()
    {
        $orderType = $_GET['ot'];       
        $buyList = OrderModel::getInstance("IM")->getList($orderType);
        
        var_dump($buyList);
    }
    
    
}