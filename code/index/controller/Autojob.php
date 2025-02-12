<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use fast\Tool;
use think\Exception;
use think\Queue;


class Autojob
{

    public static function index($orderId)
    {


        $jobHandlerClassName = 'app\index\job\Doauto';
        $jobQueueName = "autoQueue";
        $isPushed = Queue::push($jobHandlerClassName, $orderId, $jobQueueName);

        if ($isPushed !== false) {
            Tool::autoLog($orderId, '加入队列成功', 1);
        } else {
            Tool::autoLog($orderId, '加入队列失败', 1);
        }
    }




}