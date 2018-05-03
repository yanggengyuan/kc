<?php 

namespace RPC\Common;

/**
  * @summary ��־��.
  * @author  Henry.
  * @version 1.0
  * @date    2018.03.22
  */
class Log
{
    /**
     * ������־�ļ���С����
     *
     * @var int �ֽ���
     */
    private static $i_log_size = 5242880; // 1024 * 1024 * 5 = 5M

    /**
     * ���õ�����־�ļ���С����
     * 
     * @param int $i_size �ֽ���
     */
    public static function set_size($i_size)
    {
    	if( is_numeric($i_size) ){
    		self::$i_log_size = $i_size;
    	}
    }
 
    /**
     * д��־
     *
     * @param string $s_message ��־��Ϣ
     * @param string $s_type    ��־����
     */
    public static function write($s_message, $s_type = 'log')
    {        
        // �����־Ŀ¼�Ƿ��д
         if ( !file_exists(C('LOG_PATH')) ) 
         {
            @mkdir(C('LOG_PATH'));     
        }
        chmod(C('LOG_PATH'),0777);
         
    	$s_now_time = date('[Y-m-d H:i:s]');
        $s_now_day  = date('Y_m_d');
        // ��������������־Ŀ��λ��
        $s_target   = C('LOG_PATH');
        
        switch($s_type)
        {
            case 'debug':
                $s_target .= 'Out_' . $s_now_day . '.log';
                break;
            case 'error':
                $s_target .= 'Err_' . $s_now_day . '.log';
                break;
            case 'log':
                $s_target .= 'Log_' . $s_now_day . '.log';
                break;
            default:
                $s_target .= 'Log_' . $s_now_day . '.log';
                break;
        }
        
 
        //�����־�ļ���С, �������ô�С��������
        if (file_exists($s_target) && self::$i_log_size <= filesize($s_target)) 
        {
            $s_file_name = substr(basename($s_target), 0, strrpos(basename($s_target), '.log')). '_' . time() . '.log';
            rename($s_target, dirname($s_target) . DS . $s_file_name);
        }
        clearstatcache();
        $s_message = date("Y-m-d H:i:s") . '==> ' . $s_message . "\r\n";
        // д��־, ���سɹ����
        file_put_contents($s_target, $s_message, FILE_APPEND);

        
    }
}
?>