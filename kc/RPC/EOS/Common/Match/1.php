<?php

namespace RPC\Common\Match;

use Think\Model;
use Think\Exception;
use RPC\Common\BourseRedis;
use MatchRPCEngine\Common\Enum\EOrderType;
use MatchRPCEngine\Common\Enum\EOrderStatus;

/**
  * @summary �������[��Χ�߼�����].
  * @author  Henry.
  * @version 1.0
  * @date    2018.03.25.
  */
class PeripheralMatch
{   
    
    private static $_Instance;
    
    //��������.
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
     * @summary : ���[�쳣����]����.
     * @type    : ��������.
    */
    private function _addExceptTransactToMQ($order, $type)
    { 
        $orderMq = BourseRedis::getInstance(self::_currencyType);        
        $orderMq->connectBuyMQ();
        
        $orderMq->rpush($this->_getExceptTransactMQKey($type), $order);
        
        return $orderMq;
    }   
     
    /** 
     * @summary : ���[��������]����(����).
     * @type    : ��������.
    */
    private function _addOrderToMQ($order, $type)
    { 
        $orderMq = BourseRedis::getInstance(self::_currencyType);        
        $orderMq->connectBuyMQ();
        
        $orderMq->rpush($this->_getMQKey($type), $order);
        
        return $orderMq;
    }   
    
    /** 
     * @summary : ���[���׳ɹ�]���� (�ȴ���������첽���ݳ־û�)..
     * @type    : ��������.
    */
    private function _addSuccessOrderToMQ($key, $order)
    { 
        $orderMq = BourseRedis::getInstance(self::_currencyType);        
        $orderMq->connectBuyMQ();
        
        $orderMq->rpush($key, $order);
        
        return $orderMq;
    } 
    
    /** 
     * @summary : ���[������Ϣ]����.
     * @type    : ��������.
    */
    private function _addMsgToMQ($key, $Msg)
    { 
        $orderMq = BourseRedis::getInstance(self::_currencyType);        
        $orderMq->connectBuyMQ();
        
        $orderMq->rpush($key, $Msg);
        
        return $orderMq;
    } 
    
    /** 
     * @summary : ���[Ǯ��]����.
     * @type    : ��������.
    */
    private function _addPurseToMQ($purse, $type)
    { 
        $orderMq = BourseRedis::getInstance(self::_currencyType);        
        $orderMq->connectBuyMQ();
        
        $orderMq->rpush($this->_getAddPurseMQKey($type), $purse);
        
        return $orderMq;
    }  
    
