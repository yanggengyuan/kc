<?php

namespace RPC\Common\Match;

use Think\Model;
use Think\Exception;
use RPC\Common\BourseRedis;
use MatchRPCEngine\Common\Enum\EOrderType;
use MatchRPCEngine\Common\Enum\EOrderStatus;

/**
  * @summary 撮合引擎[外围逻辑处理].
  * @author  Henry.
  * @version 1.0
  * @date    2018.03.25.
  */
class PeripheralMatch
{   
    
    private static $_Instance;
    
    //货币类型.
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
     * @summary : 添加[异常交易]队列.
     * @type    : 订单类型.
    */
    private function _addExceptTransactToMQ($order, $type)
    { 
        $orderMq = BourseRedis::getInstance(self::_currencyType);        
        $orderMq->connectBuyMQ();
        
        $orderMq->rpush($this->_getExceptTransactMQKey($type), $order);
        
        return $orderMq;
    }   
     
    /** 
     * @summary : 添加[新增订单]队列(无序).
     * @type    : 订单类型.
    */
    private function _addOrderToMQ($order, $type)
    { 
        $orderMq = BourseRedis::getInstance(self::_currencyType);        
        $orderMq->connectBuyMQ();
        
        $orderMq->rpush($this->_getMQKey($type), $order);
        
        return $orderMq;
    }   
    
    /** 
     * @summary : 添加[交易成功]队列 (等待代理服务，异步数据持久化)..
     * @type    : 订单类型.
    */
    private function _addSuccessOrderToMQ($key, $order)
    { 
        $orderMq = BourseRedis::getInstance(self::_currencyType);        
        $orderMq->connectBuyMQ();
        
        $orderMq->rpush($key, $order);
        
        return $orderMq;
    } 
    
    /** 
     * @summary : 添加[反馈消息]队列.
     * @type    : 订单类型.
    */
    private function _addMsgToMQ($key, $Msg)
    { 
        $orderMq = BourseRedis::getInstance(self::_currencyType);        
        $orderMq->connectBuyMQ();
        
        $orderMq->rpush($key, $Msg);
        
        return $orderMq;
    } 
    
    /** 
     * @summary : 添加[钱包]队列.
     * @type    : 订单类型.
    */
    private function _addPurseToMQ($purse, $type)
    { 
        $orderMq = BourseRedis::getInstance(self::_currencyType);        
        $orderMq->connectBuyMQ();
        
        $orderMq->rpush($this->_getAddPurseMQKey($type), $purse);
        
        return $orderMq;
    }  
    
