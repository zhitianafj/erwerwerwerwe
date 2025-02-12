<?php

namespace app\api\controller;

use app\admin\model\Admin;
use app\admin\model\Adv;
use app\admin\model\Agreement;
use app\admin\model\Attention;
use app\admin\model\Banner;
use app\admin\model\Beizhu;
use app\admin\model\Cai;
use app\admin\model\feedback\Problem;
use app\admin\model\Nav;
use app\admin\model\Roast;
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
use app\admin\model\Ggyb;
use app\admin\model\Ggnotice;
use app\admin\model\Companyjk;
use app\admin\model\Quanqiu;

class Quanqiuapi extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];
    
    public function getAlldata(){
        $allcodes_arr = [];
        // 筛选出类型 然后foreach循环
        $model = new Quanqiu();
        $page = $this->request->param('page')?$this->request->param('page'):1;
        $where['status'] = 1;
        $list = $model
            ->where($where)
            ->field('typename as name,type')
            ->distinct(true)
            ->page($page,20)
            ->order('type asc')
            ->select();
        // 提前加载出所有的结果
        $list_all = $model
                ->where($where)
                ->field('GROUP_CONCAT(xl_symbol) as allcode_s,GROUP_CONCAT(symbol) as symbol_s')
                ->find();
        // var_dump($list_all['allcode_s']);
        if($list_all){
            $obj = new Http();
            // $allcodes_arr = $obj->get_quanqiu_data($list_all['allcode_s']);
            $allcodes_arr = $obj->get_quanqiu_data_new($list_all['symbol_s']);
            // file_put_contents('../public/logs//xxxx.txt',var_export($allcodes_arr,true)."\n\n",FILE_APPEND);
        }
        if($list){
            foreach ($list as $k => $v){
                $list[$k]['open'] = true;
                $sigle_list = $model->where(['type'=>$v['type'],'status'=>1])->select();
                // $list[$k]['list'] = $sigle_list;
                foreach ($sigle_list as $kk => $vv){
                    $sigle_list[$kk]['last'] = $allcodes_arr[$vv['xl_symbol']][0];
                    $sigle_list[$kk]['pricechange'] = $allcodes_arr[$vv['xl_symbol']][1];
                    $sigle_list[$kk]['pricepercent'] = $allcodes_arr[$vv['xl_symbol']][2];
                }
                $list[$k]['list'] = $sigle_list;
                // 实时价格
                // $list[$k]['allcodes_arr'] = $allcodes_arr;
            }
        }
        $this->success('请求成功',$list);
    }
    
    // 获取全球期货得行情数据
    public function getHqinfo_qq(){
        $query = $this->request->param('q');
        if(!$query){
            $this->error('请求失败', ['info' => []]);
        }
        //最新公告一条
        $ggInfo = model('app\admin\model\Ggnotice')->order('notice_date desc')->find()['notice_title'];
        //最新新闻一条
        $xwInfo = model('app\admin\model\Ggnews')->order('news_time desc')->find()['news_title'];
        // 通过code 加载出期货信息
        $model = new Quanqiu();
        $info_q = $model->where(['xl_symbol'=>$query])->find();
        $obj = new Http();
        // $info = $obj->get_single_data_xl($query);
        $info = $obj->get_single_data_xl_new($query);
        // $info[] = $info_q['firstprice'];
        // 一手价
        // $info[] = $info_q['once'];
        $this->success('请求成功', ['query'=>$query,'ggInfo'=>$ggInfo,'xwInfo' => $xwInfo,'info' => $info,"info_q"=>$info_q]);
    }
    
    
    
    public function getfirstinfo(){
        $model = new Quanqiu();
        $page = $this->request->param('page')?$this->request->param('page'):1;
            // file_put_contents('../public/logs//hghg.txt',var_export($this->request->param('page'),true)."\n\n",FILE_APPEND);
        $where['status'] = 1;
        $list = $model
            ->where($where)
            ->page($page,20)
            ->select();
        $list_all = $model
                ->where($where)
                ->field('GROUP_CONCAT(xl_symbol) as allcode_s,GROUP_CONCAT(symbol) as symbol_s')
                ->find();
        if($list_all){
            $obj = new Http();
            // $allcodes_arr = $obj->get_quanqiu_data($list_all['allcode_s']);
            $allcodes_arr = $obj->get_quanqiu_data_new($list_all['symbol_s']);
            // file_put_contents('../public/logs//xxxx.txt',var_export($allcodes_arr,true)."\n\n",FILE_APPEND);
        }
        if($list){
            foreach ($list as $k => $v){
                $list[$k]['last'] = $allcodes_arr[$v['xl_symbol']][0];
                $list[$k]['pricechange'] = $allcodes_arr[$v['xl_symbol']][1];
                $list[$k]['pricepercent'] = $allcodes_arr[$v['xl_symbol']][2];
            }
        }
        $this->success('请求成功',$list);
    }
    
    // 当前用户得自选
    public function getzx(){
        $page = $this->request->param('page')?$this->request->param('page'):1;
        $model = new Zixuannew();
        // $list = $model->where(['user_id'=>$this->auth->id,'type'=>6])->select();
        // $list_all = $model->where(['user_id'=>$this->auth->id,'type'=>6])->field('GROUP_CONCAT(xl_symbol) as allcode_s')->find();
        
        
        $list = $model->where(['user_id'=>$this->auth->id,'type'=>6])->page($page,20)->select();
        $list_all = $model->where(['user_id'=>$this->auth->id,'type'=>6])->field('GROUP_CONCAT(xl_symbol) as allcode_s')->find();
        if($list_all){
            $obj = new Http();
            $allcodes_arr = $obj->get_quanqiu_data_newnew($list_all['allcode_s']);
            // $allcodes_arr = $obj->get_quanqiu_data_new($list_all['symbol_s']);
        }
        if($list){
            foreach ($list as $k => $v){
                $list[$k]['last'] = $allcodes_arr[$v['xl_symbol']][0];
                $list[$k]['pricechange'] = $allcodes_arr[$v['xl_symbol']][1];
                $list[$k]['pricepercent'] = $allcodes_arr[$v['xl_symbol']][2];
                $list[$k]['title'] = $allcodes_arr[$v['xl_symbol']][3];
            }
        }
        $this->success('请求成功',$list);
    }
    
    // 跟单
    public function gendan(){
        $page = $this->request->param('page')?$this->request->param('page'):1;
        $model = new Addstrategy();
        // 多表 链接查询
        $list = $model->alias('a')->join('user u','a.user_id = u.id')->where(['buytype'=>5])->field('a.user_id,a.code,u.username,u.avatar')->page($page,20)->select();
        $this->success('请求成功',$list);
    }
    
    // 添加
    public function addStrategy_qh(){
        // 上午和下午交易时间
        if(!betweentime(config('site.swqhjy_shijian')) && !betweentime(config('site.xwqhjy_shijian'))){
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
        $model_qh = new Quanqiu();
        $data['user_id'] = $this->auth->id;
        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        // 判断 是否开启期货交易
        if(!$userInfo['is_qh']){
            $this->error("当前账户异常,禁止期货交易");
        }
        //控制下单金额
        $kz_xdmoney = config('site.qhjy_jine');
        if(floatval($kz_xdmoney)>floatval($data['creditMoney'])){
            $this->error("下单金额不能少于".$kz_xdmoney);
        }
        $sInfo = $model_qh->where('symbol',$data['allcode'])->find();
        $data['strategy_id'] = $sInfo['id'];
        $data['allcode'] = $sInfo['allcode'];
        // $data['creditMoney'] = round(bcdiv($data['cityValue'],$data['multiplying'],2));
        if($sInfo['is_jy']=='0'){
            $this->error('当前期货已禁止买入');
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
            $data['buytype'] = 5;
            $data['pingday'] = "0";
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
    
    // 卖出期货
    public function closeOut_qh(){
        if(!betweentime(config('site.swqhjy_shijian')) && !betweentime(config('site.xwqhjy_shijian'))){
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
        // $todayStime = strtotime(date('Y-m-d',time()).' 00:00:00');
        // $todayEtime = strtotime(date('Y-m-d',time()).' 23:59:59');
        // $pingday = $info['pingday'];
        // $date_current_addone = date('Y-m-d',strtotime("+{$pingday}day",$info['createtime']));
        // $this->error($date_current_addone);
        //卖出的当前时间 就是大于第二天开盘 就OK
        // $time = explode('-',config('site.swpzjy_shijian'))[0];
        // $this->error($time);
        // $todayStime_1 = strtotime($date_current_addone.' '.$time);//当前卖出股的第二天9点才能卖
        // if(!$sInfo['currentstatus']){
        //     //未开启当天平仓 就要计算下平仓时间
        //     if(time()<$todayStime_1){
        //         $this->error("T+{$pingday}平仓");
        //     }
        // }else{
        //     //如果开启当天平仓 就不受时间限制
        // }
        // file_put_contents('../public/logs/hhhh.txt',var_export($id,true)."\n\n",FILE_APPEND);
        //判断
        $obj = new Http();
        // $nowGu1 = $obj->get_quanqiu_data($info['xl_symbol']);
        $nowGu1 = $obj->get_quanqiu_data_new($info['symbol']);
        // file_put_contents('../public/logs/hhhh.txt',var_export($nowGu1,true)."\n\n",FILE_APPEND);
        // $ff = bcmul($info['number'],$info['multiplying'],2);
        $ff = bcmul($info['number'],1,2);
        $num3 = bcmul(($nowGu1[$info['xl_symbol']][0]-$info['buyprice']),$ff,2);
        //收益 也就是
        // $profitLose = $num3>=$info['creditMoney']?$info['creditMoney']:$num3;
        $profitLose = $num3;
        $info->waystatus = 1;
        $info->status = 3;
        $info->profitLose = $profitLose;
        $info->outtime = time();
        $info->sellprice = $nowGu1[$info['xl_symbol']][0];//$sInfo['cai_buy'];
        //卖出手续费
        // $info->yhfee = round($info['creditMoney'] * $info['multiplying'] * config('site.yh_fee'),2);
        $info->yhfee = 0;
        $info->sxfee = round($info['creditMoney'] * $info['multiplying'] * config('site.qfjy_fee'),2);
        
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
                // 也需要将金钱加回去
                $userInfo->balance = $money<=0?0:$money;
            }else{
                $userInfo->balance = $money<=0?0:$money;
            }
            $userInfo->save();
            $addMoney = bcadd($info['creditMoney'],$profitLose,2);
            Tool::addLog($this->auth->id,"平仓收益",$beforeMoney,Tool::getUserBalance($this->auth->id),$addMoney,0,$id);
        }
        Tool::addLog($this->auth->id,"费印花税",0,0,$info['yhfee'],1,$id);
        Tool::addLog($this->auth->id,"平仓手续",0,0,$info['sxfee'],1,$id);
        $this->success('平仓成功');
    }
    // 期货记录
    public function getNowWarehouse_qh(){
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
            // $arr1 =array_unique(array_column($list,'xl_symbol'));
            // $allcodes = implode(',',$arr1);
            $arr1 =array_unique(array_column($list,'symbol'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_quanqiu_data_new($allcodes);
            foreach($list as $k=>$v){
                $list[$k]['title'] = $v['title'];
                $list[$k]['allcode'] = $v['allcode'];
                $list[$k]['cai_buy'] = $allcodes_arr[$v['xl_symbol']][0];
                $list[$k]['createtime_name'] = date('Y-m-d H:i:s',$v['createtime']);
                $list[$k]['outtime_name'] = date('Y-m-d H:i:s',$v['outtime']);
                $list[$k]['creditMoney'] = round($v['creditMoney'],2);
                $list[$k]['number'] = round($v['number']); 
                $list[$k]['multiplying'] = $v['multiplying'];
                $list[$k]['cvalue'] = round(bcmul($v['creditMoney'],$v['multiplying'],20),2);
                // $list[$k]['profitLose'] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number']*$v['multiplying'],2);
                $list[$k]['profitLose'] = bcmul(($allcodes_arr[$v['xl_symbol']][0]-$v['buyprice']),$v['number'],2);
                $list[$k]['profitLose_rate'] = number_format(((bcdiv(($allcodes_arr[$v['xl_symbol']][0]-$v['buyprice']),$v['buyprice'],4))*100),2)."%";
                
            }
            $total_city_value = round(array_sum(array_column($list,'cvalue')),2);
            $total_position_money = round(array_sum(array_column($list,'profitLose')),2);
        }
        $this->success('请求成功', ['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
    }
    
    // 期货卖出历史
    public function getNowWarehouse_qh_lishi(){
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
    
    
    
    
}