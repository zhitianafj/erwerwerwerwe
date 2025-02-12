<?php
namespace app\index\job;

use fast\Http;
use fast\Tool;
use think\queue\Job;
use think\Db;


class Dojob
{

    public function fire(Job $job,$orderId){

        if($orderId){
            //执行订单业务
            $isJobDoneInfo = $this->demurrageCharges($orderId);
        }else{
            return false;
        }
        if ($isJobDoneInfo['code']) {
            Tool::queueLog($orderId,'队列执行结束',2);
            // 如果任务执行成功，删除任务
            $job->delete();
        }else{
            Tool::queueLog($orderId,'重新发布该任务',2);
            // 重新发布这个任务
            $job->release($isJobDoneInfo['time']); //$delay为延迟时间，表示该任务延迟2秒后再执行
        }
    }



    //处理延迟费用 平仓（1、手动卖了 2、扣到余额都没了）
    protected function demurrageCharges($orderId)
    {
        $code = 1; //1代表要删除队列
        $time = 3; //代表下次启动该任务的时间，默认3秒
        $orderInfo = Db::name('add_strategy')->where('id',$orderId)->find();
        if(!$orderInfo){ //订单不存在，删除队列
            Tool::queueLog($orderId,'不存在该订单',2);
            return [
                'code' => $code,
                'time' => $time
            ];
        }

        //判断用户在不在，不在也删除队列
        $userOnlieInfo = Db::name('user')->where('id',$orderInfo['user_id'])->find();
        if(!$userOnlieInfo){
            Tool::queueLog($orderId,'用户不存在',2);
            return [
                'code' => $code,
                'time' => $time
            ];
        }

        //判断该订单是否卖出
        if($orderInfo['status']==3){ //订单卖出，删除队列
            Tool::queueLog($orderId,'订单已卖出，无需计算延迟费',2);
            return [
                'code' => $code,
                'time' => $time
            ];
        }

        //延迟费 单次
        $delayMoney = $this->countMoney($orderInfo['creditMoney'],$orderInfo['multiplying'],$orderInfo['delay_money']);

        // 启动事务
        Db::startTrans();
        try {

            //判断用户是否还有可用余额
            $userBalance = Tool::getUserBalance($orderInfo['user_id']);
            if($userBalance<=0 || $userBalance<$delayMoney){ //用户余额不够
                $obj = new Http();
                $nowGu1 = $obj->get_stock_now_info($orderInfo['allcode']);
                $num3 = bcmul(($nowGu1[3]-$orderInfo['buyprice']),$orderInfo['number'],2);
                $upOrderRes = Db::name('add_strategy')->where('id',$orderId)->update([
                    'waystatus' => 2,
                    'status' =>  3,
                    'profitLose' => $num3,
                    'outtime' => time(),
                    'sellprice' => $nowGu1[3]
                ]);
                if($upOrderRes!=='false'){
                    $addMoney = bcadd($orderInfo['creditMoney'],$num3,2);
                    self::countUserBalance($orderInfo['user_id'],$addMoney,'自动卖出',0,$orderId);
                }
                Tool::queueLog($orderId,'用户余额不足，自动平仓',2);
            }else{
                if($userBalance>=$delayMoney){ //用户有余额，开始扣延迟费了
                    $wTime = date('w',time());
                    if ($wTime == 6 || $wTime == 0) {
                        if ($wTime == 6) { //礼拜六
                            $time = 2*24*3600; //延迟两天继续启动任务
                        }else if($wTime == 0){ //礼拜天
                            $time = 24*3600; //延迟一天继续启动任务
                        }
                    }else {
                        self::countUserBalance($orderInfo['user_id'], $delayMoney, '延迟费用', 1,$orderId);
                        if(Tool::openQueue()){
                            $time = 60;
                        }else {
                            $time = 24 * 3600; //延迟一天继续启动任务
                        }
                    }

                }
                $code = 0; //无论是扣完延迟费还是 用户余额出现特殊情况小于等于0，都需要重启下任务
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

    //延迟费计算
    protected function countMoney($xyj,$bs,$bl){
        $money = 0;
        if($bl) {
            $xyj = bcmul($xyj,$bs,20);
            $bl = bcdiv($bl, 10000, 20); //万分之20
            $money = round(bcmul($bl, $xyj, 20),2);
        }
        return $money;
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