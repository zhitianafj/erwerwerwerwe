<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use fast\Tool;
use think\Exception;
use think\Queue;


class Addjob
{

    public static function index($orderId = '', $createTime = '')
    {
        if(!$orderId){
            Tool::queueLog('','订单ID不存在',1);
        }

        if(!$createTime){
            Tool::queueLog('','创建时间不存在',1);
        }

        //该方法可传第二个参数，是为了直接测试队列是否执行
        if(Tool::openQueue()){ //后台开启了测试，走60秒
            $waitTimeInfo = self::countWaitTime($createTime, 60);
        }else {
            $waitTimeInfo = self::countWaitTime($createTime);
        }

        $jobHandlerClassName = 'app\index\job\Dojob';
        $jobQueueName = "jobQueue";
        $isPushed = Queue::later($waitTimeInfo['waitTime'], $jobHandlerClassName, $orderId, $jobQueueName);

        if ($isPushed !== false) {
            Tool::queueLog($orderId, '加入队列成功', 1,$waitTimeInfo['queueTime']);
        } else {
            Tool::queueLog($orderId, '加入队列失败', 1,$waitTimeInfo['queueTime']);
        }
    }

    /**
     * @param $createTime
     * @param float|int $waitTime
     * @return array
     */
    private static function countWaitTime($createTime, $waitTime = 2 * 24 * 3600)
    {
        $countTime = $createTime + $waitTime;
        $queueTime = date('Y-m-d H:i:s',$countTime);//此字段只是入日志表
        $wTime = date('w', $countTime); //判断当前是否礼拜
        $delayTime = ' 1:00:00'; //如果是礼拜的情况下，延迟到下礼拜一的凌晨1点执行
        if ($wTime == 6 || $wTime == 0) {
            if ($wTime == 6) { //礼拜六
                $realEndTime = date("Y-m-d", strtotime("+2 day", $countTime)).$delayTime;
                $waitTime = strtotime($realEndTime) - $createTime;
            } else if ($wTime == 0) { //礼拜天
                $realEndTime = date("Y-m-d", strtotime("+1 day", $countTime)).$delayTime;
                $waitTime = strtotime($realEndTime) - $createTime;
            } else {
                $waitTime = 0;
                $realEndTime = '';
            }
            $queueTime = $realEndTime;
        }
        return [
            'waitTime' => $waitTime,
            'queueTime' => $queueTime,
        ];
    }


}