    /** 
     * @summary : ��ϴ���.
     * @type    : ��������.
    */
    public function matchDo($order, $type)
    {        
        //У��
        if(!$this->_checkParams($order))
            return false;
        
        //��������.  
        $orderMq = $this->_addOrderToMQ($order, $type);
        //��������.
        $order = $orderMq->lpop($this->_getMQKey($type));        
        //�������.
        $reVal = $this->_addOrderToDB($type, $order);
        
        if($reVal)
        {
            //�������.
            $matchResult = $this->_matchMake($order, $type);
            
            if($matchResult)
            {
                //��ӵ����׳ɹ�����������.
                $this->_addTransactSuccessOrderToMQ($order, $type);
                //������Ϣ����.   
                $this->_addSuccessMsgToMQ($order, $type);
            }

            //���½�������.
            $this->_updateMarket($matchResult, $order, $type);        
        }
        else
        {
            $exMsg = "�û�UID" . $order["user_id"] . "�Ķ������ʧ�ܣ�" . json_encode($order);
            $this->_addTransactLog($exMsg);
        }
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
     * @summary : ��ӽ��׳ɹ���Ϣ������.
     * @type    : ��������.
    */
    private function _addSuccessMsgToMQ($order, $type)
    {
        switch($type)
        {
            case EOrderType::$BUY:
            //���򶩵����׳ɹ� ������Ϣ���.
            
            break;
            
            case EOrderType::$SELL:
            //�����������׳ɹ� ������Ϣ���.
            break;
            
        }
    }
    
    /** 
     * @summary : ��ӽ��׳ɹ�����������, �ȴ��첽������� ���ݳ־û�.
     * @type    : ��������.
    */
    private function _addTransactSuccessOrderToMQ($order, $type)
    {
        
        switch($type)
        {
            case EOrderType::$BUY:
            //���򶩵����׳ɹ� ���.
            
            break;
            
            case EOrderType::$SELL:
            //�����������׳ɹ� ���.
            break;
            
        }
    }
    
    /** 
     * @summary : ��ӽ���ʧ�ܶ���������, �ȴ��첽���������.
     * @type    : ��������.
    */
    private function _addFailMQ($order, $type)
    {
        
        switch($type)
        {
            case EOrderType::$BUY:
            //���򶩵�����ʧ�� ���.
            
            break;
            
            case EOrderType::$SELL:
            //������������ʧ�� ���.
            break;
            
        }
    }
        
    /** 
     * @summary : �������.
     * @type    : ��������.
    */
    private function _matchMake($order, $type)
    {
        $reResult = false;
        
        try
        {
            //���ô�����. 
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
           
            //ͬ����¼���׳ɹ�����־�ļ�.
            $exMsg = "�û�UID" . $order["user_id"] . $type == EOrderType::$BUY ? "����" : "����" . " �������׳ɹ���" . json_encode($order);
            $this->_addTransactLog($exMsg);
            
            $reResult = true;          
        }
        catch(Exception $ex)
        {
            //ͬ����¼�쳣��־              
            $exMsg = "�û�UID" . $order["user_id"] . $type == EOrderType::$BUY ? "����" : "����" . " ���������쳣��" . json_encode($order);
            $this->_addTransactLog($exMsg);
            //���쳣����.
            $this->_addFailMQ($order, $type);
        }
        
        return $reResult;
        
    }       
   
     /** 
     * @summary     : ���½�������.
     * @matchResult : ��Ͻ��true �ɹ���falseʧ��.
     * @order       : ��������.
     * @type        : ��������.    
    */
    private function _updateMarket($matchResult, $order, $type)
    {        
        $cacheContainer; //��������.
        $transactList;   //��������.              
        
        //��������Key.
        $catchKey = $this->_getCatchKey($type); 
        
        //�ַ����漯Ⱥ.
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
            
            //����С������.(�޼� �м� ��������)
            
            //�� > ��(�޼�)
            
            //�� < ��(�޼�)
            
            
            //���׷��������б�.
            $orderList = $cacheContainer->hget($reverseCatchKey, $order["price_entrust"]);
            $orderList = json_decode($orderList);
         
            //����ʱ������.
            $createTimeList = array_column($orderList, 'create_time');
            $orderList      = array_multisort($createTimeList, SORT_ASC, $orderList);
            
            //���������.
            foreach($orderList as $key => $val)
            {
                //����������.                
                $orderCount = ((float)$order['count_entrust']) - ((float)$val['count_entrust']);
                if($orderCount >= 0)
                {
                    unset($val);
                }
                else
                {
                   $orderList[$key]["count_entrust"] = abs($orderCount);
                }
                //���½�������.
                $cacheContainer->hset($reverseCatchKey, $order["price_entrust"], json_encode($orderList));
                
                //���뷢����Ϣ����.                
            }            
        }
        else
        {
            //���ڴ�.
            $this->_addOrderToCache($catchKey, $cacheContainer, $order);            
        }
        
        //�ͷŻ���ʵ��.
        $this->_clearCache($cacheContainer, $type);
        
    }    
    
