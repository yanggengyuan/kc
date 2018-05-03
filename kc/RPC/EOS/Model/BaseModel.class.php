<?php

namespace RPC\Model;
use Think\Model;
use Think\Exception;
/**
  * ����model����װ��ɾ�Ĳ�
  * ʹ�ñ����з��������ݿ��ʹ��Mysql InnoDB������ܽ����������
  * @author 
  * @version 1.0 time 2018-04-29
  */
class BaseModel extends Model
{
    
    public function __construct($DBName)
    {
        $this->connection = ($DBName ? $DBName : C("DB_Bourse"));
        
        parent::__construct();        
    }
     
    
     protected $autoCheckFields = false; //����ģ�͹ر��Զ����
     
     /**
      * �򵥵����ݲ�ѯ�����ܽ����� ��=�� where������
      * @param $table ���ݱ���
      * @param $param �����������ѯ�������Ǳ�Ҫ����
      * @param $field ��Ҫ��ѯ�Ĳ���
      * @return ���ض�������
      */
     public function select_all($table, $param, $field = '*')
     {
         $where = array();
         $bind = array();
         foreach ($param as $k=>$v){
             $where[$k] = ':'.$k;
             $bind[':'.$k] = $v;
         }
         
         return $this->table($table)->field($field)->where($where)->bind($bind)->select();
     }
     
     /**
      * ԭ��sql���ݲ�ѯ
      * @param $sql sql���
      * @param $param �󶨲������ɲ�������֧�ִ�������
      * @return ����һ������
      */
     public function select_one($sql, $param)
     {
        try 
        {
          $parseSql = $this->bindParam($sql, $param);
          $this->isSelectSql($parseSql);
         
          $result = $this->query($parseSql);
          return $result[0];        
     } 
     catch (Exception $e) 
     {
         throw_exception($e->getMessage());
     }
  }
     
     /**
      * @param $table ���ݱ���
      * @param $param ���µ�����
      * @param $condition ���µ�����
      * @return boolean �ɹ�����true ʧ�ܷ���false
      */
     public function update($table, $param, $condition){
         try {
             $this->startTrans();
             
             $where = array();
             $bind = array();
             $update = array();
             
             foreach ($condition as $k=>$v){
                 $where[$k] = ':w_'.$k;
                 $bind[':w_'.$k] = $v;
                 
             }
             
             foreach ($param as $k=>$v){
                 //$update[$k] = ':u_'.$k;
                 //$bind[':u_'.$k] = $v;
                 $update[$k] = $v;
                 $bind[$k] = $k;
             }
             
             $this->table($table)->where($where)->bind($bind)->save($update);
             
             if(!$this->commit()){
                 $this->rollback();
                 return false;
             }else {
                 return true;
             }
         } catch (Exception $e) {
             $this->rollback();
             throw_exception($e->getMessage());
         }
     }
     
     /**
      * ע�⣡ע�⣡ע�⣡ʹ�ñ����������ݱ�������ʹ��thinkphp��add������
      * ��ʹ�õ�thinkphp��add��������������ִ����insert��䣬�������sequence��ά�������ݱ�����idֵ
      * @param $table ���ݱ���
      * @param $param Ҫ���������
      * @param $is_auto_increment ���ݱ������Ƿ�����
      * @param $is_exist_keyid ���ݱ��������������ڱ�ʾ�����Ƿ��ظ���key���Ƿ����
      * @param string $is_return_id �Ƿ񷵻ص�ǰ������ID
      * @return boolean �ɹ�����true������ID ʧ�ܷ���false
      */
     public function insert($table, $param, $is_auto_increment=true, $is_exist_keyid=true, $is_return_id=false){
         try {
             $this->startTrans();
             
             $insert = array();
             $bind = array();
             
             if(!$is_auto_increment && $is_exist_keyid){
                 //��������������������Ҵ����������߷��ظ�key�����ñ�����last_sequence_id��������ȡ���id+1�����Դ���Ҫ���������
                 $key = isset($param['table_key']) ? $param['table_key'] : 'id'; //���������������id�� $param�ﴫ��table_key��ֵ���� 
                 $param[$key] = $this->last_sequence_id($table);
             }
             
             foreach ($param as $k => $v){
                 //$insert[$k] = ':i_'.$k;
                 //$bind[':i_'.$k] = $v;
                 $insert[$k] = $v;
                 $bind[$k] = $k;
             }
             
             $result = $this->table($table)->bind($bind)->add($insert);
             
             if($is_auto_increment){
                 //�����������ִ��sql����ʹsequence����ά��������id��������
                 $this->native_select_one("SELECT SETVAL('" . $table . "',".$result.")");
             }
             
             if(!$this->commit()){
                 $this->rollback();
                 return false;
             }else {
                 return $is_return_id === true ? ($is_auto_increment ? $result : $param[$key]) : true;
             }
         } catch (Exception $e) {
             $this->rollback();
             throw_exception($e->getMessage());
         }
     }
     
     /**
      * ��ȡ�����ID+1
      * @param $table ����
      */
     public function last_sequence_id($table){
         $sql = "SELECT NEXTVAL('" . $table . "') AS num";
         $result = $this->native_select_one($sql);
         
         if(!$result['num']) {
             throw_exception('��ȡ����ʧ�ܣ�' . $table, '');
         }
         return $result['num'];
     }
     
     /**
      * ԭ��sql�󶨲���
      */
     protected function bindParam($sql, $param){
         if(!empty($param) && !is_array($param)){
             return false;
         }
         
         foreach ($param as $k => $v){
             $bind[] = ":".$k;
             
             if(strpos($v, "'") === 0 && substr($v, -1) == "'"){
                 $value[] = $v;
             }else{
                 $value[] = "'".$v."'"; //������ֵǿ��תΪ�����ŵ��ַ���
             }
         }
         
         return str_replace($bind, $value, $sql);
      }
}
