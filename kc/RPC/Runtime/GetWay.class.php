<?php


namespace MatchEngine\Common\Match;

use MatchEngine\Common\Enum\EOrderType;

/**
  * @summary IM����������.
  * @author  Henry.
  * @version 1.0
  * @date    2018.03.22
  */
class GetWay
{
    
    private static $_Instance; 
    //����.
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
     * @summary : ���ݱ��ֺͲ�Ʒ���� �ַ��ʺϵ� �������.
     * @currencyType ����������
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
     * @summary : ���ӹ��򶩵�����(����).
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
     * @summary     : ��ȡ[���׶���]����Key.
     * @type        : ��������.
     * @isReverse   : 0 Ĭ��, 1 ����.
    */
     public function getTransctMQKey($type, $isReverse = 0)
     {
        if (!$isReverse)
            return self::$_currencyType . $type ? "sell_mq": "buy_mq";
        else
            return self::$_currencyType . $type ? "buy_mq" : "sell_mq";
     }
     
     /** 
     * @summary     : ��ȡ[����]����Key.
     * @type        : �������� 0,��Ҷ��� 1���Ҷ���.
     * @isReverse   : 0 Ĭ��, 1 ����.
    */
     public function getTransctCatchKey($type, $isReverse = 0)
     {
        if (!$isReverse)
            return self::$_currencyType . $type ? "sell_list": "buy_list";
        else
            return self::$_currencyType . $type ? "buy_list" : "sell_list";
     }
     
     /** 
     * @summary     : ��ȡ[���׳ɹ�����]����Key.
     * @type        : ��������.
     * @isReverse   : 0 Ĭ��, 1 ����.
    */
     public function getSuccessMQKey($type, $isReverse = 0)
     {
        if (!$isReverse)
            return self::$_currencyType . $type ? "success_sell_mq": "success_buy_mq";
        else
            return self::$_currencyType . $type ? "success_buy_mq" : "success_sell_mq";
     }
     
     /** 
     * @summary     : ��ȡ[��Ϣ����]����Key.
     * @type        : ��������.
     * @isReverse   : 0 Ĭ��, 1 ����.
    */
     public function getMsgMQKey($type, $isReverse = 0)
     {
        if (!$isReverse)
            return self::$_currencyType . $type ? "msg_sell_mq": "msg_buy_mq";
        else
            return self::$_currencyType . $type ? "msg_buy_mq" : "msg_sell_mq";
     }
     
     /** 
     * @summary     : ��ȡ[���Ǯ��]����Key.
     * @type        : ��������.
     * @isReverse   : 0 Ĭ��, 1 ����.
    */
     public function getAddPurseMQKey($type, $isReverse = 0)
     {
        if (!$isReverse)
            return self::$_currencyType . $type ? "purse_sell_mq": "purse_buy_mq";
        else
            return self::$_currencyType . $type ? "purse_buy_mq" : "purse_sell_mq";
     }
     
     /** 
     * @summary     : ��ȡ[�쳣����]����Key.
     * @type        : ��������.
     * @isReverse   : 0 Ĭ��, 1 ����.
    */
     public function getExceptTransactMQKey($type, $isReverse = 0)
     {
        if (!$isReverse)
            return self::$_currencyType . $type ? "except_sell_mq" : "except_buy_mq";
        else
            return self::$_currencyType . $type ? "except_buy_mq" : "except_sell_mq";
     }
     
     /** 
     * @summary : ���ӽ��׶���(��д����).
     * @type    : ��������ö�� 0���, 1����.
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
     * @summary : ���ӽ��׻���(��д����).
     * @type    : ��������ö�� 0���, 1����.
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