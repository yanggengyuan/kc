<?php

namespace MatchEngine\Common\Match;

use Think\Model;
use Think\Exception;
use MatchEngine\Common\Enum\EOrderType;
use MatchEngine\Common\Enum\EOrderStatus;
use MatchEngine\Common\Enum\EOperation;
use MatchEngine\Common\Enum\ETransactionType;
use MatchEngine\Common\Match\GetWayDistribute;
use MatchEngine\Common\Match\PeripheralMatch;

/**
  * @summary 撮合引擎[算法IM币].
  * @author  Henry.
  * @version 1.0
  * @date    2018.03.22
  */
class AlgorithmIMC implements IAlgorithm
{   
    
    private static $_Instance;
    
    //货币类型.
    private static $_currencyType;  
    //撮合外围逻辑实例.
    private static $_peripheralMatch; 
    //网关分发缓存服务集群.
    private static $_getWay; 
    
    private function __construct()
    {
        self::$_getWay          = GetWayDistribute::getInstance(self::$_currencyType);
        self::$_peripheralMatch = PeripheralMatch::getInstance(self::$_currencyType);
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
     * @summary: 运行撮合算法[IMC币].
     * @order  : 订单数据.
     * @type   : 订单类型.
    */
    public function runAlgorithm($order)
    {        
        if($order && $order["transaction_type"] != null)
        {
            switch($order["transaction_type"])
            {
                case ETransactionType::$LimitPrice:               
                    $this->_transactionLimit($order);
                break;                       
                case ETransactionType::$MarketPrice:                
                    $this->_transactionMarket($order);                
                break;            
            }  
        }
    }
    
    /** 
     * @summary: 买家交易[市价].
     * @order  : 订单数据.
     * @type   : 订单类型.
    */
    private function _buyTransactionMarket($order)
    {
        
    }
    
    /** 
     * @summary: 卖家交易[市价].
     * @order  : 订单数据.
     * @type   : 订单类型.
    */
    private function _sellTransactionMarket($order)
    {
        
    }
  
    /** 
     * @summary: 撮合交易[市价].
     * @order  : 订单数据.
     * @type   : 订单类型.
    */
    private function _transactionMarket($order)
    {
        $transactionType = $order['transaction_type'];
        switch($transactionType)
        {
            case EOrderType::$BUY:
            
                $this->_buyTransactionLimit($order);
            break;                       
            case EOrderType::$SELL:
            
                $this->_sellTransactionLimit($order);                
            break;            
        }    
    }
    
    /** 
     * @summary: 撮合交易[限价].
     * @order  : 订单数据.
     * @type   : 订单类型.
    */
    private function _transactionLimit($order)
    {
        $transactionType = $order['order_type'];        
        switch($transactionType)
        {
            case EOrderType::$BUY:         
                $this->_buyTransactionLimit($order);
            break;                       
            case EOrderType::$SELL:            
                $this->_sellTransactionLimit($order);
            break;        
        }        
    }
    
    /**
     * array_column() // 不支持低版本;
     * 以下方法兼容PHP低版本
     */
    function _array_column(array $array, $column_key, $index_key=null)
    {
        $result = [];
        foreach($array as $arr) 
        {
            if(!is_array($arr)) 
            continue;
    
            if(is_null($column_key))
            {
                $value = $arr;
            }else
            {
                $value = $arr[$column_key];
            }    
            if(!is_null($index_key))
            {
                $key = $arr[$index_key];
                $result[$key] = $value;
            }else
            {
                $result[] = $value;
            }
        }
        return $result; 
    }

    /** 
     * @summary         : 买家交易(限价).
     * @order           : 订单数据.
     * @transactionType : 交易类型.
    */
    private function _buyTransactionLimit($buyOrder)
    {
        
        //买家交易类型.
        $transactionType = EOrderType::$BUY;
        //卖家交易类型.
        $transactionTyUn = EOrderType::$SELL;
        //操作类型.
        $operationType   = EOperation::$TRANSACTION;
        //由网关分发过来的 缓存对象实例.
        $cacheObj        = self::$_getWay->connectTransactionRedis($transactionType, $operationType);
        //购买差价               
        $diffPrice       = 0;        
        //买家委托价格.              
        $buyPrice        = (float)$buyOrder["price_entrust"];    
        //卖家列表(升序).
        $sellOrderList   = self::$_peripheralMatch->getTransactionOrderFromCache($cacheObj, $transactionTyUn, $operationType, 1); 
        $sellOrderCount  = count($sellOrderList);
        
        for ($i = 0;$i < $sellOrderCount; $i++)
        {
            //卖家同价订单列表.
            $sellOrderSubList = json_decode($sellOrderList[$i], true);
            
            $sellOrder        = $sellOrderSubList[0];            
            
            //买价 >= 卖家委托价(成交).
            if($buyPrice >= $sellOrder['price_entrust'])
            {
                //时间升序.
                array_multisort($this->_array_column($sellOrderSubList, 'create_time'), SORT_ASC, $sellOrderSubList);
                //卖家单量
                $sellOrderSubCount = count($sellOrderSubList);
                
                //按同价卖家时间升序 买入.
                for ($j = 0;$j < $sellOrderSubCount; $j++)
                {
                    $buyFlow    = $buyOrder;
                    $sellFlow   = $sellOrderSubList[$j];
                    //余量.
                    //bcadd(1,1,6);count_entrust
                    $buyOrder["count_entrust"] = (float)$buyOrder["count_entrust"] - ((float)$sellFlow["count_entrust"]);
                    
                    echo " 余量= ";
                    var_dump($buyOrder["count_entrust"]);
                    
                    
                    //买<卖, 减量存.
                    if ($buyOrder["count_entrust"] < 0)
                    {
                        //余量(卖).
                        $sellOrderSubList[$j]["count_entrust"] = abs($buyOrder["count_entrust"]);
                        
                        //存入卖家订单交易完成队列 (等待代理服务异步处理 数据持久化).                            
                        $sellFlow['count_entrust'] = (float)$buyOrder["count_entrust"];   
                                                
                        self::$_peripheralMatch->addTransactionFlowToMQ($transactionTyUn, $sellFlow);                            
                        self::$_peripheralMatch->addTransactionFlowToMQ($transactionType, $buyFlow);
                        
                        //交易信息反馈. 
                        self::$_peripheralMatch->sendTransactionMsg($buyOrder, $sellOrderSubList[$j]);
                         
                    }//买>卖(删存).
                    else
                    {
                        unset($sellOrderSubList[$j]);
                        echo "删除卖家内存";
                        echo count($sellOrderSubList);
                        //无量则删.
                        if(count($sellOrderSubList) == 0)
                        {
                            self::$_peripheralMatch->delOrderFromCache($cacheObj, $transactionTyUn, $sellOrder['price_entrust']);
                        }
                        
                        self::$_peripheralMatch->addTransactionFlowToMQ($transactionTyUn, $sellFlow); 
                        
                        $buyFlow['count_entrust'] = $sellFlow['count_entrust'];
                        self::$_peripheralMatch->addTransactionFlowToMQ($transactionType, $buyFlow);
                    }
                    exit;
                    //更新内存(卖).
                    self::$_peripheralMatch->updateOrderToCache($cacheObj, $transactionTyUn, $sellOrder['price_entrust'], $sellOrderSubList); 
                    
                    //如果有返还额.
                    if($diffPrice = $buyPrice - (double)$sellOrder['price_entrust'])
                    {
                        //返款到买家钱包MQ.(仅买家有返款).
                        self::$_peripheralMatch->addPurseToMQ($transactionType, ["uid" => $buyOrder["user_id"], "amount" => $diffPrice]);
                    }
                    
                    //买家订单交易完成.
                    if($buyOrder["count_entrust"] <= 0)
                    {
                        self::$_peripheralMatch->addSuccessOrderToMQ($transactionType, $buyOrder);
                        
                        break;
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
            self::$_peripheralMatch->addOrderToCache($cacheObj, $buyOrder, $transactionType);
        }
    }
    
    /** 
     * @summary: 卖家交易(限价).
     * @order  : 订单数据.
     * @type   : 订单类型.
    */
    private function _sellTransactionLimit($sellOrder)
    {
       //买家交易类型.
        $transactionType = EOrderType::$SELL;
        //卖家交易类型.
        $transactionTyUn = EOrderType::$BUY;
        //操作类型.
        $operationType   = EOperation::$TRANSACTION;
        //由网关分发过来的 缓存对象实例.
        $cacheObj        = self::$_getWay->connectTransactionRedis($transactionType, $operationType);
        //购买差价               
        $diffPrice       = 0;        
        //买家委托价格.              
        $buyPrice        = (float)$sellOrder["price_entrust"];    
        //卖家列表(升序).
        $sellOrderList   = self::$_peripheralMatch->getTransactionOrderFromCache($cacheObj, $transactionType, $operationType, 1); 
         
        $sellOrderCount  = count($sellOrderList);
        
        for ($i = 0;$i < $sellOrderCount; $i++)
        {
            $sellOrder = $sellOrderList[$i];
            
            //买价 >= 卖家委托价(成交).
            if($buyPrice >= $sellOrder['price_entrust'])
            {
                //卖家同价订单列表.
                if($sellOrderSubList  = self::$_peripheralMatch->getTransactionOrderSubFromCache($cacheObj, $transactionType, $sellOrder['price_entrust']))
                {
                    $sellOrderSubList = json_decode($sellOrderSubList); 
                    //时间升序.
                    $sellOrderSubList = array_multisort(array_column($sellOrderSubList, 'create_time'), SORT_ASC, $sellOrderSubList);
                    //卖家单量
                    $sellOrderSubCount  = count($sellOrderSubList);
                    
                    //按同价卖家时间升序 买入.
                    for ($j = 0;$j < $sellOrderSubCount; $j++)
                    {
                        $buyFlow    = $sellOrder;
                        $sellFlow   = $sellOrderSubList[$j];
                        
                        //余量.
                        $sellOrder["count_entrust"] = (float)$sellOrder["count_entrust"] - ((float)$sellOrderSubList[$j]["count_entrust"]);
                  
                        //买<卖, 减量存.
                        if ($sellOrder["count_entrust"] < 0)
                        {
                            //余量(卖).
                            $sellOrderSubList[$j]["count_entrust"] = abs($sellOrder["count_entrust"]);
                            
                            //存入卖家订单交易完成队列 (等待代理服务异步处理 数据持久化).                            
                            $sellFlow['count_entrust'] = (float)$sellOrder["count_entrust"];   
                                                    
                            self::$_peripheralMatch->addTransactionFlowToMQ($transactionTyUn, $sellFlow);                            
                            self::$_peripheralMatch->addTransactionFlowToMQ($transactionType, $buyFlow);
                            
                            //交易信息反馈. 
                            self::$_peripheralMatch->sendTransactionMsg($sellOrder, $sellOrderSubList[$j]);
                             
                        }//买>卖(删存).
                        else
                        {
                            unset($sellOrderSubList[$j]);
                            
                            //无量则删.
                            if(count($sellOrderSubList) == 0)
                            {
                                self::$_peripheralMatch->delOrderFromCache($cacheObj, $transactionTyUn, $sellOrder['price_entrust']);
                            }
                            
                            self::$_peripheralMatch->addTransactionFlowToMQ($transactionTyUn, $sellFlow); 
                            
                            $buyFlow['count_entrust'] = $sellFlow['count_entrust'];
                            self::$_peripheralMatch->addTransactionFlowToMQ($transactionType, $buyFlow);
                        }
                        
                        //更新内存(卖).
                        self::$_peripheralMatch->updateOrderToCache($cacheObj, $transactionTyUn, $sellOrder['price_entrust'], $sellOrderSubList); 
                        
                        //如果有返还额.
                        if($diffPrice = $buyPrice - (double)$sellOrder['price_entrust'])
                        {
                            //返款到买家钱包MQ.(仅买家有返款).
                            self::$_peripheralMatch->addPurseToMQ($transactionType, ["uid" => $sellOrder["user_id"], "amount" => $diffPrice]);
                        }
                        
                        //买家订单交易完成.
                        if($sellOrder["count_entrust"] <= 0)
                        {
                            self::$_peripheralMatch->addSuccessOrderToMQ($transactionType, $sellOrder);
                            
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
            self::$_peripheralMatch->addOrderToCache($cacheObj, $sellOrder, $transactionType);
        }
    }

     
}