    /** 
     * @summary: ��ҽ���(�޼�).
     * @order  : ��������.
     * @type   : ��������.
    */
    private function _buyTransactionLimit($buyOrder, $type)
    {
        //���Key.
        $buyCatchKey     = $this->_getCatchKey($type); 
        //����Key.
        $sellCatchKey    = $this->_getCatchKey($type, true);        
        $cacheContainer  = BourseRedis::getInstance(self::_currencyType)->connectBuyRedis();        
        //ί�м۸�.              
        $entrustPrice    = (float)$buyOrder["price_entrust"];        
        //���               
        $diffPrice       = 0;        
        //ɸѡ�ɽ��׵�Key����.
        $sellCatcheKeys  = $cacheContainer->hKeys($sellCatchKey);
        //����۸�key.
        $sellCatcheKeys  = sort($sellCatcheKeys);
        $sellCount       = count($sellCatcheKeys); 
        
        //��������� ��ʼ������.
        for ($i = 0;$i < $sellCount; $i++)
        {
             $sellCatchePrice = $sellCatcheKeys[$i];
             
             //ί�м� >= ���ҵ���(�ɽ�����).
             if ($entrustPrice >= $sellCatchePrice)
             {
                if($sellOrderList   = $cacheContainer->hget($sellCatchKey, $sellCatchePrice))
                 {
                    $sellOrderList  = json_decode($sellOrderList);                     
                    //ʱ������.
                    $createTimeList = array_column($sellOrderList, 'create_time');
                    $sellOrderList  = array_multisort($createTimeList, SORT_ASC, $sellOrderList);
                    $buyOrderCount  = count($sellOrderList);
                    
                    //�ȼ����Ҷ�����ʱ������ ����.
                    for ($j = 0; $j < $buyOrderCount; $j++)
                    {
                        //����.
                        $buyOrder["count_entrust"] = (float)$buyOrder["count_entrust"] - ((float)$sellOrderList[$j]["count_entrust"]);
                        
                        //ÿһ���������׶�, ���ͷ�����Ϣ.
                        $this->_sendTransactionMsg($buyOrder, $sellOrderList[$j]);                        
                        
                        //��<��, ������.
                        if($buyOrder["count_entrust"] < 0)
                        {
                            $sellOrderList[$j]["count_entrust"] = abs($buyOrder["count_entrust"]);
                        }
                        
                        //�������Ҷ���������ɶ��� (�ȴ���������첽���� ���ݳ־û�).
                        $sellSuccesskey = $this->_getSuccessMQKey($type, 1);
                        $this->_addSuccessOrderToMQ($sellSuccesskey, $sellOrderList[$j]);
                            
                        //��>��, ɾ��.
                        if($buyOrder["count_entrust"] >= 0)
                        {                            
                            unset($sellOrderList[$j]);
                            
                            //�������� �ͷ��ڴ�.
                            if(count($sellOrderList) == 0)
                            {
                                $cacheContainer->hdel($sellCatchKey, $sellCatchePrice);
                            }
                        }
                        
                        //�����ڴ�(��).
                        $cacheContainer->hset($sellCatchKey, $sellCatchePrice, json_encode($sellOrderList));  
                        
                        //����з�����.
                        if($diffPrice = $entrustPrice - $sellCatchePrice)
                        {                                    
                            //����û�Ǯ��MQ.(������з���)
                            $this->_addPurseToMQ(["user_id" => $buyOrder["user_id"], "retreats" => $diffPrice], $type);
                        }
                        
                        //��Ҷ����������.
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
        
        //��δ��ɹ��򶩵���������ڴ�.
        if($buyOrder["count_entrust"] > 0)
        {
            $this->_addOrderToCache($buyCatchKey, $cacheContainer, $buyOrder);
        }
    }
    
    /** 
     * @summary: ���ҽ���(�޼�).
     * @order  : ��������.
     * @type   : ��������.
    */
    private function _sellTransactionLimit($sellOrder, $type)
    {
        //����key.
        $sellCatchKey   = $this->_getCatchKey($type); 
        //���key.
        $buyCatchKey    = $this->_getCatchKey($type, true);  
             
        $cacheContainer = BourseRedis::getInstance(self::_currencyType)->connectSellRedis();        
        //ί�м۸�.
        $sellPrice      = (float)$sellOrder["price_entrust"];        
        //���               
        $diffPrice;
        //ɸѡ�ɽ��׵�Key����.
        $catcheKeys     = $cacheContainer->hKeys($buyCatchKey);
        //����۸�key.
        $catcheKeys     = rsort($catcheKeys);
        $buyCount       = count($catcheKeys); 
        
        //�������� ��ʼ�������.
        for ($i = 0;$i < $buyCount; $i++)
        {
             $buyPrice = $catcheKeys[$i];
             
             //ί�м� <= ��ҵ���(�ɽ�����).
             if ($sellPrice <= $buyPrice)
             {
                if($buyOrderList    = $cacheContainer->hget($buyCatchKey, $buyPrice))
                 {
                    $buyOrderList   = json_decode($buyOrderList);
                    //ʱ������.
                    $createTimeList = array_column($buyOrderList, 'create_time');
                    $buyOrderList   = array_multisort($createTimeList, SORT_ASC, $buyOrderList);
                    $buyOrderCount  = count($buyOrderList);
                    
                    //�ȼ���Ҷ�����ʱ������ ����.
                    for ($j = 0; $j < $buyOrderCount; $j++)
                    {
                        //����.
                        $sellOrder["count_entrust"] = (float)$sellOrder["count_entrust"] - ((float)$buyOrderList[$j]["count_entrust"]);
                        
                        //ÿһ���������׶�, ���ͷ�����Ϣ.
                        $this->_sendTransactionMsg($sellOrder, $buyOrderList[$j]);
                        
                        //��<��, ������.
                        if($sellOrder["count_entrust"] < 0)
                        {
                            $buyOrderList[$j]["count_entrust"] = abs($sellOrder["count_entrust"]);
                        }
                        
                        //�����ڴ�(��).
                        $cacheContainer->hset($buyCatchKey, $buyPrice, json_encode($buyOrderList)); 
                        
                        //������Ҷ���������ɶ��� (�ȴ���������첽���� ���ݳ־û�).
                        $buySuccessMQkey = $this->_getSuccessMQKey($type);
                        $this->_addSuccessOrderToMQ($buySuccessMQkey, $buyOrderList[$j]);
                            
                        //��>=��, ɾ��.
                        if($sellOrder["count_entrust"] >= 0)
                        {
                            unset($buyOrderList[$j]);
                            
                            //�������� �ͷ��ڴ�.
                            if(count($buyOrderList) == 0)
                            {
                                $cacheContainer->hdel($buyCatchKey, $buyPrice);
                            }
                        }                         
                                                         
                        //����û�Ǯ��MQ.(�����Ҽ����û����)
                        $this->_addPurseToMQ(["user_id" => $sellOrder["user_id"], "retreats" => $buyPrice], $type);
                                                
                        //���Ҷ����������.
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
        
        //��δ��ɹ��򶩵���������ڴ�.
        if($sellOrder["count_entrust"] > 0)
        {
            $this->_addOrderToCache($sellCatchKey, $cacheContainer, $sellOrder);
        }
    }
  
    /** 
     * @summary: �޼۽���(������).
     * @order  : ��������.
     * @type   : ��������.
    */
    private function _transactionLimit($order, $type)
    {
        //�ַ����漯Ⱥ.
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
     * @summary     : ���ͽ�����Ϣ������.
     * @buyOrder    : ��Ҷ���.
     * @sellOrder   : ���Ҷ���.
    */
    private function _sendTransactionMsg($buyOrder, $sellOrder)
    {
        $buyMsgKey      = $this->_getMsgMQKey();
        $sellMsgKey     = $this->_getMsgMQKey(1);                                      
        
        //1.MQ�ӷ�����Ϣ.
        $msg = "�������׳ɹ�, �ɽ���(" . $sellOrder["price_entrust"] . "), �ɽ���(" . $sellOrder["count_entrust"] . ") ʱ��:" . date('Y-m-d H:i:s',time());
        
        $this->_addMsgToMQ($buyMsgKey,  ["user_id" => $buyOrder["user_id"],  "Msg" => $msg]);
        $this->_addMsgToMQ($sellMsgKey, ["user_id" => $sellOrder["user_id"], "Msg" => $msg]);
    }                                
    
    //�м۽���.
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
     * @summary : �������.
     * @type    : ��������.
     * @order   : ��������.
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
     * @summary : �����ݿ��л�ȡ��������.
     * @type    : ��������.
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
     * @summary         : ����ԭ�Ӷಥ����.
     * @matchResult     : ��Ͻ��true �ɹ���falseʧ��.
     * @catchObj        : ����ʵ��.
     * @catchKey        : ����Key.
     * @cacheContainer  : ������������.
     * @order           : ������������.
     * 
    */
    private function _addOrderToCache($catchKey, $cacheContainer, $order, $type)
    {
        if($order)
        {
            //��ȡ���� ���û�������.
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
    
    //�ͷŻ���ʵ��.
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
    
    //���׼�¼д��־(ͬ��).
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
