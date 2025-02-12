<?php

namespace app\api\controller;

use addons\summernote\Summernote;
use app\admin\model\Adv;
use app\admin\model\Attention;
use app\admin\model\feedback\Feedback;
use app\admin\model\strategy\Addstrategy;
use app\admin\model\strategy\Optional;
use app\admin\model\strategy\Strategy;
use app\admin\model\user\Account;
use app\admin\model\user\Bankcard;
use app\admin\model\user\Identitycard;
use app\admin\model\user\Recharge;
use app\admin\model\user\Withdraw;
use app\admin\model\zixuan\Zixuan;
use app\common\controller\Api;
use app\common\library\Auth;
use app\common\library\Ems;
use app\common\library\Sms;
use app\index\controller\Addjob;
use app\index\controller\Autojob;
use fast\Random;
use fast\Tool;
use think\App;
use think\Config;
use think\Db;
use think\Validate;
use fast\Http;
use function GuzzleHttp\Psr7\str;
use app\admin\model\shengou\Shengou;
use app\admin\model\shengou\Sgjiaoyi;
use app\admin\model\shengou\Sgjiaoyi0;
use app\admin\model\User as Useruser;
use fast\Pinyin;
use app\admin\model\news\News;
/**
 * 会员接口
 */
class Pzjy extends Api
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    
    
    //配资交易
    public function addStrategy_pz(){
        if(!betweentime(config('site.swpzjy_shijian')) && !betweentime(config('site.xwpzjy_shijian'))){
            $this->error('下单失败,不在交易时间段');
        }
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day,'status'=>'0'])->select();
            if($data11){
                $this->error('下单失败,不在交易时间段');
            }
        }
        //判断是否开启，再判断是否实名
        if(config('site.is_rz')) {
            $identity_card_info = Db::name('identity_card')->where(['user_id'=>$this->auth->id])->find();
            if(!$identity_card_info){
                $this->error("请先前往个人中心,进行实名认证");
            }
            if($identity_card_info['is_audit'] !== "1"){
                $this->error("请先前往个人中心,进行实名认证");
            }
        }
        //判断玩家是否已经禁止登陆或者禁止交易
        $data = $this->request->post();
        $model = new Strategy();
        $data['user_id'] = $this->auth->id;
        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        if(!$userInfo['is_pz']){
            $this->error("当前账户异常,禁止配资交易");
        }
        //控制下单金额
        $kz_xdmoney = config('site.pzjy_jine');
        // if(floatval($kz_xdmoney)>floatval(round(bcdiv($data['cityValue'],$data['multiplying'],2)))){
        //     $this->error("下单金额不能少于".$kz_xdmoney);
        // }
        if(floatval($kz_xdmoney)>floatval($data['money'])){
            $this->error("下单金额不能少于".$kz_xdmoney.'^'.$data['money']);
        }
        $sInfo = $model->where('allcode',$data['allcode'])->find();
        $data['strategy_id'] = $sInfo['id'];
        // $data['creditMoney'] = round(bcdiv($data['cityValue'],$data['multiplying'],2));
        // $data['creditMoney'] = round($data['money'],2);
        if($sInfo['status']=='1'){
            $this->error('已禁止买入');
        }
        if($sInfo['is_pz']=='0'){
            $this->error('已禁止买入');
        }
        //配资不能购买抢筹的票
        // if($sInfo['qcstatus']=='1'){
        //     $this->error('已禁止买入');
        // }
        if(!$data['number']){
            $this->error('买入参数错误，请重新买入');
        }
        //判断整数
        if(!Tool::is__int($data['canBuy'])){
            $this->error('请输入整数的手数');
        }
        //判断节假日 不允许买入和卖出
        //判断
        if(config('site.kq_dj')){
            if($data['money']>bcadd($userInfo['balance'],$userInfo['freeze_profit'],2)){
                $this->error('余额不足，请及时充值');
            }
        }else{
            if($data['money']>$userInfo['balance']){
                $this->error('余额不足，请及时充值');
            }
        }
        // $guinfo = Http::get_stock_now_info($data['allcode']);
        // //判断涨幅 超过8% 今天开盘价 和当前价的涨幅
        // if(!$guinfo[0]){
        //     $this->error('下单失败，请联系管理员');
        // }
        // $nummmm = Tool::getcodetype($data['allcode']);
        // $nummmm1 = 0;
        // $nummmm2 = 0;
        // if($nummmm==3){
        //     //创业
        //     $nummmm1 = round(config('site.chuangye_zhang')/100,2); 
        //     $nummmm2 = round(config('site.chuangye_die')/100,2);
        // }else if($nummmm==2){
        //     //科创
        //     $nummmm1 = round(config('site.kechuang_zhang')/100,2); 
        //     $nummmm2 = round(config('site.kechuang_die')/100,2);
        // }else if($nummmm==4){
        //     //北交
        //     $nummmm1 = round(config('site.bj_zhang')/100,2); 
        //     $nummmm2 = round(config('site.bj_die')/100,2);
        // }else{
        //     //A股
        //     $nummmm1 = round(config('site.Agu_zhang')/100,2); 
        //     $nummmm2 = round(config('site.Agu_die')/100,2);
        // }
        //判断 A股还是科创 创业 北交所
        // $zf = bcdiv($guinfo[3]-$guinfo[2],$guinfo[2],4);  新浪
        // $zf = bcdiv($guinfo[3]-$guinfo[4],$guinfo[4],4); //腾讯接口
        //p判断是否开启抢筹了 
        // if(!$sInfo['qcstatus'] && !$userInfo['is_qc']){
        //     if($zf>$nummmm1 || $zf<$nummmm2){
        //         // $this->error('已超过涨跌幅，不允许下单');
        //         $this->error('份额不足,本金优先');
        //     }
        // }
        $freeze_profit = 0;
        $balance = round(bcsub($userInfo['balance'],$data['money'],20),2);
        if(config('site.kq_dj')){
            if(floatval($data['money'])<floatval($userInfo['freeze_profit'])){
                // $balance = $userInfo['balance'];
                $freeze_profit = round(bcsub($userInfo['freeze_profit'],$data['money'],20),2);
            }else if(floatval($data['money'])>floatval($userInfo['freeze_profit'])){
                $freeze_profit = 0;
            }
        }
        $positionMoney = bcadd($userInfo['positionMoney'],$data['creditMoney'],2);
        Db::startTrans();
        try{
            $createTime = time();
            $data['createtime'] = $createTime;
            $data['c_xq'] = date("w");
            $data['gmrate'] = 0;
            $data['pidcode'] = $userInfo['superior_code'];
            $data['pidname'] = $userInfo['superior_name'];
            $data['pid'] = $userInfo['superior_id'];
            $data['is_simulations'] = $userInfo['is_simulation'];
            $data['buytype'] = 3;
            //增加上级邀请码
            if(Db::name('add_strategy')->insert($data)!==false){
                $orderId = Db::name('add_strategy')->getLastInsID();
                $beforeMoney = Tool::getUserBalance($this->auth->id);
                $userRes = Db::name('user')->where('id',$this->auth->id)->update([
                    'balance' => $balance,
                    'freeze_profit' => $freeze_profit,
                    'positionMoney' => $positionMoney,
                ]);
                Tool::addLog($this->auth->id,"下单",$beforeMoney,Tool::getUserBalance($this->auth->id),$data['money'],1);
            }
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error('下单失败');
        }
        $this->success('下单成功');
    }
    
    
    
    
    //当前配资持仓
    public function getNowWarehouse_pz_lishi(){
        $status = $this->request->get('status');
        $buytype = $this->request->get('buytype');
        if($status == 2){
            $status = 3;
        }
        
        $model = new Addstrategy();
        $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('createtime desc')->select();
        if($status == 3){
            $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('outtime desc')->select();
        }
        if($list){
            foreach($list as $k=>$v){
                $list[$k]['title'] = $v['title'];
                $list[$k]['allcode'] = $v['allcode'];
                $list[$k]['cai_buy'] = $v['sellprice'];
                $list[$k]['createtime_name'] = date('Y-m-d H:i:s',$v['createtime']);
                $list[$k]['outtime_name'] = date('Y-m-d H:i:s',$v['outtime']);
                $list[$k]['creditMoney'] = round($v['creditMoney'],2);
                $list[$k]['number'] = round($v['number']); 
                $list[$k]['multiplying'] = $v['multiplying']; 
                $list[$k]['profitLose'] = $v['profitLose'];
                $list[$k]['profitLose_rate'] = number_format(round(bcdiv($v['profitLose'],$v['creditMoney'],20),3)*100,2);
                
            }
        }
        $this->success('请求成功', ['list'=>$list]);
    }
    //当前配资持仓
    public function getNowWarehouse_pz(){
        $status = $this->request->get('status');
        $buytype = $this->request->get('buytype');
        if($status == 2){
            $status = 3;
        }
        
        $model = new Addstrategy();
        $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('createtime desc')->select();
        if($status == 3){
            $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('outtime desc')->select();
        }
        $total_city_value = 0;
        $total_position_money = 0;
        if($list){
            $arr1 =array_unique(array_column($list,'allcode'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia($allcodes);
            foreach($list as $k=>$v){
                $sModel = new Strategy();
                $sInfo = $sModel->where(['allcode'=>$v['allcode']])->find();
                $list[$k]['title'] = $v['title'];
                $list[$k]['allcode'] = $v['allcode'];
                $list[$k]['cai_buy'] = $allcodes_arr[$v['allcode']];
                $list[$k]['createtime_name'] = date('Y-m-d H:i:s',$v['createtime']);
                $list[$k]['outtime_name'] = date('Y-m-d H:i:s',$v['outtime']);
                $list[$k]['creditMoney'] = round($v['creditMoney'],2);
                $list[$k]['number'] = round($v['number']); 
                $list[$k]['multiplying'] = $v['multiplying'];
                $list[$k]['cvalue'] = round(bcmul($v['creditMoney'],$v['multiplying'],20),2);
                // $list[$k]['profitLose'] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number']*$v['multiplying'],2);
                $list[$k]['profitLose'] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number'],2);
                $list[$k]['profitLose_rate'] = number_format(((bcdiv(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['buyprice'],4))*100),2)."%";
                
            }
            $total_city_value = round(array_sum(array_column($list,'cvalue')),2);
            $total_position_money = round(array_sum(array_column($list,'profitLose')),2);
        }
        $this->success('请求成功', ['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
    }
    
    
    //配资平仓
    public function closeOut_pz(){
        if(!betweentime(config('site.swjiaoyi')) && !betweentime(config('site.xwjiaoyi'))){
            $this->error('平仓失败,不在交易时间段');
        }
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return 
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day])->select();
            if($data11){
                $this->error('下单失败,不在交易时间段');
            }
        }
        $id = $this->request->post('id');
        $allcode = $this->request->post('allcode');
        $waystatus = $this->request->post('waystatus');
        $model = new Addstrategy();
        $sModel = new Strategy();
        $info = $model->where(['id'=>$id])->find();
        $sInfo = $sModel->where(['allcode'=>$allcode])->find();
        if($info['status'] == "3"){
            $this->error('该票已卖出，等待刷新');
        }
        $todayStime = strtotime(date('Y-m-d',time()).' 00:00:00');
        $todayEtime = strtotime(date('Y-m-d',time()).' 23:59:59');
        $pingday = $info['pingday'];
        $date_current_addone = date('Y-m-d',strtotime("+{$pingday}day",$info['createtime']));
        // $this->error($date_current_addone);
        //卖出的当前时间 就是大于第二天开盘 就OK
        $time = explode('-',config('site.swjiaoyi'))[0];
        // $this->error($time);
        $todayStime_1 = strtotime($date_current_addone.' '.$time);//当前卖出股的第二天9点才能卖
        if(!$sInfo['currentstatus']){
            //未开启当天平仓 就要计算下平仓时间
            if(time()<$todayStime_1){
                $this->error("T+{$pingday}平仓");
            }
        }else{
            //如果开启当天平仓 就不受时间限制
        }
        // file_put_contents('../public/logs/hhhh.txt',var_export($id,true)."\n\n",FILE_APPEND);
        //判断
        $obj = new Http();
        $nowGu1 = $obj->get_stock_now_info($info['allcode']);
        // file_put_contents('../public/logs/hhhh.txt',var_export($nowGu1,true)."\n\n",FILE_APPEND);
        $ff = bcmul($info['number'],$info['multiplying'],2);
        $num3 = bcmul(($nowGu1[3]-$info['buyprice']),$ff,2);
        //收益 也就是
        // $profitLose = $num3>=$info['creditMoney']?$info['creditMoney']:$num3;
        $profitLose = $num3;
        $info->waystatus = 1;
        $info->status = 3;
        $info->profitLose = $profitLose;
        $info->outtime = time();
        $info->sellprice = $nowGu1[3];//$sInfo['cai_buy'];
        //卖出手续费
        $info->yhfee = round($info['creditMoney'] * $info['multiplying'] * config('site.yh_fee'),2);
        $info->sxfee = round($info['creditMoney'] * $info['multiplying'] * config('site.maic_fee'),2);
        
        //卖出收印花税
        if($info->save()!=='false'){
            $userModel = new \app\admin\model\User();
            $beforeMoney = Tool::getUserBalance($info['user_id']);
            $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
            $money = round(bcadd($userInfo['balance']+$info['creditMoney']-$info['yhfee']-$info['sxfee'],$profitLose,20),2);
            $money1 = round(bcadd($info['creditMoney']-$info['yhfee']-$info['sxfee'],$profitLose,20),2);
            $freeze_profit = round(bcadd($userInfo['freeze_profit'],$money1),2);
            if(config('site.kq_dj')){
                $userInfo->freeze_profit = $freeze_profit;
                $userInfo->balance = $money<0?0:$money;
            }else{
                $userInfo->balance = $money<0?0:$money;
            }
            $userInfo->save();
            $addMoney = bcadd($info['creditMoney'],$profitLose,2);
            Tool::addLog($this->auth->id,"平仓收益",$beforeMoney,Tool::getUserBalance($this->auth->id),$addMoney,0,$id);
        }
        Tool::addLog($this->auth->id,"费印花税",0,0,$info['yhfee'],1,$id);
        Tool::addLog($this->auth->id,"平仓手续",0,0,$info['sxfee'],1,$id);
        $this->success('平仓成功');
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    //配资交易
    public function addStrategy_pz1(){
        if(!betweentime(config('site.swpzjy_shijian')) && !betweentime(config('site.xwpzjy_shijian'))){
            $this->error('下单失败,不在交易时间段1');
        }
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day,'status'=>'0'])->select();
            if($data11){
                $this->error('下单失败,不在交易时间2段');
            }
        }
        //判断是否开启，再判断是否实名
        if(config('site.is_rz')) {
            $identity_card_info = Db::name('identity_card')->where(['user_id'=>$this->auth->id])->find();
            if($identity_card_info['is_audit'] !== "1"){
                $this->error("请先前往个人中心,进行实名认证");
            }
        }
        //判断玩家是否已经禁止登陆或者禁止交易
        $data = $this->request->post();
        $model = new Strategy();
        $data['user_id'] = $this->auth->id;
        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        
        //控制下单金额
        $kz_xdmoney = config('site.pzjy_jine');
        if(floatval($kz_xdmoney)>floatval(round(bcdiv($data['cityValue'],$data['multiplying'],2)))){
            $this->error("下单金额不能少于".$kz_xdmoney);
        }
        $sInfo = $model->where('allcode',$data['allcode'])->find();
        $data['strategy_id'] = $sInfo['id'];
        // $data['creditMoney'] = round(bcdiv($data['cityValue'],$data['multiplying'],2));
        if($sInfo['status']=='1'){
            $this->error('已禁止买入');
        }
        //配资不能购买抢筹的票
        // if($sInfo['qcstatus']=='1'){
        //     $this->error('已禁止买入');
        // }
        if(!$data['number']){
            $this->error('买入参数错误，请重新买入');
        }
        //判断整数
        if(!Tool::is__int($data['canBuy'])){
            $this->error('请输入整数的手数');
        }
        //判断节假日 不允许买入和卖出
        //判断
        if($data['money']>$userInfo['balance']){
            $this->error('余额不足，请及时充值');
        }
        $guinfo = Http::get_stock_now_info($data['allcode']);
        //判断涨幅 超过8% 今天开盘价 和当前价的涨幅
        if(!$guinfo[0]){
            $this->error('下单失败，请联系管理员');
        }
        $nummmm = Tool::getcodetype($data['allcode']);
        $nummmm1 = 0;
        $nummmm2 = 0;
        if($nummmm==3){
            //创业
            $nummmm1 = round(config('site.chuangye_zhang')/100,2); 
            $nummmm2 = round(config('site.chuangye_die')/100,2);
        }else if($nummmm==2){
            //科创
            $nummmm1 = round(config('site.kechuang_zhang')/100,2); 
            $nummmm2 = round(config('site.kechuang_die')/100,2);
        }else if($nummmm==4){
            //北交
            $nummmm1 = round(config('site.bj_zhang')/100,2); 
            $nummmm2 = round(config('site.bj_die')/100,2);
        }else{
            //A股
            $nummmm1 = round(config('site.Agu_zhang')/100,2); 
            $nummmm2 = round(config('site.Agu_die')/100,2);
        }
        //判断 A股还是科创 创业 北交所
        // $zf = bcdiv($guinfo[3]-$guinfo[2],$guinfo[2],4);  新浪
        $zf = bcdiv($guinfo[3]-$guinfo[4],$guinfo[4],4); //腾讯接口
        //p判断是否开启抢筹了 
        if(!$sInfo['qcstatus'] && !$userInfo['is_qc']){
            if($zf>$nummmm1 || $zf<$nummmm2){
                // $this->error('已超过涨跌幅，不允许下单');
                $this->error('份额不足,本金优先');
            }
        }
        $balance = round(bcsub($userInfo['balance'],$data['money'],20),2);
        $positionMoney = bcadd($userInfo['positionMoney'],$data['creditMoney'],2);
        Db::startTrans();
        try{
            $createTime = time();
            $data['createtime'] = $createTime;
            $data['c_xq'] = date("w");
            $data['gmrate'] = 0;
            $data['pidcode'] = $userInfo['superior_code'];
            $data['pidname'] = $userInfo['superior_name'];
            $data['pid'] = $userInfo['superior_id'];
            $data['is_simulations'] = $userInfo['is_simulation'];
            $data['buytype'] = 3;
            //增加上级邀请码
            if(Db::name('add_strategy')->insert($data)!==false){
                $orderId = Db::name('add_strategy')->getLastInsID();
                $beforeMoney = Tool::getUserBalance($this->auth->id);
                $userRes = Db::name('user')->where('id',$this->auth->id)->update([
                    'balance' => $balance,
                    'positionMoney' => $positionMoney,
                ]);
                Tool::addLog($this->auth->id,"下单",$beforeMoney,Tool::getUserBalance($this->auth->id),$data['money'],1);
            }
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error('下单失败');
        }
        $this->success('下单成功');
    }
    
    
    //当前配资持仓
    public function getNowWarehouse_pz1(){
        $status = $this->request->get('status');
        $buytype = $this->request->get('buytype');
        $page = $this->request->param('page')?$this->request->param('page'):1;
        if($status == 2){
            $status = 3;
        }
        
        $model = new Addstrategy();
        $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('createtime desc')->page($page,20)->select();
        if($status == 3){
            $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('outtime desc')->page($page,20)->select();
        }
        $total_city_value = 0;
        $total_position_money = 0;
        if($list){
            $arr1 =array_unique(array_column($list,'allcode'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia($allcodes);
            foreach($list as $k=>$v){
                $sModel = new Strategy();
                $sInfo = $sModel->where(['allcode'=>$v['allcode']])->find();
                $list[$k]['title'] = $v['title'];
                $list[$k]['allcode'] = $v['allcode'];
                $list[$k]['cai_buy'] = $allcodes_arr[$v['allcode']];
                $list[$k]['createtime_name'] = date('Y-m-d H:i:s',$v['createtime']);
                $list[$k]['outtime_name'] = date('Y-m-d H:i:s',$v['outtime']);
                $list[$k]['creditMoney'] = round($v['creditMoney'],2);
                $list[$k]['number'] = round($v['number']); 
                $list[$k]['multiplying'] = $v['multiplying'];
                // $list[$k]['cvalue'] = round(bcmul($v['creditMoney'],$v['multiplying'],20),2);
                $list[$k]['cvalue'] = $v['cityValue'];
                // $list[$k]['profitLose'] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number']*$v['multiplying'],2);
                $list[$k]['profitLose'] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number'],2);
                $list[$k]['profitLose_rate'] = number_format(((bcdiv(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['buyprice'],4))*100),2)."%";
                $list[$k]['zy'] = round(bcmul($allcodes_arr[$v['allcode']],1.1),2);
                $list[$k]['zs'] = round(bcmul($allcodes_arr[$v['allcode']],0.94),2);
                
            }
            $total_city_value = round(array_sum(array_column($list,'cvalue')),2);
            $total_position_money = round(array_sum(array_column($list,'profitLose')),2);
        }
        $this->success('请求成功', ['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
    }
    
    
    //当前配资持仓
    public function getNowWarehouse_pz_lishi1(){
        $status = $this->request->get('status');
        $buytype = $this->request->get('buytype');
        $page = $this->request->param('page')?$this->request->param('page'):1;
        if($status == 2){
            $status = 3;
        }
        
        $model = new Addstrategy();
        $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('createtime desc')->page($page,20)->select();
        if($status == 3){
            $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('outtime desc')->page($page,20)->select();
        }
        if($list){
            foreach($list as $k=>$v){
                $list[$k]['title'] = $v['title'];
                $list[$k]['allcode'] = $v['allcode'];
                $list[$k]['cai_buy'] = $v['sellprice'];
                $list[$k]['createtime_name'] = date('Y-m-d H:i:s',$v['createtime']);
                $list[$k]['outtime_name'] = date('Y-m-d H:i:s',$v['outtime']);
                $list[$k]['creditMoney'] = round($v['creditMoney'],2);
                $list[$k]['number'] = round($v['number']); 
                $list[$k]['multiplying'] = $v['multiplying']; 
                $list[$k]['profitLose'] = $v['profitLose'];
                $list[$k]['profitLose_rate'] = number_format(round(bcdiv($v['profitLose'],$v['creditMoney'],20),3)*100,2);
                $list[$k]['lx'] = "平仓";
                
            }
        }
        // $this->success('请求成功', ['list'=>$list]);
        $this->success('请求成功', ['list'=>$list,'total_city_value'=>0,'position_money'=>0]);
    }
    
    
    
    //配资平仓
    public function closeOut_pz1(){
        if(!betweentime(config('site.swpzjy_shijian')) && !betweentime(config('site.xwpzjy_shijian'))){
            $this->error('下单失败,不在交易时间段');
        }
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day,'status'=>'0'])->select();
            if($data11){
                $this->error('下单失败,不在交易时间段');
            }
        }
        $id = $this->request->post('id');
        $allcode = $this->request->post('allcode');
        $waystatus = $this->request->post('waystatus');
        $model = new Addstrategy();
        $sModel = new Strategy();
        $info = $model->where(['id'=>$id])->find();
        $sInfo = $sModel->where(['allcode'=>$allcode])->find();
        if($info['status'] == "3"){
            $this->error('该票已卖出，等待刷新');
        }
        $todayStime = strtotime(date('Y-m-d',time()).' 00:00:00');
        $todayEtime = strtotime(date('Y-m-d',time()).' 23:59:59');
        $pingday = $info['pingday'];
        $date_current_addone = date('Y-m-d',strtotime("+{$pingday}day",$info['createtime']));
        // $this->error($date_current_addone);
        //卖出的当前时间 就是大于第二天开盘 就OK
        $time = explode('-',config('site.swpzjy_shijian'))[0];
        // $this->error($time);
        $todayStime_1 = strtotime($date_current_addone.' '.$time);//当前卖出股的第二天9点才能卖
        if(!$sInfo['currentstatus']){
            //未开启当天平仓 就要计算下平仓时间
            if(time()<$todayStime_1){
                $this->error("T+{$pingday}平仓");
            }
        }else{
            //如果开启当天平仓 就不受时间限制
        }
        // file_put_contents('../public/logs/hhhh.txt',var_export($id,true)."\n\n",FILE_APPEND);
        //判断
        $obj = new Http();
        $nowGu1 = $obj->get_stock_now_info($info['allcode']);
        // file_put_contents('../public/logs/hhhh.txt',var_export($nowGu1,true)."\n\n",FILE_APPEND);
        // $ff = bcmul($info['number'],$info['multiplying'],2);
        $ff = bcmul($info['number'],1,2);
        $num3 = bcmul(($nowGu1[3]-$info['buyprice']),$ff,2);
        //收益 也就是
        // $profitLose = $num3>=$info['creditMoney']?$info['creditMoney']:$num3;
        $profitLose = $num3;
        $info->waystatus = 1;
        $info->status = 3;
        $info->profitLose = $profitLose;
        $info->outtime = time();
        $info->sellprice = $nowGu1[3];//$sInfo['cai_buy'];
        //卖出手续费
        // $info->yhfee = round($info['creditMoney'] * $info['multiplying'] * config('site.yh_fee'),2);
        $info->yhfee = 0;
        $info->sxfee = round($info['creditMoney'] * $info['multiplying'] * config('site.maic_fee'),2);
        
        //卖出收印花税
        if($info->save()!=='false'){
            $userModel = new \app\admin\model\User();
            $beforeMoney = Tool::getUserBalance($info['user_id']);
            $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
            $money = round(bcadd($userInfo['balance']+$info['creditMoney']-$info['yhfee']-$info['sxfee'],$profitLose,20),2);
            $money1 = round(bcadd($info['creditMoney']-$info['yhfee']-$info['sxfee'],$profitLose,20),2);
            $freeze_profit = round(bcadd($userInfo['freeze_profit'],$money1),2);
            if(config('site.kq_dj')){
                $userInfo->freeze_profit = $freeze_profit;
                $userInfo->balance = $money<0?0:$money;
            }else{
                $userInfo->balance = $money<0?0:$money;
            }
            $userInfo->save();
            $addMoney = bcadd($info['creditMoney'],$profitLose,2);
            Tool::addLog($this->auth->id,"平仓收益",$beforeMoney,Tool::getUserBalance($this->auth->id),$addMoney,0,$id);
        }
        Tool::addLog($this->auth->id,"费印花税",0,0,$info['yhfee'],1,$id);
        Tool::addLog($this->auth->id,"平仓手续",0,0,$info['sxfee'],1,$id);
        $this->success('平仓成功');
    }
}
