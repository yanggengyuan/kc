<?php

namespace RPC\Common\Enum;


/**
 * 订单状态.
 */
class EOrderStatus
{
    public static $Wailt   = 0; //等待撮合.
    public static $Finish  = 1; //交易完成.
    public static $Undo    = 2; //撤销.
}



?>