<?php


namespace MatchEngine\Common\Match;

use MatchEngine\Common\Enum\EOrderType;
use MatchEngine\Common\Enum\EOperation;

/**
  * @summary 网关分发(交易所).
  * @author  Henry.
  * @version 1.0
  * @date    2018.03.22
  */
class GetWayDistribute
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
     * @summary : 获取当前货币数据库操作实例.    
    */
    public function getCurrencyDB()
    {
        return new \MatchEngine\Model\BaseModel("DB_" . self::$_currencyType);
    }    
    
    /** 
     * @summary : 获取基础数据库实例.    
    */
    public function getBourseDB()
    {
        return new \MatchEngine\Model\BaseModel("DB_Bourse");
    } 
      
    /************************** Redis ******************************/
    
    /** 
     * @summary         : 连接交易缓存(读写分离).
     * @transactionType : 交易类型枚举 0买家, 1卖家.
     * @operationType   : 操作类型.
     * @isMQ   : 0 缓存[默认], 1 队列.
    */ 
    public function connectTransactionRedis($transactionType, $operationType, $isMQ = 0)
    {
        $transactionRedis = new \Redis();        
        
        $targe      = $isMQ ? 'MQ_' : 'REDIS_';
        $redisHost  = $targe . 'HOST_';
        $redisPort  = $targe . 'PORT_';
        $orderType  = ($transactionType == EOrderType::$BUY) ? 'BUY_' : 'SELL_';
        $redisHost .= $orderType . self::$_currencyType;
        $redisPort .= $orderType . self::$_currencyType;
        $redisAuth  = C("REDIS_AUTH");        
      
        switch($operationType)
        {
            case EOperation::$FLOW:            
                $redisHost .= "_FLOW";
                $redisPort .= "_FLOW";                      
            break;            
            case EOperation::$SUCCESS:            
                $redisHost .= "_SUCCESS";
                $redisPort .= "_SUCCESS";
            break;
            case EOperation::$EXCEPTION:            
                $redisHost .= "_EXCEPTION";
                $redisPort .= "_EXCEPTION";
            break;
            case EOperation::$MSG:            
                $redisHost .= "_MSG";
                $redisPort .= "_MSG";
            break;
            case EOperation::$PURSE:            
                $redisHost .= "_PURSE";
                $redisPort .= "_PURSE";
                $redisAuth  = "";
            break;   
            case EOperation::$UNSEQUENCE:            
                $redisHost .= "_UNSEQUENCE";
                $redisPort .= "_UNSEQUENCE"; 
            break;        
            case EOperation::$TRANSACTION:          
                $redisHost .= "_TRANSACTION";
                $redisPort .= "_TRANSACTION"; 
            break;          
        }
       
       $transactionRedis->connect(C($redisHost), C($redisPort));
       $transactionRedis->auth($redisAuth);       
        
        
       return $transactionRedis;       
    }
    
    /** 
     * @summary         : 连接交易缓存(读写分离).
     * @transactionType : 交易类型枚举 0买家, 1卖家.
     * @operationType   : 操作类型.
     * @isReverse       : 0 默认, 1 反向.
     * @isMQ            : 0 缓存[默认], 1 队列.
    */ 
    public function getRedisKey($transactionType, $operationType, $isReverse = 0,$isMQ = 0)
    {
        $reKey  = $isMQ ? 'MQ_' : 'REDIS_';        
        $reKey .= ($transactionType == EOrderType::$BUY) ? 'BUY_' : 'SELL_';
        $reKey .= self::$_currencyType;      
        
        switch($operationType)
        {
            case EOperation::$FLOW:            
                $reKey .= "_FLOW";                       
            break;            
            case EOperation::$SUCCESS:            
                $reKey .= "_SUCCESS"; 
            break;
            case EOperation::$EXCEPTION:            
                $reKey .= "_EXCEPTION"; 
            break;
            case EOperation::$MSG:            
                $reKey .= "_MSG"; 
            break;
            case EOperation::$PURSE:            
                $reKey .= "_PURSE"; 
            break;            
            case EOperation::$UNSEQUENCE:            
                $reKey .= "_UNSEQUENCE"; 
            break;  
            case EOperation::$TRANSACTION:            
                $reKey .= "_TRANSACTION"; 
            break;            
        }
        
        return $reKey;
    }
    
    /** 
     * @summary         : 添加[数据]到队列.
     * @transactionType : 交易类型(0买, 1卖).
     * @operationType   : 操作类型.
     * @data            : 订单数据.
     * @isReverse       : 1反向, 0默认.
     * @isMQ            : 1队列, 0缓存[默认].
    */
    public function addRedis($transactionType, $operationType, $data, $isReverse = 0, $isMQ = 0)
    { 
        $orderMQ = $this->connectTransactionRedis($transactionType, $operationType, $isMQ);
        $key     = $this->getRedisKey($transactionType, $operationType, $isReverse, $isMQ);
        
        $orderMQ->rpush($key, json_encode($data));
        
        return $orderMq;
    }
    
    /** 
     * @summary         : 获取队列数据.
     * @transactionType : 订单类型.
     * @operationType   : 操作类型.
     * @isReverse       : 1反向, 0默认.
     * @isMQ            : 1队列, 0缓存[默认].
    */
     public function getRedisPop($transactionType, $operationType, $isReverse = 0, $isMQ = 0)
     {
        $orderMq  = $this->connectTransactionRedis($transactionType, $operationType, $isMQ);
        $key      = $this->getRedisKey($transactionType, $operationType, $isReverse, $isMQ);
         
       if ($orderMq->llen($key) > 0)
        {   
            //出队.
            $reMQ = $orderMq->lpop($key);
            return json_decode($reMQ);
        }
     }
     
     /** 
     * @summary         : 获取队列数据.
     * @transactionType : 订单类型.
     * @operationType   : 操作类型.
     * @isReverse       : 1反向, 0默认.
     * @isMQ            : 1队列, 0缓存[默认].
     * @return          : 返回json串 数组.
    */
     public function getRedis($transactionType, $operationType, $isReverse = 0, $isMQ = 0)
     {
        $orderMq = $this->connectTransactionRedis($transactionType, $operationType, $isMQ);
        $key     = $this->getRedisKey($transactionType, $operationType, $isReverse, $isMQ);
        
        if ($orderMq->llen($key) > 0)
        {            
            $reMQ = $orderMq->lrange($key, 0, -1);
            
            return $reMQ;
        }
     }
    
}
    
    
?>