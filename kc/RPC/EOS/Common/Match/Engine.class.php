<?php

namespace MatchEngine\Common\Match;

use Think\Model;
use Think\Exception;
use MatchEngine\Common\ApiFormat;
use MatchEngine\Common\Enum\EResult;
use MatchEngine\Common\Enum\EOperation;
use MatchEngine\Common\Enum\EOrderType;
use MatchEngine\Common\Match\IAlgorithm;
use MatchEngine\Common\Match\PeripheralMatch;

/**
  * @summary �������[].
  * @author  Henry.
  * @version 1.0
  * @date    2018.03.25.
  */
class Engine
{  
    //����.
    private static $_currencyType, $_Instance, $_getWay;
    private static $_peripheralMatch;    
    
    private function __construct($currencyType)
    {
        self::$_currencyType    = $currencyType;
        self::$_getWay          = GetWayDistribute::getInstance($currencyType);
        self::$_peripheralMatch = PeripheralMatch::getInstance($currencyType);
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
     * @summary : �ɱ��ֻ�ȡ����㷨[ʵ��].   
    */ 
    private function _getMatchAlgorithm()
    {
        $reMatchObj;
       
        switch(self::$_currencyType)
        {
            case "IM" :              
                $reMatchObj = AlgorithmIMC::getInstance(self::$_currencyType);
            break;                     
        }
    
       return $reMatchObj;
        
    }    
    
     /** 
     * @summary : У�鶩������.
     * @order   : ��������.
    */
    private function _checkParams($order = array())
    { 
        $reVal = false;
        if(is_array($order))
        {
            
            $reVal = true;
        }
        
        return $reVal;
    } 
    
    /** 
     * @summary      : ��������.    
     * @order        : ��������.
     * @type         : ��������.
    */
    public function run($order)
    { 
        $matchResult = false;
        //У��
        if(!$this->_checkParams($order))
        {
            return ApiFormat::get(EResult::$PARAMERRO, "����У��ʧ��");
        }
        
        //Ԥ��(�������).
        $reVal = self::$_peripheralMatch->matchPrepare($order);
        
        if($reVal)
        {
            try
            {
                //��ȡ����(�ֱ���).
                $matchAlgorithmObj = $this->_getMatchAlgorithm();
                echo " ��ʼ���==>>";
                //����.
                $matchAlgorithmObj->runAlgorithm($order);  
                echo "��Ͻ���";
                return ApiFormat::get(EResult::$SUCCESS, "��ϳɹ�", true);
            }
            catch(Exception $ex)
            {
                $exMsg = "�û�UID" . $order["user_id"] . "�Ķ�������쳣��" . json_encode($order);
                self::$_peripheralMatch->addTransactLog($exMsg);
                              
                return ApiFormat::get(EResult::$RUNERRO, "���д���");
            }  
        }
        else
        {
            $exMsg = "�û�UID" . $order["user_id"] . "�Ķ������ʧ�ܣ�" . json_encode($order);            
            self::$_peripheralMatch->addTransactLog($exMsg);
            
            return ApiFormat::get(EResult::$DBERRO, "���ʧ��");
        }
        
    }   
    
    /** 
     * @summary         : ��ȡ��������.
     * @transactionType : ��������(0��, 1��).
     * @sort            : 1����, 0����[Ĭ��]. 
    */
    public function getTransactionOrderFromCache($transactionType)
    {
        $cacheObj = self::$_getWay->connectTransactionRedis($transactionType, EOperation::$TRANSACTION);
        
        $sort = $transactionType == EOrderType::$BUY ? 0 : 1;
        
        $transactionOrderList = self::$_peripheralMatch->getTransactionOrderFromCache($cacheObj, $transactionType, EOperation::$TRANSACTION, $sort);
        
        return $transactionOrderList;
    }
    
    
}


?>