    /** 
     * @summary : 撮合处理.
     * @type    : 订单类型.
    */
    public function matchDo($order, $type)
    {        
        //校验
        if(!$this->_checkParams($order))
            return false;
        
        //订单入列.  
        $orderMq = $this->_addOrderToMQ($order, $type);
        //订单出列.
        $order = $orderMq->lpop($this->_getMQKey($type));        
        //订单入库.
        $reVal = $this->_addOrderToDB($type, $order);
        
        if($reVal)
        {
            //订单撮合.
            $matchResult = $this->_matchMake($order, $type);
            
            if($matchResult)
            {
                //添加到交易成功订单到队列.
                $this->_addTransactSuccessOrderToMQ($order, $type);
                //反馈消息队列.   
                $this->_addSuccessMsgToMQ($order, $type);
            }

            //更新交易行情.
            $this->_updateMarket($matchResult, $order, $type);        
        }
        else
        {
            $exMsg = "用户UID" . $order["user_id"] . "的订单入库失败！" . json_encode($order);
            $this->_addTransactLog($exMsg);
        }
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
     * @summary : 添加交易成功信息到队列.
     * @type    : 订单类型.
    */
    private function _addSuccessMsgToMQ($order, $type)
    {
        switch($type)
        {
            case EOrderType::$BUY:
            //购买订单交易成功 反馈信息入队.
            
            break;
            
            case EOrderType::$SELL:
            //售卖订单交易成功 反馈信息入队.
            break;
            
        }
    }
    
    /** 
     * @summary : 添加交易成功订到到队列, 等待异步代理服务 数据持久化.
     * @type    : 订单类型.
    */
    private function _addTransactSuccessOrderToMQ($order, $type)
    {
        
        switch($type)
        {
            case EOrderType::$BUY:
            //购买订单交易成功 入队.
            
            break;
            
            case EOrderType::$SELL:
            //售卖订单交易成功 入队.
            break;
            
        }
    }
    
    /** 
     * @summary : 添加交易失败订到到队列, 等待异步代理服务处理.
     * @type    : 订单类型.
    */
    private function _addFailMQ($order, $type)
    {
        
        switch($type)
        {
            case EOrderType::$BUY:
            //购买订单交易失败 入队.
            
            break;
            
            case EOrderType::$SELL:
            //售卖订单交易失败 入队.
            break;
            
        }
    }
        
    /** 
     * @summary : 订单撮合.
     * @type    : 订单类型.
    */
    private function _matchMake($order, $type)
    {
        $reResult = false;
        
        try
        {
            //调用撮合组件. 
            switch($order['transaction_type'])
            {
                case ETransactionType::$LimitPrice:
                    $this->_transactionLimit($order, $type);
                break;
                
                case ETransactionType::$MarketPrice:
                    $this->_transactionMarket($order, $type);
                break;
                
                default:break;
            }        
           
            //同步记录交易成功到日志文件.
            $exMsg = "用户UID" . $order["user_id"] . $type == EOrderType::$BUY ? "购买" : "售卖" . " 订单交易成功！" . json_encode($order);
            $this->_addTransactLog($exMsg);
            
            $reResult = true;          
        }
        catch(Exception $ex)
        {
            //同步记录异常日志              
            $exMsg = "用户UID" . $order["user_id"] . $type == EOrderType::$BUY ? "购买" : "售卖" . " 订单交易异常！" . json_encode($order);
            $this->_addTransactLog($exMsg);
            //入异常队列.
            $this->_addFailMQ($order, $type);
        }
        
        return $reResult;
        
    }       
   
     /** 
     * @summary     : 更新交易行情.
     * @matchResult : 撮合结果true 成功，false失败.
     * @order       : 订单数据.
     * @type        : 订单类型.    
    */
    private function _updateMarket($matchResult, $order, $type)
    {        
        $cacheContainer; //缓存容器.
        $transactList;   //交易行情.              
        
        //订单缓存Key.
        $catchKey = $this->_getCatchKey($type); 
        
        //分发缓存集群.
        switch($type)
        {
            case EOrderType::$BUY:                
                $cacheContainer = BourseRedis::getInstance(self::_currencyType)->connectBuyRedis();
            break; 
                       
            case EOrderType::$SELL:
                $cacheContainer = BourseRedis::getInstance(self::_currencyType)->connectSellRedis();
            break;
            
            default:
            break;
        }
        
        if($matchResult)
        {
            $reverseCatchKey = $this->_getCatchKey($type, true);            
            
            //买量小于卖量.(限价 市价 各建缓存)
            
            //买 > 卖(限价)
            
            //买 < 卖(限价)
            
            
            //交易反方订单列表.
            $orderList = $cacheContainer->hget($reverseCatchKey, $order["price_entrust"]);
            $orderList = json_decode($orderList);
         
            //按照时间排序.
            $createTimeList = array_column($orderList, 'create_time');
            $orderList      = array_multisort($createTimeList, SORT_ASC, $orderList);
            
            //减对立库存.
            foreach($orderList as $key => $val)
            {
                //订单需求量.                
                $orderCount = ((float)$order['count_entrust']) - ((float)$val['count_entrust']);
                if($orderCount >= 0)
                {
                    unset($val);
                }
                else
                {
                   $orderList[$key]["count_entrust"] = abs($orderCount);
                }
                //更新交易行情.
                $cacheContainer->hset($reverseCatchKey, $order["price_entrust"], json_encode($orderList));
                
                //存入发送消息队列.                
            }            
        }
        else
        {
            //增内存.
            $this->_addOrderToCache($catchKey, $cacheContainer, $order);            
        }
        
        //释放缓存实例.
        $this->_clearCache($cacheContainer, $type);
        
    }    
    
    /** 
     * @summary: 买家交易(限价).
     * @order  : 订单数据.
     * @type   : 订单类型.
    */
    private function _buyTransactionLimit($buyOrder, $type)
    {
        //买家Key.
        $buyCatchKey     = $this->_getCatchKey($type); 
        //卖家Key.
        $sellCatchKey    = $this->_getCatchKey($type, true);        
        $cacheContainer  = BourseRedis::getInstance(self::_currencyType)->connectBuyRedis();        
        //委托价格.              
        $entrustPrice    = (float)$buyOrder["price_entrust"];        
        //差价               
        $diffPrice       = 0;        
        //筛选可交易的Key集合.
        $sellCatcheKeys  = $cacheContainer->hKeys($sellCatchKey);
        //排序价格key.
        $sellCatcheKeys  = sort($sellCatcheKeys);
        $sellCount       = count($sellCatcheKeys); 
        
        //从卖家最低 开始买入撮合.
        for ($i = 0;$i < $sellCount; $i++)
        {
             $sellCatchePrice = $sellCatcheKeys[$i];
             
             //委托价 >= 卖家单价(成交条件).
             if ($entrustPrice >= $sellCatchePrice)
             {
                if($sellOrderList   = $cacheContainer->hget($sellCatchKey, $sellCatchePrice))
                 {
                    $sellOrderList  = json_decode($sellOrderList);                     
                    //时间升序.
                    $createTimeList = array_column($sellOrderList, 'create_time');
                    $sellOrderList  = array_multisort($createTimeList, SORT_ASC, $sellOrderList);
                    $buyOrderCount  = count($sellOrderList);
                    
                    //等价卖家订单按时间升序 买入.
                    for ($j = 0; $j < $buyOrderCount; $j++)
                    {
                        //余量.
                        $buyOrder["count_entrust"] = (float)$buyOrder["count_entrust"] - ((float)$sellOrderList[$j]["count_entrust"]);
                        
                        //每一步订单交易都, 发送反馈消息.
                        $this->_sendTransactionMsg($buyOrder, $sellOrderList[$j]);                        
                        
                        //买<卖, 减量存.
                        if($buyOrder["count_entrust"] < 0)
                        {
                            $sellOrderList[$j]["count_entrust"] = abs($buyOrder["count_entrust"]);
                        }
                        
                        //存入卖家订单交易完成队列 (等待代理服务异步处理 数据持久化).
                        $sellSuccesskey = $this->_getSuccessMQKey($type, 1);
                        $this->_addSuccessOrderToMQ($sellSuccesskey, $sellOrderList[$j]);
                            
                        //买>卖, 删存.
                        if($buyOrder["count_entrust"] >= 0)
                        {                            
                            unset($sellOrderList[$j]);
                            
                            //无量存则 释放内存.
                            if(count($sellOrderList) == 0)
                            {
                                $cacheContainer->hdel($sellCatchKey, $sellCatchePrice);
                            }
                        }
                        
                        //更新内存(卖).
                        $cacheContainer->hset($sellCatchKey, $sellCatchePrice, json_encode($sellOrderList));  
                        
                        //如果有返还额.
                        if($diffPrice = $entrustPrice - $sellCatchePrice)
                        {                                    
                            //返款到用户钱包MQ.(仅买家有返款)
                            $this->_addPurseToMQ(["user_id" => $buyOrder["user_id"], "retreats" => $diffPrice], $type);
                        }
                        
                        //买家订单交易完成.
                        if($buyOrder["count_entrust"] <= 0)
                        {
                            $buySuccessMQkey  = $this->_getSuccessMQKey($type);
                            $this->_addSuccessOrderToMQ($buySuccessMQkey, $buyOrder);
                            
                            break;
                        }                            
                    }
                    
                 }
             }
             else
             {
                break;
             }                     
        }
        
        //有未完成购买订单，加买家内存.
        if($buyOrder["count_entrust"] > 0)
        {
            $this->_addOrderToCache($buyCatchKey, $cacheContainer, $buyOrder);
        }
    }
    
    /** 
     * @summary: 卖家交易(限价).
     * @order  : 订单数据.
     * @type   : 订单类型.
    */
    private function _sellTransactionLimit($sellOrder, $type)
    {
        //卖家key.
        $sellCatchKey   = $this->_getCatchKey($type); 
        //买家key.
        $buyCatchKey    = $this->_getCatchKey($type, true);  
             
        $cacheContainer = BourseRedis::getInstance(self::_currencyType)->connectSellRedis();        
        //委托价格.
        $sellPrice      = (float)$sellOrder["price_entrust"];        
        //差价               
        $diffPrice;
        //筛选可交易的Key集合.
        $catcheKeys     = $cacheContainer->hKeys($buyCatchKey);
        //排序价格key.
        $catcheKeys     = rsort($catcheKeys);
        $buyCount       = count($catcheKeys); 
        
        //从买家最高 开始卖出撮合.
        for ($i = 0;$i < $buyCount; $i++)
        {
             $buyPrice = $catcheKeys[$i];
             
             //委托价 <= 买家单价(成交条件).
             if ($sellPrice <= $buyPrice)
             {
                if($buyOrderList    = $cacheContainer->hget($buyCatchKey, $buyPrice))
                 {
                    $buyOrderList   = json_decode($buyOrderList);
                    //时间升序.
                    $createTimeList = array_column($buyOrderList, 'create_time');
                    $buyOrderList   = array_multisort($createTimeList, SORT_ASC, $buyOrderList);
                    $buyOrderCount  = count($buyOrderList);
                    
                    //等价买家订单按时间升序 卖出.
                    for ($j = 0; $j < $buyOrderCount; $j++)
                    {
                        //余量.
                        $sellOrder["count_entrust"] = (float)$sellOrder["count_entrust"] - ((float)$buyOrderList[$j]["count_entrust"]);
                        
                        //每一步订单交易都, 发送反馈消息.
                        $this->_sendTransactionMsg($sellOrder, $buyOrderList[$j]);
                        
                        //买<卖, 减量存.
                        if($sellOrder["count_entrust"] < 0)
                        {
                            $buyOrderList[$j]["count_entrust"] = abs($sellOrder["count_entrust"]);
                        }
                        
                        //更新内存(买).
                        $cacheContainer->hset($buyCatchKey, $buyPrice, json_encode($buyOrderList)); 
                        
                        //存入买家订单交易完成队列 (等待代理服务异步处理 数据持久化).
                        $buySuccessMQkey = $this->_getSuccessMQKey($type);
                        $this->_addSuccessOrderToMQ($buySuccessMQkey, $buyOrderList[$j]);
                            
                        //卖>=买, 删存.
                        if($sellOrder["count_entrust"] >= 0)
                        {
                            unset($buyOrderList[$j]);
                            
                            //无量存则 释放内存.
                            if(count($buyOrderList) == 0)
                            {
                                $cacheContainer->hdel($buyCatchKey, $buyPrice);
                            }
                        }                         
                                                         
                        //返款到用户钱包MQ.(最高买家价入用户余额)
                        $this->_addPurseToMQ(["user_id" => $sellOrder["user_id"], "retreats" => $buyPrice], $type);
                                                
                        //卖家订单交易完成.
                        if($sellOrder["count_entrust"] <= 0)
                        {
                            $sellSuccessMQkey  = $this->_getSuccessMQKey($type, 1);
                            $this->_addSuccessOrderToMQ($sellSuccessMQkey, $sellOrder);
                            
                            break;
                        }                            
                    }
                    
                 }
             }
             else
             {
                break;
             }                     
        }
        
        //有未完成购买订单，加买家内存.
        if($sellOrder["count_entrust"] > 0)
        {
            $this->_addOrderToCache($sellCatchKey, $cacheContainer, $sellOrder);
        }
    }
  
    /** 
     * @summary: 限价交易(撮合组件).
     * @order  : 订单数据.
     * @type   : 订单类型.
    */
    private function _transactionLimit($order, $type)
    {
        //分发缓存集群.
        switch($type)
        {
            case EOrderType::$BUY:
            
                $this->_buyTransactionLimit($order, $type);
            break;                       
            case EOrderType::$SELL:
            
                $this->_sellTransactionLimit($order, $type);                
            break;            
        }        
    }
    
     /** 
     * @summary     : 发送交易消息到队列.
     * @buyOrder    : 买家订单.
     * @sellOrder   : 卖家订单.
    */
    private function _sendTransactionMsg($buyOrder, $sellOrder)
    {
        $buyMsgKey      = $this->_getMsgMQKey();
        $sellMsgKey     = $this->_getMsgMQKey(1);                                      
        
        //1.MQ加反馈消息.
        $msg = "订单交易成功, 成交价(" . $sellOrder["price_entrust"] . "), 成交额(" . $sellOrder["count_entrust"] . ") 时间:" . date('Y-m-d H:i:s',time());
        
        $this->_addMsgToMQ($buyMsgKey,  ["user_id" => $buyOrder["user_id"],  "Msg" => $msg]);
        $this->_addMsgToMQ($sellMsgKey, ["user_id" => $sellOrder["user_id"], "Msg" => $msg]);
    }                                
    
    //市价交易.
    private function _transactionMarket($order, $type)
    {
        
    }
    
    public function testSequence($type, $order = array())
    {       
       echo $reVal;
       exit;
       
       var_dump($transactList);exit;
        
    }
    
    /** 
     * @summary : 订单入库.
     * @type    : 订单类型.
     * @order   : 订单数据.
    */
    private function _addOrderToDB($type, $order = array())
    {
        $reVal = false;
        
        $IM = new \MatchEngine\Model\BaseModel(C("DB_IM"));
        $tableName;
        $sql;        
        switch($type)
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
            $reVal = $IM->insert($tableName, $order, true);
        }
        catch(Exception $ex)
        {
            
        }
        
        return $reVal;
    }
    
