<?php

namespace RPC\Model;

use Think\Model;
use Think\Exception;
use RPC\Model\BaseModel;
use RPC\Common\Match\Engine;
use RPC\Common\Enum\EOperation;
use RPC\Common\Enum\EOrderStatus;
use RPC\Common\Enum\EOrderType;
use RPC\Common\Match\GetWayDistribute;

/**
  * 订单模型
  * @author Henry.
  * @version 2.0 time 2018-03-27
  */
class OrderModel extends BaseModel
{
    private static $_currencyType, $_Instance, $_getWay;
    
    public function __construct($currencyType)
    {
        $DBName = "DB_" . $currencyType;
        
        $this->connection    = ($DBName ? $DBName : C("DB_IM"));
        self::$_getWay       = GetWayDistribute::getInstance($currencyType);
        self::$_currencyType = $currencyType;
        
        parent::__construct();
              
    }
    
    private function __clone()
    {
    
    }
    
    public static function getInstance($currencyType)
    {               
        if (!self::$_Instance instanceof self) 
        {
            self::$_Instance = new self($currencyType);
            
        }
        return self::$_Instance;         
    }
    
    /** 
     * @summary         : 下单. 
     * @transactionType : 交易类型[买,卖].
     * @order           : 订单数据.
    */
    public function add($orderType, $order)
    {
        //获取撮合引擎(当前币种).
        $matchEngine = Engine::getInstance($order['currency_name']);
        
        $order["order_type"] = $orderType;
        //下订单到撮合引擎中.
        $reData = $matchEngine->run($order);
               
        return $reData;
        
    }   
    
    /** 
     * @summary         : 撤 单. 
     * @orderId         : 订单ID.   
     * @transactionType : 交易类型[买,卖].
    */
    public function undo($orderType, $orderId)
    {
        $reVal = false;
        $IM = self::$_getWay->getCurrencyDB();
        
        switch($orderType)
        {
            case EOrderType::$BUY:
                $tableName  = "order_buy";                
            break;
            
            case EOrderType::$SELL:
                $tableName = "order_sell";                
            break;            
        }
        
        $order = $this->getOrder($orderType, $orderId);
        
        if($order['status'] == EOrderStatus::$Wailt)
        {
            $reVal = $IM->execute("update " . $tableName . ' set status = ' . EOrderStatus::$Undo . ' where id = ' . $orderId);
        }
        
        return $reVal;
    } 
    
    /** 
     * @summary         : 订单详情.    
     * @transactionType : 订单类型.
    */
    public function getOrder($orderType, $orderId)
    {
        $IM = self::$_getWay->getCurrencyDB();
        
        switch($orderType)
        {
            case EOrderType::$BUY:
                $tableName  = "order_buy";                
            break;
            
            case EOrderType::$SELL:
                $tableName = "order_sell";                
            break;            
        }
        
        $order = $IM->query("select * from " . $tableName . ' where id = ' . $orderId . ' limit 0 , 1');
        
        return $order;
    }    
    
    /** 
     * @summary         : 获取交易行情.
     * @currencyType    : 币种.
     * @transactionType : 订单类型.
    */
    public function getList($orderType)
    {
        //获取撮合引擎(当前币种).
        $matchEngine = Engine::getInstance(self::$_currencyType);
        
        //下订单到撮合引擎中.
        $redisList = $matchEngine->getTransactionOrderFromCache($orderType);
       
        return $redisList;
    }
    
    
}