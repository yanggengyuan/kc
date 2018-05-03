<?php

namespace  EOS\Common;

use EOS\Common\Enum\EResult;

/**
* RPC��ʽ.
* @author Henry.
* @version 1.0 time 2018-03-26
*/
class ApiFormat
{ 
    
    /** 
     * @summary : ��ȡ��ʽ����������(Api�ӿ�).    
     * @code    : ������.
     * @msg     : ������Ϣ.
     * @data    : ��������.
    */
    public function get($code, $msg, $data = [])
    {        
        $reData = [ 
            "result" => [
                "code" => $code, 
                "msg"  => $msg,
            "data" => $data
            ]];
        return json_encode($reData);
    }

    
}

?>