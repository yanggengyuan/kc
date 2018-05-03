<?php
namespace EOS\Controller;

use Think\Controller;

class BaseController extends Controller
{
    protected $uid;

    public function __construct()
    {
        parent::__construct();
     

    }
}
