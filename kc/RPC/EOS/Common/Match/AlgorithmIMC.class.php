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
  * @summary �������[�㷨IM��].
  * @author  Henry.
  * @version 1.0
  * @date    2018.03.22
  */
class AlgorithmIMC implements IAlgorithm
{   
    
    private static $_Instance;
    
    //��������.
    private static $_currencyType;  
    //�����Χ�߼�ʵ��.
    private static $_peripheralMatch; 
    //���طַ��������Ⱥ.
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
     * @summary: ���д���㷨[IMC��].
     * @order  : ��������.
     * @type   : ��������.
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
     * @summary: ��ҽ���[�м�].
     * @order  : ��������.
     * @type   : ��������.
    */
    private function _buyTransactionMarket($order)
    {
        
    }
    
    /** 
     * @summary: ���ҽ���[�м�].
     * @order  : ��������.
     * @type   : ��������.
    */
    private function _sellTransactionMarket($order)
    {
        
    }
  
    /** 
     * @summary: ��Ͻ���[�м�].
     * @order  : ��������.
     * @type   : ��������.
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
     * @summary: ��Ͻ���[�޼�].
     * @order  : ��������.
     * @type   : ��������.
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
     * array_column() // ��֧�ֵͰ汾;
     * ���·�������PHP�Ͱ汾
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
     * @summary         : ��ҽ���(�޼�).
     * @order           : ��������.
     * @transactionType : ��������.
    */
    private function _buyTransactionLimit($buyOrder)
    {
        
        //��ҽ�������.
        $transactionType = EOrderType::$BUY;
        //���ҽ�������.
        $transactionTyUn = EOrderType::$SELL;
        //��������.
        $operationType   = EOperation::$TRANSACTION;
        //�����طַ������� �������ʵ��.
        $cacheObj        = self::$_getWay->connectTransactionRedis($transactionType, $operationType);
        //������               
        $diffPrice       = 0;        
        //���ί�м۸�.              
        $buyPrice        = (float)$buyOrder["price_entrust"];    
        //�����б�(����).
        $sellOrderList   = self::$_peripheralMatch->getTransactionOrderFromCache($cacheObj, $transactionTyUn, $operationType, 1); 
        $sellOrderCount  = count($sellOrderList);
        
        for ($i = 0;$i < $sellOrderCount; $i++)
        {
            //����ͬ�۶����б�.
            $sellOrderSubList = json_decode($sellOrderList[$i], true);
            
            $sellOrder        = $sellOrderSubList[0];            
            
            //��� >= ����ί�м�(�ɽ�).
            if($buyPrice >= $sellOrder['price_entrust'])
            {
                //ʱ������.
                array_multisort($this->_array_column($sellOrderSubList, 'create_time'), SORT_ASC, $sellOrderSubList);
                //���ҵ���
                $sellOrderSubCount = count($sellOrderSubList);
                
                //��ͬ������ʱ������ ����.
                for ($j = 0;$j < $sellOrderSubCount; $j++)
                {
                    $buyFlow    = $buyOrder;
                    $sellFlow   = $sellOrderSubList[$j];
                    //����.
                    //bcadd(1,1,6);count_entrust
                    $buyOrder["count_entrust"] = (float)$buyOrder["count_entrust"] - ((float)$sellFlow["count_entrust"]);
                    
                    echo " ����= ";
                    var_dump($buyOrder["count_entrust"]);
                    
                    
                    //��<��, ������.
                    if ($buyOrder["count_entrust"] < 0)
                    {
                        //����(��).
                        $sellOrderSubList[$j]["count_entrust"] = abs($buyOrder["count_entrust"]);
                        
                        //�������Ҷ���������ɶ��� (�ȴ���������첽���� ���ݳ־û�).                            
                        $sellFlow['count_entrust'] = (float)$buyOrder["count_entrust"];   
                                                
                        self::$_peripheralMatch->addTransactionFlowToMQ($transactionTyUn, $sellFlow);                            
                        self::$_peripheralMatch->addTransactionFlowToMQ($transactionType, $buyFlow);
                        
                        //������Ϣ����. 
                        self::$_peripheralMatch->sendTransactionMsg($buyOrder, $sellOrderSubList[$j]);
                         
                    }//��>��(ɾ��).
                    else
                    {
                        unset($sellOrderSubList[$j]);
                        echo "ɾ�������ڴ�";
                        echo count($sellOrderSubList);
                        //������ɾ.
                        if(count($sellOrderSubList) == 0)
                        {
                            self::$_peripheralMatch->delOrderFromCache($cacheObj, $transactionTyUn, $sellOrder['price_entrust']);
                        }
                        
                        self::$_peripheralMatch->addTransactionFlowToMQ($transactionTyUn, $sellFlow); 
                        
                        $buyFlow['count_entrust'] = $sellFlow['count_entrust'];
                        self::$_peripheralMatch->addTransactionFlowToMQ($transactionType, $buyFlow);
                    }
                    exit;
                    //�����ڴ�(��).
                    self::$_peripheralMatch->updateOrderToCache($cacheObj, $transactionTyUn, $sellOrder['price_entrust'], $sellOrderSubList); 
                    
                    //����з�����.
                    if($diffPrice = $buyPrice - (double)$sellOrder['price_entrust'])
                    {
                        //������Ǯ��MQ.(������з���).
                        self::$_peripheralMatch->addPurseToMQ($transactionType, ["uid" => $buyOrder["user_id"], "amount" => $diffPrice]);
                    }
                    
                    //��Ҷ����������.
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
        
        //��δ��ɹ��򶩵���������ڴ�.
        if($buyOrder["count_entrust"] > 0)
        {
            self::$_peripheralMatch->addOrderToCache($cacheObj, $buyOrder, $transactionType);
        }
    }
    
    /** 
     * @summary: ���ҽ���(�޼�).
     * @order  : ��������.
     * @type   : ��������.
    */
    private function _sellTransactionLimit($sellOrder)
    {
       //��ҽ�������.
        $transactionType = EOrderType::$SELL;
        //���ҽ�������.
        $transactionTyUn = EOrderType::$BUY;
        //��������.
        $operationType   = EOperation::$TRANSACTION;
        //�����طַ������� �������ʵ��.
        $cacheObj        = self::$_getWay->connectTransactionRedis($transactionType, $operationType);
        //������               
        $diffPrice       = 0;        
        //���ί�м۸�.              
        $buyPrice        = (float)$sellOrder["price_entrust"];    
        //�����б�(����).
        $sellOrderList   = self::$_peripheralMatch->getTransactionOrderFromCache($cacheObj, $transactionType, $operationType, 1); 
         
        $sellOrderCount  = count($sellOrderList);
        
        for ($i = 0;$i < $sellOrderCount; $i++)
        {
            $sellOrder = $sellOrderList[$i];
            
            //��� >= ����ί�м�(�ɽ�).
            if($buyPrice >= $sellOrder['price_entrust'])
            {
                //����ͬ�۶����б�.
                if($sellOrderSubList  = self::$_peripheralMatch->getTransactionOrderSubFromCache($cacheObj, $transactionType, $sellOrder['price_entrust']))
                {
                    $sellOrderSubList = json_decode($sellOrderSubList); 
                    //ʱ������.
                    $sellOrderSubList = array_multisort(array_column($sellOrderSubList, 'create_time'), SORT_ASC, $sellOrderSubList);
                    //���ҵ���
                    $sellOrderSubCount  = count($sellOrderSubList);
                    
                    //��ͬ������ʱ������ ����.
                    for ($j = 0;$j < $sellOrderSubCount; $j++)
                    {
                        $buyFlow    = $sellOrder;
                        $sellFlow   = $sellOrderSubList[$j];
                        
                        //����.
                        $sellOrder["count_entrust"] = (float)$sellOrder["count_entrust"] - ((float)$sellOrderSubList[$j]["count_entrust"]);
                  
                        //��<��, ������.
                        if ($sellOrder["count_entrust"] < 0)
                        {
                            //����(��).
                            $sellOrderSubList[$j]["count_entrust"] = abs($sellOrder["count_entrust"]);
                            
                            //�������Ҷ���������ɶ��� (�ȴ���������첽���� ���ݳ־û�).                            
                            $sellFlow['count_entrust'] = (float)$sellOrder["count_entrust"];   
                                                    
                            self::$_peripheralMatch->addTransactionFlowToMQ($transactionTyUn, $sellFlow);                            
                            self::$_peripheralMatch->addTransactionFlowToMQ($transactionType, $buyFlow);
                            
                            //������Ϣ����. 
                            self::$_peripheralMatch->sendTransactionMsg($sellOrder, $sellOrderSubList[$j]);
                             
                        }//��>��(ɾ��).
                        else
                        {
                            unset($sellOrderSubList[$j]);
                            
                            //������ɾ.
                            if(count($sellOrderSubList) == 0)
                            {
                                self::$_peripheralMatch->delOrderFromCache($cacheObj, $transactionTyUn, $sellOrder['price_entrust']);
                            }
                            
                            self::$_peripheralMatch->addTransactionFlowToMQ($transactionTyUn, $sellFlow); 
                            
                            $buyFlow['count_entrust'] = $sellFlow['count_entrust'];
                            self::$_peripheralMatch->addTransactionFlowToMQ($transactionType, $buyFlow);
                        }
                        
                        //�����ڴ�(��).
                        self::$_peripheralMatch->updateOrderToCache($cacheObj, $transactionTyUn, $sellOrder['price_entrust'], $sellOrderSubList); 
                        
                        //����з�����.
                        if($diffPrice = $buyPrice - (double)$sellOrder['price_entrust'])
                        {
                            //������Ǯ��MQ.(������з���).
                            self::$_peripheralMatch->addPurseToMQ($transactionType, ["uid" => $sellOrder["user_id"], "amount" => $diffPrice]);
                        }
                        
                        //��Ҷ����������.
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
        
        //��δ��ɹ��򶩵���������ڴ�.
        if($sellOrder["count_entrust"] > 0)
        {
            self::$_peripheralMatch->addOrderToCache($cacheObj, $sellOrder, $transactionType);
        }
    }

     
}
