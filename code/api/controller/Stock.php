<?php

namespace app\api\controller;

use app\admin\model\Admin;
use app\admin\model\Adv;
use app\admin\model\Agreement;
use app\admin\model\Attention;
use app\admin\model\Banner;
use app\admin\model\feedback\Problem;
use app\admin\model\Nav;
use app\admin\model\shengou\Sgjiaoyi0;
use app\admin\model\strategy\Addstrategy;
use app\admin\model\strategy\Classify;
use app\admin\model\user\Identitycard;
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
use app\admin\model\user\Account;

class Stock extends Api
{
    protected $noNeedLogin = ['getconfig_fei'];
    protected $noNeedRight = ['*'];

    //获取一些功能配置
    public function getconfig_fei(){
        $sysInfo = Db::name('sysbank')->where(['status'=>1])->select();
        $is_open_yzzr_sm = 0;
        if($sysInfo){
            $is_open_yzzr_sm = 1;
        }
        $kfurl = '';
        // 找出当前登录用户 对应的客服链接
        $admin_info = Db::name('admin')->where(['id'=>$this->auth->dailiid])->find();
        if($admin_info){
           $kfurl = $admin_info['kfurl'];
        }
        $kfurl = $kfurl?$kfurl:config('site.kf_url');
        //当前用户有没有实名
        $is_rz = 0;
        $identityInfo = Db::name('identity_card')->where(['user_id'=>$this->auth->id,'is_audit'=>1])->find();
        if($identityInfo){
            $is_rz = 1;
        }
        $data = [
            'is_xgsg' => config('site.is_xgsg'),
            'is_xxps' => config('site.is_xxps'),
            'is_xgsg_name' => config('site.is_xgsg_name'),
            'is_xxps_name' => config('site.is_xxps_name'),
            'dz_syshow' => config('site.dz_syshow'),
            'dz_syname' => config('site.dz_syname'),
            'zx_gg' => config('site.zx_gg'),
            'is_rz' => config('site.is_rz'),
            'bk_sm' => config('site.bindbank'),
            'bk_lst_sm' => config('site.bk_lst_sm'),
            'zz_sm_one' => config('site.contentmsg1'),
            'is_open_yzzr_sm' => $is_open_yzzr_sm,
            'zz_sm_two' => config('site.contentmsg2'),
            'is_rz' => $is_rz,
            'sg_cs_des' => config('site.sg_cs_des'),
            'is_gu_qian' => config('site.gqpeizhi'),
            'zc_desc' => config('site.zc_desc'),
            'min_tx_money' => config('site.zdtixian'),
            'yhxy' => config('site.yhxy'),
            'yszc' => config('site.yszc'),
            'zcxy' => config('site.zcxy'),
            'huancun' => config('site.huancun'),
            'web_url' => config('site.web_url'),
            'web_email' => config('site.web_email'),
            'mz_sm' => config('site.mz_sm'),
            'web_name' => config('site.name'),
            'web_version' => config('site.version'),
            'mai_fee' => config('site.mai_fee'),
            'maic_fee' => config('site.maic_fee'),
            'down_app_url' => config('site.down_app_url'),
            'applogo' => config('site.applogo'),
            'fx_head_wz' => config('site.fx_head_wz'),
            'fx_foot_wz' => config('site.fx_foot_wz'),
            'fx_foot_address' => config('site.fx_foot_address'),
            'fx_foot_kf_tel' => config('site.fx_foot_kf_tel'),
            'fx_foot_ts_tel' => config('site.fx_foot_ts_tel'),
            'pz_pzsxf' => config('site.pzsxf'),
            'dz_dzsxf' => config('site.dzsxf'),
            'isauth_rz' => config('site.isauth_rz'),
            'qhfee' => config('site.qhfee'),
            'qfjy_fee' => config('site.qfjy_fee'),
            'is_pzxx' => config('site.is_pzxx'),
            'is_dzxx' => config('site.is_dzxx'),
            'is_qhopen' => config('site.is_qhopen'),
            'is_jjopen' => config('site.is_jjopen'),
            'kf_url' => $kfurl,
            'is_weituo' => config('site.is_weituo'),
            'is_auth_or' => config('site.is_auth_or'),
            'dzjy_zg' => config('site.dzjy_zg'),
            'kq_zxyz' => config('site.kq_zxyz'),
            'yh_fee' => config('site.yh_fee'),
        ];
        //加密
        // $data = Rsa::jia($data);
        $this->success('请求成功', $data);
    }

