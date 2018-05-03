<?php

namespace MatchEngine\Common\Match;

use Think\Model;
use Think\Exception;
use MatchEngine\Common\Match\GetWayDistribute;
use MatchEngine\Common\Enum\EOrderType;
use MatchEngine\Common\Enum\EOrderStatus;
use MatchEngine\Common\Enum\EOperation;
use MatchEngine\Common\Enum\EResult;

/**
  * @summary 撮合引擎[外围逻辑处理].
  * @author  Henry.
  * @version 1.0
  * @date    2018.03.25.
  */
class PeripheralMatch
{   
    
    private static $_Instance, $_everyCount = 200, $_showRows = 10;
    
    //货币类型.
    private static $_currencyType;
    //网关分发缓存服务集群.
    private static $_getWay; 
    
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
            self::$_getWay = GetWayDistribute::getInstance(self::$_currencyType);
        }
        return self::$_Instance;         
    }
     
     /** 
     * @summary         : 撮合预热[订单入库].
     * @transactionType : 交易类型(0买, 1卖).
     * @order           : 订单数据.
     * @result          : 预热 1成功, 2失败.
    */
     public function matchPrepare($order)
     {
        $reVal   = false;  
        $transactionType = $order['order_type'];
        unset($order['order_type']);
        $key     = self::$_getWay->getRedisKey($transactionType, EOperation::$UNSEQUENCE, 0, 1);
        
        //订单入列(无序).
        $this->_addUnSequenceMQ($transactionType, $order);       
        
        /************ 执行队列成员入库 **************/
        
        $redisMQ = self::$_getWay->connectTransactionRedis($transactionType, EOperation::$UNSEQUENCE, 1);
        
        //有未执行队列成员.
        if($redisMQ->llen($key) > 0)
        {            
            $redisList = self::$_getWay->getRedis($transactionType, EOperation::$UNSEQUENCE, 0, 1);
            $count     = count($redisList);
            $count     = $count > self::$_everyCount ? self::$_everyCount : $count;
                        
            for ($i = 0; $i < $count; $i++)
            {
                $data = json_decode($redisList[$i], true);    
                
                if (is_array($data))
                {                    
                    //订单入库.
                    $reVal   = $this->_addOrderToDB($transactionType, $data);
                    
                    if($reVal)
                    {                        
                        //执行成功移除队列.
                        $redisMQ->lpop($key);
                    }
                    else
                    {
                        echo ' 入库失败 ';
                        //插入异常队列.
                    }
                }
            }
        }
        
        return $reVal;
     }
     
     /** 
     * @summary         : 添加[时间顺序交易]队列.
     * @transactionType : 交易类型(0买, 1卖).    
     * @order           : 订单数据.
    */
    private function _addUnSequenceMQ($transactionType, $order)
    {
        return self::$_getWay->addRedis($transactionType, EOperation::$UNSEQUENCE, $order, 0, 1);
    }
    
    /** 
     * @summary         : 添加[异常交易]队列.
     * @transactionType : 交易类型(0买, 1卖).    
     * @order           : 订单数据.
    */
    public function addExceptTransactToMQ($transactionType, $order)
    { 
        return self::$_getWay->addRedis($transactionType, EOperation::$EXCEPTION, $order, 0, 1);
    }
     
    /** 
     * @summary         : 添加[新增订单]队列(无序).
     * @transactionType : 交易类型(0买, 1卖).
     * @order           : 订单数据.
    */
    public function addOrderToMQ($transactionType, $order)
    { 
        return self::$_getWay->addRedis($transactionType, EOperation::$UNSEQUENCE, $order, 0, 1);
    }   
    
    /** 
     * @summary         : 添加[交易成功]队列 (等待代理服务，异步数据持久化).
     * @transactionType : 交易类型(0买, 1卖).   
     * @order           : 订单数据.
    */
    public function addSuccessOrderToMQ($transactionType, $order)
    { 
        return self::$_getWay->addRedis($transactionType, EOperation::$SUCCESS, $order, 0, 1);
    }
    
    /** 
     * @summary         : 添加[交易流水]队列 (等待代理服务，异步数据持久化).
     * @transactionType : 交易流水类型(0买, 1卖).    
     * @order           : 订单数据.
    */
    public function addTransactionFlowToMQ($transactionType, $order)
    { 
        return self::$_getWay->addRedis($transactionType, EOperation::$FLOW, $order, 0, 1);
    }
    
    /** 
     * @summary         : 添加[钱包]队列.
     * @transactionType : 交易类型(0买, 1卖).   
     * @order           : 订单数据.
    */
    public function addPurseToMQ($transactionType, $Data)
    { 
        return self::$_getWay->addRedis($transactionType, EOperation::$PURSE, $Data, 0, 1);        
    }
    
    /** 
     * @summary         : 添加[钱包]队列.
     * @transactionType : 交易类型(0买, 1卖).   
     * @order           : 订单数据.
    */
    public function getPurseMQ($transactionType, $Data)
    { 
        return self::$_getWay->getRedis($transactionType, EOperation::$PURSE, 0, 1);
    }
   
    /** 
     * @summary     : 发送交易消息到队列.
     * @buyOrder    : 买家订单.
     * @sellOrder   : 卖家订单.
    */
    public function sendTransactionMsg($buyOrder, $sellOrder)
    {
        $buyMsgKey  = self::$_getWay->getMsgMQKey(EOrderType::$SELL);
        $sellMsgKey = self::$_getWay->getMsgMQKey(EOrderType::$BUY);        
        
        $this->addSuccessMsgToMQ(EOrderType::$BUY, $buyOrder);
        $this->addSuccessMsgToMQ(EOrderType::$SELL, $sellOrder);
    }
    
    /** 
     * @summary         : 添加[交易成功]队列 (等待代理服务，异步数据持久化).
     * @transactionType : 交易类型(0买, 1卖).    
     * @order           : 订单数据.
    */
    public function addSuccessMsgToMQ($transactionType, $order)
    {
        //1.MQ加反馈消息.
        $msg = "订单交易成功, 成交价(" . $order["price_entrust"] . "), 成交额(" . $order["count_entrust"] . ") 时间:" . date('Y-m-d H:i:s',time());
        
        return self::$_getWay->addRedis($transactionType, EOperation::$MSG, ["user_id" => $order['user_id'], "msg" => $msg], 0, 1);
        
    }
    
    /****************************** DataBase ************************************/
    
    /** 
     * @summary         : 更新用户余额.
     * @transactionType : 交易类型(0买, 1卖).
     * @data            : 订单数据.
    */
    public function setPurseToDB($data = array())
    {
        $reVal    = false;
        $bourseDB = self::$_getWay->getBourseDB();       
        
        try
        {
            $sql = "update user_info set balance=balance + " . $data['amount'] . ", update_time= NOW() where uid=" . $data['uid'];           
            $reVal  = $bourseDB->execute($sql);            
        }
        catch(Exception $ex)
        {
            $exMsg  = " 用户UID" . $order["user_id"] . " 订单状态更新失败！" . json_encode($order);
            $exMsg .= " 异常信息 : " . $ex->getMessage();
            
            $this->addTransactLog($exMsg);
        }
       
        return $reVal;
    }
    
    /** 
     * @summary         : 更新订单状态(成功, 撤销).
     * @transactionType : 交易类型(0买, 1卖).
     * @order           : 订单数据.
    */
    public function setOrderStatusToDB($transactionType, $order = array())
    {
        $reVal = false;        
        $IM = self::$_getWay->getCurrencyDB();
        $tableName;
        $sql;  
              
        switch($transactionType)
        {
            case EOrderType::$BUY:
                $tableName  = "order_buy";                
            break;
            
            case EOrderType::$SELL:
                $tableName = "order_sell";                
            break;            
        }
        
        $dbOrder = $IM->query("select status, price_entrust from " . $tableName . ' where id=' . $order['id']);
        $dbOrder = $dbOrder[0];
        switch($transactionType)
        {
            case EOrderType::$BUY:                 
                if((int)$order['price_deal'] > (int)$dbOrder['price_entrust'])
                {          
                    $msg  = "非法交易 成交价 < 买入价==>>" . json_encode($order);
                    $this->addTransactLog($msg);
                    return false;
                }
            break;
            
            case EOrderType::$SELL:                
                if($order['price_deal'] < $dbOrder['price_entrust'])
                {
                    $msg  = "非法交易 成交价 > 卖出价==>>" . json_encode($order);
                    $this->addTransactLog($msg);
                    return false;
                }               
            break;
        }
                
        if ($dbOrder['status'] == EOrderStatus::$Wailt)
        {
            try
            {
                 
                    //更新订单状态;        
                    $sql  = "update " . $tableName;
                    $sql .= " set price_deal=" . $order['price_deal'];
                    $sql .= ", count_deal=" . $order['count_deal'];
                    $sql .= ", total_deal=" . ($order['price_deal'] * $order['count_deal']);
                    $sql .= ", status=" . EOrderStatus::$Finish;
                     
                    $sql .= ", deal_time= NOW() where id=" . $order['id'];  
                    $reVal  = $IM->execute($sql);                              
            }
            catch(Exception $ex)
            {
                $exMsg  = " 用户UID" . $order["user_id"] . " 订单状态更新失败！" . json_encode($order);
                $exMsg .= " 异常信息 : " . $ex->getMessage();
                
                $this->addTransactLog($exMsg);
            }
        } 
        else
        {
            $exMsg  = " 此订单现为 非等待状态, 所以不能操作！" . json_encode($order);
            $this->addTransactLog($msg);
        }        
        
        return $reVal;
    }
    
    /** 
     * @summary         : 订单入库.
     * @transactionType : 交易类型(0买, 1卖).
     * @order           : 订单数据.
    */
    private function _addOrderToDB($transactionType, $order = array())
    {
        $reVal = false;        
        $IM    = self::$_getWay->getCurrencyDB();
        $tableName;
        $sql;  
              
        switch($transactionType)
        {
            case EOrderType::$BUY:
                $tableName  = "order_buy";                
            break;
            
            case EOrderType::$SELL:
                $tableName = "order_sell";                
            break;
            
            default:
            break;
        }
        
        $order['status'] = EOrderStatus::$Wailt;
        
        try
        {
            //$reVal = $IM->insert($tableName, $order, true);
            
            $sql  = "insert into " . $tableName;
            $sql .= "(user_id, currency_id, currency_name, price_entrust, count_entrust, transaction_type, status) values ";
            $sql .= "(" . $order['user_id'] . ", " . $order['currency_id'] . ", '" . $order['currency_name'] . "', " . $order['price_entrust'] . ", " . $order['count_entrust'] . ", " . $order['transaction_type'] . ", " . $order['status'] . ")";
            
            $reVal  = $IM->execute($sql);  
            $msg = " 对表 " . $tableName . " 下单成功！" . json_encode($order);          
            $this->addTransactLog($msg);
            
        }
        catch(Exception $ex)
        {
            echo $ex->getMessage();
            $exMsg  = " 用户UID" . $order["user_id"] . " 下单失败！" . json_encode($order);
            $exMsg .= " 订单入库异常 : " . $ex->getMessage();
            
            $this->addTransactLog($exMsg);          
            //return ApiFormat::get(EResult::$RUNERRO, "订单入库异常", $order);  
        }
        
        return $reVal;
    }    
    
    /** 
     * @summary         : 成功订单入库.
     * @transactionType : 交易类型(0买, 1卖).
     * @order           : 订单数据.
    */
    public function addTransacitonFlowToDB($transactionType, $order = array())
    {     
        //校验
        if(!$this->_checkParams($order))
        {
            $exMsg  = "参数校验失败:" ;  
            $this->addTransactLog($exMsg. json_encode($order));
             
            return ApiFormat::get(EResult::$PARAMERRO, $exMsg, $order);
        }
        
        $reVal = false;        
        $IM = self::$_getWay->getCurrencyDB();
        $tableName;
        $sql;  
              
        switch($transactionType)
        {
            case EOrderType::$BUY:
                $tableName  = "order_buy_finish";                
            break;
            
            case EOrderType::$SELL:
                $tableName = "order_sell_finish";
            break;
        }
        
        $order['status'] = EOrderStatus::$Finish;
        
        try
        {
            $reVal = $IM->insert($tableName, $order, true);
            $msg = " 对表 " . $tableName . " 交易成功流水入库成功！" . json_encode($order);          
            $this->addTransactLog($msg);
            return ApiFormat::get($reVal? EResult::$SUCCESS : EResult::$RUNERRO, $reVal? "入库成功":"入库失败", $order);
        }
        catch(Exception $ex)
        {
            $exMsg  = " 用户UID" . $order["user_id"] . " 交易成功流水入库失败！" . json_encode($order);
            $exMsg .= " 订单入库异常 : " . $ex->getMessage();
            $this->addTransactLog($exMsg);    
            return ApiFormat::get(EResult::$RUNERRO, "订单入库异常", $order);        
        }
        
        return $reVal;
    }
    
    /** 
     * @summary         : 从数据库中获取交易详情.
     * @transactionType : 交易类型(0买, 1卖).
     * 
    */
    public function getCacheFromDB($transactionType)
    {
        $IM = self::$_getWay->getCurrencyDB();
        $tableName;
        $sqlOrderBy;
        
        switch($transactionType)
        {
            case EOrderType::$BUY:
                $tableName  = "order_buy";
                $sqlOrderBy =" order by price_entrust desc, create_time asc";
            break;
            
            case EOrderType::$SELL:
                $tableName = "order_sell";
                $sqlOrderBy =" order by price_entrust asc, create_time asc";
            break;
        }
        
        $sql  = "select id, user_id, price_entrust, count_entrust, create_time, status from " . $tableName;
        $sql .= " where status=" . EOrderStatus::$Wailt;
        $sql .= $sqlOrderBy;
        
        $transactList = $IM->query($sql);
        
        return $transactList;
    }
    
    /** 
     * @summary         : 获取交易行情.
     * @transactionType : 交易类型(0买, 1卖).
     * @sort            : 1升序, 0降序[默认]. 
     * @num             : 获取数量. 
    */
    public function getTransactionOrderFromCache($cacheObj, $transactionType, $sort = 0, $num = 80000)
    {
        $reList = [];
       
        $catchKey = self::$_getWay->getRedisKey($transactionType, EOperation::$TRANSACTION);
        var_dump($catchKey);
        var_dump($cacheObj->hlen($catchKey));
       
        //读取缓存 如果没有则读库.
        if (!$cacheObj->hlen($catchKey))
        {  
            $transactList = $this->getCacheFromDB($transactionType);           
            foreach($transactList as $key => $val)
            {
                $tempList = $cacheObj->hget($catchKey, (double)$val["price_entrust"]);
                
                if($tempList)
                {
                    $tempList = json_decode($tempList, true);                        
                }
                
                if(!in_array($val, $tempList))
                {
                    $tempList[] = $val; 
                    $cacheObj->hset($catchKey, (double)$val["price_entrust"], json_encode($tempList));                  
                }
            }            
        }
      
        //$reList = array_multisort(array_column($arr, 'price_entrust'), SORT_DESC, $arr);
        $keys = $cacheObj->hkeys($catchKey);
        //买家由高到低返回
        ($transactionType == EOrderType::$BUY) ? arsort($keys) : asort($keys);
        //交易行情现实条数.
        $showCount = count($keys);
        $showCount = $showCount > self::$_showRows ? $_showRows : $showCount;
       
        //对缓存进行排序.
        //return $cacheObj->sort($catchKey, array('LIMIT'=>array(0, $num), "SORT" => $sort ? 'ASC' : 'DESC'));
        for ($i = 0; $i < $showCount; $i++)
        {            
            $orders = $cacheObj->hget($catchKey, $keys[$i]);          
            $reList[] = $orders;
        }
        
        return $reList;
    }
    
    /** 
     * @summary         : 获取交易子行情.
     * @transactionType : 交易类型(0买, 1卖).
     * @sort            : 1升序, 0降序[默认]. 
     * @num             : 获取数量. 
    */
    public function getTransactionOrderSubFromCache($cacheObj, $transactionType, $subKey)
    {
        $catchKey = self::$_getWay->getRedisKey($transactionType, EOperation::$TRANSACTION);
        
        return $cacheObj->hget($catchKey, $subKey);//hvals        
    }
    
   /** 
     * @summary         : 删除缓存元素.
     * @transactionType : 交易类型(0买, 1卖).
     * @subKey          : 子Key. 
    */
    public function delOrderFromCache($cacheObj, $transactionType, $subKey)
    {
        $catchKey = self::$_getWay->getRedisKey($transactionType, EOperation::$TRANSACTION);
        $subKey = (double)$subKey;
        echo "hdel " . $catchKey . " " . $subKey;
        return $cacheObj->hdel($catchKey, $subKey);
    }        
        
   /** 
     * @summary         : 更新内存(交易行情).
     * @transactionType : 交易类型(0买, 1卖).
     * @subKey          : 子Key. 
    */
    public function updateOrderToCache($cacheObj, $transactionType, $subKey, $orderSubList)
    {
        $catchKey = self::$_getWay->getRedisKey($transactionType, EOperation::$TRANSACTION);
        
        return $cacheObj->hset($catchKey, $subKey, json_encode($orderSubList));
    }                  
                        
    /** 
     * @summary          : 订单原子多播定序.    
     * @catchKey         : 缓存Key.
     * @cacheContainer   : 缓存实例.     
     * @order            : 订单数据.
     * @transactionType  : 交易类型(0买, 1卖).
     * 
    */
    public function addOrderToCache($cacheContainer, $order, $transactionType)
    {
        if($order)
        {
            $catchKey   = self::$_getWay->getRedisKey($transactionType, EOperation::$TRANSACTION);
            
            //读取缓存 如果没有则读库.
            $cacheCount = $cacheContainer->hlen($catchKey);  
                  
            if(!$cacheCount)
            {
                $transactList = $this->getCacheFromDB($transactionType);
                
                foreach($transactList as $key => $val)
                {
                    $tempList = [];
                    if($tempList = $cacheContainer->hget($catchKey, $val["price_entrust"]))
                    {
                        $tempList = json_decode($tempList, true);                        
                    }
                    $tempList[] = $val;
                    $cacheContainer->hset($catchKey, $val["price_entrust"], json_encode($tempList));
                }          
            }
            else
            {
                $tempList = $cacheContainer->hget($catchKey, $order["price_entrust"]);
                
                if($tempList)
                {
                    $tempList = json_decode($tempList, true);                    

                    //此处没有想到更好的解决方案.
                    if(count($tempList) > 1)
                    {
                        $tempList[] = $order;
                        $cacheContainer->hset($catchKey, $order["price_entrust"], json_encode($tempList));
                    }
                    else
                    {
                        $orderTemp = $tempList[0];
                        //过滤重复
                        if(!($orderTemp['user_id'] == $order['user_id'] && $orderTemp['create_time'] == $order['create_time']))
                        { 
                            $tempList[] = $order;
                            $cacheContainer->hset($catchKey, $order["price_entrust"], json_encode($tempList));
                        }               
                    }
                }  
                //var_dump($tempList);
            }            
        }
        
    } 
    
    /** 
     * @summary          : 释放缓存实例.
     * @cacheContainer   : 缓存实例.
     * @transactionType  : 交易类型(0买, 1卖).
    */
    public function clearCache($cacheContainer, $transactionType)
    {
        switch($transactionType)
        {
            case EOrderType::$BUY:
                $cacheContainer->clearBuyRedis();
            break;
            case EOrderType::$SELL:
                $cacheContainer->clearSellRedis();
            break;
        }        
    }
    
    /** 
     * @summary : 写日志(同步).
     * @msg     : 记录信息.    
    */
    public function addTransactLog($msg)
    {
        \MatchEngine\Common\Log::write($msg);
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
     
}
