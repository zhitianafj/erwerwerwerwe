<?php

namespace app\api\controller;

use app\admin\model\Admin;
use app\admin\model\Adv;
use app\admin\model\Agreement;
use app\admin\model\Attention;
use app\admin\model\Banner;
use app\admin\model\feedback\Problem;
use app\admin\model\Nav;
use app\admin\model\strategy\Addstrategy;
use app\admin\model\strategy\Classify;
use app\admin\model\zixuan\Zixuan;
use app\admin\model\strategy\Exponent;
use app\admin\model\strategy\Optional;
use app\admin\model\strategy\Strategy;
use app\admin\model\sysconfig\Sysbanks;
use app\common\controller\Api;
use fast\Http;
use fast\Tool;
use think\Db;
use app\admin\model\shengou\Shengou;
use app\admin\model\news\Newcatalog;
use app\admin\model\news\Newscontent;
// 引用redis
use think\cache\driver\Redis;
use fast\Objtojson;
use app\admin\model\Zxfenzu;
use app\admin\model\Zixuannew;
use app\admin\model\Ggnews;
use app\admin\model\Bxzj;
use app\admin\model\Cai;
use app\admin\model\Etf;
use app\admin\model\Etfbaseinfo;
/**
 * 首页接口
 */
class Etfface extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];


    /**
     * Etf 接口
    */
    public function getEtfDetail(){
        $strategy = new Etf();
        $page = $this->request->param('page')?$this->request->param('page'):1; 
        $list = $strategy
                ->where(['status'=>1])
                ->field('title,CONCAT("s_",allcode) as allcode_s,allcode,code')
                ->page($page,20)
                ->select();
        if(count($list)>0){
            $arr1 =array_unique(array_column($list,'allcode_s'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia_new($allcodes);
        }
        foreach ($list as $k => $v){
            $list[$k]['allcodes_arr'] = $allcodes_arr[$v['allcode']];
        }
        $this->success('请求成功', ['list'=>$list]);
    }
    
    // 获取etf的基本信息
    public function getjjetf(){
        // 传入allcode  根据 news_time 对比当前时间 如果当前时间没有 就再采集 并存入数据
        $allcode = $this->request->param('allcode');
        $code = $this->request->param('code');
        // 新浪采集
        $Etfbaseinfo_model = new Etfbaseinfo();
        $Etfbaseinfo_info = $Etfbaseinfo_model->where(['code'=>$code])->find();
        if(!$Etfbaseinfo_info){
            // 采集入
            $url = "https://stock.finance.sina.com.cn/fundInfo/api/openapi.php/FundPageInfoService.tabjjgk?symbol={$code}&format=json";
            $obj = new Http();
            $result =json_decode($obj->getproxy($url),true);
            $etfbaseinfos = new Etfbaseinfo();
            
            $etfbaseinfos->code = $result['result']['data']['symbol'];
            $etfbaseinfos->jjqc = $result['result']['data']['jjqc'];
            $etfbaseinfos->jjjc = $result['result']['data']['jjjc'];
            $etfbaseinfos->symbol = $result['result']['data']['symbol'];
            $etfbaseinfos->clrq = $result['result']['data']['clrq'];
            $etfbaseinfos->ssrq = $result['result']['data']['ssrq'];
            
            $etfbaseinfos->xcr = $result['result']['data']['xcr'];
            $etfbaseinfos->ssdd = $result['result']['data']['ssdd'];
            $etfbaseinfos->Type1Name = $result['result']['data']['Type1Name'];
            $etfbaseinfos->Type2Name = $result['result']['data']['Type2Name'];
            $etfbaseinfos->Type3Name = $result['result']['data']['Type3Name'];
            $etfbaseinfos->NewType1Name = $result['result']['data']['NewType1Name'];
            
            $etfbaseinfos->NewType2Name = $result['result']['data']['NewType2Name'];
            $etfbaseinfos->NewType3Name = $result['result']['data']['NewType3Name'];
            $etfbaseinfos->jjgm = $result['result']['data']['jjgm'];
            $etfbaseinfos->jjfe = $result['result']['data']['jjfe'];
            $etfbaseinfos->jjltfe = $result['result']['data']['jjltfe'];
            $etfbaseinfos->jjferq = $result['result']['data']['jjferq'];
            
            $etfbaseinfos->SGXX = $result['result']['data']['SGXX'];
            $etfbaseinfos->quarter = $result['result']['data']['quarter'];
            $etfbaseinfos->ManagerName = $result['result']['data']['ManagerName'];
            $etfbaseinfos->glr = $result['result']['data']['glr'];
            $etfbaseinfos->glrurl = $result['result']['data']['glrurl'];
            $etfbaseinfos->tgr = $result['result']['data']['tgr'];
            
            $etfbaseinfos->yh = $result['result']['data']['yh'];
            $etfbaseinfos->zq = $result['result']['data']['zq'];
            $etfbaseinfos->CompanyId = $result['result']['data']['CompanyId'];
            $etfbaseinfos->ScaleYear = $result['result']['data']['ScaleYear'];
            $etfbaseinfos->ScaleQuarter = $result['result']['data']['ScaleQuarter'];
            $etfbaseinfos->ScaleStyle = $result['result']['data']['ScaleStyle'];
            
            $etfbaseinfos->FinanceYear = $result['result']['data']['FinanceYear'];
            $etfbaseinfos->FinanceQuarter = $result['result']['data']['FinanceQuarter'];
            $etfbaseinfos->FinanceStyle = $result['result']['data']['FinanceStyle'];
            $etfbaseinfos->bjjz = $result['result']['data']['bjjz'];
            $etfbaseinfos->tzmb = $result['result']['data']['tzmb'];
            $etfbaseinfos->tzfw = $result['result']['data']['tzfw'];
            $etfbaseinfos->fxsytz = $result['result']['data']['fxsytz'];
            $etfbaseinfos->fpyz = $result['result']['data']['fpyz'];
            
            $etfbaseinfos->save();
        }
        
        // 最后执行查询
        $Etfbaseinfo_info = $Etfbaseinfo_model->where(['code'=>$code])->find();
        $this->success('请求成功',$Etfbaseinfo_info);
    }
    // 买入
    public function addStrategy(){
        //判断是否实名
        if(config('site.is_rz')) {
            $identity_card_info = Db::name('identity_card')->where(['user_id' => $this->auth->id])->find();
            if(!$identity_card_info){
                $this->error("请先前往个人中心,进行实名认证");
            }
            if ($identity_card_info['is_audit'] !== "1") {
                $this->error("请先前往个人中心,进行实名认证");
            }
        }
        // $md_date = date('m-d',time());
        // $holidayop = model('app\admin\model\Holidayop')->where('time_str',$md_date)->find();
        // if(!isset($holidayop['status']) || !$holidayop['status']){
        //     $this->error('不允许交易');
        // }
        if(!betweentime(config('site.etfswjiaoyi')) && !betweentime(config('site.etfxwjiaoyi'))){
            $this->error('下单失败,不在交易时间段');
        }
        // 开启节假日 不能交易，如果开启了 则需要判断 某个时间是否开启了，如果开启了。就能交易
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day,'status'=>'0'])->select();
            if($data11){
                $this->error('下单失败,不在交易时间段');
            }
        }
        //判断玩家是否已经禁止登陆或者禁止交易
        $data = $this->request->post();
        $model = new Etf();
        $data['user_id'] = $this->auth->id;
        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        //控制下单金额
        $kz_xdmoney = config('site.etfzuidijyje');
        if(floatval($kz_xdmoney)>floatval($data['cityValue'])){
            $this->error("下单金额不能少于".$kz_xdmoney);
        }
        $sInfo = $model->where('allcode',$data['allcode'])->find();
        $data['strategy_id'] = $sInfo['id'];
        $data['creditMoney'] = $data['cityValue'];
        if($sInfo['status']=='0'){
            $this->error('已禁止买入');
        }
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
            $data['buytype'] = 6;
            // 是否开启委托 如果开启 status=2 否则status=1
            // var_dump(config('site.is_weituo'));
            // exit;
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
    //绿色的卖出 卖出 要记录 锁仓 收益资金
    public function sell(){ 
        if(!betweentime(config('site.etfswjiaoyi')) && !betweentime(config('site.etfxwjiaoyi'))){
            $this->error('平仓失败,不在交易时间段');
        }
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day,'status'=>'0'])->select();
            if($data11){
                $this->error('下单失败,不在交易时间段');
            }
        }
        // 当前记录ID
        $id = $this->request->post('id');
        // 股票代码全程
        $allcode = $this->request->post('allcode');
        // 手数
        $canbuy = $this->request->post('canBuy');
        // 固定1
        $waystatus = $this->request->post('waystatus');
        $model = new Addstrategy();
        $sModel = new Etf();
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
        $time = explode('-',config('site.etfswjiaoyi'))[0];
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
        //判断 判断当前传入的手数和代码里面数是否一致 如果一致就是直接全部平仓
        if(intval($canbuy) == $info['canBuy']){
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
                $money = round(bcadd($userInfo['balance']+$info['creditMoney']-$info['yhfee']-$info['sxfee']    ,$profitLose,20),2);
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
        }else{
            // 将其中一条拆分成两条，修改原来的数据 ，新增现在的数据
            // 需要改的 creditMoney  allMoney canBuy cityValue money recommendnum number
            //新增的 creditMoney = $canbuy(手数) *100 *股价(原来)
            // 新增的 canBuy = $canbuy(手数)
            // 新增 allMoney(买入手续费) = creditMoney * 费率
            // 新增的 cityValue = $canbuy(手数) *100 *股价(原来)
            // 新增的 money = creditMoney + allMoney
            // 新增的 recommendnum = $canbuy(手数) *100 *股价(原来)
            // 新增的 number = $canbuy(手数) *100
            $new_info_act = new Addstrategy();
            $new_info = $info;
            unset($new_info->id);
            $new_info = Objtojson::object2array($new_info);
            $new_info['creditMoney'] = round($canbuy * 100 * $info['buyprice']);
            $new_info['allMoney'] = round($canbuy * 100 * $info['buyprice'] * config('site.mai_fee'),2);
            $new_info['canBuy'] = $canbuy;
            // 在其他地方应该是按照最新价来计算市值，现在这个地方就按照买入价
            $new_info['cityValue'] = round($canbuy * 100 * $info['buyprice'],2);
            $new_info['money'] =bcadd($new_info['creditMoney'], $new_info['allMoney'],2);
            $new_info['recommendnum'] = round($canbuy * 100 * $info['buyprice']);
            $new_info['number'] = $canbuy * 100;
            // 将ID过滤 新增成一条已经卖出的记录。并且要对user表进行修改
            $obj = new Http();
            $nowGu1 = $obj->get_stock_now_info($new_info['allcode']);
            // file_put_contents('../public/logs/hhhh.txt',var_export($nowGu1,true)."\n\n",FILE_APPEND);
            $num3 = bcmul(($nowGu1[3]-$new_info['buyprice']),$new_info['number'],2);
            //收益 也就是
            $profitLose = $num3;
            $new_info["waystatus"] = 1;
            $new_info["status"] = 3;
            $new_info["profitLose"] = $profitLose;
            $new_info["outtime"] = time();
            $new_info["sellprice"] = $nowGu1[3];//$sInfo['cai_buy'];
            //卖出手续费
            $new_info["yhfee"] = round($new_info['creditMoney'] * config('site.yh_fee'),2);
            $new_info['sxfee'] = round($new_info['creditMoney'] * config('site.maic_fee'),2);
            //卖出收印花税
            // var_dump($new_info);
            // return;
            $new_info_act->data($new_info);
            if($new_info_act->save()!=='false'){
                $userModel = new \app\admin\model\User();
                $beforeMoney = Tool::getUserBalance($info['user_id']);
                $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
                $money = round(bcadd($userInfo['balance']+$new_info['creditMoney']-$new_info['yhfee']-$new_info['sxfee'],$profitLose,20),2);
                $money1 = round(bcadd($new_info['creditMoney']-$new_info['yhfee']-$new_info['sxfee'],$profitLose,20),2);
                $freeze_profit = round(bcadd($userInfo['freeze_profit'],$money1),2);
                if(config('site.kq_dj')){
                    $userInfo->freeze_profit = $freeze_profit;
                    $userInfo->balance = $money<0?0:$money;
                }else{
                    $userInfo->balance = $money<0?0:$money;
                }
                $userInfo->save();
                $addMoney = bcadd($new_info['creditMoney'],$profitLose,2);
                Tool::addLog($this->auth->id,"平仓收益",$beforeMoney,Tool::getUserBalance($this->auth->id),$addMoney,0,$id);
            }
            Tool::addLog($this->auth->id,"费印花税",0,0,$new_info['yhfee'],1,$new_info_act->id);
            Tool::addLog($this->auth->id,"平仓手续",0,0,$new_info['sxfee'],1,$new_info_act->id);




            // 修改老的记录
            $info['creditMoney'] = $info['creditMoney'] - $new_info['creditMoney'];
            $info['allMoney'] = $info['allMoney'] - $new_info['allMoney'];
            $info['canBuy'] = $info['canBuy'] - $new_info['canBuy'];
            $info['cityValue'] = $info['cityValue'] - $new_info['cityValue'];
            $info['money'] = $info['money'] - $new_info['money'];
            $info['recommendnum'] = $info['recommendnum'] - $new_info['recommendnum'];
            $info['number'] = $info['number'] - $new_info['number'];
            // $info 做修改
            $info->save();

            $this->success('平仓成功');
        }

    }
    

    //持仓列表里面的卖出
    public function sellAll(){
        if(!betweentime(config('site.etfswjiaoyi')) && !betweentime(config('site.etfxwjiaoyi'))){
            $this->error('平仓失败,不在交易时间段');
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
        $sModel = new Etf();
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
        $time = explode('-',config('site.etfswjiaoyi'))[0];
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
            // $money = round(bcadd($userInfo['balance']+$info['creditMoney']-$info['yhfee']-$info['sxfee'],$profitLose,20),2);
            // $userInfo->balance = $money;
            // $userInfo->save();
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
    //持仓列表
    public function getNowWarehouse(){
        $status = $this->request->get('status');
        $buytype = $this->request->get('buytype');
        $page = $this->request->get('page')?$this->request->get('page'):1;
        if($status == 2){
            $status = 3;
        }
        $total_city_value = 0;
        $total_position_money = 0;
        $model = new Addstrategy();
        $list = $model
            ->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])
            ->order('createtime desc')
            ->page($page,20)
            ->select();
        if($status == 3){
            $list = $model
                ->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])
                ->order('outtime desc')
                ->page($page,20)
                ->select();
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
        $this->success('请求成功', ['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
    }
    
    //查询tab
    public function getNowWarehouse_lishi()
    {
        $status = $this->request->get('status');
        $buytype = $this->request->get('buytype');
        $type = $this->request->get('type');
        $page = $this->request->get('page') ? $this->request->get('page') : 1;
        $s_time = $this->request->get('s_time');
        $e_time = $this->request->get('e_time');
        if ($status == 2) {
            $status = 3;
        }
        $model = new Addstrategy();
        if (!$type || $type == '1') {
            $list = $model
                ->where(['deletetime' => Null, 'user_id' => $this->auth->id, 'status' => $status, 'buytype' => $buytype])
                ->whereTime('createtime', 'today')
                ->order('outtime desc')
                ->page($page, 20)
                ->select();
        } else {
            $s_time_s = strtotime($s_time);
            $e_time_s = strtotime($e_time);
            $list = $model
                ->where(['deletetime' => Null, 'user_id' => $this->auth->id, 'status' => $status, 'buytype' => $buytype])
                ->where('createtime','>=',$s_time_s)
                ->where('createtime','<=',$e_time_s)
                ->order('outtime desc')
                ->page($page, 20)
                ->select();
        }
        if ($list) {
            foreach ($list as $k => $v) {
                $list[$k]['title'] = $v['title'];
                $list[$k]['allcode'] = $v['allcode'];
                $list[$k]['cai_buy'] = $v['sellprice'];
                $list[$k]['createtime_name'] = date('Y-m-d H:i:s', $v['createtime']);
                $list[$k]['outtime_name'] = date('Y-m-d H:i:s', $v['outtime']);
                $list[$k]['creditMoney'] = round($v['creditMoney'], 2);
                $list[$k]['number'] = round($v['number']);
                $list[$k]['profitLose'] = $v['profitLose'];
                $list[$k]['profitLose_rate'] = number_format(round(bcdiv($v['profitLose'],$v['creditMoney'],20),3)*100,2);
                $list[$k]['lx'] = '平仓';
            }
        }
        $this->success('请求成功', ['list' => $list]);
    }
    
    // 查询历史委托
    public function getNowWarehouse_lishi_wt()
    {
        // 查询 所有的 买入卖出  只要是4  就是已撤单 其他 都是已成交
        $status = $this->request->get('status');
        $buytype = $this->request->get('buytype');
        $type = $this->request->get('type');
        $page = $this->request->get('page') ? $this->request->get('page') : 1;
        $s_time = $this->request->get('s_time');
        $e_time = $this->request->get('e_time');
        if ($status == 2) {
            $status = 3;
        }
        $model = new Addstrategy();
        if (!$type || $type == '1') {
            $list = $model
                ->where(['deletetime' => Null, 'user_id' => $this->auth->id, 'buytype' => $buytype])
                ->whereTime('createtime', 'today')
                ->order('createtime desc')
                ->page($page, 20)
                ->select();
        } else {
            $s_time_s = strtotime($s_time);
            $e_time_s = strtotime($e_time);
            $list = $model
                ->where(['deletetime' => Null, 'user_id' => $this->auth->id, 'buytype' => $buytype])
                ->where('createtime','>=',$s_time_s)
                ->where('createtime','<=',$e_time_s)
                ->order('createtime desc')
                ->page($page, 20)
                ->select();
        }
        if ($list) {
            foreach ($list as $k => $v) {
                $list[$k]['title'] = $v['title'];
                $list[$k]['allcode'] = $v['allcode'];
                $list[$k]['cai_buy'] = $v['sellprice'];
                $list[$k]['createtime_name'] = date('Y-m-d H:i:s', $v['createtime']);
                $list[$k]['outtime_name'] = date('Y-m-d H:i:s', $v['outtime']);
                $list[$k]['creditMoney'] = round($v['creditMoney'], 2);
                $list[$k]['number'] = round($v['number']);
                $list[$k]['profitLose'] = $v['profitLose'];
                $list[$k]['profitLose_rate'] = number_format(round(bcdiv($v['profitLose'],$v['creditMoney'],20),3)*100,2);
                if($v['status'] == '4'){
                    $list[$k]['lx'] = '已撤单';
                }else{
                    $list[$k]['lx'] = '已成交';
                }
            }
        }
        $this->success('请求成功', ['list' => $list]);
    }
    
    // 撤单操作
    public function cheAll(){
        // 记录一张委托撤单表
        $id = $this->request->post('id');
        $allcode = $this->request->post('allcode');
        $waystatus = $this->request->post('waystatus');
        $model = new Addstrategy();
        $info = $model->where(['id'=>$id])->find();
        if($info['status'] == "4"){
            $this->error('该票已撤单，等待刷新');
        }
        $info->status = 4;
        $resutl = $info->save();
        if($resutl !== false){
            // 并且退还资金
            $userModel = new \app\admin\model\User();
            $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
            $money = round($userInfo['balance'] + $info['allMoney'] + $info['creditMoney'],2);
            $userInfo->balance = $money;
            $userInfo->save();
            $this->success('撤单成功');
        }else{
            $this->error('撤单失败');
        }
    }
    
    public function mrSellLst(){
        $keyword = $this->request->get('keyword');
        $model = new Addstrategy();
        $list = $model
            ->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>1,'buytype'=>6])
            ->where('allcode','like','%'.$keyword.'%')
            ->order('createtime desc')
            ->select();
        $this->success('请求成功',$list);
    }
    
    
    // 获取委托
    public function getNowWarehouse_weituo(){
        $status = $this->request->get('status');
        $buytype = $this->request->get('buytype');
        $page = $this->request->get('page')?$this->request->get('page'):1;
        $total_city_value = 0;
        $total_position_money = 0;
        $model = new Addstrategy();
        $list = $model
            ->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])
            ->order('createtime desc')
            ->page($page,20)
            ->select();
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
            // $total_city_value = round(array_sum(array_column($list,'citycc')),2);
            // $total_position_money = round(array_sum(array_column($list,'profitLose')),2);
        }
        // $this->success('请求成功', ['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
        $this->success('请求成功', ['list'=>$list]);
    }
    
    /**
     * 个股详情界面
    */
    public function getHqinfo_1(){
        $query = $this->request->param('q');
        if(!$query){
            $this->error('请求成功', ['info' => []]);
        }
        $info = Http::get_stock_now_info($query);
        $list = array(
            "c_q"=>round($info[3]-$info[4],2),
            "c_b"=>round(($info[3]-$info[4])/$info[4]*100,2),
            "cur_p"=>round($info[3],2),
            "today_p"=>round($info[5],2),
            "yes_p"=>round($info[4],2),
            "zg"=>round($info[33],2),
            "zd"=>round($info[34],2),
            'zy'=>round(bcmul($info[3],1.1),2), //止盈价格
            'zs'=>round(bcmul($info[3],0.94),2) //止损价格
        );
        $zxInfo = model('app\admin\model\Zixuannew')->where(['allcode'=>$query,'user_id'=>$this->auth->id])->find();
        $is_zx = 0;
        if($zxInfo){
            $is_zx = 1;
        }
        //最新公告一条
        $ggInfo = model('app\admin\model\Ggnotice')->order('notice_date desc')->find()['notice_title'];
        //最新新闻一条
        $xwInfo = model('app\admin\model\Ggnews')->order('news_time desc')->find()['news_title'];

        $nameInfo = getDetailCodeJdName($query);

        //当前code是否持仓
        $is_cc = 0;
        $str_model = new Addstrategy();
        $str_info = $str_model->where(['user_id'=>$this->auth->id,'allcode'=>$query,'status'=>1,'buytype'=>6])->find();
        if($str_info){
            $is_cc = 1;
        }

        $this->success('请求成功', ['is_cc'=>$is_cc,'is_zx'=>$is_zx,'info' => $info,'ggInfo'=>$ggInfo,'xwInfo' => $xwInfo,'name1'=>$nameInfo['name1'],'name2'=>$nameInfo['name2'],'list' => $list]);
    }
}
