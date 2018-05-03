<?php

namespace RPC\Common\Enum;

/**
 * 操作类型.
 */
class EOperation
{
  
  public static $FLOW       = 1; //流水.
  public static $SUCCESS    = 2; //成功.
  public static $EXCEPTION  = 3; //异常.
  public static $MSG        = 4; //消息.
  public static $PURSE      = 5; //钱包.
  public static $UNSEQUENCE = 6; //无序.
  public static $TRANSACTION= 7; //交易.

}


?>