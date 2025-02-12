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
use fast\Tool;
use think\Db;
use app\admin\model\shengou\Shengou;
use app\admin\model\news\Newcatalog;
use app\admin\model\news\Newscontent;
// 引用redis
use think\cache\driver\Redis;
/**
 * 首页接口
 */
class Index extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    public function tessss(){
        $page_url = "http://q.10jqka.com.cn/api.php?t=indexflash&";
        // $proxy = "183.247.215.218";
        $result = Http::t1($page_url);
        var_dump($result);
        
        
        // $page_url = "http://q.10jqka.com.cn/api.php?t=indexflash&";
        // $proxy = "202.102.86.228:8080";
        // $result = Http::tttttt($page_url,$proxy);
        // var_dump($result);
        // curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        // curl_setopt($ch, CURLOPT_PROXY, $proxy);
        
        
        
        // $page_url = "http://q.10jqka.com.cn/api.php?t=indexflash&";
        // $proxy = "183.247.215.218";
        // $arr = array(
        //         "CURLOPT_PROXYPORT"=>80,
        //         "CURLOPT_PROXY"=>$proxy
        //     );
        
        // $result = Http::get($page_url,[],$arr);
        // var_dump($result);
    }
    public function getHqinfo_1(){
        $query = $this->request->get('q');
        $info = Http::get_stock_now_info($query);
        $list = array(
            "c_q"=>round($info[3]-$info[4],2),
            "c_b"=>round(($info[3]-$info[4])/$info[4]*100,2),
            "cur_p"=>round($info[3],2),
            "today_p"=>round($info[5],2),
            "yes_p"=>round($info[4],2),
            "zg"=>round($info[33],2),
            "zd"=>round($info[34],2)
        );
        $this->success('请求成功', ['info' => $info,'list' => $list]);
    }
    public function getHqinfo(){
        $query = $this->request->get('query');
        $info = Http::get_stock_now_info($query);
        $list = array(
            "c_q"=>round($info[3]-$info[4],2),
            "c_b"=>round(($info[3]-$info[4])/$info[4]*100,2),
            "cur_p"=>round($info[3],2),
            "today_p"=>round($info[5],2),
            "yes_p"=>round($info[4],2),
            "zg"=>round($info[33],2),
            "zd"=>round($info[34],2)
        );
        $this->success('请求成功', ['list' => $list,'currentPrice'=>round($info[3],2)]);
    }
    //获取三大实时行情 
    public function sandahangqing(){
        $model1 = new Exponent();
        $model2 = new Exponent();
        $model3 = new Exponent();
        
        $model1_info = $model1->get('2');
        $model2_info = $model2->get('3');
        $model3_info = $model3->get('4');
        $list = array();
        $list_shangzheng = array(
            'title'=>$model1_info['name'],
            'nums'=>$model1_info['one'],
            'num'=>$model1_info['two'],
            'rate'=>$model1_info['three'],
        );
        $list_shenzheng = array(
            'title'=>$model2_info['name'],
            'nums'=>$model2_info['one'],
            'num'=>$model2_info['two'],
            'rate'=>$model2_info['three'],
        );
        $list_chuangye = array(
            'title'=>$model3_info['name'],
            'nums'=>$model3_info['one'],
            'num'=>$model3_info['two'],
            'rate'=>$model3_info['three'],
        );
        // $shangzheng = Http::get_stock_now_info("sh000001");
        // $shenzheng = Http::get_stock_now_info("sz399001");
        // $chuangye = Http::get_stock_now_info("sz399006");
        // // var_dump($shangzheng);
        // $shangzheng_title = $shangzheng[1];//mb_convert_encoding($shangzheng[1],'UTF-8','GBK');
        // $shangzheng_nums = round($shangzheng[3],2);
        // $shangzheng_num = round(round($shangzheng[3],2)-round($shangzheng[4],2),2);
        // $shangzheng_rate = (bcdiv($shangzheng_num,round($shangzheng[4],2),4)*100)."%";
        // $list_shangzheng = array(
        //         'title'=>$shangzheng_title,
        //         'nums'=>$shangzheng_nums,
        //         'num'=>$shangzheng_num,
        //         'rate'=>$shangzheng_rate,
        // );
        
        // $shenzheng_title = $shenzheng[1];//mb_convert_encoding($shenzheng[1],'UTF-8','GBK');
        // $shenzheng_nums = round($shenzheng[3],2);
        // $shenzheng_num = round(round($shenzheng[3],2)-round($shenzheng[4],2),2);
        // $shenzheng_rate = (bcdiv(round($shenzheng[3],2)-round($shenzheng[4],2),round($shenzheng[4],2),4)*100)."%";
        
        // $list_shenzheng = array(
        //         'title'=>$shenzheng_title,
        //         'nums'=>$shenzheng_nums,
        //         'num'=>$shenzheng_num,
        //         'rate'=>$shenzheng_rate,
        // );
        // $chuangye_title =  $chuangye[1];//mb_convert_encoding($chuangye[1],'UTF-8','GBK');
        // $chuangye_nums = round($chuangye[3],2);
        // $chuangye_num = round(round($chuangye[3],2)-round($chuangye[4],2),2);
        // $chuangye_rate = (bcdiv(round($chuangye[3],2)-round($chuangye[4],2),round($chuangye[4],2),4)*100)."%";
        
        
        // $list_chuangye = array(
        //         'title'=>$chuangye_title,
        //         'nums'=>$chuangye_nums,
        //         'num'=>$chuangye_num,
        //         'rate'=>$chuangye_rate,
        // );  
        array_push($list,$list_shangzheng,$list_shenzheng,$list_chuangye);
        $this->success('请求成功', ['list' => $list]);
    }
    /**
     * 首页
     *
     */
    public function index()
    {
        $this->success('请求成功');
    }
    public function getapplogo(){
        $this->success('请求成功', ['src' => config('site.applogo')]);
    }
    public function banner(){
        $model = new Banner();
        $list = $model->field('id,title,image,link')->where(['deletetime'=>Null])->select();
        $this->success('请求成功', ['list' => $list]);
    }

    public function nav(){
        $model = new Nav();
        // $list = $model->field('id,title,image,link,type,type1')->where(['deletetime'=>Null])->select();
        $list = $model->field('id,title,image,link,type,type1')->where(['status'=>'1'])->select();
        $this->success('请求成功', ['list' => $list]);
    }

    public function adv(){
        $model = new Adv();
        $list = $model->field('id,title,image,link,describe')->where(['deletetime'=>Null])->select();
        $this->success('请求成功', ['list' => $list]);
    }

    public function strategyOld(){
        $model = new Strategy();
        $where['deletetime'] = Null;
        $list = $model
            ->where($where)
            ->field('id,title,code,allcode,prefixcode,conditioncode,stockNum,buyPrice,currentPrice,profitPrice,losePrice,yield')
            ->order('buyPrice desc')
            ->paginate(5)
            ->each(function($data, $key){
                $buy = model('app\admin\model\strategy\Buy')
                    ->where(['strategy_id'=> $data->id])
                    ->field('user_id,createtime')
                    ->order('createtime desc')
                    ->find();
                $userInfo = model('app\admin\model\User')
                    ->where(['id'=> $buy['user_id']])
                    ->field('username,avatar')
                    ->find();
                $data['userName'] = substr_replace($userInfo['username'], '********', 3, 8);
                $data['avatar'] = $userInfo['avatar'];
                $data['time'] = tranTime($buy['createtime']);
                $data['yield'] = $data['yield']?$data['yield'].'%':'0%';

                //重新赋值


                return $data;
            });
        $this->success('请求成功', ['list'=>$list]);
    }

    public function strategy(){
        $model = new Addstrategy();
        $where['deletetime'] = Null;
        $where['status'] = 3;
        $list = $model
            ->where($where)
            ->order('buyprice desc')
            ->group('allcode')
            ->paginate(5)
            ->each(function($data, $key){

                $userInfo = model('app\admin\model\User')
                    ->where(['id'=> $data['user_id']])
                    ->field('username,nickname,avatar')
                    ->find();
                $data['userName'] = substr_replace($userInfo['username'], '********', 3, 8);//$userInfo['nickname'];
                $data['avatar'] = $userInfo['avatar'];
                $data['time'] = tranTime($data['createtime']);

//                $guNowInfo = getOneGu($data['allcode']);

                $data['stockNum'] = $data['number'];
                $data['currentPrice'] = $data['sellprice'];
                $data['yield'] = $data['gmrate']?$data['gmrate'].'%':'0%';

                $data['buyPrice'] = $data['buyprice'];
                $data['profitPrice'] = $data['profitPrice'];
                $data['losePrice'] = $data['losePrice'];
                //重新赋值


                return $data;
            });
        $this->success('请求成功', ['list'=>$list]);
    }



    public function intelligent(){
        $model = new \app\admin\model\User();
        $list = $model
            ->field('id,username,avatar,all_earnings_rate,transaction_num')
            ->order('transaction_num desc')
            ->paginate(5)
            ->each(function($data, $key){
                $data['username'] = substr_replace($data['username'], '****', 3, 4);
                $data['all_earnings_rate'] = $data['all_earnings_rate']?$data['all_earnings_rate'].'%':'0%';
                $att = new Attention();
                $where['deletetime'] = Null;
                $where['user_id'] = $this->auth->id;
                $where['expert_user_id'] = $data['id'];
                $subscription = $att->where($where)->find();
                if($subscription){
                    $data['subscription'] = 1;
                }else {
                    $data['subscription'] = 0;
                }
                return $data;
            });
        $this->success('请求成功', ['list'=>$list]);
    }

    public function set(){
        $model = new Agreement();
        $list = $model->where('id',2)->find();
        $this->success('请求成功', ['list'=>$list]);
    }

    public function problemLst(){
        $model = new Problem();
        $where['deletetime'] = Null;
        $list = $model
            ->field('id,title,createtime')
            ->where($where)
            ->paginate(5)
            ->each(function($data, $key){
                $data['createtime'] = date('Y-m-d',$data['createtime']);
                return $data;
            });
        $this->success('请求成功', ['list'=>$list]);
    }

    public function problemDetail(){
        $model = new Problem();
        $detail = $model
            ->field('content')
            ->where('id',$this->request->get('id'))
            ->find();
        $this->success('请求成功', ['detail'=>$detail['content']]);
    }

    public function market(){
        //上证指数
        $exponentInfo = model("app\admin\model\strategy\Exponent")->field('id,name,one,two,three')->where(['deletetime'=>Null])->select();
        //热门行业
        $hotInfo = [];
        $strategyInfo = model("app\admin\model\strategy\Strategy")->where(['deletetime'=>Null])->select();
        if($strategyInfo){
            $i = 0;
            foreach($strategyInfo as $k=>$v){
                $cateInfo = model("app\admin\model\strategy\Classify")->where(['id'=>$v['classify_id']])->find();
                if($cateInfo['is_hot']){
                    $hotInfo[] = $v;
                    $i ++ ;
                    if($i>6){
                        break;
                    }
                }
            }
        }
        //涨
        $where1['cai_changepercent'] = ['>',0];
        $where1['deletetime'] = Null;
        $riseInfo = model("app\admin\model\strategy\Strategy")->where($where1)->limit(10)->select();
        $where2['cai_changepercent'] = ['<',0];
        $where2['deletetime'] = Null;
        $fallInfo = model("app\admin\model\strategy\Strategy")->where($where2)->limit(10)->select();

        $this->success('请求成功', ['exponentInfo'=>$exponentInfo,'hotInfo'=>$hotInfo,'riseInfo'=>$riseInfo,'fallInfo'=>$fallInfo]);

    }
    
    

    public function marketDetail(){
        $id = $this->request->get('id');
        $model = new Strategy();
        $detail = $model
            ->where('allcode',$id)
            ->field('title,code,allcode,prefixcode,conditioncode,mostHigh,mostLow,stockNum,currentPrice,buyPrice,stopProfitPrice,stopLosePrice,yield,cityProfit,cityClean,changeHands,minValue')
            ->find();
        if($this->auth->id){
            $userJoinModel = new Optional();
            $joinInfo = $userJoinModel->where(['user_id'=>$this->auth->id,'deletetime'=>Null,'strategy_id'=>$id])->find();
            if($joinInfo['is_attention']){
                $detail['is_join'] = 1 ;
            }else{
                $detail['is_join'] = 0 ;
            }
        }else{
            $detail['is_join'] = 0 ;
        }
        $this->success('请求成功', ['detail'=>$detail]);
    }

    public function strategyDetail(){
        $id = $this->request->get('id');
        $model = new Strategy();
        $detail = $model
            ->where(['code'=>$id])
            ->field('title,code,creditMoney,creditMoneys,multiplying,canBuyDesc,autoDesc,allMoneyDesc,allcode')
            ->find();
        if($detail) {
            if ($this->auth->id) {
                $userJoinModel = new Optional();
                $joinInfo = $userJoinModel->where(['user_id' => $this->auth->id, 'deletetime' => Null, 'strategy_id' => $id])->find();
                if ($joinInfo['is_attention']) {
                    $detail['is_join'] = 1;
                } else {
                    $detail['is_join'] = 0;
                }
            } else {
                $detail['is_join'] = 0;
            }
            if ($detail['creditMoneys']) {
                $detail['creditMoneysArr'] = explode('|', $detail['creditMoneys']);
            } else {
                $detail['creditMoneysArr'] = [];
            }
            if ($detail['multiplying']) {
                $detail['multiplyingArr'] = explode('|', $detail['multiplying']);
            } else {
                $detail['multiplyingArr'] = [];
            }
            //所有sz30都是创业  sh68都是科创 bj北交 剩下的 就是A股
            if(str_contains($detail['allcode'],'sh68')){
                $detail['zhang_per'] = config('site.kechuang_zhang');
                $detail['die_per'] = config('site.kechuang_die');
            }else if(str_contains($detail['allcode'],'sh30')){
                $detail['zhang_per'] = config('site.chuangye_zhang');
                $detail['die_per'] = config('site.chuangye_die');
            }else if(str_contains($detail['allcode'],'bj')){
                $detail['zhang_per'] = config('site.bj_zhang');
                $detail['die_per'] = config('site.bj_die');
            }else{
                $detail['zhang_per'] = config('site.Agu_zhang');
                $detail['die_per'] = config('site.Agu_die');
            }
            //获取买入手续费
            $detail['mai_fee'] = config('site.mai_fee');
            $detail['zwmaxshou'] = config('site.zwmaxshou');
        }else{
            $detail = [];
        }
        $this->success('请求成功', ['detail'=>$detail]);
    }

    public function searchStrategy(){
        $model = new Strategy();
        $title = $this->request->get('title');
        $where['title'] = ['like','%'.$title.'%'];
        $list = $model->where($where)->field('title,allcode')->select();
        if($list){
            foreach($list as $k=>$v){
                $list[$k]['jianchen'] = strtolower(pinyin_long($v['title']));
            }
        }
        $this->success('请求成功', ['list'=>$list]);
    }


    public function getOneGu(){
        $result = getOneGu('sh601009');
        pp($result);exit;
    }
    public function getalldata(){
        $page = 1;
        $allcodes_arr = [];
        // $type = 1;//$this->request->get('type');
        $type = $this->request->param('type');
        $tab = $this->request->param('tab');
        if(!$type){
            $type = 1;
        }
        if(!$tab){
            $tab = 1;
        }
        $redis = new Redis();
        $obj = new Http();
        if($type == 1){
            // 涨幅榜
            if($tab==1 || $tab==3){
                if(!betweentime("09:00-16:00")){
                    // $this->error('停盘不采集');
                    if($redis->has('dddd1')){
                        $result = $redis->get('dddd1');
                        if(!$result){
                            $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=0&node=sh_a&symbol=&_s_r_a=init";
                           $result =json_decode($obj->getproxy($url),true);
                           if(isset($result)){
                               $redis->set('dddd1',$result);
                           }else{
                               $result = $redis->get('dddd1');
                           }
                        }
                    }else{
                        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=0&node=sh_a&symbol=&_s_r_a=init";
                        $result =json_decode($obj->getproxy($url),true);
                        // $this->error($result);
                        if(isset($result)){
                            $redis->set('dddd1',$result);
                        }else{
                            $result = $redis->get('dddd1');
                        }
                    }
                }else{
                    $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=0&node=sh_a&symbol=&_s_r_a=init";
                    $result =json_decode($obj->getproxy($url),true);
                    if(isset($result)){
                        $redis->set('dddd1',$result);
                    }else{
                        $result = $redis->get('dddd1');
                    }
                }
            }
            // 跌幅榜
            if($tab==2 || $tab==4){
                if(!betweentime("09:00-16:00")){
                    if($redis->has('dddd2')){
                        $result = $redis->get('dddd2');
                        if(!$result){
                            $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=1&node=sh_a&symbol=&_s_r_a=init";
                            $result =json_decode($obj->getproxy($url),true);
                            if(isset($result)){
                                $redis->set('dddd2',$result);
                            }else{
                                $result = $redis->get('dddd2');
                            }
                        }
                    }else{
                        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=1&node=sh_a&symbol=&_s_r_a=init";
                            $result =json_decode($obj->getproxy($url),true);
                            if(isset($result)){
                                $redis->set('dddd2',$result);
                            }else{
                                $result = $redis->get('dddd2');
                            }
                    }
                }else{
                    $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=1&node=sh_a&symbol=&_s_r_a=init";
                    $result =json_decode($obj->getproxy($url),true);
                    if(isset($result)){
                        $redis->set('dddd2',$result);
                    }else{
                        $result = $redis->get('dddd2');
                    }
                }
            }
        }else if($type == 2){
            // 涨幅榜
            if($tab==1 || $tab==3){
                if(!betweentime("09:00-16:00")){
                    if($redis->has('dddd3')){
                        $result = $redis->get('dddd3');
                        if(!$result){
                            $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=0&node=sz_a&symbol=&_s_r_a=init";
                            $result =json_decode($obj->getproxy($url),true);
                            if(isset($result)){
                                $redis->set('dddd3',$result);
                            }else{
                                $result = $redis->get('dddd3');
                            }
                        }
                    }else{
                        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=0&node=sz_a&symbol=&_s_r_a=init";
                        $result =json_decode($obj->getproxy($url),true);
                        if(isset($result)){
                            $redis->set('dddd3',$result);
                        }else{
                            $result = $redis->get('dddd3');
                        }
                    }
                }else{
                    $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=0&node=sz_a&symbol=&_s_r_a=init";
                    $result =json_decode($obj->getproxy($url),true);
                    if(isset($result)){
                        $redis->set('dddd3',$result);
                    }else{
                        $result = $redis->get('dddd3');
                    }
                }
                
            }
            // 跌幅榜
            if($tab==2 || $tab==4){
                if(!betweentime("09:00-16:00")){
                    if($redis->has('dddd4')){
                        $result = $redis->get('dddd4');
                        if(!$result){
                            $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=1&node=sz_a&symbol=&_s_r_a=init";
                            $result =json_decode($obj->getproxy($url),true);
                            if(isset($result)){
                                $redis->set('dddd4',$result);
                            }else{
                                $result = $redis->get('dddd4');
                            }
                        }
                    }else{
                        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=1&node=sz_a&symbol=&_s_r_a=init";
                        $result =json_decode($obj->getproxy($url),true);
                        if(isset($result)){
                            $redis->set('dddd4',$result);
                        }else{
                            $result = $redis->get('dddd4');
                        }
                    }
                }else{
                    $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=1&node=sz_a&symbol=&_s_r_a=init";
                    $result =json_decode($obj->getproxy($url),true);
                    if(isset($result)){
                        $redis->set('dddd4',$result);
                    }else{
                        $result = $redis->get('dddd4');
                    }
                }
            }
        }else if($type == 3){
            // 涨幅榜
            if($tab==1 || $tab==3){
                if(!betweentime("09:00-16:00")){
                    if($redis->has('dddd5')){
                        $result = $redis->get('dddd5');
                        if(!$result){
                            $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=0&node=cyb&symbol=&_s_r_a=sort";
                            $result =json_decode($obj->getproxy($url),true);
                            // var_dump(11111111111111111);
                            // var_dump($result);
                            if(isset($result)){
                                $redis->set('dddd5',$result);
                            }else{
                                $result = $redis->get('dddd5');
                            }
                        }
                    }else{
                        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=0&node=cyb&symbol=&_s_r_a=sort";
                        $result =json_decode($obj->getproxy($url),true);
                        if(isset($result)){
                            $redis->set('dddd5',$result);
                        }else{
                            $result = $redis->get('dddd5');
                        }
                    }
                    
                }else{
                    $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=0&node=cyb&symbol=&_s_r_a=sort";
                    $result =json_decode($obj->getproxy($url),true);
                    if(isset($result)){
                        $redis->set('dddd5',$result);
                    }else{
                        $result = $redis->get('dddd5');
                    }
                }
            }
            // 跌幅榜
            if($tab==2 || $tab==4){
                if(!betweentime("09:00-16:00")){
                    if($redis->has('dddd6')){
                        $result = $redis->get('dddd6');
                        if(!$result){
                            $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=1&node=cyb&symbol=&_s_r_a=sort";
                            $result =json_decode($obj->getproxy($url),true);
                            if(isset($result)){
                                $redis->set('dddd6',$result);
                            }else{
                                $result = $redis->get('dddd6');
                            }
                        }
                    }else{
                        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=1&node=cyb&symbol=&_s_r_a=sort";
                        $result =json_decode($obj->getproxy($url),true);
                        // file_put_contents('../public/logs//yyyy.txt',var_export($result,true)."\n\n",FILE_APPEND);
                        if(isset($result)){
                            $redis->set('dddd6',$result);
                        }else{
                            $result = $redis->get('dddd6');
                        }
                    }
                }else{
                    $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=1&node=cyb&symbol=&_s_r_a=sort";
                    $result =json_decode($obj->getproxy($url),true);
                    // file_put_contents('../public/logs//yyyy.txt',var_export($result,true)."\n\n",FILE_APPEND);
                    if(isset($result)){
                        $redis->set('dddd6',$result);
                    }else{
                        $result = $redis->get('dddd6');
                    }
                }
            }
        }else if($type == 4){
            // 涨幅榜
            if($tab==1 || $tab==3){
                if(!betweentime("09:00-16:00")){
                    if($redis->has('dddd7')){
                        $result = $redis->get('dddd7');
                        if(!$result){
                            $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=0&node=hs_bjs&symbol=&_s_r_a=sort";
                            $result =json_decode($obj->getproxy($url),true);
                            if(isset($result)){
                                $redis->set('dddd7',$result);
                            }else{
                                $result = $redis->get('dddd7');
                            }
                        }
                    }else{
                        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=0&node=hs_bjs&symbol=&_s_r_a=sort";
                        $result =json_decode($obj->getproxy($url),true);
                        // file_put_contents('../public/logs//yyyy.txt',var_export($result,true)."\n\n",FILE_APPEND);
                        if(isset($result)){
                            $redis->set('dddd7',$result);
                        }else{
                            $result = $redis->get('dddd7');
                        }
                    }
                }else{
                    $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=0&node=hs_bjs&symbol=&_s_r_a=sort";
                    $result =json_decode($obj->getproxy($url),true);
                    // file_put_contents('../public/logs//yyyy.txt',var_export($result,true)."\n\n",FILE_APPEND);
                    if(isset($result)){
                        $redis->set('dddd7',$result);
                    }else{
                        $result = $redis->get('dddd7');
                    }
                }
            }
            // 跌幅榜
            if($tab==2 || $tab==4){
                if(!betweentime("09:00-16:00")){
                    if($redis->has('dddd8')){
                        $result = $redis->get('dddd8');
                        if(!$result){
                            $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=1&node=hs_bjs&symbol=&_s_r_a=sort";
                            $result =json_decode($obj->getproxy($url),true);
                            if(isset($result)){
                                $redis->set('dddd8',$result);
                            }else{
                                $result = $redis->get('dddd8');
                            }
                        }
                    }else{
                        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=1&node=hs_bjs&symbol=&_s_r_a=sort";
                        $result =json_decode($obj->getproxy($url),true);
                        // file_put_contents('../public/logs//yyyy.txt',var_export($result,true)."\n\n",FILE_APPEND);
                        if(isset($result)){
                            $redis->set('dddd8',$result);
                        }else{
                            $result = $redis->get('dddd8');
                        }
                    }
                }else{
                    $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=10&sort=changepercent&asc=1&node=hs_bjs&symbol=&_s_r_a=sort";
                    $result =json_decode($obj->getproxy($url),true);
                    // file_put_contents('../public/logs//yyyy.txt',var_export($result,true)."\n\n",FILE_APPEND);
                    if(isset($result)){
                        $redis->set('dddd8',$result);
                    }else{
                        $result = $redis->get('dddd8');
                    }
                }
            }
        }else if($type == 5){
            // $result = [];
            if($tab==1 || $tab==3){
                // $map['ad.cai_changepercent'] = ['>',0];
            }
            if($tab==2 || $tab==4){
                // $map['ad.cai_changepercent'] = ['<',0];
            }
            $orderstr = "ad.cai_changepercent desc";
            $list = Db::name('zixuan')->alias("a") //取一个别名
                ->join('strategy ad','a.allcode = ad.allcode')
                // ->where(['a.user_id'=>$this->auth->id])
                ->where(['a.user_id'=>5022])
                // ->where($map)
                ->order($orderstr)
                ->field('ad.title as name,ad.allcode as symbol,ad.allcode as allcode_s,ad.code,ad.cai_trade as trade,ad.cai_changepercent as changepercent,ad.cai_volume as volume')
                ->select();
            $list = collection($list)->toArray();
            if($tab==1 || $tab==3){
                $result = Tool::list_sort_by($list,"cai_changepercent","desc");
            }else if($tab==2 || $tab==4){
                $result = Tool::list_sort_by($list,"cai_changepercent","asc");
            }
            
            if(count($list)>0){
                $arr1 =array_unique(array_column($list,'allcode_s'));
                $allcodes = implode(',',$arr1);
                $obj = new Http();
                $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia_new($allcodes);
            }
            // var_dump($allcodes_arr);
        }
        if($type == 5){
            foreach ($result as $k => $v){
                $result[$k]['allcode'] = $v['symbol'];
                $result[$k]['title'] = $v['name'];
                $result[$k]['cai_trade'] = $allcodes_arr[$v['symbol']][3];
                $result[$k]['cai_changepercent'] = $v['changepercent'];
                $result[$k]['cai_volume'] = $v['volume'];
                $result[$k]['trade'] = $allcodes_arr[$v['symbol']][3];
                $result[$k]['changepercent'] = $allcodes_arr[$v['symbol']][32];
                $result[$k]['volume'] = $allcodes_arr[$v['symbol']][36];
            }
        }else{
            foreach ($result as $k => $v){
                $result[$k]['allcode'] = $v['symbol'];
                $result[$k]['title'] = $v['name'];
                $result[$k]['cai_trade'] = $v['trade'];
                $result[$k]['cai_changepercent'] = $v['changepercent'];
                $result[$k]['cai_volume'] = $v['volume'];
            }
        }
        
        // $type =1 沪市 2深市 3创业 4北交 5自选
        // $tab =1 涨幅榜 2跌幅榜 3 5分钟涨幅 4 5分钟跌幅
        // $list = [];
        $this->success('请求成功', ['list'=>$result]);
    }
    public function getalldata_fei(){
        $page = 1;
        // $type = 1;//$this->request->get('type');
        $type = $this->request->get('type');
        $tab = $this->request->get('tab');
        if(!$type){
            $type = 1;
        }
        if(!$tab){
            $tab = 1;
        }
        $map = [];
        $orderstr = "cai_changepercent desc";
        if($tab==1 || $tab==3){
            $map['cai_changepercent'] = ['>',0];
        }
        if($tab==2 || $tab==4){
            $map['cai_changepercent'] = ['<',0];
        }
        $model = new Strategy(); 
        $list = $model
                ->where(['type'=>$type])
                ->where($map)
                ->order($orderstr)
                ->orderRaw('rand()')
                ->field('title,allcode,code,cai_trade,cai_changepercent,cai_volume')
                ->page($page,15)
                ->select();
        if($type == 5){
            if($tab==1 || $tab==3){
                $map['ad.cai_changepercent'] = ['>',0];
            }
            if($tab==2 || $tab==4){
                $map['ad.cai_changepercent'] = ['<',0];
            }
            $orderstr = "ad.cai_changepercent desc";
            $list = Db::name('zixuan')->alias("a") //取一个别名
                ->join('strategy ad','a.allcode = ad.allcode')
                ->where(['a.user_id'=>$this->auth->id])
                ->where($map)
                ->order($orderstr)
                ->field('ad.title,ad.allcode,ad.code,ad.cai_trade,ad.cai_changepercent,ad.cai_volume')
                ->select();
        }
        $list = collection($list)->toArray();
        if($tab==1 || $tab==3){
            $list = Tool::list_sort_by($list,"cai_changepercent","desc");
        }else if($tab==2 || $tab==4){
            $list = Tool::list_sort_by($list,"cai_changepercent","asc");
        }
        foreach ($list as $k => $v){
            $list[$k]['symbol'] = $v['allcode'];
            $list[$k]['name'] = $v['title'];
            $list[$k]['trade'] = $v['cai_trade'];
            $list[$k]['changepercent'] = $v['cai_changepercent'];
            $list[$k]['volume'] = $v['cai_volume'];
        }
        $this->success('请求成功', ['list'=>$list]);
    }
    public function getalldata1(){
        $page = 1;
        // $type = 1;//$this->request->get('type');
        $type = $this->request->get('type');
        $tab = $this->request->get('tab');
        if(!$type){
            $type = 1;
        }
        if(!$tab){
            $tab = 1;
        }
        $map = [];
        $orderstr = "cai_changepercent desc";
        if($tab==1 || $tab==3){
            $map['cai_changepercent'] = ['>',0];
        }
        if($tab==2 || $tab==4){
            $map['cai_changepercent'] = ['<',0];
        }
        $model = new Strategy(); 
        $list = $model
                ->where(['type'=>$type])
                ->where($map)
                ->order($orderstr)
                ->orderRaw('rand()')
                ->field('title,allcode,code,cai_trade,cai_changepercent,cai_volume')
                ->page($page,15)
                ->select();
        if($type == 5){
            if($tab==1 || $tab==3){
                $map['ad.cai_changepercent'] = ['>',0];
            }
            if($tab==2 || $tab==4){
                $map['ad.cai_changepercent'] = ['<',0];
            }
            $orderstr = "ad.cai_changepercent desc";
            $list = Db::name('zixuan')->alias("a") //取一个别名
                ->join('strategy ad','a.allcode = ad.allcode')
                ->where(['a.user_id'=>$this->auth->id])
                ->where($map)
                ->order($orderstr)
                ->field('ad.title,ad.allcode,ad.code,ad.cai_trade,ad.cai_changepercent,ad.cai_volume')
                ->select();
        }
        foreach ($list as $k => $v){
            $obj = new Http();
            $nowGu1 = $obj->get_stock_now_info($v['allcode']);
            if($nowGu1){
                $list[$k]['cai_trade'] = $nowGu1[3];
                //$list[$k]['cai_volume'] = $nowGu1[8];//新浪接口
                $list[$k]['cai_volume'] = $nowGu1[6];//腾讯接口
                if($tab==1 || $tab==3){
                    if(round((bcdiv(($nowGu1[3]-$nowGu1[4]),$nowGu1[4],4)*100),4)>0){
                        $list[$k]['cai_changepercent'] = round((bcdiv(($nowGu1[3]-$nowGu1[4]),$nowGu1[4],4)*100),4);
                    }
                }else if($tab==2 || $tab==4){
                    if(round((bcdiv(($nowGu1[3]-$nowGu1[4]),$nowGu1[4],4)*100),4)<0){
                        $list[$k]['cai_changepercent'] = round((bcdiv(($nowGu1[3]-$nowGu1[4]),$nowGu1[4],4)*100),4);
                    }
                }
            }
        }
        $list = collection($list)->toArray();
        if($tab==1 || $tab==3){
            $list = Tool::list_sort_by($list,"cai_changepercent","desc");
        }else if($tab==2 || $tab==4){
            $list = Tool::list_sort_by($list,"cai_changepercent","asc");
        }
        $this->success('请求成功', ['list'=>$list]);
    }
    public function sandahangqing_new(){
        // $shangzheng = Http::get_stock_now_info("sh000001");
        // $shenzheng = Http::get_stock_now_info("sz399001");
        // $chuangye = Http::get_stock_now_info("sz399006");
        $allcodes_arr = [];
        $model = new Strategy();
        $list = $model
                ->where(['allcode'=>['in',['sh000001','sz399001','sz399006','sz399333','sz399606']]])
                // ->where($map)
                // ->order('orderflag asc')
                // ->orderRaw('rand()')
                ->field('title,CONCAT("s_",allcode) as allcode_s,allcode,code')
                // ->limit(9)
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
    // 查询所有的指数
    public function getalldata_zhishu(){
        $allcodes_arr = [];
        $model = new Strategy(); 
        $list = $model
                ->where(['type1'=>1,'vipqcstatus'=>0])
                // ->where($map)
                ->order('orderflag asc')
                // ->orderRaw('rand()')
                ->field('title,CONCAT("s_",allcode) as allcode_s,allcode,code')
                ->limit(9)
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
       
        // $this->success('请求成功', ['list'=>$list,'allcodes_arr'=>$allcodes_arr,'one'=>$allcodes_arr['sh000001'][3]]);
    }
    
    public function getalldata_zhishuall(){
        $allcodes_arr = [];
        $model = new Strategy(); 
        $list = $model
                ->where(['type1'=>1,'vipqcstatus'=>0])
                // ->where($map)
                ->order('orderflag asc')
                // ->orderRaw('rand()')
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
       
        // $this->success('请求成功', ['list'=>$list,'allcodes_arr'=>$allcodes_arr,'one'=>$allcodes_arr['sh000001'][3]]);
    }
    //获取上涨平盘 下跌 股票
    public function getgunum(){
        //直接采集
        $num1 = 0;
        $num2 = 0;
        $num3 = 0;
        $total = 0;
        $num0 = 0;
        $flag = 0;
        $redis = new Redis();
        if(!betweentime("09:00-16:00")){
            // 直接获取redis里面的
            $flag = 0;
            $result2 = json_decode($redis->get('getgunum'),true);
            $num0 = intval($result2['data']['diff'][0]['f6']) + intval($result2['data']['diff'][1]['f6']);
            $num1 = intval($result2['data']['diff'][0]['f104']) + intval($result2['data']['diff'][1]['f104']);
            $num3 = intval($result2['data']['diff'][0]['f105']) + intval($result2['data']['diff'][1]['f105']);
            $num2 = intval($result2['data']['diff'][0]['f106']) + intval($result2['data']['diff'][1]['f106']);
            $total = $num1 + $num2 + $num3;
            $total = $total>0?$total:1;
            $num11 = $num1>0?$num1:"--";
            $num22 = $num2>0?$num2:"--";
            $num33 = $num3>0?$num3:"--";
            $num00 = $num0>0?$num0:"--";
            $num1_per = round(bcdiv($num1,$total,2)*100,2)>0?round(bcdiv($num1,$total,2)*100,2):33;
            $num2_per = round(bcdiv($num1,$total,2)*100,2)>0?round(bcdiv($num2,$total,2)*100,2):33;
            $num3_per = round(bcdiv($num1,$total,2)*100,2)>0?round(bcdiv($num3,$total,2)*100,2):33;
        }else{
            $flag = 1;
            // 直接采集回来更好
            $url = "http://push2.eastmoney.com/api/qt/ulist.np/get?fid=f3&pi=0&pz=20&po=1&ut=bd1d9ddb04089700cf9c27f6f7426281&fltt=2&fields=f2,f3,f4,f6,f104,f105,f106&np=1&cb=qqgsData=&secids=1.000001,0.399001&wbp2u=|0|0|0|web";
            $result = Http::getproxy($url);
            $result1 = Tool::get_between($result,'(',')');
            $redis->set('getgunum',$result1);
            // $redis->set('getgunum',$result1);
            // file_put_contents('../public/logs//dz.txt',var_export($result1,true)."\n\n",FILE_APPEND);
            $result2 = json_decode($result1,true);
            // var_dump($result2);
            // $num1 = intval($result2['data']['diff'][0]['f104']) + intval($result2['data']['diff'][1]['f104']);
            // $num3 = intval($result2['data']['diff'][0]['f105']) + intval($result2['data']['diff'][1]['f105']);
            // $num2 = intval($result2['data']['diff'][0]['f106']) + intval($result2['data']['diff'][1]['f106']);
            // $total = $num1 + $num2 + $num3;
            if(!$result1){
                // 证明没有采集都 没有采集到的 就去redis里面的
                // if(!isset($redis->get))
                // $result2 = json_decode($redis->get('getgunum'),true);
                // $num0 = intval($result2['data']['diff'][0]['f6']) + intval($result2['data']['diff'][1]['f6']);
                // $num1 = intval($result2['data']['diff'][0]['f104']) + intval($result2['data']['diff'][1]['f104']);
                // $num3 = intval($result2['data']['diff'][0]['f105']) + intval($result2['data']['diff'][1]['f105']);
                // $num2 = intval($result2['data']['diff'][0]['f106']) + intval($result2['data']['diff'][1]['f106']);
                // $total = $num1 + $num2 + $num3;
            }
            else{
                // 采集到了 就用采集里面的 并且将最新的采集存入redis
                // $result2 = json_decode($result1,true);
                // $num0 = intval($result2['data']['diff'][0]['f6']) + intval($result2['data']['diff'][1]['f6']);
                // $num1 = intval($result2['data']['diff'][0]['f104']) + intval($result2['data']['diff'][1]['f104']);
                // $num3 = intval($result2['data']['diff'][0]['f105']) + intval($result2['data']['diff'][1]['f105']);
                // $num2 = intval($result2['data']['diff'][0]['f106']) + intval($result2['data']['diff'][1]['f106']);
                // $total = $num1 + $num2 + $num3;
                // $redis->set('getgunum',$result1);
            }
            $num0 = intval($result2['data']['diff'][0]['f6']) + intval($result2['data']['diff'][1]['f6']);
            $num1 = intval($result2['data']['diff'][0]['f104']) + intval($result2['data']['diff'][1]['f104']);
            $num3 = intval($result2['data']['diff'][0]['f105']) + intval($result2['data']['diff'][1]['f105']);
            $num2 = intval($result2['data']['diff'][0]['f106']) + intval($result2['data']['diff'][1]['f106']);
            $total = $num1 + $num2 + $num3;
            $total = $total>0?$total:1;
            $num11 = $num1>0?$num1:"--";
            $num22 = $num2>0?$num2:"--";
            $num33 = $num3>0?$num3:"--";
            $num00 = $num0>0?$num0:"--";
            $num1_per = round(bcdiv($num1,$total,2)*100,2)>0?round(bcdiv($num1,$total,2)*100,2):33;
            $num2_per = round(bcdiv($num1,$total,2)*100,2)>0?round(bcdiv($num2,$total,2)*100,2):33;
            $num3_per = round(bcdiv($num1,$total,2)*100,2)>0?round(bcdiv($num3,$total,2)*100,2):33;
        }
        $this->success('请求成功', ['flag'=>$flag,'num1'=>$num11,'num0'=>$num00,'num2'=>$num22,'num3'=>$num33,'num1_per'=>$num1_per."%",'num2_per'=>$num2_per."%",'num3_per'=>$num3_per."%"]);
    }
    //获取充值配置参数
    public function getchargeconfig(){
        $agreement_info = Db::name('agreement')->where(['id'=>2])->find();
        $count_sysbank = Db::name('sysbank')->where(['status'=>1])->count();
        $count_sysbank_list = Db::name('sysbank')->where(['status'=>1])->select();
        $charge_low = config('site.charge_low');
        $this->success('请求成功', [
            'charge_low'=>$charge_low,
            'contentmsg'=>config('site.contentmsg1'),
            'contentmsg_gb'=>$agreement_info['guanbicontent'],
            'flag'=>$count_sysbank,'bindbank'=>config('site.bindbank'),
            'count_sysbank_list'=>count($count_sysbank_list),
            "sysbank_list"=>$count_sysbank_list
        ]);
    }

    //获取充值配置参数
    public function getchargeconfignew(){
        $agreement_info = Db::name('agreement')->where(['id'=>2])->find();
        // dump($this->auth->id);die;
        $count_sysbank_list = [];
        // if($this->auth->id == 1){
            $count_sysbank_list = Db::name('sysbank')->where(['status'=>1])->select();
        // }
        $is_open_sm = 1;
        if(!$count_sysbank_list){
            $is_open_sm = 0;
        }
        $data = [
            'is_sm' => $is_open_sm,
            'charge_low' => config('site.charge_low'),
            'contentmsg_gb'=>$agreement_info['guanbicontent'],
            "sysbank_list"=>$count_sysbank_list,
            'min_tx_money' => config('site.zdtixian')
        ];
        $data = Rsa::jia($data);
        $this->success('请求成功', $data);
    }

    public function getyhkconfig(){
        //获取银行卡配置
        $bankid = $this->request->get('bankid');
        $model = new Sysbanks();
        if($bankid){
            $list = $model->where(['status'=>1,'id'=>$bankid])->select();
        }else{
            $list = $model->where(['status'=>1])->select();
        }
        $this->success('请求成功', ['list'=>$list,'contentmsg'=>config('site.contentmsg2')]);
    }
    
    public function getyhkconfignew(){
        //获取银行卡配置$bankid
        // $bankid = $this->request->param('bankid');
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $bankid = Rsa::check(isset($paramInfo['bankid'])?$paramInfo['bankid']:0);
        $model = new Sysbanks();
        if($bankid){
            $list = $model->where(['status'=>1,'id'=>$bankid])->select();
        }else{
            $list = $model->where(['status'=>1])->select();
        }
        $data = ['list'=>$list,'charge_low'=>config('site.charge_low')];
        $data = Rsa::jia($data);
        $this->success('请求成功', $data);
    }
    
    // 加载申购
    public function getnewgu(){
        //加载今日申购代码 
        $time_str = date('Y-m-d',time());
        $model = new Shengou();
        $jrsg_list = $model->where(['sg_date'=>$time_str,'sgswitch'=>1])->select();
        $jrss_list = $model->where(['ss_date'=>$time_str,'sgswitch'=>1])->select();
        $jrzc_list = $model->where(['zq_jk_date'=>$time_str,'sgswitch'=>1])->select();
        $jjfb_list = $model->where(['sg_date'=>['>',$time_str],'sgswitch'=>1])->select();
        $this->success('返回成功',['jrsg_list'=>$jrsg_list,"jrss_list"=>$jrss_list,'jrzc_list'=>$jrzc_list,'jjfb_list'=>$jjfb_list]);
    }
    
    public function getnewscatalog(){
        $model = new Newcatalog();
        $model1 = new Newscontent();
        $catalog_list = $model->where(['status'=>1])->select();
        foreach ($catalog_list as $k => $v){
            $catalog_list[$k]['name'] = $v['title'];
            $catalog_list[$k]['list'] = $model1->where(['catalog_id'=>$v['id']])->limit(10)->select();
        }
        $this->success('返回成功',['catalog_list'=>$catalog_list]);
    }
    public function getnewscatalogs_new(){
        $model = new Newcatalog();
        $catalog_list = $model->where(['status'=>1])->field('title as name,id')->select();
        array_unshift($catalog_list,[
            'id'=>0,
            'name'=>'热门'
            ]);
            
            // asort($catalog_list);
        $this->success('返回成功',['catalog_list'=>$catalog_list]);
    }
    public function getnewscatalog_new(){
        $model1 = new Newscontent();
        $id = $this->request->get('id');
        if(!$id){
            // $catalog_list = $model1->where(['is_hot'=>1])->select();column('name','id')
            $catalog_list = $model1->where(['is_hotswitch'=>1])->field('id,biaoti as title,is_linkdata,imgArrimages,faxingshe as badgeText,faxingshe1 as subContent,maincontent')->limit(10)->order('id desc')->select();
        }else{
            $catalog_list = $model1->where(['catalog_id'=>$id])->limit(10)->field('id,biaoti as title,is_linkdata,imgArrimages,faxingshe as badgeText,faxingshe1 as subContent,maincontent')->order('id desc')->select();
        }
        foreach ($catalog_list as $k => $v){
            $arrall = [];
            if($v["imgArrimages"]){
                $arrimgs = explode(',',$v["imgArrimages"]);
                foreach ($arrimgs as $vv){
                    // var_dump($vv);
                    // array_push($arrall,$vv);
                    $arrall[] = $vv;
                }
                // var_dump($arrall);
                $catalog_list[$k]['imgArr'] = $arrall;
                $catalog_list[$k]['img'] = $arrimgs[0];
            }else{
                $catalog_list[$k]['img'] = "0";
            }
            $catalog_list[$k]['label'] = rand(0,4);
        }
        $this->success('返回成功',['catalog_list'=>$catalog_list]);
    }
    
    public function getnewscatalogs_new_detail(){
        $model1 = new Newscontent();
        $id = $this->request->get('id');
        $catalog_list = $model1->where(['id'=>$id])->select();
        $this->success('返回成功',['catalog_list'=>$catalog_list[0]]);
    }
    public function getjyconfig(){
        //获取银行卡配置
        $this->success('请求成功', ['maxxg'=>config('site.maxxg'),'gpjiaoyi'=>config('site.gpjiaoyi')]);
    }
    public function configinfo(){
        $this->success("请求成功",[
            'gqpeizhi' => config('site.gqpeizhi')
        ]);
    }
    
    
    public function getpublickey(){
        
        $public_key = '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAu8oSJJAAF4+t4JPoP+LV
3qZTp32K/8tCWGfR/+HE4YwVap63pADKfTkJhBtdaVJK++4DZTxp4zmAbNpV9cNt
eAizRcGb1ytyZp+dLjpW3jBE9DarE5xKBkNCFkf2pF5mfE6inlG2lBSYa0MNt8ZY
s7nPmu+qNYlIeshfm8OuEmNuJVRUNHY7jPgEjZq9Z5Q+kA0MJ7P097PSWfR1FJ12
WufsDH93JK4D7C4iACPoU2l1NywVmOGnjtqdjYfZSlu1kpPKAy0USdEDVxwMWR/v
WbK6Jk7rJWvpR7IY/jLWSTSdwPBA/HT/exdU+YT7BwEy2vzD4Ik/fLSj1LEaNyiK
AQIDAQAB
-----END PUBLIC KEY-----';
        $this->success("请求成功",[
            //'key' => config('site.public_key')
            'key' => $public_key
        ]);
    }
    
    
    
    
    
    
    
    
    
    
}
