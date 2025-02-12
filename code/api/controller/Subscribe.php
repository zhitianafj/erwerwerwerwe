<?php

namespace app\api\controller;

use app\admin\model\Admin;
use app\admin\model\Adv;
use app\admin\model\Agreement;
use app\admin\model\Attention;
use app\admin\model\Banner;
use app\admin\model\Cai;
use app\admin\model\Companyjk;
use app\admin\model\feedback\Problem;
use app\admin\model\Nav;
use app\admin\model\shengou\Sgjiaoyi;
use app\admin\model\shengou\Sgjiaoyi0;
use app\admin\model\ShengouCai;
use app\admin\model\strategy\Addstrategy;
use app\admin\model\strategy\Classify;
use app\admin\model\Topic;
use app\admin\model\User as Useruser;
use app\admin\model\UserAnswer;
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

class Subscribe extends Api
{
    protected $noNeedLogin = ['lst','detail'];
    protected $noNeedRight = ['*'];

    //列表
    public function lst(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $type = Rsa::check($paramInfo['type']);
        $page = Rsa::check($paramInfo['page']);

        $model = new Shengou();
        //$type = $this->request->param('type');
        //$page = $this->request->param('page')?$this->request->param('page'):1;
        $where = [];
        $now_time = strtotime(date('Y-m-d',time()));
        
        // $where['sg_date_int'] = ['>=',$now_time];
        if($type==1){ //即将上市
            $where['sg_date_int'] = ['<',$now_time];
            $where['ss_date_int'] = ['>',$now_time];
        }else if($type==2){ //上市表现

        }else{
            $where['sgswitch'] = 1;
        }
        $list = $model
            ->where($where)
            ->field('sg_date')
            ->distinct(true)
            ->page($page,20)
            ->order('sg_date asc')
            ->select();
            // $list['maxxg'] = config('site.maxxg');
        if($list){
            foreach($list as $k=>$v){
                // 判断是不是当前时间 如果是当前时间 则做一个标识
                $list[$k]['flag'] = 1;
                // if($v['sg_date'] == date('Y-m-d',time())){
                //     $list[$k]['flag'] = 1;
                // }else{
                //     $list[$k]['flag'] = 0;
                // }
                // 增加一个字段 最大
                $list[$k]['sg_date_xq'] = $model->where('sg_date',$v['sg_date'])->find()['sg_date_xq'];
                if($type==1){
                    $list[$k]['sub_info'] = $model->where($where)->where(['sg_date'=>$v['sg_date']])->distinct(true)->field('id,code,name,fx_price,fx_rate,sg_limit,sg_date,zq_rate,ss_date,sg_type,fx_num')->select();
                }else{
                    $list[$k]['sub_info'] = $model->where(['sg_date'=>$v['sg_date'],'sgswitch'=>1])->field('id,code,name,fx_price,fx_rate,sg_limit,sg_date,zq_rate,ss_date,sg_type,fx_num')->select();
                }
            }
        }
        $data = Rsa::jia(['list'=>$list,'maxxg'=>config('site.maxxg')]);
        $this->success('请求成功',$data);
    }

    //详情
    public function lstDetail(){
        $model = new Shengou();
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        // $id = $this->request->param('id');
        $id = Rsa::check($paramInfo['id']);
        $info = $model
            ->where('id',$id)
            ->find();
        if($info){
            if($info['sg_type'] == "1"){
                $info['sg_type_text'] = "沪市";
            }else if($info['sg_type'] == "2"){
                $info['sg_type_text'] = "深市";
            }else if($info['sg_type'] == "3"){
                $info['sg_type_text'] = "创业板";
            }else if($info['sg_type'] == "5"){
                $info['sg_type_text'] = "科创板";
            }else if($info['sg_type'] == "4"){
                $info['sg_type_text'] = "北交所";
            }
        }
        //加密
        $data = Rsa::jia(['info'=>$info,'maxxg'=>config('site.maxxg'),'psmax'=>config('site.psmax'),'psjy0_ss'=>config('site.psjy0_ss'),'kqssss'=>config('site.kqssss')]);
        $this->success('请求成功',$data);
    }

