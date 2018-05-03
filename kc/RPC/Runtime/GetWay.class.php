<?php


namespace MatchEngine\Common\Match;

use MatchEngine\Common\Enum\EOrderType;

/**
  * @summary IM交易所网关.
  * @author  Henry.
  * @version 1.0
  * @date    2018.03.22
  */
class GetWay
{
    
    private static $_Instance; 
    //币种.
    private static $_currencyType;      
      
    private function __construct()
    {
     
    }
    
    private function __clone()
    {
    
    }
    
    public static function getInstance($currencyType)
    {
         if (!self::$_Instance instanceof self) 
         {
             self::$_Instance = new self();
             self::$_currencyType = $currencyType;
         }
         return self::$_Instance;         
    }   
    
    /** 
     * @summary : 根据币种和产品规则 分发适合的 撮合引擎.
     * @currencyType ：货币类型
    */ 
    public function toMatchMode($currencyType)
    {
        $reMatchObj;
       
        switch($currencyType)
        {
            case "IM" :              
                $reMatchObj = \MatchEngine\Common\IMMatch::getInstance($currencyType);
            break;            
            
            default:  
                          
            break;            
        }
    
       return $reMatchObj;        
        
    }    
    
    /** 
     * @summary : 连接购买订单队列(无序).
    */ 
    private function _ToBuyMQ($order = array())
    {     
        $bourseRedis = MatchEngine\Common\BourseRedis::getInstance();
        
        //self::$orderBuyMq = $this->_connectRedis();
        self::$orderBuyMq->set('name', 'Henry');    
        $name = self::$orderBuyMq->get('name');    

        switch($currencyType)
        {
            case "IM" :              
                $orderBuyList = \MatchEngine\Common\IMMatch::getInstance();
            break;
            
            default:                          
            break;            
        }
    
       return $orderBuyList;        
        
    } 
    
    /** 
     * @summary     : 获取[交易订单]队列Key.
     * @type        : 订单类型.
     * @isReverse   : 0 默认, 1 反向.
    */
     public function getTransctMQKey($type, $isReverse = 0)
     {
        if (!$isReverse)
            return self::$_currencyType . $type ? "sell_mq": "buy_mq";
        else
            return self::$_currencyType . $type ? "buy_mq" : "sell_mq";
     }
     
     /** 
     * @summary     : 获取[交易]缓存Key.
     * @type        : 订单类型 0,买家订单 1卖家订单.
     * @isReverse   : 0 默认, 1 反向.
    */
     public function getTransctCatchKey($type, $isReverse = 0)
     {
        if (!$isReverse)
            return self::$_currencyType . $type ? "sell_list": "buy_list";
        else
            return self::$_currencyType . $type ? "buy_list" : "sell_list";
     }
     
     /** 
     * @summary     : 获取[交易成功订单]队列Key.
     * @type        : 订单类型.
     * @isReverse   : 0 默认, 1 反向.
    */
     public function getSuccessMQKey($type, $isReverse = 0)
     {
        if (!$isReverse)
            return self::$_currencyType . $type ? "success_sell_mq": "success_buy_mq";
        else
            return self::$_currencyType . $type ? "success_buy_mq" : "success_sell_mq";
     }
     
     /** 
     * @summary     : 获取[消息反馈]队列Key.
     * @type        : 订单类型.
     * @isReverse   : 0 默认, 1 反向.
    */
     public function getMsgMQKey($type, $isReverse = 0)
     {
        if (!$isReverse)
            return self::$_currencyType . $type ? "msg_sell_mq": "msg_buy_mq";
        else
            return self::$_currencyType . $type ? "msg_buy_mq" : "msg_sell_mq";
     }
     
     /** 
     * @summary     : 获取[添加钱包]队列Key.
     * @type        : 订单类型.
     * @isReverse   : 0 默认, 1 反向.
    */
     public function getAddPurseMQKey($type, $isReverse = 0)
     {
        if (!$isReverse)
            return self::$_currencyType . $type ? "purse_sell_mq": "purse_buy_mq";
        else
            return self::$_currencyType . $type ? "purse_buy_mq" : "purse_sell_mq";
     }
     
     /** 
     * @summary     : 获取[异常交易]队列Key.
     * @type        : 订单类型.
     * @isReverse   : 0 默认, 1 反向.
    */
     public function getExceptTransactMQKey($type, $isReverse = 0)
     {
        if (!$isReverse)
            return self::$_currencyType . $type ? "except_sell_mq" : "except_buy_mq";
        else
            return self::$_currencyType . $type ? "except_buy_mq" : "except_sell_mq";
     }
     
     /** 
     * @summary : 连接交易队列(读写分离).
     * @type    : 交易类型枚举 0买家, 1卖家.
    */ 
    public function connectTransactionMQ($type)
    {
        $this->_tansactionMQ = new \Redis();
        if($type == EOrderType::$BUY)
        {
            switch(self::_currencyType)
            {
                case "IM":              
                    $this->_tansactionMQ->connect(C("MQ_HOST_BUY_IM"), C("MQ_PORT_BUY_IM"));        
                    $this->_tansactionMQ->auth(C("REDIS_AUTH"));
                break;            
                
                default:   
                break;            
            }
        }
        else
        {
            switch(self::_currencyType)
            {
                case "IM":              
                    $this->_tansactionMQ->connect(C("MQ_HOST_SELL_IM"), C("MQ_PORT_SELL_IM"));        
                    $this->_tansactionMQ->auth(C("REDIS_AUTH"));
                break;            
                
                default:   
                break;            
            } 
        }
        
        return $this->_tansactionMQ;
    }
    
    
    /** 
     * @summary : 连接交易缓存(读写分离).
     * @type    : 交易类型枚举 0买家, 1卖家.
    */ 
    public function connectTransactionRedis($type)
    {
        $this->_tansactionRedis = new \Redis();
        
        if($type == EOrderType::$BUY)
        {
            switch(self::_currencyType)
            {
                case "IM":              
                    $this->_tansactionRedis->connect(C("REDIS_HOST_BUY_IM"), C("REDIS_PORT_BUY_IM"));        
                    $this->_tansactionRedis->auth(C("REDIS_AUTH"));
                break;            
                
                default:   
                break;            
            }        
        }
        else
        {
            switch(self::_currencyType)
            {
                case "IM":              
                    $this->_tansactionRedis->connect(C("REDIS_HOST_SELL_IM"), C("REDIS_PORT_SELL_IM"));        
                    $this->_tansactionRedis->auth(C("REDIS_AUTH"));
                break;            
                
                default:
                break;            
            }
        }
        
        return $this->_tansactionRedis;
    }
    
    
}
    
    
?>