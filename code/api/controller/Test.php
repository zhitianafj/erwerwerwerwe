<?php

namespace app\api\controller;
use app\common\controller\Api;
use think\Queue;


class Test extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    public function addQueue(){

    }

}