    public function xxlst(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $type = Rsa::check($paramInfo['type']);
        $page = Rsa::check($paramInfo['page']);
        $model = new Shengou();
        // $type = $this->request->param('type');
        // $page = $this->request->param('page')?$this->request->param('page'):1;
        $where = [];
        $now_time = strtotime(date('Y-m-d',time()));
        // $where['sg_date_int'] = ['>=',$now_time];
        if($type==1){ //即将上市
            $where['sg_date_int'] = ['<',$now_time];
            $where['ss_date_int'] = ['>',$now_time];
        }else if($type==2){ //上市表现

        }else{
            $where['xxswitch'] = 1;
        }
        $list = $model
            ->where($where)
            ->field('sg_date')
            ->distinct(true)
            ->page($page,20)
            ->order('sg_date asc')
            ->select();
        if($list){
            foreach($list as $k=>$v){
                // 配售不需要管 星期
                $list[$k]['flag'] = 1;
                // if($v['sg_date'] == date('Y-m-d',time())){
                //     $list[$k]['flag'] = 1;
                // }else{
                //     $list[$k]['flag'] = 0;
                // }
                $list[$k]['sg_date_xq'] = $model->where('sg_date',$v['sg_date'])->find()['sg_date_xq'];
                $sgInfo = $model
                    ->where(['sg_date'=>$v['sg_date']])
                    ->where($where)
                    ->field('id,code,name,fx_price,fx_rate,sg_limit,sg_date,zq_rate,ss_date,sg_nums,xx_nums,sg_type,fx_num')
                    ->select();

                // foreach($sgInfo as $k1=>$v1){
                //     $max_num = 0;
                //     if($v1['fx_price']!='0.00') {
                //         $max_num = intval($this->auth->balance / $v1['fx_price'] / 100);
                //     }
                //     // $num = 0;
                //     $num = $max_num;
                //     if(!config('site.gqpeizhi')) {
                //         //股（手）的情况 1手就是100股
                //         $dj_money = bcmul($v1['fx_price'], ($num * 100));
                //     }else {
                //         //签的情况
                //         $lushi = Tool::get_codetype($v1['code']);
                //         if ($lushi == 1000) {//是沪市
                //             $dj_money = bcmul($v1['fx_price'], ($num * 1000));
                //         } else {
                //             $dj_money = bcmul($v1['fx_price'], ($num * 500));
                //         }
                //     }
                //     $sgInfo[$k1]['max_num'] = $max_num;
                //     $sgInfo[$k1]['num'] = $num;
                //     // $sgInfo[$k1]['num'] = $max_num;
                //     $sgInfo[$k1]['dj_money'] = $dj_money;
                //     // 计算出百分比
                //     $count = Db::name('sgjiaoyi')->where(['code'=>$v1['code']])->sum('sg_nums');
                //     $sgInfo[$k1]['p'] = round(bcdiv($count,bcadd($count,$v1['xx_nums'],20),20)*100,2);
                // }

                $list[$k]['sub_info'] = $sgInfo;
            }
        }
        $data = Rsa::jia(['list'=>$list]);
        $this->success('请求成功',$data);
    }

