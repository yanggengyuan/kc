<?php


namespace MatchEngine\Common\Match;

use MatchEngine\Common\Enum\EOrderType;
use MatchEngine\Common\Enum\EOperation;

/**
  * @summary ���طַ�(������).
  * @author  Henry.
  * @version 1.0
  * @date    2018.03.22
  */
class GetWayDistribute
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
     * @summary : ��ȡ��ǰ�������ݿ����ʵ��.    
    */
    public function getCurrencyDB()
    {
        return new \MatchEngine\Model\BaseModel("DB_" . self::$_currencyType);
    }    
    
    /** 
     * @summary : ��ȡ�������ݿ�ʵ��.    
    */
    public function getBourseDB()
    {
        return new \MatchEngine\Model\BaseModel("DB_Bourse");
    } 
      
    /************************** Redis ******************************/
    
    /** 
     * @summary         : ���ӽ��׻���(��д����).
     * @transactionType : ��������ö�� 0���, 1����.
     * @operationType   : ��������.
     * @isMQ   : 0 ����[Ĭ��], 1 ����.
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
     * @summary         : ���ӽ��׻���(��д����).
     * @transactionType : ��������ö�� 0���, 1����.
     * @operationType   : ��������.
     * @isReverse       : 0 Ĭ��, 1 ����.
     * @isMQ            : 0 ����[Ĭ��], 1 ����.
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
     * @summary         : ���[����]������.
     * @transactionType : ��������(0��, 1��).
     * @operationType   : ��������.
     * @data            : ��������.
     * @isReverse       : 1����, 0Ĭ��.
     * @isMQ            : 1����, 0����[Ĭ��].
    */
    public function addRedis($transactionType, $operationType, $data, $isReverse = 0, $isMQ = 0)
    { 
        $orderMQ = $this->connectTransactionRedis($transactionType, $operationType, $isMQ);
        $key     = $this->getRedisKey($transactionType, $operationType, $isReverse, $isMQ);
        
        $orderMQ->rpush($key, json_encode($data));
        
        return $orderMq;
    }
    
    /** 
     * @summary         : ��ȡ��������.
     * @transactionType : ��������.
     * @operationType   : ��������.
     * @isReverse       : 1����, 0Ĭ��.
     * @isMQ            : 1����, 0����[Ĭ��].
    */
     public function getRedisPop($transactionType, $operationType, $isReverse = 0, $isMQ = 0)
     {
        $orderMq  = $this->connectTransactionRedis($transactionType, $operationType, $isMQ);
        $key      = $this->getRedisKey($transactionType, $operationType, $isReverse, $isMQ);
         
       if ($orderMq->llen($key) > 0)
        {   
            //����.
            $reMQ = $orderMq->lpop($key);
            return json_decode($reMQ);
        }
     }
     
     /** 
     * @summary         : ��ȡ��������.
     * @transactionType : ��������.
     * @operationType   : ��������.
     * @isReverse       : 1����, 0Ĭ��.
     * @isMQ            : 1����, 0����[Ĭ��].
     * @return          : ����json�� ����.
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