    /** 
     * @summary : 从数据库中获取交易详情.
     * @type    : 订单类型.
     * 
    */
    private function _getCacheFromDB($type)
    {
        $IM = new \MatchEngine\Model\BaseModel(C("DB_IM"));
        $tableName;
        $sqlOrderBy;
        
        switch($type)
        {
            case EOrderType::$BUY:
                $tableName  = "order_buy";
                $sqlOrderBy =" order by price_entrust desc, create_time asc";
            break;
            
            case EOrderType::$SELL:
                $tableName = "order_sell";
                $sqlOrderBy =" order by price_entrust asc, create_time asc";
            break;
            
            default:
            break;
        }
        
        $sql  = "select id, user_id, price_entrust, count_entrust, create_time, status from " . $tableName;
        $sql .= " where status=" . EOrderStatus::$Wailt;
        $sql .= $sqlOrderBy;
        
        $transactList = $IM->query($sql);
        
        return $transactList;
    }
    
    /** 
     * @summary         : 订单原子多播定序.
     * @matchResult     : 撮合结果true 成功，false失败.
     * @catchObj        : 缓存实例.
     * @catchKey        : 缓存Key.
     * @cacheContainer  : 交易行情数据.
     * @order           : 交易行情数据.
     * 
    */
    private function _addOrderToCache($catchKey, $cacheContainer, $order, $type)
    {
        if($order)
        {
            //读取缓存 如果没有则读库.
            $transactList = $cacheContainer->hget($catchKey);           
            if(!$transactList)
            {
                $transactList = $this->_getCacheFromDB($type, $order);
                
                foreach($transactList as $key => $val)
                {
                    $tempList = [];
                    if($tempList = $cacheContainer->hget($catchKey, $val["price_entrust"]))
                    {
                        $tempList = json_decode($tempList);                        
                    }
                    $tempList[] = $val;
                    $cacheContainer->hset($catchKey, $val["price_entrust"], json_encode($tempList));
                }          
            }
            else
            {
                $tempList[] = $order;
                $cacheContainer->hset($catchKey, $order["price_entrust"], json_encode($tempList));
            }
        }
        
    } 
    
    //释放缓存实例.
    private function _clearCache($cacheContainer, $type)
    {
        switch($type)
        {
            case EOrderType::BUY:
                $cacheContainer->clearBuyRedis();
            break;
            case EOrderType::SELL:
                $cacheContainer->clearSellRedis();
            break;
        }        
    }
    
    //交易记录写日志(同步).
    private function _addTransactLog($msg)
    {
        \MatchEngine\Common\Log::write($msg);
    }
    
    public function aa()
    {
        $IM = new \MatchEngine\Model\BaseModel(C("DB_IM"));
        
        $datas = $IM->table("order_buy")->find();        
        
        //user_info currency
        
        //currency_stream order_buy order_buy_finish order_sell order_sell_finish       
      
        var_dump($datas);exit;
        
        
      
       
    }
                
       
     
}