    //我要申购
    public function add(){
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
        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        if($userInfo['status'] == "forbidden"){
            $this->error("当前账户异常,禁止交易");
        }
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        //申购时间设置
        $md_date = date('m-d',time());
        $holidayop = model('app\admin\model\Holidayop')->where(['time_str'=>$md_date,'status'=>'0'])->find();
        // if(isset($holidayop['status']) && !$holidayop['status']){
        //     $this->error('节假日不允许交易');
        // }
        // 如果是关闭 则不能交易
        if($holidayop){
            $this->error('节假日不允许交易');
        }
        if(!betweentime(config('site.swshengou')) && !betweentime(config('site.xwshengou'))){
            $this->error('下单失败,不在交易时间段');
        }

        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $code = Rsa::check($paramInfo['code']);
        $sg_nums = Rsa::check($paramInfo['sg_nums']);
        $total_nums = Rsa::check($paramInfo['total_nums']);
        $money = Rsa::check($paramInfo['money']);
        //$code = $this->request->post('code');
        //$sg_nums = $this->request->post('sg_nums');
        //$total_nums = $this->request->post('total_nums');

        $psjy0_ss = config('site.psjy0_ss');
        // //判断最低交易金额
        $kz_xdmoney = config('site.psjy0_jine');
        if(floatval($kz_xdmoney)>floatval($money)){
            $this->error("交易失败，交易金额少于".$kz_xdmoney);
        }

        $modelinfo = new Shengou();
        $info = $modelinfo->where(['code'=>$code])->find();
        if(!$info){
            $this->error('申购参数错误,请重试');
        }
        if(config('site.gqpeizhi')){
            if(intval($psjy0_ss)> intval($sg_nums)){
                $this->error("交易失败，交易签数少于".$psjy0_ss);
            }
            if(!Tool::is__int($sg_nums)){
                $this->error('请输入正确的签数');
            }
        }else{
            if(intval($psjy0_ss)> intval($sg_nums)){
                $this->error("交易失败，交易手数少于".$psjy0_ss);
            }
            if(!Tool::is__int($sg_nums)){
                $this->error('请输入正确的手数');
            }
        }
        //判断当前玩家下了多少次 和订单上限对比
        if(intval(round($total_nums,0))>intval($info['sg_limit'])){
            // $this->error("该新股最大申购上限为".$info['sg_limit'].",请重新下单");
            $this->error("下单不能超过申购上限");
        }
        //判断订单数 当前用户的订单数
        $totalcount = Db::name('sgjiaoyi0')->where(['user_id'=>$this->auth->id,"shengouid"=>$info['id']])->count();
        if($totalcount>=intval($info['dd_limit'])){
            // $this->error("超过最大订单下单数");
        }
        $modelssss = new Sgjiaoyi0();
        $count = $modelssss->where(['code'=>$code,'user_id'=>$this->auth->id])->count();
        if($count>=$info["dd_limit"]){
            $this->error('请勿重复申购');
        }
        Db::startTrans();
        $model = new Sgjiaoyi0();
        $data = [
            'user_id' => $this->auth->id,
            'code' => $code,
            'sg_num' => $sg_nums,
            'sg_nums' => round($total_nums,0),
            // 'money' => round(round($total_nums,0)*$info['fx_price'],2),
            'money' => $money,
            'shengouid' => $info['id'],
            'name' => $info['name'],
            'sg_fx_price' => $info['fx_price'],
            'sg_hy_rate' => $info['hy_rate'],
            'sg_sg_date' => $info['sg_date'],
            'sg_zq_jk_date' => $info['zq_jk_date'],
            'sg_ss_date' => $info['ss_date'],
            'sg_type' => $info['sg_type']
        ];
        $model->data($data);
        $result = $model->save();
        //增加资金明细
        // Tool::addLog($this->auth->id,'新股申购冻结金额',0,0,$dj_money,0,0);
        if($result !== false){
            // 当前讲冻结资金清空  申购无资金操作
            // Db::name('user')->where('id',$this->auth->id)->update([
            //         'freeze_profit' => 0
            //     ]);
            Db::commit();
            $this->success('申购成功');
        }else{
            Db::rollback();
            $this->error('申购失败');
        }
    }

