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
use fast\Rsa;
use fast\Rsahelper;
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
use app\admin\model\Newsss;
/**
 * 首页接口
 */
class Indexnew extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];


    /*
    查询大盘行情
    */
    public function sandahangqing_new(){
        // 第一组：sh000001 sz399001 sz399006
        // 第二组：sh000016 sh000300 sz399005
        // 第三组：sh000009 sh000010
        $allcodes_arr = [];
        $model = new Strategy();
        $list = $model
                ->where(['allcode'=>['in',['sh000001','sz399001','sz399006','sh000016','sh000300','sz399005','sh000009','sh000010']]])
                ->field('title,CONCAT("s_",allcode) as allcode_s,allcode,code')
                ->select();
        if(count($list)>0){
            $arr1 =array_unique(array_column($list,'allcode_s'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia_new($allcodes);
            // file_put_contents('../public/logs//xxxx.txt',var_export($allcodes_arr,true)."\n\n",FILE_APPEND);
        }
        // $list = collection($list)->toArray();
        foreach ($list as $k => $v){
            $list[$k]['allcodes_arr'] = $allcodes_arr[$v['allcode']];
        }
        //加密
        $data = Rsa::jia(['list'=>$list]);
        $this->success('请求成功', $data);
    }
    
    /*
    新股申购个数 和 线下配售个数
    新股申购大于当前时间，并且是开启了的
    线下配售大于当前时间，并且是开启了的
    */
     public function sgandps(){
        $strategy = new Strategy();
        $Shengou = new Shengou();
        $addstrategy = new Addstrategy();
        // $list_sg = $Shengou->where(['sgswitch'=>1])->whereTime('sg_date_int','>','today')->select();
        $list_sg = $Shengou->where(['sgswitch'=>1])->field('name,code,fx_price')->select();
        // $list_sg1 = $Shengou->where(['sgswitch'=>1])->whereTime('sg_date_int','today')->select();
        // $list_ps = $Shengou->where(['xxswitch'=>1])->whereTime('sg_date_int','>','today')->select();
        $list_ps = $Shengou->where(['xxswitch'=>1])->field('name,code')->select();
        $list_dzjy = $strategy->where(['is_zfa'=>1])->field('title as name,code,allcode,zfanum')->select();
        // 循环并计算出 进度
        foreach ($list_dzjy as $k => $v){
            $count = Db::name('add_strategy')->where(['allcode'=>$v['allcode'],'buytype'=>'7'])->sum('number');
            if(bcadd($count,$v['zfanum'],20)>0){
                $list_dzjy[$k]['p'] = round((1-bcdiv($count,bcadd($count,$v['zfanum'],20),20))*100);
            }else{
                $list_dzjy[$k]['p'] = round(1)*100;
            }
        }
        // $this->success('请求成功', ['is_xgsg'=>config('site.is_xgsg'),'flag_xgsg'=>1,'name_xgsg'=>'新股申购','list_sg_count'=>count($list_sg),'list_sg'=>$list_sg,'is_xxps'=>config('site.is_xxps'),'res.data.flag_xxps'=>2,'name_xxps'=>'线下配售','list_ps_count'=>count($list_ps),'list_ps'=>$list_ps]);

         //加密
         $data = ['is_xgsg'=>config('site.is_xgsg'),'flag_xgsg'=>1,'name_xgsg'=>config('site.is_xgsg_name'),'list_sg_count'=>count($list_sg),'list_sg'=>$list_sg,'process_xgsg'=>0,'is_xxps'=>config('site.is_xxps'),'flag_xxps'=>2,'name_xxps'=>config('site.is_xxps_name'),'list_ps_count'=>count($list_ps),'list_ps'=>$list_ps,'process_xxps'=>0,'is_dzjy'=>config('site.dz_syshow'),'flag_dzjy'=>3,'name_dzjy'=>config('site.dz_syname'),'list_dzjy_count'=>count($list_dzjy),'list_dzjy'=>$list_dzjy,'process_dzjy'=>1];
         $data = Rsa::jia($data);
        $this->success('请求成功', $data);
     }
    
    /*
    热门行业 热门概念
    */
    public function remeninfo(){
        $redis = new Redis();
        if($redis->has('cai_json1_time')){
             $end_time = strtotime('+'.config('site.rm_cai_time').'minute',$redis->get('cai_json1_time'));
             if($end_time < time()){
                 $this->rehyCai(); //热门行业采集
             }else{ //没过期，就判断json1内容在不在
                 if(!$redis->has('cai_json1')){
                     $this->rehyCai();
                 }
             }
        }else{
            $this->rehyCai();
        }
        if($redis->has('cai_json2_time')){
            $end_time = strtotime('+'.config('site.rm_cai_time').'minute',$redis->get('cai_json2_time'));
            if($end_time < time()){
                $this->rmgnCai(); //热门行业采集
            }else{ //没过期，就判断json1内容在不在
                if(!$redis->has('cai_json2')){
                    $this->rmgnCai();
                }
            }
        }else{
            $this->rmgnCai();
        }
        $result_json1_new = json_decode($redis->get('cai_json1'));
        $result_json2_new = json_decode($redis->get('cai_json2'));
        $this->success('请求成功', ['rm_bk'=>$result_json1_new,'rm_gn'=>$result_json2_new]);
    }

    //热门行业采集
    public function rehyCai(){
        $redis = new Redis();
        $i = 0;
        // 热门板块
        $url1 = "http://api.jiaoyibiji.com/bkhotlst?pz=2&page=1&px=1&token=".config('site.tokenp');
        $result1 = Http::get($url1);
        $result_json1 = json_decode($result1,true);
        $result_json1_new = [];
        if(is_array($result_json1)) {
            foreach ($result_json1 as $k => $v) {
                if ($i >= 3) {
                    break;
                }
                $result_json1_new[] = $v;
                $i++;
            }
            // 取前三个 循环 并且获取
            foreach ($result_json1_new as $k => $v) {
                // 并且要在fa_strategy 获取allcode
                $info_return = $this->getAllcode($v['stockcode']);
                if (!$info_return) {
                    continue;
                }
                $result_json1_new[$k]['df'] = $this->getdf($info_return['allcode']);
            }
        }

        if($result_json1_new){
            $redis->set('cai_json1',json_encode($result_json1_new));
            $redis->set('cai_json1_time',time());
        }

    }

    //热门概念采集
    public function rmgnCai(){
        $redis = new Redis();
        $j = 0;
        // 热门概念
        $url2 = "http://api.jiaoyibiji.com/bkhotlst?pz=3&page=1&px=1&token=".config('site.tokenp');
        $result2 = Http::get($url2);
        $result_json2 = json_decode($result2,true);
        $result_json2_new = [];
        if(is_array($result_json2)) {
            foreach ($result_json2 as $k => $v) {
                if ($j >= 3) {
                    break;
                }
                $result_json2_new[] = $v;
                $j++;
            }
            // 取前三个 循环 并且获取
            foreach ($result_json2_new as $k => $v) {
                // 并且要在fa_strategy 获取allcode
                $info_return = $this->getAllcode($v['stockcode']);
                if (!$info_return) {
                    continue;
                }
                $result_json2_new[$k]['df'] = $this->getdf($info_return['allcode']);
            }
        }
        if($result_json2_new){
            $redis->set('cai_json2',json_encode($result_json2_new));
            $redis->set('cai_json2_time',time());
        }
    }

    /**
     * 涨跌分布
    */
    public function zdfenbu(){
        // 直接采集三方接口  这个一定要用socket，多播发送 这样就不会卡程序
        // 休眠5s
        $chart1 = [];
        $chart2 = [];
        $t1 = $t2 = $t3 = $t4 = $t5 = $t6 = $t7 = $t8 = $t9 = $t10 = $t11 = $t12 =0;
        $result = [];
        // 直接取redis
       $result = Db::name('zdf')->where(['id'=>1])->find();
        if(!$result){
            $this->success('请求成功', ['chart1_total'=>0,'chart1'=>$chart1,'chart2_total'=>0,'chart2'=>$chart2]);
        }
        $t1 =$result['t1'];$t2 =$result['t2'];$t3 =$result['t3'];$t4 =$result['t4'];$t5 =$result['t5'];$t6=$result['t6'];$t7 =$result['t7'];$t8 =$result['t8'];$t9 =$result['t9'];$t10 =$result['t10'];$t11=$result['t11'];$t12 =$result['t12'];
        $t1_arr = array('value' => $t1, 'color' => 'red');
        $t2_arr = array('value' => $t2, 'color' => 'red');
        $t3_arr = array('value' => $t3, 'color' => 'red');
        $t4_arr = array('value' => $t4, 'color' => 'red');
        $t5_arr = array('value' => $t5, 'color' => 'red');
        $t6_arr = array('value' => $t6, 'color' => 'red');
        
        
        $t7_arr = array('value' => $t7, 'color' => 'green');
        $t8_arr = array('value' => $t8, 'color' => 'green');
        $t9_arr = array('value' => $t9, 'color' => 'green');
        $t10_arr = array('value' => $t10, 'color' => 'green');
        $t11_arr = array('value' => $t11, 'color' => 'green');
        $t12_arr = array('value' => $t12, 'color' => 'green');
        $chart1 = [$t1_arr,$t2_arr,$t3_arr,$t4_arr,$t5_arr,$t6_arr];
        $chart2 = [$t7_arr,$t8_arr,$t9_arr,$t10_arr,$t11_arr,$t12_arr];

        $data = ['chart1_total'=>($t1+$t2+$t3+$t4+$t5+$t6),'chart1'=>$chart1,'chart2_total'=>($t7+$t8+$t9+$t10+$t11+$t12),'chart2'=>$chart2];
        //加密
        $data = Rsa::jia($data);
        $this->success('请求成功', $data);
    }
    
    /**
     * 涨跌分布
    */
    public function zdfenbu_redis(){
        // 直接采集三方接口  这个一定要用socket，多播发送 这样就不会卡程序
        // 休眠5s
        $chart1 = [];
        $chart2 = [];
        $t1 = $t2 = $t3 = $t4 = $t5 = $t6 = $t7 = $t8 = $t9 = $t10 = $t11 = $t12 =0;
        $result = [];
        // 直接取redis
        $redis = new Redis();
        if($redis->has('ddddzdfff')){
            $result = $redis->get('ddddzdf');
        }
        if(!is_array($result)){
            $this->success('请求成功', ['chart1_total'=>0,'chart1'=>$chart1,'chart2_total'=>0,'chart2'=>$chart2]);
        }
        $t1_arr = array('value' => $t1, 'color' => 'red');
        $t2_arr = array('value' => $t2, 'color' => 'red');
        $t3_arr = array('value' => $t3, 'color' => 'red');
        $t4_arr = array('value' => $t4, 'color' => 'red');
        $t5_arr = array('value' => $t5, 'color' => 'red');
        $t6_arr = array('value' => $t6, 'color' => 'red');
        
        
        $t7_arr = array('value' => $t7, 'color' => 'green');
        $t8_arr = array('value' => $t8, 'color' => 'green');
        $t9_arr = array('value' => $t9, 'color' => 'green');
        $t10_arr = array('value' => $t10, 'color' => 'green');
        $t11_arr = array('value' => $t11, 'color' => 'green');
        $t12_arr = array('value' => $t12, 'color' => 'green');
        $chart1 = [$t1_arr,$t2_arr,$t3_arr,$t4_arr,$t5_arr,$t6_arr];
        $chart2 = [$t7_arr,$t8_arr,$t9_arr,$t10_arr,$t11_arr,$t12_arr];
        
        $this->success('请求成功', ['chart1_total'=>($t1+$t2+$t3+$t4+$t5+$t6),'chart1'=>$chart1,'chart2_total'=>($t7+$t8+$t9+$t10+$t11+$t12),'chart2'=>$chart2]);
    }
    /**
     * 涨跌分布
    */
    public function zdfenbu111(){
        // 直接采集三方接口  这个一定要用socket，多播发送 这样就不会卡程序
        // 休眠5s
        $chart1 = [];
        $chart2 = [];
        $url = "http://api.jiaoyibiji.com/zdfenbu?&token=".config('site.tokenp');
        $result = Http::get($url);
        $result_json = json_decode($result,true);
        // var_dump($result_json);
        // 增加一个判断 
        if(!is_array($result_json)){
            $this->success('请求成功', ['chart1_total'=>0,'chart1'=>$chart1,'chart2_total'=>0,'chart2'=>$chart2]);
        }
        // var_dump($result_json);
        // 0-2 2-4 4-6 6-8 >8 >11
        
        $t1 = $t2 = $t3 = $t4 = $t5 = $t6 = $t7 = $t8 = $t9 = $t10 = $t11 = $t12 =0;
        foreach ($result_json as $k => $v){
            if($v['zdf']>=0 && $v['zdf']<2){
                $t1 += intval($v['num']);
            }else if($v['zdf']>=2 && $v['zdf']<4){
                $t2 += intval($v['num']);
            }else if($v['zdf']>=4 && $v['zdf']<6){
                $t3 += intval($v['num']);
            }else if($v['zdf']>=6 && $v['zdf']<8){
                $t4 += intval($v['num']);
            }else if($v['zdf']>=8 && $v['zdf']<=11){
                $t5 += intval($v['num']);
            }else if($v['zdf']>11){
                $t6 += intval($v['num']);
            }else if($v['zdf']<=0 && $v['zdf']>-2){
                $t7 += intval($v['num']);
            }else if($v['zdf']<=-2 && $v['zdf']>-4){
                $t8 += intval($v['num']);
            }else if($v['zdf']<=-4 && $v['zdf']>-6){
                $t9 += intval($v['num']);
            }else if($v['zdf']<=-6 && $v['zdf']>-8){
                $t10 += intval($v['num']);
            }else if($v['zdf']<=-8 && $v['zdf']>=-11){
                $t11 += intval($v['num']);
            }else if($v['zdf']<-11){
                $t12 += intval($v['num']);
            }
        }
        $t1_arr = array('value' => $t1, 'color' => 'red');
        $t2_arr = array('value' => $t2, 'color' => 'red');
        $t3_arr = array('value' => $t3, 'color' => 'red');
        $t4_arr = array('value' => $t4, 'color' => 'red');
        $t5_arr = array('value' => $t5, 'color' => 'red');
        $t6_arr = array('value' => $t6, 'color' => 'red');
        
        
        $t7_arr = array('value' => $t7, 'color' => 'green');
        $t8_arr = array('value' => $t8, 'color' => 'green');
        $t9_arr = array('value' => $t9, 'color' => 'green');
        $t10_arr = array('value' => $t10, 'color' => 'green');
        $t11_arr = array('value' => $t11, 'color' => 'green');
        $t12_arr = array('value' => $t12, 'color' => 'green');
        $chart1 = [$t1_arr,$t2_arr,$t3_arr,$t4_arr,$t5_arr,$t6_arr];
        $chart2 = [$t7_arr,$t8_arr,$t9_arr,$t10_arr,$t11_arr,$t12_arr];
        
        $this->success('请求成功', ['chart1_total'=>($t1+$t2+$t3+$t4+$t5+$t6),'chart1'=>$chart1,'chart2_total'=>($t7+$t8+$t9+$t10+$t11+$t12),'chart2'=>$chart2]);
    }
    
    /**
     * 获取标记：涨幅 跌幅 成交额 涨速 跌速 换手率
    */
    public function getTab(){
        $t1 = array('name' => '涨幅榜', 'sort' => 'changepercent','asc' => '0');
        $t2 = array('name' => '跌幅榜', 'sort' => 'changepercent','asc' => '1');
        $t3 = array('name' => '成交额', 'sort' => 'amount','asc' => '0');
        // $t4 = array('name' => '涨速榜', 'sort' => '','asc' => '0');
        // $t5 = array('name' => '跌速榜', 'sort' => '','asc' => '0');
        $t6 = array('name' => '换手率', 'sort' => 'turnoverratio','asc' => '0');
        // $tab = [$t1,$t2,$t3,$t4,$t5,$t6];
        $tab = [$t1,$t2,$t3,$t6];
        $this->success('请求成功', ['tab'=>$tab]);
    }
    /**
     * 获取涨幅 跌幅 成交额 涨速 跌速 换手率 详情
    */
    public function getTabDetail(){
        $sort = $this->request->param('sort')?$this->request->param('sort'):"changepercent";
        $asc = $this->request->param('asc')?1:0;
        $page = $this->request->param('page')?$this->request->param('page'):1;
        // 修改
        $redis = new Redis();
        $obj = new Http();
        // $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$page}&num=20&sort={$sort}&asc={$asc}&node=sh_a&symbol=&_s_r_a=init";
        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$page}&num=20&sort={$sort}&asc={$asc}&node=hs_a&symbol=&_s_r_a=init";
        if($redis->has('dddd1')){
            $result =json_decode($obj->get($url),true);
            if(isset($result)){
                $redis->set('dddd1',$result);
            }else{
                $result = $redis->get('dddd1');
            }
        }else{
            $result =json_decode($obj->get($url),true);
            if(isset($result)){
                $redis->set('dddd1',$result);
            }else{
                $result = $redis->get('dddd1');
            }
        }
        $this->success('请求成功', ['list'=>$result]);
    }


    //深沪
    public function getShenhuDetail(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        // dump($paramInfo);
        $sort = Rsa::check(isset($paramInfo['sort'])?$paramInfo['sort']:'');
        $page = Rsa::check($paramInfo['page']);
        $sort = $sort?$sort:'changepercent';
        //$sort = $this->request->param('sort')?$this->request->param('sort'):"changepercent";
        $asc = 0;
        //$page = $this->request->param('page')?$this->request->param('page'):1;
        // 修改
        // $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$page}&num=20&sort={$sort}&asc={$asc}&node=sh_a&symbol=&_s_r_a=init";
        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$page}&num=20&sort={$sort}&asc={$asc}&node=hs_a&symbol=&_s_r_a=init";
        
        
        $redis_key = 'shhu_'.$page.'_'.$sort.'_'.$asc;
        $result = fivePlate($this->redis,$redis_key,$url);
        //加密
        $data = Rsa::jia(['list'=>$result]);
        $this->success('请求成功', $data);
    }

    public function getCyDetail(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $sort = Rsa::check(isset($paramInfo['sort'])?$paramInfo['sort']:'');
        $page = Rsa::check($paramInfo['page']);
        $asc = Rsa::check(isset($paramInfo['asc'])?1:0);
        $sort = $sort?$sort:'changepercent';
        //$sort = $this->request->param('sort')?$this->request->param('sort'):"changepercent";
        //$asc = $this->request->param('asc')?1:0;
        //$page = $this->request->param('page')?$this->request->param('page'):1;
        // 修改
        // $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$page}&num=20&sort={$sort}&asc={$asc}&node=sh_a&symbol=&_s_r_a=init";
        // https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=40&sort=changepercent&asc=0&node=cyb&symbol=&_s_r_a=sort
        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$page}&num=20&sort={$sort}&asc={$asc}&node=cyb&symbol=&_s_r_a=sort";
        
        $redis_key = 'cyb_'.$page.'_'.$sort.'_'.$asc;
        $result = fivePlate($this->redis,$redis_key,$url);
        //加密
        $data = Rsa::jia(['list'=>$result]);
        $this->success('请求成功', $data);
    }

    public function getCyDetail_bak(){
        $sort = $this->request->param('sort')?$this->request->param('sort'):"changepercent";
        $asc = $this->request->param('asc')?1:0;
        $page = $this->request->param('page')?$this->request->param('page'):1;
        // 修改
        $redis = new Redis();
        $obj = new Http();
        // $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$page}&num=20&sort={$sort}&asc={$asc}&node=sh_a&symbol=&_s_r_a=init";
        // https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=40&sort=changepercent&asc=0&node=cyb&symbol=&_s_r_a=sort
        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$page}&num=20&sort={$sort}&asc={$asc}&node=cyb&symbol=&_s_r_a=sort";
        if($redis->has('ddddcyb')){
            $result =json_decode($obj->get($url),true);
            if(isset($result)){
                $redis->set('ddddcyb',$result);
            }else{
                $result = $redis->get('ddddcyb');
            }
        }else{
            $result =json_decode($obj->get($url),true);
            if(isset($result)){
                $redis->set('ddddcyb',$result);
            }else{
                $result = $redis->get('ddddcyb');
            }
        }
        //加密
        $data = Rsa::jia(['list'=>$result]);
        $this->success('请求成功', $data);
    }
    
    /**
     * 创业板接口 type = 3
    */
    public function getCyDetail_fei(){
        // 方式二 直接用三方接口
        $strategy = new Strategy();
        $page = $this->request->param('page')?$this->request->param('page'):1;
        // var_dump($page);
        $list = $strategy
                ->where(['type'=>3])
                ->field('title,CONCAT("s_",allcode) as allcode_s,allcode,code,type')
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
            // $list[$k]['zdfsort'] = $allcodes_arr[$v['allcode']][5];
        }
        // 用过滤
        usort($list, function ($a, $b) {
            return $a['allcodes_arr'][5] < $b['allcodes_arr'][5];
        });
        $this->success('请求成功', ['list'=>$list,'page'=>$page]);
    }


    public function getKcDetail_bak(){
        $sort = $this->request->param('sort')?$this->request->param('sort'):"changepercent";
        $asc = $this->request->param('asc')?1:0;
        $page = $this->request->param('page')?$this->request->param('page'):1;
        // 修改
        $redis = new Redis();
        $obj = new Http();
        // $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$page}&num=20&sort={$sort}&asc={$asc}&node=sh_a&symbol=&_s_r_a=init";
        // https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=40&sort=changepercent&asc=0&node=cyb&symbol=&_s_r_a=sort
        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$page}&num=20&sort={$sort}&asc={$asc}&node=kcb&symbol=&_s_r_a=sort";

        //pp($obj->get($url));exit;

        if($redis->has('ddddkcb')){
            $result =json_decode($obj->get($url),true);
            if(isset($result)){
                $redis->set('ddddkcb',$result);
            }else{
                $result = $redis->get('ddddkcb');
            }
        }else{
            $result =json_decode($obj->get($url),true);
            if(isset($result)){
                $redis->set('ddddkcb',$result);
            }else{
                $result = $redis->get('ddddkcb');
            }
        }
        //pp($result);exit;
        //加密
        $data = Rsa::jia(['list'=>$result]);
        $this->success('请求成功', $data);
    }

    public function getKcDetail(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $sort = Rsa::check(isset($paramInfo['sort'])?$paramInfo['sort']:'');
        $page = Rsa::check($paramInfo['page']);
        $asc = Rsa::check(isset($paramInfo['asc'])?1:0);
        $sort = $sort?$sort:'changepercent';
        //$sort = $this->request->param('sort')?$this->request->param('sort'):"changepercent";
        //$asc = $this->request->param('asc')?1:0;
        //$page = $this->request->param('page')?$this->request->param('page'):1;
        // $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$page}&num=20&sort={$sort}&asc={$asc}&node=sh_a&symbol=&_s_r_a=init";
        // https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=40&sort=changepercent&asc=0&node=cyb&symbol=&_s_r_a=sort
        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$page}&num=20&sort={$sort}&asc={$asc}&node=kcb&symbol=&_s_r_a=sort";
        
        $redis_key = 'kc_'.$page.'_'.$sort.'_'.$asc;
        $result = fivePlate($this->redis,$redis_key,$url);
        //加密
        $data = Rsa::jia(['list'=>$result]);
        $this->success('请求成功', $data);
    }
    /**
     * 科创接口 type = 5
    */
    public function getKcDetail_fei(){
        $strategy = new Strategy();
        $page = $this->request->param('page')?$this->request->param('page'):1; 
        $list = $strategy
                ->where(['type'=>5])
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
        // 用过滤
        usort($list, function ($a, $b) {
            return $a['allcodes_arr'][5] < $b['allcodes_arr'][5];
        });
        $this->success('请求成功', ['list'=>$list]);
    }

    public function getBjDetail_bak(){
        $sort = $this->request->param('sort')?$this->request->param('sort'):"changepercent";
        $asc = $this->request->param('asc')?1:0;
        $page = $this->request->param('page')?$this->request->param('page'):1;
        // 修改
        $redis = new Redis();
        $obj = new Http();
        // $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$page}&num=20&sort={$sort}&asc={$asc}&node=sh_a&symbol=&_s_r_a=init";
        // https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=40&sort=changepercent&asc=0&node=cyb&symbol=&_s_r_a=sort
        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$page}&num=20&sort={$sort}&asc={$asc}&node=hs_bjs&symbol=&_s_r_a=sort";
        if($redis->has('ddddbjs')){
            $result =json_decode($obj->get($url),true);
            if(isset($result)){
                $redis->set('ddddbjs',$result);
            }else{
                $result = $redis->get('ddddbjs');
            }
        }else{
            $result =json_decode($obj->get($url),true);
            if(isset($result)){
                $redis->set('ddddbjs',$result);
            }else{
                $result = $redis->get('ddddbjs');
            }
        }
        //加密
        $data = Rsa::jia(['list'=>$result]);
        $this->success('请求成功', $data);
    }

    public function getBjDetail(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $sort = Rsa::check(isset($paramInfo['sort'])?$paramInfo['sort']:'');
        $page = Rsa::check($paramInfo['page']);
        $asc = Rsa::check(isset($paramInfo['asc'])?1:0);
        $sort = $sort?$sort:'changepercent';

        //$sort = $this->request->param('sort')?$this->request->param('sort'):"changepercent";
        //$asc = $this->request->param('asc')?1:0;
        //$page = $this->request->param('page')?$this->request->param('page'):1;
        // $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$page}&num=20&sort={$sort}&asc={$asc}&node=sh_a&symbol=&_s_r_a=init";
        // https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=40&sort=changepercent&asc=0&node=cyb&symbol=&_s_r_a=sort
        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$page}&num=20&sort={$sort}&asc={$asc}&node=hs_bjs&symbol=&_s_r_a=sort";
        
        $redis_key = 'bj_'.$page.'_'.$sort.'_'.$asc;
        $result = fivePlate($this->redis,$redis_key,$url);
        //加密
        $data = Rsa::jia(['list'=>$result]);
        $this->success('请求成功', $data);
    }
    /**
     * 北交接口 type = 4
    */
    public function getBjDetail_fei(){
        $strategy = new Strategy();
        $page = $this->request->param('page')?$this->request->param('page'):1; 
        $list = $strategy
                ->where(['type'=>4])
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
        // 用过滤
        usort($list, function ($a, $b) {
            return $a['allcodes_arr'][5] < $b['allcodes_arr'][5];
        });
        $this->success('请求成功', ['list'=>$list]);
    }
    
    /**
     * 港股 数据
    */
    public function getHkDetail(){
        $sort = $this->request->param('sort')?$this->request->param('sort'):"changepercent";
        $asc = $this->request->param('asc')?1:0;
        $page = $this->request->param('page')?$this->request->param('page'):1;
        $redis = new Redis();
        $obj = new Http();
        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHKStockData?page={$page}&num=10&sort={$sort}&asc={$asc}&node=qbgg_hk&_s_r_a=init";
        if($redis->has('dddd1')){
            $result =json_decode($obj->getproxy($url),true);
            if(isset($result)){
                $redis->set('dddd1',$result);
            }else{
                $result = $redis->get('dddd1');
            }
        }else{
            $result =json_decode($obj->getproxy($url),true);
            if(isset($result)){
                $redis->set('dddd1',$result);
            }else{
                $result = $redis->get('dddd1');
            }
        }
        $this->success('请求成功', ['list'=>$result]);
    }
    
    // --自选--------------------------------------------------------------------------------------------
    /**
     * 自选接口
    */
    public function getZixuan(){
        // 判断 如果
        $fenzu_id = $this->request->param('fenzu_id');
        $zixuannew = new Zixuannew();
        if($fenzu_id){
            $list = $zixuannew->where(['fenzu_id' => $fenzu_id])
            ->field('CONCAT("s_",allcode) as allcode_s,allcode')
            ->select();
        }else{
            $list = $zixuannew
            ->field('CONCAT("s_",allcode) as allcode_s,allcode')
            ->select();
        }
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
    /**
     * 沪深创
    */
    public function getHSC(){
        $allcodes_arr = [];
        $model = new Strategy();
        $list = $model
                ->where(['allcode'=>['in',['sh000001','sz399001','sz399006']]])
                ->field('title,CONCAT("s_",allcode) as allcode_s,allcode,code')
                ->select();
        if(count($list)>0){
            $arr1 =array_unique(array_column($list,'allcode_s'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia_new($allcodes);
            // file_put_contents('../public/logs//xxxx.txt',var_export($allcodes_arr,true)."\n\n",FILE_APPEND);
        }
        // $list = collection($list)->toArray();
        foreach ($list as $k => $v){
            $list[$k]['allcodes_arr'] = $allcodes_arr[$v['allcode']];
        }
        $this->success('请求成功', ['list'=>$list]);
    }
    /**
     * 获取自选分组列表
    */
    public function getFenzu_list(){
        $fenzu = new Zxfenzu();
        $fenzu_list = $fenzu->where(['user_id' => $this->auth->id])->select();
        $this->success('请求成功', ['list'=>$fenzu_list]);
    }
    /**
     * 新增分组
    */
    public function addFenzu(){
        $name = $this->request->param('name');
        if(!$name){
            $this->error('名字不能为空');
        }
        $fenzu = new Zxfenzu([
            'name'  =>  $name,
            'user_id' =>  $this->auth->id
        ]);
        $result = $fenzu->save();
        if($result !== false){
            // 新增成功 并且返回ID
            $this->success('新增成功',['id'=>$fenzu->id]);
        }else{
            $this->error('新增失败');
        }
    }
    /**
     * 删除分组
    */
    public function deleteFenzu($id){
        $fenzu = Zxfenzu::get($id);
        if(!$fenzu){
            $this->error('未找到当前记录');
        }
        $result = $fenzu->delete();
        if($result !== false){
            $this->success('删除成功');
        }else{
            $this->error('删除失败');
        }
    }
    /**
     * 修改分组
    */
    public function UpdateFenzu(){
        $id = $this->request->param('id');
        $name = $this->request->param('name');
        $fenzu = Zxfenzu::get($id);
        if(!$fenzu){
            $this->error('未找到当前记录');
        }
        $fenzu->name = $name;
        $result = $fenzu->save();
        if($result !== false){
            $this->success('修改成功');
        }else{
            $this->error('修改失败');
        }
    }
    
    
    // ---------------------------------------------------------------------------------------------
    
    // -个股详情--------------------------------------------------------------------------------------------
    
    public function getHqinfo_xl(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $query = Rsa::check($paramInfo['q']);
        //$query = $this->request->param('q');
        if(!$query){
            $this->error('请求成功', ['info' => []]);
        }
        if(config('site.apis') == "2"){
            $info = Http::get_stock_now_info($query);
        }else{
            $info = Http::get_single_data_xl($query);
        }
        //加密
        $data = Rsa::jia(['info' => $info,'apis'=>config('site.apis'),'lastmoney'=>$this->auth->balance]);
        $this->success('请求成功', $data);
    }
    
    /**
     * 个股详情界面
    */
    public function getHqinfo_1(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $query = Rsa::check($paramInfo['q']);
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
        $str_info = $str_model->where(['user_id'=>$this->auth->id,'allcode'=>$query,'status'=>1,'buytype'=>1])->find();
        if($str_info){
            $is_cc = 1;
        }
        // 当前用户是否开启了 融券
        $this->success('请求成功', ['is_cc'=>$is_cc,'is_rq'=>$this->auth->is_rq,'is_zx'=>$is_zx,'info' => $info,'ggInfo'=>$ggInfo,'xwInfo' => $xwInfo,'name1'=>$nameInfo['name1'],'name2'=>$nameInfo['name2'],'list' => $list]);
    }

    //是否自选
    public function isZx(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $query = Rsa::check($paramInfo['q']);

        //$query = $this->request->param('q');
        if(!$query){
            $this->error('请求成功', ['info' => []]);
        }
        $zxInfo = model('app\admin\model\Zixuannew')->where(['allcode'=>$query,'user_id'=>$this->auth->id])->find();
        $is_zx = 0;
        if($zxInfo){
            $is_zx = 1;
        }

        //当前code是否持仓
        $is_cc = 0;
        $str_model = new Addstrategy();
        $str_info = $str_model->where(['user_id'=>$this->auth->id,'allcode'=>$query,'status'=>1,'buytype'=>1])->find();
        if($str_info){
            $is_cc = 1;
        }
        //加密
        $data = Rsa::jia( ['is_zx'=>$is_zx,'is_cc'=>$is_cc]);
        $this->success('请求成功',$data);
    }

    public function getHqinfo_2(){
        $query = $this->request->get('q');
        $info = Http::get_stock_now_info($query);
        $this->success('请求成功', ['info' => $info]);
    }
    /**
     * 加载个股新闻和公告和简况和研报
     * 通过接口来，接口采集到了 就存一份数据库 ，首先加载数据的
    */
    public function getGgnews(){
        // 传入allcode  根据 news_time 对比当前时间 如果当前时间没有 就再采集 并存入数据
        $allcode = $this->request->param('allcode');
        $code = $this->request->param('code');
        // 只要当前时间过了零点，就点击采集一次，然后再查询获取所有的
        $ggnews = new Ggnews();
        
        
        $url = "http://api.jiaoyibiji.com/stockinfo_list?code={$code}&page=1&token=".config('site.tokenp');
        $result = Http::get($url);
        $result_json = json_decode($result,true);
        if(!is_array($result_json)){
            $this->error('请求失败');
        }
        $data = [];
        foreach ($result_json as $k => $v){
            // 判断news_id 是否存在 如果存在掠过
            $info = $ggnews->where(['news_id' => $v['news_id']])->find();
            if($info){
                continue;
            }
            $url1 = "http://api.jiaoyibiji.com/stockinfo_content?news_id={$v['news_id']}&token=".config('site.tokenp');
            $result1 = Http::get($url1);
            $result_json1 = json_decode($result1,true);
            $data_1 = array(
                "allcode"  => $allcode,
                "code" => $code,
                "news_time" => $v['news_time'],
                "news_id" => $v['news_id'],
                "news_title" => $v['news_title'],
                "art_title" => $result_json1[0]['art_title'],
                "art_content" => $result_json1[0]['art_content'],
                "art_abstract" => $result_json1[0]['art_abstract'],
                "art_time" => $result_json1[0]['art_time']
            );
            $data[] = $data_1;
        }
        $ggnews->saveAll($data);
    }
    // ---------------------------------------------------------------------------------------------
    
    // 辅助方法 根据code 获取allcode 无 返回false
    private function getAllcode($code){
        $info = Strategy::where(['code'=>$code])->find();
        if(!$info){
            return false;
        }
        return $info;
    }
    
    public function getdf($allcode){
        $obj = new Http();
        $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia_new($allcode);
        if(!is_array($allcodes_arr)){
            return 0;
        }
        return $allcodes_arr[$allcode][3];
    }
    
    // 北向资金
    public function bxzj(){
        // 先判断 当前时间 是否有
        // var_dump(date('Ymd'));
        $time_str = date('Ymd');
        $time_sstr = date('m.d');
        // $time_str = "20220830";
        // $time_sstr = "08.30";
        $model = new Bxzj();
        // 取最新得第一条 和第二条
        $model_info_first = $model->order('id desc')->find();
        // var_dump($model_info_first);
        // var_dump($model_info_first['id']);
        $model_info_second = $model->where('id','<',$model_info_first['id'])->order('id desc')->find();
        // var_dump($model_info_second);
        $cai_model = new Cai();
        // $cai_info = $cai_model->whereTime('createtime', '-5 minute')->where(['allcode'=>$time_str,'type'=>'bxzj'])->select();
        // var_dump($cai_info);
        $cai_info = $cai_model->whereTime('createtime', '-5 minute')->where(['allcode'=>$time_str,'type'=>'bxzj'])->order('id desc')->find();
        if(!$cai_info){
            // 采集时间超过了5分钟 就执行以下采集并且保存在数据库中
            $date_info_result = $this->caijibxzj($time_str,$time_sstr);
        }
        // 查询数据库 并且返回数据
        $list = $model->order('id desc')->limit(7)->select();
        // 查询今日流出资金 
        $categories = array_column($list,'trade_sdate');
        $data1 = array_column($list,'hgt');
        $data2 = array_column($list,'sgt');
        $data3 = array_column($list,'north_money');
        $this->success('请求成功', ['categories' => $categories,'data1'=>$data1,'data2'=>$data2,'data3'=>$data3,'current'=>$model_info_first['north_money'],'diffmoney'=>round(bcsub($model_info_first['north_money'],$model_info_second['north_money']),2)]);
    }
    
    // 采集北向资金
    public function caijibxzj($date,$date_str){
        // $date = date('Ymd');
        // $date_str = date('m.d');
        // $date = "20220823";
        $result = false;
        $url = "http://api.jiaoyibiji.com/moneyflow_hsgt?date={$date}&token=".config('site.tokenp');
        $result = Http::get($url);
        $result_json = json_decode($result,true);
        if(empty($result_json)){
            return false;
        }
        // 并且新增或者修改
        $cai_model = new Cai();
        $model = new Bxzj();
        $cai_model['allcode'] = $date;
        $cai_model['type'] = "bxzj";
        $cai_model['createtime'] = time();
        $cai_model->save();
        
        $model_info = $model->where(['trade_date'=>$date])->find();
        if($model_info){
            $model_info["trade_date"] = $result_json[0]['trade_date'];
            $model_info["ggt_ss"] = round($result_json[0]['ggt_ss']/100,2);
            $model_info["ggt_sz"] = round($result_json[0]['ggt_sz']/100,2);
            $model_info["hgt"] = round($result_json[0]['hgt']/100,2);
            $model_info["sgt"] = round($result_json[0]['sgt']/100,2);
            $model_info["north_money"] = round($result_json[0]['north_money']/100,2);
            $model_info["south_money"] = round($result_json[0]['south_money']/100,2);
            $result = $model_info->save();
        }else{
            $model["trade_date"] = $result_json[0]['trade_date'];
            $model["ggt_ss"] = round($result_json[0]['ggt_ss']/100,2);
            $model["ggt_sz"] = round($result_json[0]['ggt_sz']/100,2);
            $model["hgt"] = round($result_json[0]['hgt']/100,2);
            $model["sgt"] = round($result_json[0]['sgt']/100,2);
            $model["north_money"] = round($result_json[0]['north_money']/100,2);
            $model["south_money"] = round($result_json[0]['south_money']/100,2);
            $model["dw"] = "100000000";
            $model["trade_sdate"] = $date_str;
            $result = $model->save();
        }
        return true;
    }
    
    // 获取国内经济新闻
    public function getGuoneinews(){
        //解密
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        // 方式二 直接用三方接口
        $newsss = new Newsss();
        /*$size = $this->request->param('size')?$this->request->param('size'):20;
        $page = $this->request->param('page')?$this->request->param('page'):1;
        $type = $this->request->param('type')?$this->request->param('type'):1;*/
        $size = Rsa::check($paramInfo['size']);
        $page = Rsa::check($paramInfo['page']);
        $type = Rsa::check($paramInfo['type']);
        $list = $newsss
                ->where(['type'=>$type])
                ->order('createtime desc')
                ->page($page,$size)
                ->select();
        $data = ['list'=>$list,'page'=>$page];
        //加密 新闻内容 无需加解密
        // $data = Rsa::jia($data);
        $this->success('请求成功', $data);
    }
    // 获取新闻详情
    public function getNewsssDetail(){
        $newsss = new Newsss();
        //解密
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $id = Rsa::check($paramInfo['news_id']);
        //$id = $this->request->param('news_id')?$this->request->param('news_id'):0;
        $newsss_info = $newsss->where(['news_id'=>$id])->find();
        //加密
        $data = Rsa::jia($newsss_info);
        $this->success('请求成功', $data);
    }
}
