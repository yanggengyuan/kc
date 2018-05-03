<?php
namespace MatchEngine\Common\Match;

/**
  * @summary 撮合算法[接口].
  * @author  Henry.
  * @version 1.0
  * @date    2018.03.22
  */
interface IAlgorithm  
{
    //运行算法(各币种撮合算法可能有不同).
    public function runAlgorithm($order);
   
    
} 


?>