    //增加申购记录
    public function xxadd(){
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

        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        $beforeMoney = $userInfo['balance'];
        // if($userInfo['status'] == "forbidden"){
        //     $this->error("当前账户异常,禁止交易");
        // }
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        //申购时间设置
        $md_date = date('m-d',time());
        $holidayop = model('app\admin\model\Holidayop')->where(['time_str'=>$md_date,'status'=>'0'])->find();
        // if(isset($holidayop['status']) && !$holidayop['status']){
        //     $this->error('节假日不允许交易');
        // }
        if($holidayop){
            $this->error('节假日不允许交易');
        }
        if(!betweentime(config('site.swpstime')) && !betweentime(config('site.xwpstime'))){
            $this->error('下单失败,不在交易时间段');
        }

        //申购时间设置
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $code = Rsa::check($paramInfo['code']);
        $sg_nums = Rsa::check($paramInfo['sg_nums']);
        $money = Rsa::check($paramInfo['money']);
        $dj_money = Rsa::check($paramInfo['dj_money']);
        $total_nums = Rsa::check($paramInfo['total_nums']);
        $miyao = Rsa::check($paramInfo['miyao']);
        //$code = $this->request->post('code');
        //$sg_nums = $this->request->post('sg_nums');//手数、签数
        //$money = $this->request->post('money');
        //$dj_money = $this->request->post('dj_money');
        //$total_nums = $this->request->post('total_nums');
        $psjy_ss = config('site.psjy_ss');
        //判断最低交易金额
        $kz_xdmoney = config('site.psjy_jine');
        if(floatval($kz_xdmoney)>floatval($money)){
            $this->error("交易失败，交易金额少于".$kz_xdmoney);
        }

        $modelinfo = new Shengou();
        $info = $modelinfo->where(['code'=>$code])->find();
        if(!$info){
            $this->error('申购参数错误,请重试');
        }
        if($info['content'] && ($info['content'] !== $miyao)){
            $this->error('请输入正确的密钥');
        }
        if(config('site.gqpeizhi')){
            if(intval($psjy_ss)> intval($sg_nums)){
                $this->error("交易失败，交易签数少于".$psjy_ss);
            }
            if(!Tool::is__int($sg_nums)){
                $this->error('请输入正确的签数');
            }
        }else{
            if(intval($psjy_ss)> intval($sg_nums)){
                $this->error("交易失败，交易手数少于".$psjy_ss);
            }
            if(!Tool::is__int($sg_nums)){
                $this->error('请输入正确的手数');
            }
        }
        //判断当前玩家下了多少次 和订单上限对比
        if(intval(round($total_nums,0))>intval($info['sg_limit'])){
            // $this->error("该新股最大申购上限为".$info['sg_limit'].",请重新下单");
            $this->error("下单不能超过申购上限");
        }
        //判断订单数 当前用户的订单数
        $totalcount = Db::name('sgjiaoyi')->where(['user_id'=>$this->auth->id,"shengouid"=>$info['id']])->count();
        if($totalcount>=intval($info['dd_limit'])){
            $this->error("超过最大订单下单数");
        }
        // 判断份额不足
        // if(intval(round($total_nums,0))>intval($info['xx_nums'])){
        //     // $this->error("该新股最大申购上限为".$info['sg_limit'].",请重新下单");
        //     $this->error("份额不足");
        // }
        Db::startTrans();
        $model = new Sgjiaoyi();
        $data = [
            'user_id' => $this->auth->id,
            'code' => $code,
            'sg_num' => $sg_nums,
            'sg_nums' => round($total_nums,0),
            'money' => $money,
            'dj_money' => $dj_money,
            'shengouid' => $info['id'],
            'name' => $info['name'],
            'sg_fx_price' => $info['fx_price'],
            'sg_hy_rate' => $info['hy_rate'],
            'sg_sg_date' => $info['sg_date'],
            'sg_zq_jk_date' => $info['zq_jk_date'],
            'sg_ss_date' => $info['ss_date']
        ];
        $model->data($data);
        $result = $model->save();
        //冻结账户 冻结金额 并且账户余额扣除支付金额
        $usermodel = new Useruser();
        $userinfo = $usermodel->where(["id"=>$this->auth->id])->find();
        if($userinfo->balance<$money){
            Db::rollback();
            $this->error('账户余额不足');
        }
        //判断T+1
        $balance = round($userinfo['balance']-$money,2);
        $freeze_profit = 0;
        if(config('site.kq_dj')){
            // 情况1：当前资金小于可提资金
            $t0 = floatval($userInfo['balance']);
            $t1 =round(floatval($userInfo['balance'])- floatval($userInfo['freeze_profit']),2);
            if(floatval($money)<=$t1){
                $balance = round(bcadd(bcsub($t1,floatval($money)),floatval($userInfo['freeze_profit'])),2);
                $freeze_profit = $userInfo['freeze_profit'];
            }
            // 第二种情况 可提资金不够，但是小于可用资金 
            else if($t1<floatval($money) && floatval($money)<floatval($userInfo['balance'])){
                // 判断冻结资金 要扣多少出来
                $s =round(bcsub(floatval($money),$t1,20),2);
                $freeze_profit =round(bcsub(floatval($userInfo['freeze_profit']),$s,20));
                
                $balance = $freeze_profit;
            }else if(floatval($money) == floatval($userInfo['balance'])){
                 $balance = $freeze_profit = 0;
            }
        }
        $userinfo->id = $this->auth->id;
        $userinfo->money = round($userinfo['money']-$money,2);
        // $userinfo->balance = round($userinfo['balance']-$money,2);
        $userinfo->balance = $balance;
        $userinfo->freeze_profit = $freeze_profit;
        $userinfo->sg_freeze_money = round($userinfo['sg_freeze_money']+$money,2);
        $result1 = $userinfo->save();
        //增加资金明细
        Tool::addLog($this->auth->id,"配售申购".$code."冻结金额",$beforeMoney,$balance,$money,1);
        // 更新
        $info->xx_nums = intval($info['xx_nums']) - intval($total_nums);
        // $result2 = $info->save();
        $result2 = true;
        if($result !== false && $result1 !== false && $result2 !== false){
            Db::commit();
            $this->success('配售成功');
        }else{
            Db::rollback();
            $this->error('配售失败');
        }
    }

