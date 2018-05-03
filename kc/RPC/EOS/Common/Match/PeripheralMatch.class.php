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
  * @summary �������[��Χ�߼�����].
  * @author  Henry.
  * @version 1.0
  * @date    2018.03.25.
  */
class PeripheralMatch
{   
    
    private static $_Instance, $_everyCount = 200, $_showRows = 10;
    
    //��������.
    private static $_currencyType;
    //���طַ��������Ⱥ.
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
     * @summary         : ���Ԥ��[�������].
     * @transactionType : ��������(0��, 1��).
     * @order           : ��������.
     * @result          : Ԥ�� 1�ɹ�, 2ʧ��.
    */
     public function matchPrepare($order)
     {
        $reVal   = false;  
        $transactionType = $order['order_type'];
        unset($order['order_type']);
        $key     = self::$_getWay->getRedisKey($transactionType, EOperation::$UNSEQUENCE, 0, 1);
        
        //��������(����).
        $this->_addUnSequenceMQ($transactionType, $order);       
        
        /************ ִ�ж��г�Ա��� **************/
        
        $redisMQ = self::$_getWay->connectTransactionRedis($transactionType, EOperation::$UNSEQUENCE, 1);
        
        //��δִ�ж��г�Ա.
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
                    //�������.
                    $reVal   = $this->_addOrderToDB($transactionType, $data);
                    
                    if($reVal)
                    {                        
                        //ִ�гɹ��Ƴ�����.
                        $redisMQ->lpop($key);
                    }
                    else
                    {
                        echo ' ���ʧ�� ';
                        //�����쳣����.
                    }
                }
            }
        }
        
        return $reVal;
     }
     
     /** 
     * @summary         : ���[ʱ��˳����]����.
     * @transactionType : ��������(0��, 1��).    
     * @order           : ��������.
    */
    private function _addUnSequenceMQ($transactionType, $order)
    {
        return self::$_getWay->addRedis($transactionType, EOperation::$UNSEQUENCE, $order, 0, 1);
    }
    
    /** 
     * @summary         : ���[�쳣����]����.
     * @transactionType : ��������(0��, 1��).    
     * @order           : ��������.
    */
    public function addExceptTransactToMQ($transactionType, $order)
    { 
        return self::$_getWay->addRedis($transactionType, EOperation::$EXCEPTION, $order, 0, 1);
    }
     
    /** 
     * @summary         : ���[��������]����(����).
     * @transactionType : ��������(0��, 1��).
     * @order           : ��������.
    */
    public function addOrderToMQ($transactionType, $order)
    { 
        return self::$_getWay->addRedis($transactionType, EOperation::$UNSEQUENCE, $order, 0, 1);
    }   
    
    /** 
     * @summary         : ���[���׳ɹ�]���� (�ȴ���������첽���ݳ־û�).
     * @transactionType : ��������(0��, 1��).   
     * @order           : ��������.
    */
    public function addSuccessOrderToMQ($transactionType, $order)
    { 
        return self::$_getWay->addRedis($transactionType, EOperation::$SUCCESS, $order, 0, 1);
    }
    
    /** 
     * @summary         : ���[������ˮ]���� (�ȴ���������첽���ݳ־û�).
     * @transactionType : ������ˮ����(0��, 1��).    
     * @order           : ��������.
    */
    public function addTransactionFlowToMQ($transactionType, $order)
    { 
        return self::$_getWay->addRedis($transactionType, EOperation::$FLOW, $order, 0, 1);
    }
    
    /** 
     * @summary         : ���[Ǯ��]����.
     * @transactionType : ��������(0��, 1��).   
     * @order           : ��������.
    */
    public function addPurseToMQ($transactionType, $Data)
    { 
        return self::$_getWay->addRedis($transactionType, EOperation::$PURSE, $Data, 0, 1);        
    }
    
    /** 
     * @summary         : ���[Ǯ��]����.
     * @transactionType : ��������(0��, 1��).   
     * @order           : ��������.
    */
    public function getPurseMQ($transactionType, $Data)
    { 
        return self::$_getWay->getRedis($transactionType, EOperation::$PURSE, 0, 1);
    }
   
    /** 
     * @summary     : ���ͽ�����Ϣ������.
     * @buyOrder    : ��Ҷ���.
     * @sellOrder   : ���Ҷ���.
    */
    public function sendTransactionMsg($buyOrder, $sellOrder)
    {
        $buyMsgKey  = self::$_getWay->getMsgMQKey(EOrderType::$SELL);
        $sellMsgKey = self::$_getWay->getMsgMQKey(EOrderType::$BUY);        
        
        $this->addSuccessMsgToMQ(EOrderType::$BUY, $buyOrder);
        $this->addSuccessMsgToMQ(EOrderType::$SELL, $sellOrder);
    }
    
    /** 
     * @summary         : ���[���׳ɹ�]���� (�ȴ���������첽���ݳ־û�).
     * @transactionType : ��������(0��, 1��).    
     * @order           : ��������.
    */
    public function addSuccessMsgToMQ($transactionType, $order)
    {
        //1.MQ�ӷ�����Ϣ.
        $msg = "�������׳ɹ�, �ɽ���(" . $order["price_entrust"] . "), �ɽ���(" . $order["count_entrust"] . ") ʱ��:" . date('Y-m-d H:i:s',time());
        
        return self::$_getWay->addRedis($transactionType, EOperation::$MSG, ["user_id" => $order['user_id'], "msg" => $msg], 0, 1);
        
    }
    
    /****************************** DataBase ************************************/
    
    /** 
     * @summary         : �����û����.
     * @transactionType : ��������(0��, 1��).
     * @data            : ��������.
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
            $exMsg  = " �û�UID" . $order["user_id"] . " ����״̬����ʧ�ܣ�" . json_encode($order);
            $exMsg .= " �쳣��Ϣ : " . $ex->getMessage();
            
            $this->addTransactLog($exMsg);
        }
       
        return $reVal;
    }
    
    /** 
     * @summary         : ���¶���״̬(�ɹ�, ����).
     * @transactionType : ��������(0��, 1��).
     * @order           : ��������.
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
                    $msg  = "�Ƿ����� �ɽ��� < �����==>>" . json_encode($order);
                    $this->addTransactLog($msg);
                    return false;
                }
            break;
            
            case EOrderType::$SELL:                
                if($order['price_deal'] < $dbOrder['price_entrust'])
                {
                    $msg  = "�Ƿ����� �ɽ��� > ������==>>" . json_encode($order);
                    $this->addTransactLog($msg);
                    return false;
                }               
            break;
        }
                
        if ($dbOrder['status'] == EOrderStatus::$Wailt)
        {
            try
            {
                 
                    //���¶���״̬;        
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
                $exMsg  = " �û�UID" . $order["user_id"] . " ����״̬����ʧ�ܣ�" . json_encode($order);
                $exMsg .= " �쳣��Ϣ : " . $ex->getMessage();
                
                $this->addTransactLog($exMsg);
            }
        } 
        else
        {
            $exMsg  = " �˶�����Ϊ �ǵȴ�״̬, ���Բ��ܲ�����" . json_encode($order);
            $this->addTransactLog($msg);
        }        
        
        return $reVal;
    }
    
    /** 
     * @summary         : �������.
     * @transactionType : ��������(0��, 1��).
     * @order           : ��������.
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
            $msg = " �Ա� " . $tableName . " �µ��ɹ���" . json_encode($order);          
            $this->addTransactLog($msg);
            
        }
        catch(Exception $ex)
        {
            echo $ex->getMessage();
            $exMsg  = " �û�UID" . $order["user_id"] . " �µ�ʧ�ܣ�" . json_encode($order);
            $exMsg .= " ��������쳣 : " . $ex->getMessage();
            
            $this->addTransactLog($exMsg);          
            //return ApiFormat::get(EResult::$RUNERRO, "��������쳣", $order);  
        }
        
        return $reVal;
    }    
    
    /** 
     * @summary         : �ɹ��������.
     * @transactionType : ��������(0��, 1��).
     * @order           : ��������.
    */
    public function addTransacitonFlowToDB($transactionType, $order = array())
    {     
        //У��
        if(!$this->_checkParams($order))
        {
            $exMsg  = "����У��ʧ��:" ;  
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
            $msg = " �Ա� " . $tableName . " ���׳ɹ���ˮ���ɹ���" . json_encode($order);          
            $this->addTransactLog($msg);
            return ApiFormat::get($reVal? EResult::$SUCCESS : EResult::$RUNERRO, $reVal? "���ɹ�":"���ʧ��", $order);
        }
        catch(Exception $ex)
        {
            $exMsg  = " �û�UID" . $order["user_id"] . " ���׳ɹ���ˮ���ʧ�ܣ�" . json_encode($order);
            $exMsg .= " ��������쳣 : " . $ex->getMessage();
            $this->addTransactLog($exMsg);    
            return ApiFormat::get(EResult::$RUNERRO, "��������쳣", $order);        
        }
        
        return $reVal;
    }
    
    /** 
     * @summary         : �����ݿ��л�ȡ��������.
     * @transactionType : ��������(0��, 1��).
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
     * @summary         : ��ȡ��������.
     * @transactionType : ��������(0��, 1��).
     * @sort            : 1����, 0����[Ĭ��]. 
     * @num             : ��ȡ����. 
    */
    public function getTransactionOrderFromCache($cacheObj, $transactionType, $sort = 0, $num = 80000)
    {
        $reList = [];
       
        $catchKey = self::$_getWay->getRedisKey($transactionType, EOperation::$TRANSACTION);
        var_dump($catchKey);
        var_dump($cacheObj->hlen($catchKey));
       
        //��ȡ���� ���û�������.
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
        //����ɸߵ��ͷ���
        ($transactionType == EOrderType::$BUY) ? arsort($keys) : asort($keys);
        //����������ʵ����.
        $showCount = count($keys);
        $showCount = $showCount > self::$_showRows ? $_showRows : $showCount;
       
        //�Ի����������.
        //return $cacheObj->sort($catchKey, array('LIMIT'=>array(0, $num), "SORT" => $sort ? 'ASC' : 'DESC'));
        for ($i = 0; $i < $showCount; $i++)
        {            
            $orders = $cacheObj->hget($catchKey, $keys[$i]);          
            $reList[] = $orders;
        }
        
        return $reList;
    }
    
    /** 
     * @summary         : ��ȡ����������.
     * @transactionType : ��������(0��, 1��).
     * @sort            : 1����, 0����[Ĭ��]. 
     * @num             : ��ȡ����. 
    */
    public function getTransactionOrderSubFromCache($cacheObj, $transactionType, $subKey)
    {
        $catchKey = self::$_getWay->getRedisKey($transactionType, EOperation::$TRANSACTION);
        
        return $cacheObj->hget($catchKey, $subKey);//hvals        
    }
    
   /** 
     * @summary         : ɾ������Ԫ��.
     * @transactionType : ��������(0��, 1��).
     * @subKey          : ��Key. 
    */
    public function delOrderFromCache($cacheObj, $transactionType, $subKey)
    {
        $catchKey = self::$_getWay->getRedisKey($transactionType, EOperation::$TRANSACTION);
        $subKey = (double)$subKey;
        echo "hdel " . $catchKey . " " . $subKey;
        return $cacheObj->hdel($catchKey, $subKey);
    }        
        
   /** 
     * @summary         : �����ڴ�(��������).
     * @transactionType : ��������(0��, 1��).
     * @subKey          : ��Key. 
    */
    public function updateOrderToCache($cacheObj, $transactionType, $subKey, $orderSubList)
    {
        $catchKey = self::$_getWay->getRedisKey($transactionType, EOperation::$TRANSACTION);
        
        return $cacheObj->hset($catchKey, $subKey, json_encode($orderSubList));
    }                  
                        
    /** 
     * @summary          : ����ԭ�Ӷಥ����.    
     * @catchKey         : ����Key.
     * @cacheContainer   : ����ʵ��.     
     * @order            : ��������.
     * @transactionType  : ��������(0��, 1��).
     * 
    */
    public function addOrderToCache($cacheContainer, $order, $transactionType)
    {
        if($order)
        {
            $catchKey   = self::$_getWay->getRedisKey($transactionType, EOperation::$TRANSACTION);
            
            //��ȡ���� ���û�������.
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

                    //�˴�û���뵽���õĽ������.
                    if(count($tempList) > 1)
                    {
                        $tempList[] = $order;
                        $cacheContainer->hset($catchKey, $order["price_entrust"], json_encode($tempList));
                    }
                    else
                    {
                        $orderTemp = $tempList[0];
                        //�����ظ�
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
     * @summary          : �ͷŻ���ʵ��.
     * @cacheContainer   : ����ʵ��.
     * @transactionType  : ��������(0��, 1��).
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
     * @summary : д��־(ͬ��).
     * @msg     : ��¼��Ϣ.    
    */
    public function addTransactLog($msg)
    {
        \MatchEngine\Common\Log::write($msg);
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
     
}
