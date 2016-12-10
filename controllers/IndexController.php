<?php

class IndexController extends Common
{

    public function __construct()
    {
        parent::__construct();

    }

    public function indexAction()
    {
        echo 222;
       $this->display('member/index');
    }

}