    //申购记录
    public function getsgnewgu0(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $status = Rsa::check($paramInfo['status']);
        $page = Rsa::check($paramInfo['page']);

        $model = new Sgjiaoyi0();
        if(!$this->auth->id){
            $this->error('参数错误,请重新登陆');
        }
        $map = [];
        //$status = $this->request->get('status');
        if($status==1){
            $map['status'] = '1';
        }else if($status==2){
            $map['status'] = '2';
        }else if($status==3){
            $map['status'] = '3';
        }else{
            $map['status'] = '0';
        }
        //$page = $this->request->get('page')?$this->request->get('page'):1;
        $dxlog_list = $model
            ->where(['user_id'=>$this->auth->id])
            ->where(['is_cc'=>['neq',1]])
            ->where($map)
            ->order('createtime desc')
            ->page($page,20)
            ->select();
        foreach ($dxlog_list as $k => $v) {
            $dxlog_list[$k]['codejson'] = array(
                "text"=>$dxlog_list[$k]['name'],
                "color"=>"#ff6219",
                "size"=>28
            );
            $color = "#ff6219";
            if($v['status']=="0"){
                $dxlog_list[$k]['status_txt'] = "申购中";
            }else if($v['status']=="1"){
                if(!$v['renjiao']){
                    if(config('site.gqpeizhi')){
                        $dxlog_list[$k]['status_txt'] = "中签".$v['sg_num']."(签)";
                    }else{
                        $dxlog_list[$k]['status_txt'] = "中签".$v['zq_num']."(股)";
                    }
                }else{
                    if(config('site.gqpeizhi')){
                        $dxlog_list[$k]['status_txt'] = "中签".$v['sg_num']."(签)(已认缴)";
                    }else{
                        $dxlog_list[$k]['status_txt'] = "中签".$v['zq_num']."(股)(已认缴)";
                    }
                }
                $color = "#ff6219";
            }else if($v['status']=="2"){
                $dxlog_list[$k]['status_txt'] = "未中签";
            }else if($v['status']=="3"){
                $dxlog_list[$k]['status_txt'] = "已弃购";
            }
            $dxlog_list[$k]['tag'] = array(
                "text"=>$dxlog_list[$k]['status_txt'],
                "color"=>$color,
                "size"=>28
            );
            $dxlog_list[$k]['sg_ss_tag'] = 1;
            if($dxlog_list[$k]['sg_ss_date'] == "0000-00-00"){
                $dxlog_list[$k]['sg_ss_tag'] = 0;
            }

            // $dxlog_list[$k]['createtime_txt'] = date('Y-m-d H:i:s',$v['createtime']);
            $dxlog_list[$k]['createtime_txt'] = date('Y-m-d',$v['createtime']);
        }
        //加密
        $data = Rsa::jia(['dxlog_list'=>$dxlog_list,'kq_zdrj'=>config('site.kq_zdrj')]);
        $this->success('返回成功',$data);
    }