    public function getconfig(){
        // $sysInfo = Db::name('sysbank')->where(['status'=>1])->select();
        // $is_open_yzzr_sm = 0;
        // if($sysInfo){
        //     $is_open_yzzr_sm = 1;
        // }
        $kfurl = '';
        // 找出当前登录用户 对应的客服链接
        $admin_info = Db::name('admin')->where(['id'=>$this->auth->dailiid])->find();
        if($admin_info){
            $kfurl = $admin_info['kfurl'];
        }
        $kfurl = $kfurl?$kfurl:config('site.kf_url');
        //当前用户有没有实名
        $is_rz = 0;
        $identityInfo = Db::name('identity_card')->where(['user_id'=>$this->auth->id,'is_audit'=>1])->find();
        if($identityInfo){
            $is_rz = 1;
        }
        $data = [
            'is_xgsg' => config('site.is_xgsg'),
            'is_xxps' => config('site.is_xxps'),
            'is_xgsg_name' => config('site.is_xgsg_name'),
            'is_xxps_name' => config('site.is_xxps_name'),
            'dz_syshow' => config('site.dz_syshow'),
            'is_dzxx' => config('site.dz_syshow'),
            'dz_syname' => config('site.dz_syname'),
            'is_dy' => config('site.vip_syshow'),
            'vip_syname' => config('site.vip_syname'),
            // 'zx_gg' => config('site.zx_gg'),
            // 'bk_sm' => config('site.bindbank'),
            // 'bk_lst_sm' => config('site.bk_lst_sm'),
            // 'zz_sm_one' => config('site.contentmsg1'),
            // 'is_open_yzzr_sm' => $is_open_yzzr_sm,
            // 'zz_sm_two' => config('site.contentmsg2'),
            'is_rz' => $is_rz,
            // 如果后台开启实名认证
            'is_rzconfig' => config('site.is_rz'),
            'is_gu_qian' => config('site.gqpeizhi'),
            // 'zc_desc' => config('site.zc_desc'),
            'min_tx_money' => config('site.zdtixian'),
            // 'zcxy' => config('site.zcxy'),
            'huancun' => config('site.huancun'),
            'web_url' => config('site.web_url'),
            'web_email' => config('site.web_email'),
            'web_name' => config('site.name'),
            'web_version' => config('site.version'),
            'mai_fee' => config('site.mai_fee'),
            'maic_fee' => config('site.maic_fee'),
            // 'down_app_url' => config('site.down_app_url'),
            // 'applogo' => config('site.applogo'),
            'pz_pzsxf' => config('site.pzsxf'),
            'dz_dzsxf' => config('site.zfssfee'),
            'isauth_rz' => config('site.isauth_rz'),
            // 'qhfee' => config('site.qhfee'),
            // 'qfjy_fee' => config('site.qfjy_fee'),
            'is_pzxx' => config('site.is_pzxx'),
            // 'is_dzxx' => config('site.is_dzxx'),
            // 'is_qhopen' => config('site.is_qhopen'),
            // 'is_jjopen' => config('site.is_jjopen'),
            'kf_url' => $kfurl,
            'is_weituo' => config('site.is_weituo'),
            'is_auth_or' => config('site.is_auth_or'),
            // 'dzjy_zg' => config('site.dzjy_zg'),
            // 'kq_zxyz' => config('site.kq_zxyz'),
            'yh_fee' => config('site.yh_fee'),
            'kqssss' => config('site.kqssss'),
            'yzmy' => config('site.yzmy'),
        ];
        //加密
        $data = Rsa::jia($data);
        $this->success('请求成功', $data);
    }

    //获取用户信息
    public function info(){
        $list = Db::name('user')
            ->where('id',$this->auth->id)
            ->find();
        if($list){
            //加持仓本金
            $sum_creditMoney = Db::name('add_strategy')->where(['status'=>1,'user_id'=>$this->auth->id])->sum('creditMoney');
            // $list['property_money'] = bcadd($list['balance'],$sum_creditMoney,2);
            $list['property_money'] = bcadd($list['balance'],0,2);
            $list['versions'] = '1.0.0';
            $authenticationModel = new Identitycard();
            // $where['is_audit'] = 1;
            $where['user_id'] = $this->auth->id;
            $info = $authenticationModel->where($where)->find();
            $list['is_authentication'] = 0;
            if($info){
                $list['is_authentication'] = $info['is_audit'];
                //$list['nickname'] = $info['name'];
            }
            // if($info){
            //     $list['is_authentication'] = 1;
            // }else {
            //     $list['is_authentication'] = 0;
            // }
            //获取未读消息的个数
            // $msgcount = Db::name('news')->where(['user_id'=>$this->auth->id,'is_read'=>0])->count();
            $list['money'] = $list['balance'];
            $list['gqpeizhi'] = config('site.gqpeizhi');
            // $list['msgcount'] = $msgcount;

            //是否认证
            $list['is_rz'] = 0;
            $identityInfo = Db::name('identity_card')->where(['user_id'=>$this->auth->id,'is_audit'=>1])->find();
            if($identityInfo){
                $list['is_rz'] = 1;
            }

            //是否持仓
            $list['is_cc'] = 0;
            $str_model = new Addstrategy();
            $str_info = $str_model->where(['user_id'=>$this->auth->id,'status'=>1,'buytype'=>1])->find();
            if($str_info){
                $list['is_cc'] = 1;
            }

            // 是否绑定了银行卡 
            $list['is_card'] = 0;
            $str_account = new Account();
            $account_info = $str_account->where(['user_id'=>$this->auth->id,'deletetime'=>Null])->find();
            if($account_info){
                $list['is_card'] = 1;
            }
            //消息通知
            $num = model('app\admin\model\news\News')->where(['user_id'=>$this->auth->id,'is_read'=>0])->count();
            $list['xx_num'] = $num?$num:0;
        }

        $data = ['list'=>$list];
        //加密
        $data = Rsa::jia($data);
        $this->success('请求成功',$data);
    }

