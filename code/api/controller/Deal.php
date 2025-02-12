<?php

namespace app\api\controller;

use app\admin\model\Admin;
use app\admin\model\Adv;
use app\admin\model\Agreement;
use app\admin\model\Attention;
use app\admin\model\Banner;
use app\admin\model\Cai;
use app\admin\model\feedback\Problem;
use app\admin\model\Ggnotice;
use app\admin\model\Ggyb;
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
use fast\Rsa;
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

class Deal extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];

    public function mrLst(){
        // 是没有隐藏了
        $keyword = $this->request->get('keyword');
        $list = model('app\admin\model\strategy\Strategy')
            ->field('id,allcode,title,code')
            ->where('allcode','like','%'.$keyword.'%')
            ->where('status','0')
            ->select();
        $this->success('请求成功',$list);
    }
    public function mrLstjj(){
        $keyword = $this->request->get('keyword');
        $list = model('app\admin\model\Etf')
            ->field('id,allcode,title,code')
            ->where('allcode','like','%'.$keyword.'%')
            ->select();
        $this->success('请求成功',$list);
    }
    public function mrSellLst(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $keyword = Rsa::check($paramInfo['keyword']);
        //$keyword = $this->request->get('keyword');
        $model = new Addstrategy();
        $list = $model
            ->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>1,'buytype'=>1])
            ->where('allcode','like','%'.$keyword.'%')
            ->order('createtime desc')
            ->select();
        //加密
        $data = Rsa::jia($list);
        $this->success('请求成功',$data);
    }

    public function gudetail(){
        $query = $this->request->param('q');
        if(!$query){
            $this->error('请求成功', ['info' => []]);
        }
        $info = Http::get_stock_now_info($query);
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

        $this->success('请求成功', ['is_type'=>1,'is_show_time'=>1,'type_time'=>'15:30','is_zx'=>$is_zx,'info' => $info,'ggInfo'=>$ggInfo,'xwInfo' => $xwInfo,'name1'=>$nameInfo['name1'],'name2'=>$nameInfo['name2']]);
    }




    //普通交易
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
        if(!betweentime(config('site.swjiaoyi')) && !betweentime(config('site.xwjiaoyi'))){
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

        $query = $this->request->param('query');
        if($query){
            $paramInfo = Rsa::jie($query);
            $data = $paramInfo;
        }else{
            $data = $this->request->post();
            // $this->error($data['code']);
        }
        unset($data['jiami']);
        //pp($data);exit;
        //$data = $this->request->post();
        $data['is_auto_money'] = "1";
        $data['is_buy'] = "1";
        $data['is_sell'] = "0";
        $data['losePrice'] = "";
        $data['profitPrice'] = "";
        $model = new Strategy();
        $data['user_id'] = $this->auth->id;
        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        //控制下单金额
        $kz_xdmoney = config('site.guzuidijiaoyijinge');
        if(floatval($kz_xdmoney)>floatval($data['cityValue'])){
            $this->error("下单金额不能少于".$kz_xdmoney);
        }
        $sInfo = $model->where('allcode',$data['allcode'])->find();
        $data['strategy_id'] = $sInfo['id'];
        $data['creditMoney'] = $data['cityValue'];
        if($sInfo['status']=='1'){
            $this->error('已禁止买入');
        }
        if(!$data['number']){
            $this->error('买入参数错误，请重新买入');
        }
        
        //判断整数
        if(!Tool::is__int($data['canBuy'])){
            $this->error('请输入整数的手数');
        }
        //$data['money'] = bcadd($data['cityValue'],floatval($data['cityValue']) * floatval(config('site.mai_fee')),2);
        $data['money'] = bcadd($data['cityValue'],$data['allMoney'],2);
        // file_put_contents('../public/logs//xxxx.txt',var_export($data['allMoney'],true)."\n\n",FILE_APPEND);
        // file_put_contents('../public/logs//xxxx.txt',var_export($data['cityValue'],true)."\n\n",FILE_APPEND);
        
        // file_put_contents('../public/logs//xxxx.txt',var_export($data['money'],true)."\n\n",FILE_APPEND);
        //判断节假日 不允许买入和卖出
        // 冻结资金 其实就是一个显示的问题 正在起作用的还是balance
        if(floatval($data['money'])>floatval($userInfo['balance'])){
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
        }else if($nummmm==5){
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
        // var_dump($sInfo['qcstatus']);
        // var_dump($userInfo['is_qc']);
        // var_dump($zf);
        // var_dump($nummmm1);
        // var_dump($nummmm2);
        // exit();
        //p判断是否开启抢筹了
        // 判断是否开启委托
        if(!config('site.is_weituo')){
            if(!$sInfo['qcstatus'] && !$userInfo['is_qc']){
                if($zf>$nummmm1 || $zf<$nummmm2){
                    // $this->error('已超过涨跌幅，不允许下单');
                    $this->error('超过涨跌幅限制');
                    $data['is_zd'] = 1;
                }
            } 
        }
        // 情况1 优先使用冻结金额
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
                // 两个池子减少了多少
                $ss1 = $data['money'];
                $ss2 = 0;
                // file_put_contents('../public/logs//xxxx.txt',var_export("balance:".$balance,true)."\n\n",FILE_APPEND);
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
        // 相当于 下单的时候 记录下用的钱
        
        // 先清空冻结 然后
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
            $data['buytype'] = 1;
            $data['type'] = $sInfo['type'];
            $data['balance'] = $ss1;
            $data['freeze_profit'] = $ss2;
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
                // 分两个是委托下单还是
                if(config('site.is_weituo')=="1"){
                    Tool::addLog($this->auth->id,"委托下单",$beforeMoney,Tool::getUserBalance($this->auth->id),$data['money'],1,$orderId);
                }else{
                    Tool::addLog($this->auth->id,"普通下单",$beforeMoney,Tool::getUserBalance($this->auth->id),$data['money'],1,$orderId);
                }
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


    //买入页面底部的数据 只查询今天的
    public function getNowWarehouse1(){
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
            ->whereTime('createtime', 'today')
            ->order('createtime desc')
            ->page($page,20)
            ->select();
        if($status == 3){
            $list = $model
                ->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])
                ->whereTime('createtime', 'today')
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
                $list[$k]['profitLose_rate'] =number_format(((bcdiv(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['buyprice'],4))*100),2)."%";

            }
            $total_city_value = round(array_sum(array_column($list,'citycc')),2);
            $total_position_money = round(array_sum(array_column($list,'profitLose')),2);
        }
        $this->success('请求成功', ['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
    }

    //持仓列表
    public function getNowWarehouse(){
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
        //加密
        $data = Rsa::jia(['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
        $this->success('请求成功', $data);
    }

    //卖出页面底部数据 只查询今天的
    public function getNowWarehouse_lishi1(){
        $status = $this->request->get('status');
        $buytype = $this->request->get('buytype');
        $page = $this->request->get('page')?$this->request->get('page'):1;
        if($status == 2){
            $status = 3;
        }

        $model = new Addstrategy();
        $list = $model
            ->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])
            ->whereTime('updatetime', 'today')
            ->order('createtime desc')
            ->page($page,20)
            ->select();
        if($status == 3){
            $list = $model
                ->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])
                ->whereTime('updatetime', 'today')
                ->order('outtime desc')
                ->page($page,20)
                ->select();
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
                $list[$k]['profitLose_rate'] = number_format(round(bcdiv($v['profitLose'],$v['creditMoney'],20),3)*100,2);

            }
        }
        $this->success('请求成功', ['list'=>$list]);
    }

    //查询tab
    public function getNowWarehouse_lishi()
    {
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $status = Rsa::check($paramInfo['status']);
        $buytype = Rsa::check($paramInfo['buytype']);
        $type = Rsa::check($paramInfo['type']);
        $page = Rsa::check($paramInfo['page']);

        //$status = $this->request->get('status');
        //$buytype = $this->request->get('buytype');
        //$type = $this->request->get('type');
        //$page = $this->request->get('page') ? $this->request->get('page') : 1;
        // $s_time = $this->request->get('s_time');
        // $e_time = $this->request->get('e_time');
        $s_time = Rsa::check($paramInfo['s_time']);
        $e_time = Rsa::check($paramInfo['e_time']);
        if ($status == 2) {
            $status = 3;
        }
        $model = new Addstrategy();
        if (!$type || $type == '1') {
            $list = $model
                ->where(['deletetime' => Null, 'user_id' => $this->auth->id, 'status' => $status, 'buytype' => $buytype])
                ->whereTime('updatetime', 'today')
                ->order('outtime desc')
                ->page($page, 20)
                ->select();
        } else {
            $s_time_s = strtotime($s_time." 00:00:00");
            // var_dump($s_time_s);
            $e_time_s = strtotime($e_time." 23:59:59");
            // var_dump($e_time_s);
            $list = $model
                ->where(['deletetime' => Null, 'user_id' => $this->auth->id, 'status' => $status, 'buytype' => $buytype])
                ->where('updatetime', 'between time', [$s_time_s, $e_time_s])
                // ->where('updatetime','>=',$s_time_s)
                // ->where('updatetime','<=',$e_time_s)
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
        //加密
        $data = Rsa::jia(['list' => $list]);
        $this->success('请求成功', $data);
    }

    //历史交易详情
    public function getHistoryDetail(){
        $id = $this->request->get('id');
        $info = Db::name('add_strategy')->where(['id'=>$id])->find();
        $profitLose = $info['profitLose'];//bcmul($info['cityValue'],$nowGu['increase'],2);
        $backMoney = bcadd($info['creditMoney'],$profitLose,2);
        $info['backMoney'] = $backMoney;
        $info['lx'] = "平仓";
        $this->success('返回成功',['list'=>$info]);
    }

    //持仓列表里面的卖出
    public function sellAll_fei(){
        if(!betweentime(config('site.swjiaoyi')) && !betweentime(config('site.xwjiaoyi'))){
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
    
    //持仓列表里面的卖出
    public function sellAll(){
        if(!betweentime(config('site.swjiaoyi')) && !betweentime(config('site.xwjiaoyi'))){
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
        $userModel999 = new \app\admin\model\User();
        $userInfo999 = $userModel999->where(['id'=>$this->auth->id])->find();
        if($userInfo999['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $id = Rsa::check($paramInfo['id']);
        $allcode = Rsa::check($paramInfo['allcode']);
        $waystatus = Rsa::check($paramInfo['waystatus']);
        // $id = $this->request->post('id');
        // $allcode = $this->request->post('allcode');
        // $waystatus = $this->request->post('waystatus');
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
            $freeze_profit = round(bcadd($userInfo['freeze_profit'],$money1,20),2);
            if(config('site.kq_dj')){
                $userInfo->freeze_profit = $freeze_profit;
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
    //绿色的卖出 卖出 要记录 锁仓 收益资金
    public function sell(){ 
        if(!betweentime(config('site.swjiaoyi')) && !betweentime(config('site.xwjiaoyi'))){
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
        
        $userModel999 = new \app\admin\model\User();
        $userInfo999 = $userModel999->where(['id'=>$this->auth->id])->find();
        if($userInfo999['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $id = Rsa::check($paramInfo['id']);
        $allcode = Rsa::check($paramInfo['allcode']);
        $canbuy = Rsa::check($paramInfo['canBuy']);
        $waystatus = Rsa::check($paramInfo['waystatus']);


        // 当前记录ID
        //$id = $this->request->post('id');
        // 股票代码全程
        //$allcode = $this->request->post('allcode');
        // 手数
        //$canbuy = $this->request->post('canBuy');
        // 固定1
        //$waystatus = $this->request->post('waystatus');
        $model = new Addstrategy();
        $sModel = new Strategy();
        $info = $model->where(['id'=>$id])->find();
        $sInfo = $sModel->where(['allcode'=>$allcode])->find();
        if(!$info){
            $this->error('股票不存在！');
        }
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
                $money = round(bcadd($userInfo['balance']+$info['creditMoney']-$info['yhfee']-$info['sxfee'],$profitLose,20),2);
                $money1 = round(bcadd($info['creditMoney']-$info['yhfee']-$info['sxfee'],$profitLose,20),2);
                $freeze_profit = round(bcadd($userInfo['freeze_profit'],$money1,20),2);
                if(config('site.kq_dj')){
                    $userInfo->freeze_profit = $freeze_profit;
                    $userInfo->balance =$money;
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
                $freeze_profit = round(bcadd($userInfo['freeze_profit'],$money1,20),2);
                if(config('site.kq_dj')){
                    $userInfo->freeze_profit = $freeze_profit;
                    $userInfo->balance = $money;
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

    //返回仓位信息
    public function getPositions(){
        /*$query = $this->request->param('q');
        if(!$query){
            $this->error('参数错误');
        }
        $model = new Addstrategy();
        $info = $model->where(['id'=>$id])->find();
        $this->success('请求成功', ['info' => $info]);*/
    }
    
    // 获取委托
    public function getNowWarehouse_weituo(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $status = Rsa::check($paramInfo['status']);
        $buytype = Rsa::check($paramInfo['buytype']);
        $page = Rsa::check($paramInfo['page']);

        //$status = $this->request->get('status');
        //$buytype = $this->request->get('buytype');
        //$page = $this->request->get('page')?$this->request->get('page'):1;
        $total_city_value = 0;
        $total_position_money = 0;
        $model = new Addstrategy();
        $list = $model
            ->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>["in",$buytype]])
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
                $list[$k]['cjlx'] = '挂单';
            }
            // $total_city_value = round(array_sum(array_column($list,'citycc')),2);
            // $total_position_money = round(array_sum(array_column($list,'profitLose')),2);
        }
        // $this->success('请求成功', ['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
        $data = Rsa::jia(['list'=>$list]);
        $this->success('请求成功',$data);
    }
    
    // 撤单操作
    public function cheAll(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $id = Rsa::check($paramInfo['id']);
        $allcode = Rsa::check(isset($paramInfo['allcode'])?$paramInfo['allcode']:'');
        $waystatus = Rsa::check(isset($paramInfo['waystatus'])?$paramInfo['waystatus']:'');

        // 记录一张委托撤单表
        //$id = $this->request->post('id');
        //$allcode = $this->request->post('allcode');
        //$waystatus = $this->request->post('waystatus');
        $model = new Addstrategy();
        $info = $model->where(['id'=>$id])->find();
        if($info['status'] == "1"){
            $this->error('该票已转持仓，等待刷新');
        }
        if($info['status'] == "4"){
            $this->error('该票已撤单，等待刷新');
        }
        $info->status = 4;
        $resutl = $info->save();
        if($resutl !== false){
            // 并且退还资金
            $userModel = new \app\admin\model\User();
            $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
            // 前金额
            $cmoney = $userInfo['balance'];
            $money = round($userInfo['balance'] + $info['allMoney'] + $info['creditMoney'],2);
            $userInfo->balance = $money;
            // 加回冻结资金用的钱
            $userInfo->freeze_profit = round($userInfo['freeze_profit'] + $info['freeze_profit'],2);
            $userInfo->save();
            // 撤单的时候出现出现一个问题 要还原资金 还原T+1资金
            Tool::addLog($this->auth->id,"委托撤单",$cmoney,Tool::getUserBalance($this->auth->id),round($info['allMoney'] + $info['creditMoney'],2),1,$id);
            $this->success('撤单成功');
        }else{
            $this->error('撤单失败');
        }
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
            $s_time_s = strtotime($s_time." 00:00:00");
            $e_time_s = strtotime($e_time." 23:00:00");
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
    
    
    
    // ---------------配资模块----------------------------------------------------------------
    //配资交易
    public function addStrategy_pz(){

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
        if(!betweentime(config('site.swpzjy_shijian')) && !betweentime(config('site.xwpzjy_shijian'))){
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

        $query = $this->request->param('query');
        if($query){
            $paramInfo = Rsa::jie($query);
            $data = $paramInfo;
        }else{
            $data = $this->request->post();
            // $this->error($data['code']);
        }
        unset($data['jiami']);
        //pp($data);exit;
        //$data = $this->request->post();
        $data['is_auto_money'] = "1";
        $data['is_buy'] = "1";
        $data['is_sell'] = "0";
        $data['losePrice'] = "";
        $data['profitPrice'] = "";
        $model = new Strategy();
        $data['user_id'] = $this->auth->id;
        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        //控制下单金额
        $kz_xdmoney = config('site.guzuidijiaoyijinge');
        if(floatval($kz_xdmoney)>floatval($data['cityValue'])){
            $this->error("下单金额不能少于".$kz_xdmoney);
        }
        $sInfo = $model->where('allcode',$data['allcode'])->find();
        $data['strategy_id'] = $sInfo['id'];
        $data['creditMoney'] = $data['cityValue'];
        if($sInfo['status']=='1'){
            $this->error('已禁止买入');
        }
        if(!$data['number']){
            $this->error('买入参数错误，请重新买入');
        }
        
        //判断整数
        if(!Tool::is__int($data['canBuy'])){
            $this->error('请输入整数的手数');
        }
        //$data['money'] = bcadd($data['cityValue'],floatval($data['cityValue']) * floatval(config('site.mai_fee')),2);
        $data['money'] = bcadd($data['cityValue'],$data['allMoney'],2);
        // file_put_contents('../public/logs//xxxx.txt',var_export($data['allMoney'],true)."\n\n",FILE_APPEND);
        // file_put_contents('../public/logs//xxxx.txt',var_export($data['cityValue'],true)."\n\n",FILE_APPEND);
        
        // file_put_contents('../public/logs//xxxx.txt',var_export($data['money'],true)."\n\n",FILE_APPEND);
        //判断节假日 不允许买入和卖出
        // 冻结资金 其实就是一个显示的问题 正在起作用的还是balance
        if(floatval($data['money'])>floatval($userInfo['balance'])){
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
        // var_dump($sInfo['qcstatus']);
        // var_dump($userInfo['is_qc']);
        // var_dump($zf);
        // var_dump($nummmm1);
        // var_dump($nummmm2);
        // exit();
        //p判断是否开启抢筹了
        // 判断是否开启委托
        if(!config('site.is_weituo')){
            if(!$sInfo['qcstatus'] && !$userInfo['is_qc']){
                if($zf>$nummmm1 || $zf<$nummmm2){
                    // $this->error('已超过涨跌幅，不允许下单');
                    $this->error('份额不足,本金优先');
                    $data['is_zd'] = 1;
                }
            } 
        }
        // 情况1 优先使用冻结金额
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
                // 两个池子减少了多少
                $ss1 = $data['money'];
                $ss2 = 0;
                // file_put_contents('../public/logs//xxxx.txt',var_export("balance:".$balance,true)."\n\n",FILE_APPEND);
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
        // 相当于 下单的时候 记录下用的钱
        
        // 先清空冻结 然后
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
            $data['type'] = $sInfo['type'];
            $data['balance'] = $ss1;
            $data['freeze_profit'] = $ss2;
            // 股数放大
            $data['number'] = intval($data['number']) * intval($data['multiplying']);
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
                // 分两个是委托下单还是
                if(config('site.is_weituo')=="1"){
                    Tool::addLog($this->auth->id,"委托下单-配资",$beforeMoney,Tool::getUserBalance($this->auth->id),$data['money'],1,$orderId);
                }else{
                    Tool::addLog($this->auth->id,"普通下单-配资",$beforeMoney,Tool::getUserBalance($this->auth->id),$data['money'],1,$orderId);
                }
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
    // 配资持仓
    public function getNowWarehouse_pz(){
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
                // 首页是根据倍数来的 加入是3倍 就是收益的3倍 
                $list[$k]['profitLose'] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number']*intval($v['multiplying']),2);
                // $list[$k]['profitLose_rate'] = number_format(((bcdiv(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['buyprice'],4))*100*intval($v['multiplying'])),2)."%";
                $list[$k]['profitLose_rate'] = number_format(((bcdiv(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['buyprice'],4))*100),2)*intval($v['multiplying'])."%";

            }
            $total_city_value = round(array_sum(array_column($list,'citycc')),2);
            $total_position_money = round(array_sum(array_column($list,'profitLose')),2);
        }
        //加密
        $data = Rsa::jia(['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
        $this->success('请求成功', $data);
    }
    public function sellAllStockPz(){
        if(!betweentime(config('site.swpzjy_shijian')) && !betweentime(config('site.xwpzjy_shijian'))){
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
        $userModel999 = new \app\admin\model\User();
        $userInfo999 = $userModel999->where(['id'=>$this->auth->id])->find();
        if($userInfo999['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $id = Rsa::check($paramInfo['id']);
        $allcode = Rsa::check($paramInfo['allcode']);
        $waystatus = Rsa::check($paramInfo['waystatus']);
        // $id = $this->request->post('id');
        // $allcode = $this->request->post('allcode');
        // $waystatus = $this->request->post('waystatus');
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
        $todayStime_1 = strtotime($date_current_addone.' '.'09:30:00');//当前卖出股的第二天9点才能卖
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
        // 收益算上倍数
        $num3 = bcmul(($nowGu1[3]-$info['buyprice']),$info['number']*intval($info['multiplying']),2);
        //收益 也就是
        $profitLose = $num3;
        $info->waystatus = 1;
        $info->status = 3;
        $info->profitLose = $profitLose;
        $info->outtime = time();
        $info->sellprice = $nowGu1[3];//$sInfo['cai_buy'];
        //卖出手续费
        $info->yhfee = round(intval($info['number']) * floatval($nowGu1[3]) * config('site.yh_fee'),2);
        $info->sxfee = round(intval($info['number']) * floatval($nowGu1[3]) * config('site.maic_fee'),2);

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
            $freeze_profit = round(bcadd($userInfo['freeze_profit'],$money1,20),2);
            if(config('site.kq_dj')){
                $userInfo->freeze_profit = $freeze_profit;
                $userInfo->balance = $money;
            }else{
                $userInfo->balance = $money<0?0:$money;
            }
            $userInfo->save();
            $addMoney = bcadd($info['creditMoney'],$profitLose,2);
            Tool::addLog($this->auth->id,"配资-平仓收益",$beforeMoney,Tool::getUserBalance($this->auth->id),$addMoney,0,$id);
        }
        Tool::addLog($this->auth->id,"配资-印花税",0,0,$info['yhfee'],1,$id);
        Tool::addLog($this->auth->id,"配资-平仓手续",0,0,$info['sxfee'],1,$id);
        $this->success('平仓成功');
    }
    
    // --------------融券模块--------------------------------------------------------------------
    public function addStrategy_rq(){

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
        if(!betweentime(config('site.swjiaoyi_rq')) && !betweentime(config('site.xwjiaoyi_rq'))){
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

        $query = $this->request->param('query');
        if($query){
            $paramInfo = Rsa::jie($query);
            $data = $paramInfo;
        }else{
            $data = $this->request->post();
            // $this->error($data['code']);
        }
        unset($data['jiami']);
        //pp($data);exit;
        //$data = $this->request->post();
        $data['is_auto_money'] = "1";
        $data['is_buy'] = "1";
        $data['is_sell'] = "0";
        $data['losePrice'] = "";
        $data['profitPrice'] = "";
        $model = new Strategy();
        $data['user_id'] = $this->auth->id;
        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        //控制下单金额
        $kz_xdmoney = config('site.guzuidijiaoyijinge_rq');
        if(floatval($kz_xdmoney)>floatval($data['cityValue'])){
            $this->error("下单金额不能少于".$kz_xdmoney);
        }
        $sInfo = $model->where('allcode',$data['allcode'])->find();
        $data['strategy_id'] = $sInfo['id'];
        $data['creditMoney'] = $data['cityValue'];
        if($sInfo['status']=='1'){
            $this->error('已禁止买入');
        }
        if(!$data['number']){
            $this->error('买入参数错误，请重新买入');
        }
        
        //判断整数
        if(!Tool::is__int($data['canBuy'])){
            $this->error('请输入整数的手数');
        }
        //$data['money'] = bcadd($data['cityValue'],floatval($data['cityValue']) * floatval(config('site.mai_fee')),2);
        $data['money'] = bcadd($data['cityValue'],$data['allMoney'],2);
        // file_put_contents('../public/logs//xxxx.txt',var_export($data['allMoney'],true)."\n\n",FILE_APPEND);
        // file_put_contents('../public/logs//xxxx.txt',var_export($data['cityValue'],true)."\n\n",FILE_APPEND);
        
        // file_put_contents('../public/logs//xxxx.txt',var_export($data['money'],true)."\n\n",FILE_APPEND);
        //判断节假日 不允许买入和卖出
        // 冻结资金 其实就是一个显示的问题 正在起作用的还是balance
        if(floatval($data['money'])>floatval($userInfo['balance'])){
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
        // var_dump($sInfo['qcstatus']);
        // var_dump($userInfo['is_qc']);
        // var_dump($zf);
        // var_dump($nummmm1);
        // var_dump($nummmm2);
        // exit();
        //p判断是否开启抢筹了
        // 判断是否开启委托
        if(!config('site.is_weituo')){
            if(!$sInfo['qcstatus'] && !$userInfo['is_qc']){
                if($zf>$nummmm1 || $zf<$nummmm2){
                    // $this->error('已超过涨跌幅，不允许下单');
                    $this->error('份额不足,本金优先');
                    $data['is_zd'] = 1;
                }
            } 
        }
        // 情况1 优先使用冻结金额
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
                // 两个池子减少了多少
                $ss1 = $data['money'];
                $ss2 = 0;
                // file_put_contents('../public/logs//xxxx.txt',var_export("balance:".$balance,true)."\n\n",FILE_APPEND);
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
        // 相当于 下单的时候 记录下用的钱
        
        // 先清空冻结 然后
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
            // 买入类型  8=融券
            $data['buytype'] = 8;
            $data['type'] = $sInfo['type'];
            $data['balance'] = $ss1;
            $data['freeze_profit'] = $ss2;
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
                // 分两个是委托下单还是
                if(config('site.is_weituo')=="1"){
                    Tool::addLog($this->auth->id,"委托下单",$beforeMoney,Tool::getUserBalance($this->auth->id),$data['money'],1,$orderId);
                }else{
                    Tool::addLog($this->auth->id,"融券下单",$beforeMoney,Tool::getUserBalance($this->auth->id),$data['money'],1,$orderId);
                }
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
    
    public function getNowWarehouse_rq(){
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
                // 首页是根据倍数来的 加入是3倍 就是收益的3倍 
                // $list[$k]['profitLose'] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number']*intval($v['multiplying']),2);
                $list[$k]['profitLose'] = bcmul(($v['buyprice']-$allcodes_arr[$v['allcode']]),$v['number']*intval($v['multiplying']),2);
                // $list[$k]['profitLose_rate'] = number_format(((bcdiv(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['buyprice'],4))*100*intval($v['multiplying'])),2)."%";
                $list[$k]['profitLose_rate'] = number_format(((bcdiv(($v['buyprice']-$allcodes_arr[$v['allcode']]),$v['buyprice'],4))*100),2)*intval($v['multiplying'])."%";

            }
            $total_city_value = round(array_sum(array_column($list,'citycc')),2);
            $total_position_money = round(array_sum(array_column($list,'profitLose')),2);
        }
        //加密
        $data = Rsa::jia(['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
        $this->success('请求成功', $data);
    }
    
    public function sellAllStockRq(){
        if(!betweentime(config('site.swjiaoyi_rq')) && !betweentime(config('site.xwjiaoyi_rq'))){
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
        $userModel999 = new \app\admin\model\User();
        $userInfo999 = $userModel999->where(['id'=>$this->auth->id])->find();
        if($userInfo999['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $id = Rsa::check($paramInfo['id']);
        $allcode = Rsa::check($paramInfo['allcode']);
        $waystatus = Rsa::check($paramInfo['waystatus']);
        // $id = $this->request->post('id');
        // $allcode = $this->request->post('allcode');
        // $waystatus = $this->request->post('waystatus');
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
        $time = explode('-',config('site.swjiaoyi_rq'))[0];
        // $this->error($time);
        $todayStime_1 = strtotime($date_current_addone.' '.'09:30:00');//当前卖出股的第二天9点才能卖
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
        // 收益算上倍数
        // $num3 = bcmul(($nowGu1[3]-$info['buyprice']),$info['number']*intval($info['multiplying']),2);
        $num3 = bcmul(($info['buyprice']-$nowGu1[3]),$info['number']*intval($info['multiplying']),2);
        //收益 也就是
        $profitLose = $num3;
        $info->waystatus = 1;
        $info->status = 3;
        $info->profitLose = $profitLose;
        $info->outtime = time();
        $info->sellprice = $nowGu1[3];//$sInfo['cai_buy'];
        //卖出手续费
        $info->yhfee = round(intval($info['number']) * floatval($nowGu1[3]) * config('site.yh_fee'),2);
        $info->sxfee = round(intval($info['number']) * floatval($nowGu1[3]) * config('site.maic_fee'),2);

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
            $freeze_profit = round(bcadd($userInfo['freeze_profit'],$money1,20),2);
            if(config('site.kq_dj')){
                $userInfo->freeze_profit = $freeze_profit;
                $userInfo->balance = $money;
            }else{
                $userInfo->balance = $money<0?0:$money;
            }
            $userInfo->save();
            $addMoney = bcadd($info['creditMoney'],$profitLose,2);
            Tool::addLog($this->auth->id,"融券-平仓收益",$beforeMoney,Tool::getUserBalance($this->auth->id),$addMoney,0,$id);
        }
        Tool::addLog($this->auth->id,"融券-印花税",0,0,$info['yhfee'],1,$id);
        Tool::addLog($this->auth->id,"融券-平仓手续",0,0,$info['sxfee'],1,$id);
        $this->success('平仓成功');
    }
    
    
    public function mrSellLstRq(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $keyword = Rsa::check($paramInfo['keyword']);
        //$keyword = $this->request->get('keyword');
        $model = new Addstrategy();
        $list = $model
            ->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>1,'buytype'=>8])
            ->where('allcode','like','%'.$keyword.'%')
            ->order('createtime desc')
            ->select();
        $is_pz = $this->auth->is_pz;
        //加密
        $data = Rsa::jia(['list'=>$list,'is_pz'=>$is_pz]);
        $this->success('请求成功',$data);
    }
    
    
    public function sellRq(){ 
        if(!betweentime(config('site.swjiaoyi_rq')) && !betweentime(config('site.xwjiaoyi_rq'))){
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
        
        $userModel999 = new \app\admin\model\User();
        $userInfo999 = $userModel999->where(['id'=>$this->auth->id])->find();
        if($userInfo999['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $id = Rsa::check($paramInfo['id']);
        $allcode = Rsa::check($paramInfo['allcode']);
        $canbuy = Rsa::check($paramInfo['canBuy']);
        $waystatus = Rsa::check($paramInfo['waystatus']);


        // 当前记录ID
        //$id = $this->request->post('id');
        // 股票代码全程
        //$allcode = $this->request->post('allcode');
        // 手数
        //$canbuy = $this->request->post('canBuy');
        // 固定1
        //$waystatus = $this->request->post('waystatus');
        $model = new Addstrategy();
        $sModel = new Strategy();
        $info = $model->where(['id'=>$id])->find();
        $sInfo = $sModel->where(['allcode'=>$allcode])->find();
        if(!$info){
            $this->error('股票不存在！');
        }
        if($info['status'] == "3"){
            $this->error('该票已卖出，等待刷新');
        }
        $todayStime = strtotime(date('Y-m-d',time()).' 00:00:00');
        $todayEtime = strtotime(date('Y-m-d',time()).' 23:59:59');
        $pingday = $info['pingday'];
        $date_current_addone = date('Y-m-d',strtotime("+{$pingday}day",$info['createtime']));
        // $this->error($date_current_addone);
        //卖出的当前时间 就是大于第二天开盘 就OK
        // $time = explode('-',config('site.swjiaoyi'))[0];
        // $this->error($time);
        $time = "09:30:00";
        $todayStime_1 = strtotime($date_current_addone.' '.$time);//当前卖出股的第二天9点才能卖
        if(!$sInfo['currentstatus']){
            //未开启当天平仓 就要计算下平仓时间
            if(time()<$todayStime_1){
                // $this->error("T+{$pingday}平仓");
                $this->error("非盘中时间,不能操作买卖");
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
            // $num3 = bcmul(($nowGu1[3]-$info['buyprice']),$info['number'],2);
            $num3 = bcmul(($info['buyprice']-$nowGu1[3]),$info['number']*intval($info['multiplying']),2);
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
                $freeze_profit = round(bcadd($userInfo['freeze_profit'],$money1,20),2);
                if(config('site.kq_dj')){
                    $userInfo->freeze_profit = $freeze_profit;
                    $userInfo->balance =$money;
                }else{
                    $userInfo->balance = $money<0?0:$money;
                }
                $userInfo->save();
                $addMoney = bcadd($info['creditMoney'],$profitLose,2);
                Tool::addLog($this->auth->id,"融券-平仓收益",$beforeMoney,Tool::getUserBalance($this->auth->id),$addMoney,0,$id);
            }
            Tool::addLog($this->auth->id,"融券-印花税",0,0,$info['yhfee'],1,$id);
            Tool::addLog($this->auth->id,"融券-平仓手续",0,0,$info['sxfee'],1,$id);
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
            // $num3 = bcmul(($nowGu1[3]-$new_info['buyprice']),$new_info['number'],2);
            $num3 = bcmul(($new_info['buyprice']-$nowGu1[3]),$new_info['number']*intval($new_info['multiplying']),2);
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
                $freeze_profit = round(bcadd($userInfo['freeze_profit'],$money1,20),2);
                if(config('site.kq_dj')){
                    $userInfo->freeze_profit = $freeze_profit;
                    $userInfo->balance = $money;
                }else{
                    $userInfo->balance = $money<0?0:$money;
                }
                $userInfo->save();
                $addMoney = bcadd($new_info['creditMoney'],$profitLose,2);
                Tool::addLog($this->auth->id,"融券-平仓收益",$beforeMoney,Tool::getUserBalance($this->auth->id),$addMoney,0,$id);
            }
            Tool::addLog($this->auth->id,"融券-费印花税",0,0,$new_info['yhfee'],1,$new_info_act->id);
            Tool::addLog($this->auth->id,"融券-平仓手续",0,0,$new_info['sxfee'],1,$new_info_act->id);




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
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}