    //认缴 是需要扣除余额的 等上市就转持仓
    public function renjiao_act(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $id = Rsa::check($paramInfo['id']);
        // $id = $this->request->post('id');
        $model = new Sgjiaoyi0();
        $model_user = new \app\admin\model\User();
        $row = $model->get($id);
        if(!$row){
            $this->error('记录不存在,认缴失败');
        }
        if($row['renjiao']){
            $this->error('已认缴,请勿重新认缴');
        }
        //做数据修改
        Db::startTrans();
        $row->renjiao = 1;
        $row->renjiao_time = time();
        $result = $row->save();

        //修改玩家的账户余额
        // $userinfo = $model_user->get($row['user_id']);
        $usermodel = new Useruser();
        $userinfo = $usermodel->where(["id"=>$row['user_id']])->find();
        if(!$userinfo){
            Db::rollback();
            $this->error('参数错误,请重新登陆');
        }
        // 认缴的时候 直接清空 优先使用冻结资金
        $freeze_profit = 0;
        $balance = round(bcsub($userinfo['balance'],$row['sy_renjiao'],20),2);
        if(config('site.wxzrenjiao')){
            if(floatval($userinfo['balance'])<floatval($row['sy_renjiao'])){
                Db::rollback();
                $this->error('本金不足,充值成功后,再申请认缴');
            }
            if(config('site.kq_dj')){
                // 情况1：当前资金小于可提资金
                // 总资金
                $t0 = floatval($userinfo['balance']);
                $t1 =round(floatval($userinfo['balance'])- floatval($userinfo['freeze_profit']),2);
                $gg = [
                      "balance" =>   $userinfo['balance'],
                      "freeze_profit" =>   $userinfo['freeze_profit'],
                      "t1" =>   $t1,
                ];
                if(floatval($row['sy_renjiao'])<=$t1){
                    $balance = round(bcadd(bcsub($t1,floatval($row['sy_renjiao'])),floatval($userinfo['freeze_profit'])),2);
                    $freeze_profit = $userinfo['freeze_profit'];
                }
                // 第二种情况 可提资金不够，但是小于可用资金 
                else if($t1<floatval($row['sy_renjiao']) && floatval($row['sy_renjiao'])<floatval($userinfo['balance'])){
                    // 判断冻结资金 要扣多少出来
                    $s =round(bcsub(floatval($row['sy_renjiao']),$t1,20),2);
                    $freeze_profit =round(bcsub(floatval($userinfo['freeze_profit']),$s,20),2);
                    
                    $balance = $freeze_profit;
                    $hh = [
                        't1' => $t1, 
                        'sy_renjiao' => $row['sy_renjiao'], 
                        's' => $s, 
                        'freeze_profit' => $userinfo['freeze_profit'], 
                        'freeze_profitn' =>$freeze_profit, 
                    ];
                }else if(floatval($row['sy_renjiao']) == floatval($userinfo['balance'])){
                     $balance = $freeze_profit = 0;
                }
            }
        }else{
            // 关闭无限制认缴 其实也是需要锁定资金的 只不过大于等于的时候 这个钱要成负数
            if(config('site.kq_dj')){
                // 情况1：当前资金小于可提资金
                // 总资金
                $t0 = floatval($userinfo['balance']);
                $t1 =round(floatval($userinfo['balance'])- floatval($userinfo['freeze_profit']),2);
                $gg = [
                      "balance" =>   $userinfo['balance'],
                      "freeze_profit" =>   $userinfo['freeze_profit'],
                      "t1" =>   $t1,
                ];
                if(floatval($row['sy_renjiao'])<=$t1){
                    $balance = round(bcadd(bcsub($t1,floatval($row['sy_renjiao'])),floatval($userinfo['freeze_profit'])),2);
                    $freeze_profit = $userinfo['freeze_profit'];
                }
                // 第二种情况 可提资金不够，但是小于可用资金 
                else if($t1<floatval($row['sy_renjiao']) && floatval($row['sy_renjiao'])<floatval($userinfo['balance'])){
                    // 判断冻结资金 要扣多少出来
                    $s =round(bcsub(floatval($row['sy_renjiao']),$t1,20),2);
                    $freeze_profit =round(bcsub(floatval($userinfo['freeze_profit']),$s,20),2);
                    
                    $balance = $freeze_profit;
                    $hh = [
                        't1' => $t1, 
                        'sy_renjiao' => $row['sy_renjiao'], 
                        's' => $s, 
                        'freeze_profit' => $userinfo['freeze_profit'], 
                        'freeze_profitn' =>$freeze_profit, 
                    ];
                }else if(floatval($row['sy_renjiao']) >= floatval($userinfo['balance'])){
                    $freeze_profit = 0;
                    $balance = round(bcsub($userinfo['balance'],$row['sy_renjiao'],20),2);
                }
            }
        }

        Tool::addLog($row['user_id'],"新股".$row['code']."认缴",$userinfo['balance'],$balance,$row['sy_renjiao'],1);
        $userinfo->balance =$balance;//round($userinfo['balance'] - $row['zq_money'],2);
        $userinfo->freeze_profit =$freeze_profit;
        $userinfo->sg_freeze_money = round($userinfo['sg_freeze_money'] +$row['sy_renjiao'],2);
        $result1 = $userinfo->save();
        //增加资金明细
        if($result !== false && $result1 !== false){
            Db::commit();
            $this->success('认缴成功',['list'=>$row]);
        }else{
            Db::rollback();
            $this->error('认缴失败,等待刷新,重新再试');
        }
    }

