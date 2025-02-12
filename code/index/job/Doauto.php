<?php
namespace app\index\job;

use fast\Http;
use fast\Tool;
use think\queue\Job;
use think\Db;


class Doauto
{

    public function fire(Job $job,$orderId){
        if($orderId){
            //执行订单业务
            $isJobDoneInfo = $this->demurrageCharges($orderId);
        }else{
            return false;
        }
        if ($isJobDoneInfo['code']) {
            Tool::autoLog($orderId,'队列执行结束',2);
            // 如果任务执行成功，删除任务
            $job->delete();
        }else{
            //Tool::autoLog($orderId,'重新发布该任务',2);
            // 重新发布这个任务
            $job->release($isJobDoneInfo['time']); //$delay为延迟时间，表示该任务延迟2秒后再执行
        }
    }



    //处理延迟费用 平仓（1、手动卖了 2、扣到余额都没了）
    protected function demurrageCharges($orderId)
    {
        $code = 1; //1代表要删除队列
        $time = 1; //代表下次启动该任务的时间，默认3秒
        $orderInfo = Db::name('add_strategy')->where('id',$orderId)->find();
        if(!$orderInfo){ //订单不存在，删除队列
            Tool::autoLog($orderId,'不存在该订单',2);
            return [
                'code' => $code,
                'time' => $time
            ];
        }

        //判断用户在不在，不在也删除队列
        $userOnlieInfo = Db::name('user')->where('id',$orderInfo['user_id'])->find();
        if(!$userOnlieInfo){
            Tool::autoLog($orderId,'用户不存在',2);
            return [
                'code' => $code,
                'time' => $time
            ];
        }

        //判断该订单是否卖出
        if($orderInfo['status']==3){ //订单卖出，删除队列
            Tool::autoLog($orderId,'订单已卖出，无需判断当前价、止盈价和止损价的大小',2);
            return [
                'code' => $code,
                'time' => $time
            ];
        }

        //判断是否是当天
        $sTime = strtotime(date('Y-m-d',time()).' 00:00:00');
        $eTime = strtotime(date('Y-m-d',time()).' 23:59:59');
        $toTime = strtotime(date('Y-m-d',strtotime("+1 day"))." 9:25");
        if($orderInfo['createtime']>=$sTime && $orderInfo['createtime']<=$eTime){
            $waitTime = $toTime - time();
            Tool::autoLog($orderId,'当天不允许卖出',2);
            return [
                'code' => 0,
                'time' => $waitTime
            ];
        }



        // 启动事务
        Db::startTrans();
        try {

            //判断当前价、止盈价和止损价的大小
            $obj = new Http();
            $nowGu1 = $obj->get_stock_now_info($orderInfo['allcode']);

            if(isset($nowGu1[3])) {
                if ($nowGu1[3] >= $orderInfo['profitPrice'] || $nowGu1[3] <= $orderInfo['losePrice']) {

                    if (autoTime()) {
                        $wTime = date('w', time());
                        if ($wTime != 6 && $wTime != 0) {
                            $num3 = bcmul(($nowGu1[3] - $orderInfo['buyprice']), $orderInfo['number'], 2);
                            $upOrderRes = Db::name('add_strategy')->where('id', $orderId)->update([
                                'waystatus' => 2,
                                'status' => 3,
                                'profitLose' => $num3,
                                'outtime' => time(),
                                'sellprice' => $nowGu1[3]
                            ]);
                            if ($upOrderRes !== 'false') {
                                $addMoney = bcadd($orderInfo['creditMoney'], $num3, 2);
                                self::countUserBalance($orderInfo['user_id'], $addMoney, '当前价大于止盈价或者止损价，自动卖出',0,$orderId);
                            }
                            Tool::autoLog($orderId, '当前价大于止盈价或者止损价，自动卖出', 2);
                        } else {
                            $code = 0;
                        }
                    } else {
                        $code = 0;
                    }
                } else {
                    $code = 0;
                }
            }else{
                $code = 0;
            }

            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $code = 0; //需要重启任务
        }
        return [
            'code' => $code,
            'time' => $time,
        ];
    }


    //获取操作前用户余额，以及操作用户余额
    protected function countUserBalance($userId,$money,$content,$type=0,$orderId){
        $beforeMoney = Tool::getUserBalance($userId);
        if($type==1) { //减
            $userUpdateRes = Db::name('user')->where('id', $userId)->setDec('balance',$money);
        }else{ //0 加
            $userUpdateRes = Db::name('user')->where('id', $userId)->setInc('balance',$money);
        }
        if($userUpdateRes!=='false') {
            Tool::addLog($userId, $content, $beforeMoney, Tool::getUserBalance($userId), $money, $type,$orderId);
        }
    }
}