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
use fast\Rsa;
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
class Dzjy extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';
    
    //大宗交易
    public function addStrategy_dz(){
        if(!betweentime(config('site.dzjy_shijian'))){
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
        if(!$userInfo['is_dz']){
            $this->error("当前账户异常,禁止大宗交易");
        }
        //控制下单金额
        $kz_xdmoney = config('site.dzjy_jine');
        if(floatval($kz_xdmoney)>floatval($data['money'])){
            $this->error("交易失败，可用余额少于".$kz_xdmoney);
        }
        $sInfo = $model->where('allcode',$data['allcode'])->find();
        $data['strategy_id'] = $sInfo['id'];
        // $data['creditMoney'] = $data['cityValue'];
        if($sInfo['status']=='1'){
            $this->error('已禁止买入');
        }
        if($sInfo['is_dz']=="0"){
            $this->error('已禁止买入');
        }
        if(!$data['number']){
            $this->error('买入参数错误，请重新买入');
        }
        //判断整数
        if(!Tool::is__int($data['canBuy'])){
            $this->error('请输入整数的手数');
        }
        //控制下单手数
        $kz_dzjy_ss = config('site.dzjy_ss');
        if(floatval($kz_dzjy_ss)>floatval($data['canBuy'])){
            $this->error("交易失败，购买手数少于".$kz_dzjy_ss);
        }
        //判断节假日 不允许买入和卖出
        //判断
        if($data['money']>$userInfo['balance']){
            $this->error('余额不足，请及时充值');
        }
        $freeze_profit = 0;
        $balance = round(bcsub($userInfo['balance'],$data['money'],20),2);
        if(config('site.kq_dj')){
            if(floatval($data['money'])<floatval($userInfo['freeze_profit'])){
                // $balance = $userInfo['balance'];
                $freeze_profit = round(bcsub($userInfo['freeze_profit'],$data['money'],20),2);
            }else if(floatval($data['money'])>floatval($userInfo['freeze_profit'])){
                $freeze_profit = 0;
                // $balance = round(bcsub(($userInfo['balance']+$userInfo['freeze_profit']),$data['money'],20),2);
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
            $data['buytype'] = 2;
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
    
    //大宗持仓
    public function getNowWarehouse(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $status = Rsa::check($paramInfo['status']);
        $buytype = Rsa::check($paramInfo['buytype']);
        $page = Rsa::check($paramInfo['page']);

        //$status = $this->request->get('status');
        //$buytype = $this->request->get('buytype');
        if($status == 2){
            $status = 3;
        }
        //$page = $this->request->get('page')?$this->request->get('page'):1;
        $total_city_value = 0;
        $total_position_money = 0;
        $model = new Addstrategy();
        $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('createtime desc')
            ->page($page,20)->select();
        if($status == 3){
            $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('outtime desc')
            ->page($page,20)->select();
        }
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
                $list[$k]['citycc'] = bcmul(($allcodes_arr[$v['allcode']]),$v['number'],2);
                $list[$k]['number'] = round($v['number']);
                $list[$k]['profitLose'] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number'],2);
                $list[$k]['profitLose_rate'] = number_format(((bcdiv(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['buyprice'],4))*100),2)."%";
                
            }
            $total_city_value = round(array_sum(array_column($list,'citycc')),2);
            $total_position_money = round(array_sum(array_column($list,'profitLose')),2);
        }
        $data = Rsa::jia(['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
        $this->success('请求成功', $data);
    }
    //大宗交易持仓_历史
    public function getNowWarehouse_lishi(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $status = Rsa::check($paramInfo['status']);
        $buytype = Rsa::check($paramInfo['buytype']);
        $page = Rsa::check($paramInfo['page']);

        //$status = $this->request->get('status');
        //$buytype = $this->request->get('buytype');
        //$page = $this->request->get('page')?$this->request->get('page'):1;
        if($status == 2){
            $status = 3;
        }
        $total_city_value = 0;
        $total_position_money = 0;
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
                $list[$k]['profitLose'] = $v['profitLose'];
                // $list[$k]['profitLose_rate'] = number_format(round(bcdiv($v['profitLose'],$v['creditMoney'],20),3)*100,2)."%";
                $list[$k]['profitLose_rate'] = number_format(((bcdiv(($v['sellprice']-$v['buyprice']),$v['buyprice'],4))*100),2)."%";
                
            }
            $total_city_value = round(array_sum(array_column($list,'creditMoney')),2);
            $total_position_money = round(array_sum(array_column($list,'profitLose')),2);
        }
        $data = Rsa::jia(['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
        $this->success('请求成功', $data);
    }
    
    
    //大宗平仓
    public function closeOut_dz(){
        //买出改成单独的时间
        if(!betweentime(config('site.dzjyzfa_maichu_shijian')) && !betweentime(config('site.dzjyzfaxw_maichu_shijian'))){
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

        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $id = Rsa::check($paramInfo['id']);
        $allcode = Rsa::check($paramInfo['allcode']);
        $waystatus = Rsa::check($paramInfo['waystatus']);

        //$id = $this->request->post('id');
        //$allcode = $this->request->post('allcode');
        //$waystatus = $this->request->post('waystatus');


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
        //卖出的当前时间 就是大于第二天开盘 就OK
        $time = explode('-',config('site.dzjyzfa_maichu_shijian'))[0];
        // $this->error($time);
        $todayStime_1 = strtotime($date_current_addone.' '.$time);//当前卖出股的第二天9点才能卖
        if(!$sInfo['currentstatus']){
            // $this->error($date_current_addone);
            //未开启当天平仓 就要计算下平仓时间
            if(time()<$todayStime_1){
                // $this->error('T+1平仓');
                $this->error("T+{$pingday}平仓");
            }
        }else{
            
            // $this->error('222222222');
            //如果开启当天平仓 就不受时间限制
        }
        // file_put_contents('../public/logs/hhhh.txt',var_export($id,true)."\n\n",FILE_APPEND);
        //判断
        $obj = new Http();
        $nowGu1 = $obj->get_stock_now_info($info['allcode']);
        // file_put_contents('../public/logs/hhhh.txt',var_export($nowGu1,true)."\n\n",FILE_APPEND);
        $num3 = bcmul(($nowGu1[3]-$info['buyprice']),$info['number'],2);
        //收益 也就是
        $profitLose = $num3;
        $info->waystatus = 1;
        $info->status = 3;
        $info->profitLose = $profitLose;
        $info->outtime = time();
        $info->sellprice = $nowGu1[3];//$sInfo['cai_buy'];
        //卖出手续费
        $info->yhfee = round($info['creditMoney'] * config('site.yh_fee'),2);
        $info->sxfee = round($info['creditMoney'] * config('site.maic_fee'),2);
        
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
                // $userInfo->balance = $money<0?0:$money;
                $userInfo->balance = $money;
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
    
    
    public function getHistoryDetail(){
        $id = $this->request->get('id');
        $model = new Addstrategy();
        $info = $model->where(['id'=>$id])->find();

        // $nowGu = getOneGu($info['allcode']);
        $profitLose = $info['profitLose'];//bcmul($info['cityValue'],$nowGu['increase'],2);

        $backMoney = bcadd($info['creditMoney'],$profitLose,2);
        $data = [
            'creditMoney' => $info['creditMoney'],
            'profitLose' => $profitLose,
            'backMoney' => $backMoney,
        ];

        $this->success('返回成功',['list'=>$data]);
    }
    
    public function lst(){
        // 获取大宗列表 
        $model = new Strategy();
        $type = $this->request->param('type');
        $page = $this->request->param('page')?$this->request->param('page'):1;
        $list = $model->where(['is_zfa'=>"1"])
            ->distinct(true)
            ->page($page,20)
            ->order('id desc')
            ->select();
            // var_dump($this->auth->balance);
        if($list){
            $arr1 =array_unique(array_column($list,'allcode'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia_new($allcodes);
            // var_dump($allcodes_arr);
            foreach($list as $k=>$v){
                // 计算出 已经购买的手数
                $list[$k]['totalbuy'] = Db::name('add_strategy')->where(['allcode'=>$v['allcode'],'buytype'=>'7'])->sum('number');
                $list[$k]['cai_pricechange'] = $allcodes_arr[$v['allcode']][32];
                $list[$k]['cai_price'] = $allcodes_arr[$v['allcode']][3];
                // 要除掉手续费 也就是最大
                $s =bcmul((1+floatval(config('site.dzsxf'))),(floatval($v['cai_buy'])*100),20); 
                $list[$k]['max_num'] = intval(floatval($this->auth->balance)/$s);
                $list[$k]['p'] =round(bcdiv($list[$k]['totalbuy'],bcadd($list[$k]['totalbuy'],$v['zfanum'],20),20),2)*100;
                // 价格折扣率
                // $list[$k]['rate'] = round(bcdiv(bcsub(floatval($allcodes_arr[$v['allcode']][3]),floatval($v["cai_buy"])),floatval($allcodes_arr[$v['allcode']][3]),20)*100,2);
                // $list[$k]['rate'] =round(((floatval($allcodes_arr[$v['allcode']][3]) - floatval($v['cai_buy']))/floatval($allcodes_arr[$v['allcode']][3]))*100,2);
                $list[$k]['rate'] = $v['zfrate'];
            }
        }
        $this->success('请求成功', ['list'=>$list,'balance'=>$this->auth->balance,'fee'=>config('site.dzsxf')]);
    }
    //大宗交易
    public function addStrategy_zfa(){
        if(!betweentime(config('site.dzjyzfa_shijian')) && !betweentime(config('site.dzjyzfaxw_shijian'))){
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
        $query = $this->request->param('query');
        if($query){
            $paramInfo = Rsa::jie($query);
            $data = $paramInfo;
        }else{
            $data = $this->request->post();
            // $this->error($data['code']);
        }
        unset($data['jiami']);
        // unset($data['miyao']);
        // $paramInfo = Rsa::jie($query);
        // $data  = $paramInfo;
        $model = new Strategy();
        $data['user_id'] = $this->auth->id;
        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        if(!$userInfo['is_dz']){
            $this->error("当前账户异常,禁止大宗交易");
        }
        //控制下单金额
        $kz_xdmoney = config('site.dzjyzfa_jine');
        if(floatval($kz_xdmoney)>floatval($data['money'])){
            $this->error("交易失败，最低交易金额少于".$kz_xdmoney);
        }
        $sInfo = $model->where('allcode',$data['allcode'])->find();
        $data['strategy_id'] = $sInfo['id'];
        // $data['creditMoney'] = $data['cityValue'];
        if($sInfo['status']=='1'){
            $this->error('已禁止买入');
        }
        if($sInfo['conditioncode'] && ($sInfo['conditioncode'] !== $data['miyao'])){
            $this->error('请输入正确的密钥');
        }
        unset($data['miyao']);
        if(!$data['number']){
            $this->error('买入参数错误，请重新买入');
        }
        //判断整数
        if(!Tool::is__int($data['canBuy'])){
            $this->error('请输入整数的手数');
        }
        //控制下单手数
        $kz_dzjy_ss = config('site.dzjyzfa_ss');
        if(floatval($kz_dzjy_ss)>floatval($data['canBuy'])){
            $this->error("交易失败，购买手数少于".$kz_dzjy_ss);
        }
        // 判断增发数量够不够
        if(intval($sInfo['zfanum'])<intval($data['canBuy']*100)){
            $this->error("份额不足");
        }
        //判断节假日 不允许买入和卖出
        //判断
        if(floatval($data['money'])>floatval($userInfo['balance'])){
            $this->error('余额不足，请及时充值');
        }
        $freeze_profit = 0;
        $balance = round(bcsub($userInfo['balance'],$data['money'],20),2);
        $ss1 = 0;
        $ss2 = 0;
        // 修改成优先使用可用资金 就是balance
        if(config('site.kq_dj')){
            // 情况1：当前资金小于可提资金
            // 总资金
            $t0 = floatval($userInfo['balance']);
            $t1 =round(floatval($userInfo['balance'])- floatval($userInfo['freeze_profit']),2);
            if(floatval($data['money'])<=$t1){
                $balance = round(bcadd(bcsub($t1,floatval($data['money']),20),floatval($userInfo['freeze_profit']),20),2);
                $freeze_profit = $userInfo['freeze_profit'];
                $ss1 = $data['money'];
                $ss2 = 0;
                // file_put_c
            }
            // 第二种情况 可提资金不够，但是小于可用资金 
            else if($t1<floatval($data['money']) && floatval($data['money'])<floatval($userInfo['balance'])){
                // 判断冻结资金 要扣多少出来
                $s =round(bcsub(floatval($data['money']),$t1,20),2);
                $freeze_profit =round(bcsub(floatval($userInfo['freeze_profit']),$s,20),2);
                
                $balance = $freeze_profit;
                $ss1 = $t1;
                $ss2 = $s;
            }else if(floatval($data['money']) == floatval($userInfo['balance'])){
                 $balance = $freeze_profit = 0;
                $ss1 = $userInfo['freeze_profit'];
                $ss2 = $userInfo['freeze_profit'];
            }
        }
        $positionMoney = bcadd($userInfo['positionMoney'],$data['creditMoney'],2);
        Db::startTrans();
        try{
            $createTime = time();
            $data['createtime'] = $createTime;
            $data['pingday'] = $sInfo['pingday'];
            $data['c_xq'] = date("w");
            $data['gmrate'] = 0;
            $data['pidcode'] = $userInfo['superior_code'];
            $data['pidname'] = $userInfo['superior_name'];
            $data['pid'] = $userInfo['superior_id'];
            $data['is_simulations'] = $userInfo['is_simulation'];
            $data['buytype'] = 7;
            $data['balance'] = $ss1;
            $data['freeze_profit'] = $ss2;
            if(config('site.is_weituo')=="1"){
                $data['status'] = 2;
            }else{
                $data['status'] = 1;
            }
            //增加上级邀请码
            if(Db::name('add_strategy')->insert($data)!==false){
                // 减少增发数量  canBuy*100 
                
                // Db::name('strategy')->where(['allcode'=>$data['allcode']])->
                $sInfo->zfanum = intval($sInfo['zfanum'])-intval($data['canBuy']*100);
                $sInfo->save();
                
                $orderId = Db::name('add_strategy')->getLastInsID();
                $beforeMoney = Tool::getUserBalance($this->auth->id);
                $userRes = Db::name('user')->where('id',$this->auth->id)->update([
                    'balance' => $balance,
                    'freeze_profit' => $freeze_profit,
                    'positionMoney' => $positionMoney,
                ]);
                Tool::addLog($this->auth->id,"增发下单到委托",$beforeMoney,Tool::getUserBalance($this->auth->id),$data['money'],1,$orderId);
            }
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error('下单失败'.$e);
        }
        $this->success('下单成功');
    }
    
    
    
    // vip调研------------------------------------------------------------------------------------
    public function lstDy(){
        // 获取大宗列表 
        $model = new Strategy();
        $type = $this->request->param('type');
        $page = $this->request->param('page')?$this->request->param('page'):1;
        $list = $model->where(['is_dy'=>"1"])
            ->distinct(true)
            ->page($page,20)
            ->order('id desc')
            ->select();
            // var_dump($this->auth->balance);
        if($list){
            $arr1 =array_unique(array_column($list,'allcode'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia_new($allcodes);
            // var_dump($allcodes_arr);
            foreach($list as $k=>$v){
                // 计算出 已经购买的手数
                // $list[$k]['totalbuy'] = Db::name('add_strategy')->where(['allcode'=>$v['allcode'],'buytype'=>'7'])->sum('number');
                $list[$k]['cai_pricechange'] = $allcodes_arr[$v['allcode']][32];
                $list[$k]['cai_price'] = $allcodes_arr[$v['allcode']][3];
                $list[$k]['cai_buy'] = $allcodes_arr[$v['allcode']][3];
                // 要除掉手续费 也就是最大
                $s =bcmul((1+floatval(config('site.dzsxf'))),(floatval($v['cai_buy'])*100),20); 
                $list[$k]['max_num'] = intval(floatval($this->auth->balance)/$s);
                // $list[$k]['p'] =round(bcdiv($list[$k]['totalbuy'],bcadd($list[$k]['totalbuy'],$v['zfanum'],20),20),2)*100;
                // 价格折扣率
                // $list[$k]['rate'] = round(bcdiv(bcsub(floatval($allcodes_arr[$v['allcode']][3]),floatval($v["cai_buy"])),floatval($allcodes_arr[$v['allcode']][3]),20)*100,2);
            }
        }
        $this->success('请求成功', ['list'=>$list,'balance'=>$this->auth->balance,'fee'=>config('site.dzsxf')]);
    }
    public function addStrategy_dy(){
        if(!betweentime(config('site.vip_sw_shijian')) && !betweentime(config('site.vip_xw_shijian'))){
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
        $query = $this->request->param('query');
        if($query){
            $paramInfo = Rsa::jie($query);
            $data = $paramInfo;
        }else{
            $data = $this->request->post();
            // $this->error($data['code']);
        }
        unset($data['jiami']);
        // unset($data['miyao']);
        // $paramInfo = Rsa::jie($query);
        // $data  = $paramInfo;
        $model = new Strategy();
        $data['user_id'] = $this->auth->id;
        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        if(!$userInfo['is_dz']){
            $this->error("当前账户异常,禁止大宗交易");
        }
        //控制下单金额
        $kz_xdmoney = config('site.vip_jine');
        if(floatval($kz_xdmoney)>floatval($data['money'])){
            $this->error("交易失败，最低交易金额少于".$kz_xdmoney);
        }
        $sInfo = $model->where('allcode',$data['allcode'])->find();
        $data['strategy_id'] = $sInfo['id'];
        // $data['creditMoney'] = $data['cityValue'];
        if($sInfo['status']=='1'){
            $this->error('已禁止买入');
        }
        if($sInfo['conditioncode'] && ($sInfo['conditioncode'] !== $data['miyao'])){
            $this->error('请输入正确的密钥');
        }
        unset($data['miyao']);
        if(!$data['number']){
            $this->error('买入参数错误，请重新买入');
        }
        //判断整数
        if(!Tool::is__int($data['canBuy'])){
            $this->error('请输入整数的手数');
        }
        //控制下单手数
        $kz_dzjy_ss = config('site.vip_ss');
        if(floatval($kz_dzjy_ss)>floatval($data['canBuy'])){
            $this->error("交易失败，购买手数少于".$kz_dzjy_ss);
        }
        // 判断增发数量够不够
        // if(intval($sInfo['zfanum'])<intval($data['canBuy']*100)){
        //     $this->error("份额不足");
        // }
        //判断节假日 不允许买入和卖出
        //判断
        if(floatval($data['money'])>floatval($userInfo['balance'])){
            $this->error('余额不足，请及时充值');
        }
        $freeze_profit = 0;
        $balance = round(bcsub($userInfo['balance'],$data['money'],20),2);
        $ss1 = 0;
        $ss2 = 0;
        // 修改成优先使用可用资金 就是balance
        if(config('site.kq_dj')){
            // 情况1：当前资金小于可提资金
            // 总资金
            $t0 = floatval($userInfo['balance']);
            $t1 =round(floatval($userInfo['balance'])- floatval($userInfo['freeze_profit']),2);
            if(floatval($data['money'])<=$t1){
                $balance = round(bcadd(bcsub($t1,floatval($data['money']),20),floatval($userInfo['freeze_profit']),20),2);
                $freeze_profit = $userInfo['freeze_profit'];
                $ss1 = $data['money'];
                $ss2 = 0;
                // file_put_c
            }
            // 第二种情况 可提资金不够，但是小于可用资金 
            else if($t1<floatval($data['money']) && floatval($data['money'])<floatval($userInfo['balance'])){
                // 判断冻结资金 要扣多少出来
                $s =round(bcsub(floatval($data['money']),$t1,20),2);
                $freeze_profit =round(bcsub(floatval($userInfo['freeze_profit']),$s,20),2);
                
                $balance = $freeze_profit;
                $ss1 = $t1;
                $ss2 = $s;
            }else if(floatval($data['money']) == floatval($userInfo['balance'])){
                 $balance = $freeze_profit = 0;
                $ss1 = $userInfo['freeze_profit'];
                $ss2 = $userInfo['freeze_profit'];
            }
        }
        $positionMoney = bcadd($userInfo['positionMoney'],$data['creditMoney'],2);
        Db::startTrans();
        try{
            $createTime = time();
            $data['createtime'] = $createTime;
            $data['pingday'] = $sInfo['pingday'];
            $data['c_xq'] = date("w");
            $data['gmrate'] = 0;
            $data['pidcode'] = $userInfo['superior_code'];
            $data['pidname'] = $userInfo['superior_name'];
            $data['pid'] = $userInfo['superior_id'];
            $data['is_simulations'] = $userInfo['is_simulation'];
            $data['buytype'] = 9;
            // $data['ssss']= 1;
            $data['balance'] = $ss1;
            $data['freeze_profit'] = $ss2;
            if(config('site.is_weituo')=="1"){
                $data['status'] = 2;
            }else{
                $data['status'] = 1;
            }
            //增加上级邀请码
            if(Db::name('add_strategy')->insert($data)!==false){
                $orderId = Db::name('add_strategy')->getLastInsID();
                $beforeMoney = Tool::getUserBalance($this->auth->id);
                $userRes = Db::name('user')->where('id',$this->auth->id)->update([
                    'balance' => $balance,
                    'freeze_profit' => $freeze_profit,
                    'positionMoney' => $positionMoney,
                ]);
                Tool::addLog($this->auth->id,"VIP调研票下单",$beforeMoney,Tool::getUserBalance($this->auth->id),$data['money'],1,$orderId);
            }
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error('下单失败'.$e);
        }
        $this->success('下单成功');
    }
    public function closeOut_dy(){
        if(!betweentime(config('site.vip_sw_maichu_shijian')) && !betweentime(config('site.vip_xw_maichu_shijian'))){
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

        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $id = Rsa::check($paramInfo['id']);
        $allcode = Rsa::check($paramInfo['allcode']);
        $waystatus = Rsa::check($paramInfo['waystatus']);

        //$id = $this->request->post('id');
        //$allcode = $this->request->post('allcode');
        //$waystatus = $this->request->post('waystatus');


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
        //卖出的当前时间 就是大于第二天开盘 就OK
        $time = explode('-',config('site.vip_sw_maichu_shijian'))[0];
        // $this->error($time);
        // $time = '09:30:00';
        $todayStime_1 = strtotime($date_current_addone.' '.$time);//当前卖出股的第二天9点才能卖
        if(!$sInfo['currentstatus']){
            // $this->error($date_current_addone);
            //未开启当天平仓 就要计算下平仓时间
            if(time()<$todayStime_1){
                // $this->error('T+1平仓');
                $this->error("T+{$pingday}平仓");
            }
        }else{
            
            // $this->error('222222222');
            //如果开启当天平仓 就不受时间限制
        }
        // file_put_contents('../public/logs/hhhh.txt',var_export($id,true)."\n\n",FILE_APPEND);
        //判断
        $obj = new Http();
        $nowGu1 = $obj->get_stock_now_info($info['allcode']);
        // file_put_contents('../public/logs/hhhh.txt',var_export($nowGu1,true)."\n\n",FILE_APPEND);
        $num3 = bcmul(($nowGu1[3]-$info['buyprice']),$info['number'],2);
        //收益 也就是
        $profitLose = $num3;
        $info->waystatus = 1;
        $info->status = 3;
        $info->profitLose = $profitLose;
        $info->outtime = time();
        $info->sellprice = $nowGu1[3];//$sInfo['cai_buy'];
        //卖出手续费 根据市值来计算
        $info->yhfee = round(intval($info['number']) * floatval($nowGu1[3]) * config('site.yh_fee'),2);
        $info->sxfee = round(intval($info['number']) * floatval($nowGu1[3]) * config('site.maic_fee'),2);
        
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
                // $userInfo->balance = $money<0?0:$money;
                $userInfo->balance = $money;
            }else{
                $userInfo->balance = $money<0?0:$money;
            }
            $userInfo->save();
            $addMoney = bcadd($info['creditMoney'],$profitLose,2);
            Tool::addLog($this->auth->id,"VIP调研-平仓收益",$beforeMoney,Tool::getUserBalance($this->auth->id),$addMoney,0,$id);
        }
        Tool::addLog($this->auth->id,"VIP调研-费印花税",0,0,$info['yhfee'],1,$id);
        Tool::addLog($this->auth->id,"VIP调研-平仓手续",0,0,$info['sxfee'],1,$id);
        $this->success('平仓成功');
    }
    public function getNowWarehouseDy(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $status = Rsa::check($paramInfo['status']);
        $buytype = Rsa::check($paramInfo['buytype']);
        $page = Rsa::check($paramInfo['page']);

        //$status = $this->request->get('status');
        //$buytype = $this->request->get('buytype');
        if($status == 2){
            $status = 3;
        }
        //$page = $this->request->get('page')?$this->request->get('page'):1;
        $total_city_value = 0;
        $total_position_money = 0;
        $model = new Addstrategy();
        $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('createtime desc')
            ->page($page,20)->select();
        if($status == 3){
            $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('outtime desc')
            ->page($page,20)->select();
        }
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
                $list[$k]['citycc'] = bcmul(($allcodes_arr[$v['allcode']]),$v['number'],2);
                $list[$k]['number'] = round($v['number']);
                $list[$k]['profitLose'] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number'],2);
                $list[$k]['profitLose_rate'] = number_format(((bcdiv(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['buyprice'],4))*100),2)."%";
                
            }
            $total_city_value = round(array_sum(array_column($list,'citycc')),2);
            $total_position_money = round(array_sum(array_column($list,'profitLose')),2);
        }
        $data = Rsa::jia(['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
        $this->success('请求成功', $data);
    }
    //大宗交易持仓_历史
    public function getNowWarehouse_lishiDy(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $status = Rsa::check($paramInfo['status']);
        $buytype = Rsa::check($paramInfo['buytype']);
        $page = Rsa::check($paramInfo['page']);

        //$status = $this->request->get('status');
        //$buytype = $this->request->get('buytype');
        //$page = $this->request->get('page')?$this->request->get('page'):1;
        if($status == 2){
            $status = 3;
        }
        $total_city_value = 0;
        $total_position_money = 0;
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
                $list[$k]['profitLose'] = $v['profitLose'];
                // $list[$k]['profitLose_rate'] = number_format(round(bcdiv($v['profitLose'],$v['creditMoney'],20),3)*100,2)."%";
                $list[$k]['profitLose_rate'] = number_format(((bcdiv(($v['sellprice']-$v['buyprice']),$v['buyprice'],4))*100),2)."%";
                
            }
            $total_city_value = round(array_sum(array_column($list,'creditMoney')),2);
            $total_position_money = round(array_sum(array_column($list,'profitLose')),2);
        }
        $data = Rsa::jia(['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
        $this->success('请求成功', $data);
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}