    //加载配售交易记录
    public function getsgnewgu(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $status = Rsa::check($paramInfo['status']);
        $page = Rsa::check($paramInfo['page']);
        $model = new Sgjiaoyi();
        if(!$this->auth->id){
            $this->error('参数错误,请重新登陆');
        }
        $map = [];
        //$status = $this->request->get('status');
        if($status==1){
            $map['status'] = '1';
        }else if($status==2){
            $map['status'] = '2';
        }else if($status==3){
            $map['status'] = '3';
        }else{
            $map['status'] = '0';
        }
        $page = $this->request->get('page')?$this->request->get('page'):1;
        $dxlog_list = $model
            ->where(['user_id'=>$this->auth->id])
            ->where(['is_cc'=>['neq',1]])
            ->where($map)
            ->order('createtime desc')
            ->page($page,20)
            ->select();
        foreach ($dxlog_list as $k => $v) {
            $dxlog_list[$k]['codejson'] = array(
                "text"=>$dxlog_list[$k]['name'],
                "color"=>"#ed3f14",
                "size"=>28
            );
            $color = "#ed3f14";
            if($v['status']=="0"){
                if(config('site.gqpeizhi')){
                    $dxlog_list[$k]['status_txt'] = "申购中:".$v['sg_num']."(签)";
                }else{
                    $dxlog_list[$k]['status_txt'] = "申购中:".$v['sg_num']."(手)";
                }
            }else if($v['status']=="1"){
                if(config('site.gqpeizhi')){
                    $dxlog_list[$k]['status_txt'] = "中签:".$v['zq_nums']."(签)";
                }else{
                    $dxlog_list[$k]['status_txt'] = "中签:".$v['zq_num']."(股)";
                }
                $color = "#EC4028";
            }else if($v['status']=="2"){
                $dxlog_list[$k]['status_txt'] = "未中签";
            }else if($v['status']=="3"){
                $dxlog_list[$k]['status_txt'] = "已弃购";
            }
            $dxlog_list[$k]['tag'] = array(
                "text"=>$dxlog_list[$k]['status_txt'],
                "color"=>$color,
                "size"=>28
            );
            $dxlog_list[$k]['sg_ss_tag'] = 1;
            if($dxlog_list[$k]['sg_ss_date'] == "0000-00-00"){
                $dxlog_list[$k]['sg_ss_tag'] = 0;
            }
            $dxlog_list[$k]['createtime_txt'] = date('Y-m-d H:i:s',$v['createtime']);
        }
        $data = Rsa::jia(['dxlog_list'=>$dxlog_list]);
        $this->success('返回成功',$data);
    }