    public function ballot()
    {
        //判断当前用户是否0申购中签成功 并且只查一条信息
        $model = new Sgjiaoyi0();
        $sgmodel = new Shengou();
        $is_rj = 0;
        $list = $model->where(['user_id' => $this->auth->id, 'status' => 1, 'renjiao' => 0])->select();
        if ($list) {
            $sgmodel_info = $sgmodel->where(['code' => $list[0]['code']])->find();
            $list[0]['gqpeizhi'] = config('site.gqpeizhi');
            $list[0]['content'] = $sgmodel_info['content'];
            if ($list[0]) {
                $is_rj = 1;
            }
            foreach ($list as $k => $v){
                // $list[$k]['marketCode'] = 
            }
            $data = ['is_rj' => $is_rj,'count'=>count($list),'info' => $list];
            $data = Rsa::jia($data);
            $this->success('请求成功', $data);
        } else {
            $this->error('请求失败');
        }
    }
    
    public function contracts(){
        $uid = $this->auth->id;
        $authenticationModel = new Identitycard();
        $where['user_id'] = $uid;
        $info = $authenticationModel->where($where)->find();
        if(empty($info) || $info['is_audit'] != 1){
            $this->error('请先完成实名认证');
        }
        
        $contracts = Db::name('user') ->field('contract_1,contract_2') ->where(['id'=>$uid]) ->find();
        $user_contracts = [];
        if (empty($contracts['contract_1'])) {
            $user_contracts[] = ["type"=>1,"name"=>"证券投资顾问咨询服务协议","status"=>0,"link"=>"","id"=>0];
        }else{
            $contract_tamp = json_decode($contracts['contract_1'],true);
            $contract_tamp['name'] = "证券投资顾问咨询服务协议";
            $url = config('site.contrace_api').'/api/Contract/get_contract';
            // dump($contract_tamp);
            // dump($url);
            $obj = new Http();
            $result = json_decode($obj->post($url, ['id'=>$contract_tamp['id']]), true);
            // dump($result);
            if($result['code'] == 1){
                $contract_tamp['status'] = $result['data']['tgdata'] == 2 ? 1 : 0;
            }
            $user_contracts[] = $contract_tamp;
        }
        if (empty($contracts['contract_2'])) {
            $user_contracts[] = ["type"=>2,"name"=>"商业核心信息保密协议书","status"=>0,"link"=>"","id"=>0];
        }else{
            $contract_tamp = json_decode($contracts['contract_2'],true);
            $contract_tamp['name'] = "商业核心信息保密协议书";
            $url = config('site.contrace_api').'/api/Contract/get_contract';
            $obj = new Http();
            $result = json_decode($obj->post($url, ['id'=>$contract_tamp['id']]), true);
            if($result['code'] == 1){
                $contract_tamp['status'] = $result['data']['tgdata'] == 2 ? 1 : 0;
            }
            $user_contracts[] = $contract_tamp;
        }
        
        $this->success('请求成功', $user_contracts);
    }
    
    public function createContract(){
        $uid = $this->auth->id;
        $authenticationModel = new Identitycard();
        $where['user_id'] = $uid;
        $info = $authenticationModel->where($where)->find();
        if(empty($info) || $info['is_audit'] != 1){
            $this->error('请先完成实名认证');
        }
        $query = $this->request->param('query');
        if($query){
            $paramInfo = Rsa::jie($query);
        // dump($info);
            $apiData = [
                "typedata"=>$paramInfo['type'],
                "name"=>$info['name'],
                "idnumber"=>$info['id_card'],
                "address"=>$paramInfo['address'],
            ];
            
            $url = config('site.contrace_api').'/api/Contract/create_contract';
            // dump($url);
            $obj = new Http();
            $result = json_decode($obj->post($url, $apiData), true);
            // dump($result);
            if ($result['code'] == 1) {
                $saveData = $result['data'];
                $saveData['type'] = $paramInfo['type'];
                $saveData['status'] = 0;
                Db::name('user') ->where(['id'=>$uid]) ->update(['contract_'.$paramInfo['type']=>json_encode($saveData,JSON_UNESCAPED_UNICODE)]);
                $this->success('合同生成成功，请签署', ['link'=>$saveData['link']]);
            }else{
                $this->error('合同生成失败，请稍后再试');
            }
        }else{
            $this->error('参数有误');
        }
    }

}