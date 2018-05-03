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
  * @summary 撮合引擎[].
  * @author  Henry.
  * @version 1.0
  * @date    2018.03.25.
  */
class Engine
{  
    //币种.
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
     * @summary : 由币种获取撮合算法[实例].   
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
     * @summary : 校验订单参数.
     * @order   : 订单参数.
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
     * @summary      : 启动引擎.    
     * @order        : 订单数据.
     * @type         : 订单类型.
    */
    public function run($order)
    { 
        $matchResult = false;
        //校验
        if(!$this->_checkParams($order))
        {
            return ApiFormat::get(EResult::$PARAMERRO, "参数校验失败");
        }
        
        //预热(订单入库).
        $reVal = self::$_peripheralMatch->matchPrepare($order);
        
        if($reVal)
        {
            try
            {
                //获取引擎(现币种).
                $matchAlgorithmObj = $this->_getMatchAlgorithm();
                echo " 开始撮合==>>";
                //计算.
                $matchAlgorithmObj->runAlgorithm($order);  
                echo "撮合结束";
                return ApiFormat::get(EResult::$SUCCESS, "撮合成功", true);
            }
            catch(Exception $ex)
            {
                $exMsg = "用户UID" . $order["user_id"] . "的订单撮合异常！" . json_encode($order);
                self::$_peripheralMatch->addTransactLog($exMsg);
                              
                return ApiFormat::get(EResult::$RUNERRO, "运行错误");
            }  
        }
        else
        {
            $exMsg = "用户UID" . $order["user_id"] . "的订单入库失败！" . json_encode($order);            
            self::$_peripheralMatch->addTransactLog($exMsg);
            
            return ApiFormat::get(EResult::$DBERRO, "入库失败");
        }
        
    }   
    
    /** 
     * @summary         : 获取交易行情.
     * @transactionType : 交易类型(0买, 1卖).
     * @sort            : 1升序, 0降序[默认]. 
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