    //详情
    public function detail(){
        $code = $this->request->get('code');
        $model = new Shengou();
        $info = $model->where('code',$code)->find();

        // 只要当前时间过了零点，就点击采集一次，然后再查询获取所有的
        $codeCai = model('app\admin\model\ShengouCai')->where(['code'=>$code,'type'=>'jj'])->find();
        $twelve_time = date('Y-m-d',time()).' 00:00:00';
        if(time() > strtotime($twelve_time)) {
            if (!$codeCai || ($codeCai['createtime'] < strtotime($twelve_time))) {
                $ggnews = new Companyjk();
                $url = "http://api.jiaoyibiji.com/company_profile?code={$code}&token=" . config('site.tokenp');
                $result = Http::get($url);
                $result_json = json_decode($result, true);

                if (is_array($result_json)) {
                    $onlineInfo = $ggnews->where('code',$code)->find();
                    $data = [
                        'code' => $code,
                        'corporate_name' => $result_json[0]['corporate_name'],
                        'former_name' => $result_json[0]['former_name'],
                        'code_a' => $result_json[0]['code_a'],
                        'name_abbr' => $result_json[0]['name_abbr'],
                        'region' => $result_json[0]['region'],
                        'industry' => $result_json[0]['industry'],
                        'concept' => $result_json[0]['concept'],
                        'chairman' => $result_json[0]['chairman'],
                        'legal_person' => $result_json[0]['legal_person'],
                        'president' => $result_json[0]['president'],
                        'secretary' => $result_json[0]['secretary'],
                        'found_date' => $result_json[0]['found_date'],
                        'reg_capital' => $result_json[0]['reg_capital'],
                        'employees_num' => $result_json[0]['employees_num'],
                        'management_num' => $result_json[0]['management_num'],
                        'org_tel' => $result_json[0]['org_tel'],
                        'org_email' => $result_json[0]['org_email'],
                        'org_web' => $result_json[0]['org_web'],
                        'addr' => $result_json[0]['addr'],
                        'reg_addr' => $result_json[0]['reg_addr'],
                        'reg_profile' => $result_json[0]['reg_profile'],
                        'main_business' => $result_json[0]['main_business'],
                    ];
                    if(!$onlineInfo){
                        $model = new Companyjk();
                        $model->data($data);
                        $model->save();
                    }else {
                        $onlineInfo->save();
                    }

                    if(!$codeCai){
                        $codeCaiModel = new ShengouCai();
                        $codeCaiModel->data([
                            'code' => $code,
                            'type' => 'jj',
                            'createtime' => time()
                        ]);
                        $codeCaiModel->save();
                    }else{
                        model('app\admin\model\ShengouCai')->where('id',$codeCai['id'])->update([
                            'createtime' => time()
                        ]);
                    }

                }

            }
        }
        $ggnewsModel = new Companyjk();
        $jjInfo = $ggnewsModel
            ->where('code',$code)
            ->find();
        $info['reg_profile'] = $jjInfo['reg_profile'];
        $this->success('请求成功',$info);
    }

    //题目列表
    public function topic()
    {
        $topicLst = Db::name('topic')->select();
        foreach ($topicLst as $k => $v) {
            $topicLst[$k]['son'] = Db::name('topic_answer')->where('topic_id',$v['id'])->select();
        }
        $this->success('请求成功',$topicLst);
    }

    //提交评估
    public function addtopic()
    {
        $real_name = $this->request->post('real_name');
        $sfz = $this->request->post('sfz');
        $answer = $this->request->post('answer');
        $id = $this->request->post('id');
        $model = new UserAnswer();
        if(!$id) {
            $data = [
                'user_id' => $this->auth->id,
                'real_name' => $real_name,
                'sfz' => $sfz,
                'answer' => $answer
            ];
            $model->data($data);
            $result = $model->save();
        }else{
            $row = $model->find($id);
            $row->real_name = $real_name;
            $row->sfz = $sfz;
            $row->answer = $answer;
            $result = $row->save();
        }
        if ($result != false) {
            $this->success('提交成功');
        } else {
            $this->error('提交失败');
        }
    }

    //获取当前用户是否评估
    public function seetopic(){
        $model = new UserAnswer();
        $is_pg = 0;
        $user_answer_id = 0;
        $real_name = '';
        $sfz = '';
        $userAnswerInfo = $model->where('user_id',$this->auth->id)->find();
        if($userAnswerInfo){
            $is_pg = 1;
            $user_answer_id = $userAnswerInfo['id'];
            $real_name = $userAnswerInfo['real_name'];
            $sfz = $userAnswerInfo['sfz'];
        }
        $info = [
            'is_pg' => $is_pg,
            'user_answer_id' => $user_answer_id,
            'real_name' => $real_name,
            'sfz' => $sfz
        ];
        $this->success('请求成功',$info);
    }

}