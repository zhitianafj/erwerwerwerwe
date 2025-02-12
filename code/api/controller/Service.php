<?php

namespace app\api\controller;

use app\admin\model\Admin;
use app\admin\model\Adv;
use app\admin\model\Agreement;
use app\admin\model\Attention;
use app\admin\model\Banner;
use app\admin\model\feedback\Feedbackset;
use app\admin\model\feedback\Problem;
use app\admin\model\Nav;
use app\admin\model\strategy\Strategy;
use app\common\controller\Api;
use fast\Http;
use think\Db;
use fast\Pinyin;
use fast\Tool;
use app\admin\model\shengou\Shengou;
use simple_html_dom;
use app\admin\model\news\Newscontent;
use app\admin\model\strategy\Exponent;
use app\admin\model\Newsss;
use app\admin\model\strategy\Addstrategy;
use app\admin\model\User;
use app\admin\model\Quanqiu;
use app\admin\model\Etf;
use app\admin\model\Etfbaseinfo;
use think\cache\driver\Redis;


class Service extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];
    
    // 监控第二天开盘 排除节假日
    public function fx_strategy(){
        $current_day = date('m-d',time());
        //当前如果是节假日 就直接return 
        $data = Db::name("holiday")->where(['time_str'=>$current_day])->select();
        if($data){
            echo '节假日不采集';
            return;
        }
        // 获取所有 status = 3 并且 fxstatus = 0的所有的数据
        $list = Db::name("add_strategy")->where(['status' => '3','fxsatus' => 0])->select();
        foreach ($list as $k => $v){
            // 直接找到用户ID 做修改
            $user_info =User::get($v['user_id']);
            $user_info->balance = bcadd($user_info['balance'],$v['fxmoney']);
            $user_info->save();
            // 做两部操作 当前用户加价
            // $v 做修改
            $add_strategy_info = Addstrategy::get($v['id']);
            $add_strategy_info->fxsatus = 1;
            $add_strategy_info->save();
            
            
            
        }
        
    }
    
    
    
    public function test567(){
        $data = Http::get_stock_now_info_pj_jk('000001,600000');
        var_dump($data);
    }
    
    public function test234(){
        $data = Http::get_stock_now_info_pjzuixinjia('sh000001,sh600000');
        
        var_dump($data['sh000001']);
    }
    public function testttt11(){
        // $url = "http://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=40&sort=changepercent&asc=0&node=hs_a&symbol=&_s_r_a=page";
        
        // $data = Http::getproxy($url);
        // var_dump($data);
        $output = Http::get_stock_now_info_proxy('sh600000');
        var_dump($output);
    }
    public function testttt1(){
        // $url = "http://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=40&sort=changepercent&asc=0&node=hs_a&symbol=&_s_r_a=page";
        
        // $data = Http::getproxy($url);
        // var_dump($data);
        $url = "http://qt.gtimg.cn/q=sz399001";
        $output = Http::getproxy($url);
        $strs=explode("=", $output);
        // var_dump($strs);
        $return_str=$strs[1];
        // var_dump($return_str);
        // $return_str= substr($return_str, 1,strlen($return_str)-4);
        var_dump(explode('~', $return_str));
        // return explode(',', $return_str);
        // return explode('~', $return_str);
        // var_dump($return_str);
    }
    
    
    public function testttt(){
        $url = "http://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=40&sort=changepercent&asc=0&node=hs_a&symbol=&_s_r_a=page";
        $data = Http::post($url);
        var_dump($data);
        // $shangzheng = Http::get_stock_now_info("sh600519");
        // var_dump($shangzheng);
        // $result = str_contains('rrrrrrrrraaaa','1111111111');
        // var_dump($result);
    }
    
    function getbanbycode(){
        //sh600000 sz300000
        if(substr('sz300000',2,3)=="300"){
            // return true;
            var_dump(111);
        }else{
            var_dump(222);
        }
    }
    function get_stock_now_info(){
        $stock_code = '600519';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://hq.sinajs.cn/list=sz".$stock_code);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_ENCODING ,"utf-8"); //加入gzip解析
        curl_setopt($ch, CURLOPT_HEADER, 0);
        // 3. 执行并获取HTML文档内容
        $output = curl_exec($ch);
        // 4. 释放curl句柄
        curl_close($ch);
        $output = mb_convert_encoding($output, "utf-8", "gbk");
        $strs=explode("=", $output);
        if(strlen($strs[1])<10){
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://hq.sinajs.cn/list=sh".$stock_code);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_ENCODING ,'utf-8'); //加入gzip解析
            curl_setopt($ch, CURLOPT_HEADER, 0);
            // 3. 执行并获取HTML文档内容
            $output = curl_exec($ch);
            // 4. 释放curl句柄
            curl_close($ch);
            $output = mb_convert_encoding($output, "utf-8", "gbk");
            $strs=explode("=", $output);
        }
        $return_str=$strs[1];
        $return_str= substr($return_str, 1,strlen($return_str)-4);
        var_dump($return_str);
        // return explode(',', $return_str);
    }
    
    function testddd(){
        $obj = new Http();
        var_dump($obj->get_stock_now_info('sh6005'));
        $dd = $obj->get_stock_now_info('sh6005');
        if($dd[0]){
            var_dump('11111');
        }else{
            $ddd = bcdiv(1761.14-1773.78,1773.78,4)*100;
            var_dump($ddd);
            var_dump('2222');
        }
    }
    public function set(){
        $model = new Feedbackset();
        $detail = $model
            ->field('labels,content')
            ->where('id',2)
            ->find();
        if($detail){
            $detail['labelsArr'] = explode('|',$detail['labels']);
        }
        $this->success('请求成功', ['list'=>$detail]);
    }
    // 采集阿里云市场板块接口
    public function takebankuai(){
        
        $host = "https://ali-stock.showapi.com";
        $path = "/stock-block-list";
        $method = "GET";
        $appcode = "7d23ce521e8a4c88b763bdb84b095599";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "";
        $bodys = "";
        $url = $host . $path;
    
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos("$".$host, "https://"))
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        var_dump(curl_exec($curl));
        // $json = json_decode(curl_exec($curl));
    }
    
    public function takedapan(){
        $host = "https://ali-stock.showapi.com";
        $path = "/stockindexsearch";
        $method = "GET";
        $appcode = "7d23ce521e8a4c88b763bdb84b095599";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "market=sh&page=1";
        $bodys = "";
        $url = $host . $path . "?" . $querys;
    
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        if (1 == strpos("$".$host, "https://"))
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        var_dump(curl_exec($curl));
    }
    
    public function takedapan_bycode(){
        $host = "https://ali-stock.showapi.com";
        $path = "/indexDayHis";
        $method = "GET";
        $appcode = "7d23ce521e8a4c88b763bdb84b095599";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "code=000001&month=202110";
        $bodys = "";
        $url = $host . $path . "?" . $querys;
    
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        if (1 == strpos("$".$host, "https://"))
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        var_dump(curl_exec($curl));
        // $host = "https://ali-stock.showapi.com";
        // $path = "/indexDayHis";
        // $method = "GET";
        // $appcode = "7d23ce521e8a4c88b763bdb84b095599";
        // $headers = array();
        // array_push($headers, "Authorization:APPCODE " . $appcode);
        // $querys = "code=000001&month=201703";
        // $bodys = "";
        // $url = $host . $path . "?" . $querys;
        
        // $obj = new Http();
        // $result = $obj->curl_ali($host,$path,$method,$appcode,$querys,$bodys);
        // var_dump($result);
    }
    
    public function takegupiao_query(){
        $host = "https://ali-stock.showapi.com";
        $path = "/name-to-stockinfo";
        $method = "GET";
        $appcode = "7d23ce521e8a4c88b763bdb84b095599";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "code=000001";
        $bodys = "";
        $url = $host . $path . "?" . $querys;
    
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos("$".$host, "https://"))
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        var_dump(curl_exec($curl));
    }
    
    
    //采集新浪接口的数据  上证指数 接口API http://hq.sinajs.cn/list=s_sh000001
    
    
    /**
     * 新浪股票数据API
     * 新浪科技
     */
    public function sinaSharesApi(){
        header("Content-Type:text/html;charset=gb2312");
        header("Access-Control-Allow-Origin: http://hq.sinajs.cn/");
        $stockInfoString = file_get_contents("http://hq.sinajs.cn/list=sh000001");
        var_dump($stockInfoString);
        // $obj = new Http();
        // // http://hq.sinajs.cn/list=s_sh000001
        // $url = "http://hq.sinajs.cn/list=s_sh000001";
        // $data = file_get_contents ('http://hq.sinajs.cn/list=s_sh000001');//API接口
        // var_dump($data);
// 		$getdata = $obj->curlfun($url);
// 		var_dump($getdata);
// 		$data_arr = explode(',',$getdata);
        // $url = "http://hq.sinajs.cn/list=s_sh000001";
        // $method = "GET";
        // $headers = array();
        // $ret = httpRequest($url, $method, $postfields = null, $headers, $debug = false);
        // $ret = iconv('GB2312', 'UTF-8', $ret);
        // $ret_arr1 = explode(';', $ret);
        // array_pop($ret_arr1);
        // $shares_arr = array();
        // foreach ($ret_arr1 as $k1 => $v1) {
        //     $temp_v1 = trim(substr($v1, 4));
        //     $tem_arr1 = explode('=', $temp_v1);
        //     $tem_arr3 = explode(',', $tem_arr1[1]);
        //     $shares_arr[] = array(
        //         'shares_name' => substr($tem_arr3[0], 1),
        //         'shares_code' => substr($tem_arr1[0], 11),
        //         'shares_price' => $tem_arr3[1],
        //         'shares_rate' => $tem_arr3[3],
        //     );
        // }
        // $result = array(
        //     'code' => 1,
        //     'data' => $shares_arr,
        //     'msg' => 'success',
        // );
        // // $this->ajaxReturn($result);
        // var_dump($result);
    }
    
    
    
    public function get_time(){
       #判断当前时间是否在时间段内，如果是，则执行
       $Day = date('Y-m-d ',time());
       $timeBegin = strtotime($Day."09:30".":00");
       $timeEnd = strtotime($Day."15:00".":00");
       $curr_time = time();
       if($curr_time >= $timeBegin && $curr_time <= $timeEnd){
          return true; 
       }
       return false;
    }
    
    
    //获取上证指数  10s执行一次  get 方式执行
    public function getsh(){
        if(!$this->get_time()){
            $this->error('时间未到');
            sleep(5);
        }
        //判断时间范围
        $url = "http://web.juhe.cn:8080/finance/stock/hs";
        $params = array(
            "gid"=>'sh000001',
            "type"=>"",
            "key"=>"fdee7ac6e87f4034ca883418585b94c1"
        );
        $obj = new Http();
        $result =json_decode($obj->get($url,$params),true);
        //修改
       Db::name("exponent")->where('id', 2)->update(['one' => $result['result'][0]['dapandata']['dot'],'two'=>$result['result'][0]['dapandata']['nowPic'],'three'=>$result['result'][0]['dapandata']['rate']]);
    }
    //获取深证指数
    public function getsz(){
        if(!$this->get_time()){
            $this->error('时间未到');
            sleep(5);
        }
        $url = "http://web.juhe.cn:8080/finance/stock/hs";
        $params = array(
            "gid"=>'sz399001',
            "type"=>"",
            "key"=>"fdee7ac6e87f4034ca883418585b94c1"
        );
        $obj = new Http();
        $result =json_decode($obj->get($url,$params),true);
        //修改
       Db::name("exponent")->where('id', 3)->update(['one' => $result['result'][0]['dapandata']['dot'],'two'=>$result['result'][0]['dapandata']['nowPic'],'three'=>$result['result'][0]['dapandata']['rate']]);
    }
    //获取深证指数
    public function getcy(){
        if(!$this->get_time()){
            $this->error('时间未到');
            sleep(5);
        }
        $url = "http://web.juhe.cn:8080/finance/stock/hs";
        $params = array(
            "gid"=>'sz399006',
            "type"=>"",
            "key"=>"fdee7ac6e87f4034ca883418585b94c1"
        );
        $obj = new Http();
        $result =json_decode($obj->get($url,$params),true);
        // var_dump($result);
        // var_dump($result['result'][0]['dapandata']);
        //修改
        Db::name("exponent")->where('id', 4)->update(['one' => $result['result'][0]['dapandata']['dot'],'two'=>$result['result'][0]['dapandata']['nowPic'],'three'=>$result['result'][0]['dapandata']['rate']]);
    }
    
    
    //采集前20指沪股列表
    public function gethugushang(){
        // if(!$this->get_time()){
        //     $this->error('时间未到');
        //     sleep(5);
        // }
        for ($x=1; $x<=22; $x++) {
            $url = "http://web.juhe.cn:8080/finance/stock/shall";
            $params = array(
                "stock"=>"",
                "page"=>$x,
                "type"=>"4",
                "key"=>"fdee7ac6e87f4034ca883418585b94c1"
            );
            $obj = new Http();
            $result =json_decode($obj->get($url,$params),true);
            $this->addStrategy($result,'qwientewngdsgfsdhIds');
        } 
        
        echo 1;exit;
    }
    
    //采集前20只深圳股市列表
    public function gethugu(){
        // if(!$this->get_time()){
        //     $this->error('时间未到');
        //     sleep(5);
        // }
    for ($x=1; $x<=33; $x++) {
        $url = "http://web.juhe.cn:8080/finance/stock/szall";
        $params = array(
            "stock"=>"",
            "page"=>$x,
            "type"=>"4",
            "key"=>"fdee7ac6e87f4034ca883418585b94c1"
        );
        $obj = new Http();
        $result =json_decode($obj->get($url,$params),true);
        $this->addStrategy($result,'qwientewngdsgfsdhIds');
        }        echo 1;exit;
    }

    public function getguone(){
        $url = "http://web.juhe.cn:8080/finance/stock/hs";
        $params = array(
            "gid"=>"sz001317",
            "key"=>"fdee7ac6e87f4034ca883418585b94c1"
        );
        $obj = new Http();
        $result =json_decode($obj->get($url,$params),true);
        // 
        var_dump($result);
        $this->addStrategy1($result,'qwientewngdsgfsdhIds');
    }
    //股入实际数据库
    public function addStrategy1($data,$key){
        if($key!='qwientewngdsgfsdhIds'){
            exit;
        }
        if(!$data['error_code']){
            $info = $data['result'][0]['data'];
            foreach($info as $k=>$v){
               $model = new Strategy();
               $onlineInfo = $model->where(['deletetime'=>Null,'allcode'=>$v['gid']])->find();
               if(isset($onlineInfo['id']) && $onlineInfo['id']){
                   $onlineInfo->code = $v['code'];
                   $onlineInfo->cai_trade = $v['trade'];
                   $onlineInfo->cai_pricechange = $v['pricechange'];
                   $onlineInfo->cai_changepercent = $v['changepercent'];
                   $onlineInfo->cai_buy = $v['buy'];
                   $onlineInfo->cai_sell = $v['sell'];
                   $onlineInfo->cai_settlement = $v['settlement'];
                   $onlineInfo->cai_open = $v['open'];
                   $onlineInfo->cai_high = $v['high'];
                   $onlineInfo->cai_low = $v['low'];
                   $onlineInfo->cai_volume = $v['volume'];
                   $onlineInfo->cai_amount = $v['amount'];
                   $onlineInfo->cai_ticktime = $v['ticktime'];
                   $onlineInfo->save();
               }else{
                   $model->title = $v['name'];
                   $model->allcode = $v['symbol'];
                   $model->code = $v['code'];
                   $model->cai_trade = $v['trade'];
                   $model->cai_pricechange = $v['pricechange'];
                   $model->cai_changepercent = $v['changepercent'];
                   $model->cai_buy = $v['buy'];
                   $model->cai_sell = $v['sell'];
                   $model->cai_settlement = $v['settlement'];
                   $model->cai_open = $v['open'];
                   $model->cai_high = $v['high'];
                   $model->cai_low = $v['low'];
                   $model->cai_volume = $v['volume'];
                   $model->cai_amount = $v['amount'];
                   $model->cai_ticktime = $v['ticktime'];
                   $model->save();
               }
            }
        }
    }
    //股入实际数据库
    public function addStrategy($data,$key){
        if($key!='qwientewngdsgfsdhIds'){
            exit;
        }
        if(!$data['error_code']){
            $info = $data['result']['data'];
            foreach($info as $k=>$v){
               $model = new Strategy();
               $onlineInfo = $model->where(['deletetime'=>Null,'allcode'=>$v['symbol']])->find();
               if(isset($onlineInfo['id']) && $onlineInfo['id']){
                   $onlineInfo->code = $v['code'];
                   $onlineInfo->cai_trade = $v['trade'];
                   $onlineInfo->cai_pricechange = $v['pricechange'];
                   $onlineInfo->cai_changepercent = $v['changepercent'];
                   $onlineInfo->cai_buy = $v['buy'];
                   $onlineInfo->cai_sell = $v['sell'];
                   $onlineInfo->cai_settlement = $v['settlement'];
                   $onlineInfo->cai_open = $v['open'];
                   $onlineInfo->cai_high = $v['high'];
                   $onlineInfo->cai_low = $v['low'];
                   $onlineInfo->cai_volume = $v['volume'];
                   $onlineInfo->cai_amount = $v['amount'];
                   $onlineInfo->cai_ticktime = $v['ticktime'];
                   $onlineInfo->save();
               }else{
                   $model->title = $v['name'];
                   $model->allcode = $v['symbol'];
                   $model->code = $v['code'];
                   $model->cai_trade = $v['trade'];
                   $model->cai_pricechange = $v['pricechange'];
                   $model->cai_changepercent = $v['changepercent'];
                   $model->cai_buy = $v['buy'];
                   $model->cai_sell = $v['sell'];
                   $model->cai_settlement = $v['settlement'];
                   $model->cai_open = $v['open'];
                   $model->cai_high = $v['high'];
                   $model->cai_low = $v['low'];
                   $model->cai_volume = $v['volume'];
                   $model->cai_amount = $v['amount'];
                   $model->cai_ticktime = $v['ticktime'];
                   $model->save();
               }
            }
        }
    }
    //股入实际数据库
    public function addStrategy2($data,$key){
        if($key!='qwientewngdsgfsdhIds'){
            exit;
        }
        if($data){
            $info = $data;
            foreach($info as $k=>$v){
               $model = new Strategy();
               $onlineInfo = $model->where(['deletetime'=>Null,'allcode'=>$v['symbol']])->find();
               if(isset($onlineInfo['id']) && $onlineInfo['id']){
                   $onlineInfo->code = $v['code'];
                   $onlineInfo->cai_trade = $v['trade'];
                   $onlineInfo->cai_pricechange = $v['pricechange'];
                   $onlineInfo->cai_changepercent = $v['changepercent'];
                   $onlineInfo->cai_buy = $v['buy'];
                   $onlineInfo->cai_sell = $v['sell'];
                   $onlineInfo->cai_settlement = $v['settlement'];
                   $onlineInfo->cai_open = $v['open'];
                   $onlineInfo->cai_high = $v['high'];
                   $onlineInfo->cai_low = $v['low'];
                   $onlineInfo->cai_volume = $v['volume'];
                   $onlineInfo->cai_amount = $v['amount'];
                   $onlineInfo->cai_ticktime = $v['ticktime'];
                   $onlineInfo->type = 4;
                   $onlineInfo->save();
               }else{
                   $model->title = $v['name'];
                   $model->allcode = $v['symbol'];
                   $model->code = $v['code'];
                   $model->cai_trade = $v['trade'];
                   $model->cai_pricechange = $v['pricechange'];
                   $model->cai_changepercent = $v['changepercent'];
                   $model->cai_buy = $v['buy'];
                   $model->cai_sell = $v['sell'];
                   $model->cai_settlement = $v['settlement'];
                   $model->cai_open = $v['open'];
                   $model->cai_high = $v['high'];
                   $model->cai_low = $v['low'];
                   $model->cai_volume = $v['volume'];
                   $model->cai_amount = $v['amount'];
                   $model->cai_ticktime = $v['ticktime'];
                   $onlineInfo->type = 4;
                   $model->save();
               }
            }
        }
    }
    //采集创业板
    public function caijibjsuo(){
        $url = "http://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=1&num=100&sort=symbol&asc=1&node=hs_bjs&symbol=&_s_r_a=page";
        $url = "http://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page=2&num=40&sort=changepercent&asc=0&node=hs_a&symbol=&_s_r_a=page";
        $obj = new Http();
        $result =json_decode($obj->get($url),true);
        // var_dump($result);
        $this->addStrategy2($result,'qwientewngdsgfsdhIds');
    }
    //采集所有的A股
    public function caijiAgutest(){
        for ($x=1; $x<=20; $x++) {
            $url = "http://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$x}&num=40&sort=changepercent&asc=0&node=hs_a&symbol=&_s_r_a=page";
            $obj = new Http();
            $result =json_decode($obj->getproxy($url),true);
            var_dump($result);
            // $this->addStrategy3($result,'qwientewngdsgfsdhIds');
            // sleep(1000);
        }
    }
    //采集所有的A股
    public function caijiAgu(){
        $current_day = date('m-d',time());
        //当前如果是节假日 就直接return 
        $data = Db::name("holiday")->where(['time_str'=>$current_day])->select();
        if($data){
            echo '节假日不采集';
            return;
        }
        if(!betweentime("09:00-16:00")){
            //$this->error('停盘不采集');
        }
        for ($x=1; $x<=20; $x++) {
            $url = "http://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$x}&num=40&sort=changepercent&asc=0&node=hs_a&symbol=&_s_r_a=page";
            $obj = new Http();
            $result =json_decode($obj->get($url),true);
            var_dump($result);
            // $this->addStrategy3($result,'qwientewngdsgfsdhIds',false);
            // sleep(1000);
        }
    }
    
    //采集所有的A股
    public function caijiAgu2(){
        $current_day = date('m-d',time());
        //当前如果是节假日 就直接return 
        $data = Db::name("holiday")->where(['time_str'=>$current_day])->select();
        if($data){
            echo '节假日不采集';
            return;
        }
        if(!betweentime("09:00-16:00")){
            //$this->error('停盘不采集');
        }
        for ($x=21; $x<=40; $x++) {
            $url = "http://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$x}&num=40&sort=changepercent&asc=0&node=hs_a&symbol=&_s_r_a=page";
            $obj = new Http();
            $result =json_decode($obj->get($url),true);
            // var_dump($result);
            $this->addStrategy3($result,'qwientewngdsgfsdhIds',false);
            // sleep(1000);
        }
    }
    
    //采集所有的A股
    public function caijiAgu3(){
        $current_day = date('m-d',time());
        //当前如果是节假日 就直接return 
        $data = Db::name("holiday")->where(['time_str'=>$current_day])->select();
        if($data){
            echo '节假日不采集';
            return;
        }
        if(!betweentime("09:00-16:00")){
            //$this->error('停盘不采集');
        }
        for ($x=41; $x<=60; $x++) {
            $url = "http://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$x}&num=40&sort=changepercent&asc=0&node=hs_a&symbol=&_s_r_a=page";
            $obj = new Http();
            $result =json_decode($obj->get($url),true);
            // var_dump($result);
            $this->addStrategy3($result,'qwientewngdsgfsdhIds',false);
            // sleep(1000);
        }
    }
    //采集所有的A股
    public function caijiAgu4(){
        $current_day = date('m-d',time());
        //当前如果是节假日 就直接return 
        $data = Db::name("holiday")->where(['time_str'=>$current_day])->select();
        if($data){
            echo '节假日不采集';
            return;
        }
        if(!betweentime("09:00-16:00")){
            //$this->error('停盘不采集');
        }
        for ($x=61; $x<=80; $x++) {
            $url = "http://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$x}&num=40&sort=changepercent&asc=0&node=hs_a&symbol=&_s_r_a=page";
            $obj = new Http();
            $result =json_decode($obj->get($url),true);
            // var_dump($result);
            $this->addStrategy3($result,'qwientewngdsgfsdhIds',false);
            // sleep(1000);
        }
    }
    //采集所有的A股
    public function caijiAgu5(){
        $current_day = date('m-d',time());
        //当前如果是节假日 就直接return 
        $data = Db::name("holiday")->where(['time_str'=>$current_day])->select();
        if($data){
            echo '节假日不采集';
            return;
        }
        if(!betweentime("09:00-16:00")){
            //$this->error('停盘不采集');
        }
        for ($x=81; $x<=100; $x++) {
            $url = "http://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$x}&num=40&sort=changepercent&asc=0&node=hs_a&symbol=&_s_r_a=page";
            $obj = new Http();
            $result =json_decode($obj->get($url),true);
            // var_dump($result);
            $this->addStrategy3($result,'qwientewngdsgfsdhIds',false);
            // sleep(1000);
        }
    }
    //采集所有的A股
    public function caijiAgu6(){
        $current_day = date('m-d',time());
        //当前如果是节假日 就直接return 
        $data = Db::name("holiday")->where(['time_str'=>$current_day])->select();
        if($data){
            echo '节假日不采集';
            return;
        }
        if(!betweentime("09:00-16:00")){
            //$this->error('停盘不采集');
        }
        for ($x=101; $x<=120; $x++) {
            $url = "http://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$x}&num=40&sort=changepercent&asc=0&node=hs_a&symbol=&_s_r_a=page";
            $obj = new Http();
            $result =json_decode($obj->get($url),true);
            // var_dump($result);
            $this->addStrategy3($result,'qwientewngdsgfsdhIds',false);
            // sleep(1000);
        }
    }
    
    //采集所有的A股
    public function caijiAgu7(){
        for ($x=121; $x<=128; $x++) {
            $url = "http://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeData?page={$x}&num=40&sort=changepercent&asc=0&node=hs_a&symbol=&_s_r_a=page";
            $obj = new Http();
            // $result =json_decode($obj->getproxy($url),true);
            $result =json_decode($obj->get($url),true);
            // var_dump($result);
            $this->addStrategy3($result,'qwientewngdsgfsdhIds',false);
            // sleep(500);
        }
    }
    
    // 采集指数
    public function caijiAgu8(){
        $current_day = date('m-d',time());
        //当前如果是节假日 就直接return 
        $data = Db::name("holiday")->where(['time_str'=>$current_day])->select();
        if($data){
            echo '节假日不采集';
            return;
        }
        if(!betweentime("09:00-16:00")){
            // //$this->error('停盘不采集');
        }
        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeDataSimple?page=1&num=40&sort=symbol&asc=1&node=dpzs&_s_r_a=init";
            $obj = new Http();
            $result =json_decode($obj->getproxy($url),true);
            // var_dump($result);
            $this->addStrategy3($result,'qwientewngdsgfsdhIds',true);
    }
    //股入实际数据库
    public function addStrategy3($data,$key,$flag){
        if($key!='qwientewngdsgfsdhIds'){
            exit;
        }
        if($data){
            $info = $data;
            foreach($info as $k=>$v){
               $model = new Strategy();
               $onlineInfo = $model->where(['allcode'=>$v['symbol']])->find();
               if(isset($onlineInfo['id']) && $onlineInfo['id']){
                   $onlineInfo->title = $v['name'];
                   $onlineInfo->code = $v['code'];
                   $onlineInfo->cai_trade = $v['trade'];
                   $onlineInfo->cai_pricechange = $v['pricechange'];
                   $onlineInfo->cai_changepercent = round($v['changepercent'],2);
                   $onlineInfo->cai_buy = $v['buy'];
                   $onlineInfo->cai_sell = $v['sell'];
                   $onlineInfo->cai_settlement = $v['settlement'];
                   $onlineInfo->cai_open = $v['open'];
                   $onlineInfo->cai_high = $v['high'];
                   $onlineInfo->cai_low = $v['low'];
                   $onlineInfo->cai_volume = $v['volume'];
                   $onlineInfo->cai_amount = $v['amount'];
                   $onlineInfo->cai_ticktime = $v['ticktime'];
                   $onlineInfo->type = 0;
                   if(str_contains($v['symbol'],'bj')){
                        $onlineInfo->type = 4;//北交所
                   }else if(str_contains($v['symbol'],'sh')){
                        $onlineInfo->type = 1;//沪
                        if(str_contains($v['symbol'],'sh68')){
                            $onlineInfo->type = 5;//科创
                        }
                   }else if(str_contains($v['symbol'],'sz')){
                        $onlineInfo->type = 2;//深
                        if(str_contains($v['symbol'],'sz30')){
                            $onlineInfo->type = 3;//创业
                        }
                   }
                   if($flag){
                       $onlineInfo->type1 = 1;
                   }else{
                       $onlineInfo->type1 = 0;
                   }
                   $onlineInfo->save();
               }else{
                   $model->title = $v['name'];
                   $model->allcode = $v['symbol'];
                   $model->code = $v['code'];
                   $model->cai_trade = $v['trade'];
                   $model->cai_pricechange = $v['pricechange'];
                   $model->cai_changepercent = round($v['changepercent'],2);
                   $model->cai_buy = $v['buy'];
                   $model->cai_sell = $v['sell'];
                   $model->cai_settlement = $v['settlement'];
                   $model->cai_open = $v['open'];
                   $model->cai_high = $v['high'];
                   $model->cai_low = $v['low'];
                   $model->cai_volume = $v['volume'];
                   $model->cai_amount = $v['amount'];
                   $model->cai_ticktime = $v['ticktime'];
                   $model->type = 0;
                   if(str_contains($v['symbol'],'bj')){
                        $model->type = 4;
                   }else if(str_contains($v['symbol'],'sh')){
                        $model->type = 1;
                        if(str_contains($v['symbol'],'sh68')){
                            $model->type = 5;
                        }
                   }else if(str_contains($v['symbol'],'sz')){
                        $model->type = 2;
                        if(str_contains($v['symbol'],'sz30')){
                            $model->type = 3;
                        }
                   }
                   if($flag){
                       $model->type1 = 1;
                   }else{
                       $model->type1 = 0;
                   }
                   $model->save();
               }
            }
        }
    }
    // 采集全球期货
    public function caijiquanqiu(){
        $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getFuturesGlobalData?page=1&num=80&sort=symbol&asc=1&node=global_qh&_s_r_a=init";
        $obj = new Http();
        $result =json_decode($obj->getproxy($url),true);
        // var_dump($result);
        $this->addquanqiu($result,'qwientewngdsgfsdhIds');
    }
    
    public function addquanqiu($info,$key){
        if($key!='qwientewngdsgfsdhIds'){
            exit;
        }
        if($info){
           foreach($info as $k=>$v){
            // 判断是否存在
               $model = new Quanqiu();
               $quanqiu_info = $model->where(['symbol'=>$v['symbol']])->find();
               if($quanqiu_info){
                //   做更新
                    $quanqiu_info->allcode = $v['symbol'];
                    $quanqiu_info->xl_symbol = 'hf_'.$v['symbol'];
                    $quanqiu_info->symbol = $v['symbol'];
                    $quanqiu_info->last = $v['last'];
                    $quanqiu_info->pricechange = $v['pricechange'];
                    $quanqiu_info->bid = $v['bid'];
                    $quanqiu_info->ask = $v['ask'];
                    $quanqiu_info->asksize = $v['asksize'];
                    $quanqiu_info->bidsize = $v['bidsize'];
                    $quanqiu_info->currentvol = $v['currentvol'];
                    $quanqiu_info->dateupdate = $v['dateupdate'];
                    $quanqiu_info->high = $v['high'];
                    $quanqiu_info->localdatetime = $v['localdatetime'];
                    $quanqiu_info->low = $v['low'];
                    $quanqiu_info->market = $v['market'];
                    $quanqiu_info->name = $v['name'];
                    $quanqiu_info->open = $v['open'];
                    $quanqiu_info->prev = $v['prev'];
                    $quanqiu_info->timeupdate = $v['timeupdate'];
                    $quanqiu_info->totalvol = $v['totalvol'];
                    $quanqiu_info->save();
               }else{
                //    不存在 做插入
                    $model->allcode = $v['symbol'];
                    $model->xl_symbol = 'hf_'.$v['symbol'];
                    $model->symbol = $v['symbol'];
                    $model->last = $v['last'];
                    $model->pricechange = $v['pricechange'];
                    $model->bid = $v['bid'];
                    $model->ask = $v['ask'];
                    $model->asksize = $v['asksize'];
                    $model->bidsize = $v['bidsize'];
                    $model->currentvol = $v['currentvol'];
                    $model->dateupdate = $v['dateupdate'];
                    $model->high = $v['high'];
                    $model->localdatetime = $v['localdatetime'];
                    $model->low = $v['low'];
                    $model->market = $v['market'];
                    $model->name = $v['name'];
                    $model->open = $v['open'];
                    $model->prev = $v['prev'];
                    $model->timeupdate = $v['timeupdate'];
                    $model->totalvol = $v['totalvol'];
                    
                    $model->save();
               }
           } 
        }
    }
    
    
    // 采集ETF基金
    public function caijiETF(){
        // $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeDataSimple?page=1&num=40&sort=symbol&asc=1&node=etf_hq_fund&_s_r_a=init";
        // $obj = new Http();
        // $result =json_decode($obj->getproxy($url),true);
        // // var_dump($result);
        // $this->addETF($result,'qwientewngdsgfsdhIds');
        for ($x=1; $x<=19; $x++) {
            $url = "https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeDataSimple?page={$x}&num=40&sort=symbol&asc=1&node=etf_hq_fund&_s_r_a=init";
            $obj = new Http();
            $result =json_decode($obj->getproxy($url),true);
            // var_dump($result);
            // $this->addETF($result,'qwientewngdsgfsdhIds');
            $this->addETFs($result,'qwientewngdsgfsdhIds');
        }
    }
    public function addETFs($info,$key){
        if($key!='qwientewngdsgfsdhIds'){
            exit;
        }
        if($info){
           foreach($info as $k=>$v){
            // 判断是否存在
               $model = new Strategy();
               $etf_info = $model->where(['allcode'=>$v['symbol']])->find();
               if($etf_info){
                //   做更新
                    $etf_info->title = $v['name'];
                    $etf_info->code = $v['code'];
                    $etf_info->allcode = $v['symbol'];
                    $etf_info->type = 6;
                    $etf_info->save();
               }else{
                //    不存在 做插入
                    $model->title = $v['name'];
                    $model->code = $v['code'];
                    $model->allcode = $v['symbol'];
                    $model->type = 6;
                    $model->save();
               }
           } 
        }
    }
    public function addETF($info,$key){
        if($key!='qwientewngdsgfsdhIds'){
            exit;
        }
        if($info){
           foreach($info as $k=>$v){
            // 判断是否存在
               $model = new Etf();
               $etf_info = $model->where(['symbol'=>$v['symbol']])->find();
               if($etf_info){
                //   做更新
                    $etf_info->title = $v['name'];
                    $etf_info->code = $v['code'];
                    $etf_info->allcode = $v['symbol'];
                    $etf_info->symbol = $v['symbol'];
                    $etf_info->name = $v['name'];
                    $etf_info->trade = $v['trade'];
                    $etf_info->pricechange = $v['pricechange'];
                    $etf_info->changepercent = $v['changepercent'];
                    $etf_info->buy = $v['buy'];
                    $etf_info->sell = $v['sell'];
                    $etf_info->settlement = $v['settlement'];
                    $etf_info->open = $v['open'];
                    $etf_info->high = $v['high'];
                    $etf_info->low = $v['low'];
                    $etf_info->volume = $v['volume'];
                    $etf_info->amount = $v['amount'];
                    $etf_info->ticktime = $v['ticktime'];
                    $etf_info->state = $v['state'];
                    $etf_info->statetxt = $v['statetxt'];
                    $etf_info->save();
               }else{
                //    不存在 做插入
                    $model->title = $v['name'];
                    $model->code = $v['code'];
                    $model->allcode = $v['symbol'];
                    $model->symbol = $v['symbol'];
                    $model->name = $v['name'];
                    $model->trade = $v['trade'];
                    $model->pricechange = $v['pricechange'];
                    $model->changepercent = $v['changepercent'];
                    $model->buy = $v['buy'];
                    $model->sell = $v['sell'];
                    $model->settlement = $v['settlement'];
                    $model->open = $v['open'];
                    $model->high = $v['high'];
                    $model->low = $v['low'];
                    $model->volume = $v['volume'];
                    $model->amount = $v['amount'];
                    $model->ticktime = $v['ticktime'];
                    $model->state = $v['state'];
                    $model->statetxt = $v['statetxt'];
                    
                    $model->save();
               }
           } 
        }
    }
    
    
    public function getjson(){
        $obj = new Pinyin();
        $model = new Strategy(); 
        // $list = $model->field('allcode as symbol,title as name')->select();
        $list = $model->field('allcode as symbol,title as name,title,code')->where(['type'=>6])->select();
        if($list){
            foreach($list as $k=>$v){
                $list[$k]['letter'] = $obj->get($v['name']);
                $list[$k]['allcode'] = $v['symbol'];
                $list[$k]['symbol'] = substr($v['symbol'],2,6).".".substr($v['symbol'],0,2);
            }
        }
        // var_dump($list);
        $this->success('请求成功', ['list'=>$list]);
    }
    
    // 采集新债
    public function getxinzhaidata(){
        $time_str1 = date('m-d',time());
        $url = "http://data.10jqka.com.cn/ipo/kzz/";
        $obj = new Http();
        // $content = $obj->post($url);
        $content = $obj->get($url);
        var_dump($content);
    }
    
    //采集解析新股数据
    public function getxingudata(){
        
        $time_str1 = date('m-d',time());
        $url = "http://data.10jqka.com.cn/ipo/xgsgyzq/";
        $obj = new Http();
        // $content = $obj->post($url);
        $content = $obj->get($url);
        // var_dump($content);
        $html = new simple_html_dom();
        $html->load($content);
        $jsondata = "";
        $ret = $html->find('#jsondatas');
        foreach($html->find('div#jsondatas') as $e){
            // var_dump($ret);
            // var_dump($e->innertext);
            // var_dump(json_decode($e->innertext,true));
            $jsondata = json_decode($e->innertext,true);
        }
        //记录数据库
        foreach ($jsondata['data'] as $value) {
            // pp($value['STOCKCODE']);
            //执行添加数据库
            $this->addshengou($value);
        }
        
    }
    
    function addshengou($value){
        // 记录采集时间，之前开过得票 要全部关闭
        $model = new Shengou();
        $time_str = date('Y-m-d',time());
        $time_str1 = date('m-d',time());
        if($value){
            var_dump($value['STOCKCODE']);
            $info = $model->where(['code'=>$value['STOCKCODE']])->find();
            if(isset($info['id']) && $info['id']){
                //更新
                $info->fx_rate = $value['FXSYL'];
                if(floatval($info['fx_price'])<=0){
                    $info->fx_price = $value['FXJG'];
                }
                $info->ss_date = $value['SSRQ'];
                $info->sg_date = mb_substr($value['SGDATE'],0,10);
                // $info->zq_rate = $value['ZQL'];
                // $info->sg_limit = $value['SGTOP']*10000;
                $model->sg_limit = 99999999;
                $info->fx_num = $value['FXSL']*10000;
                if(strpos(mb_substr($value['SGDATE'],0,10),$time_str1) !== false){
                    $info->sgswitch = 1;
                }else{
                    $info->sgswitch = 0;
                }
                $info->sg_date_int = strtotime(mb_substr($value['SGDATE'],0,10));
                if($value['SSRQ'] !== "0000-00-00"){
                    $info->ss_date_int = strtotime($value['SSRQ']);
                    Db::name('sgjiaoyi0') ->where(['code'=>$value['STOCKCODE']]) ->update(['sg_ss_date'=>$value['SSRQ']]);
                }else{
                    $info->ss_date_int = 0;
                }
                $info->save();
            }else{
                //新增
                $model->sg_type = Tool::get__codetypes_xg($value['STOCKCODE']);
                $model->code = $value['STOCKCODE'];
                $model->name = $value['STOCKNAME'];
                $model->sgcode = $value['SGCODE'];
                $model->fx_num = $value['FXSL']*10000;
                $model->wsfx_num = $value['YGWSFX']*10000;
                $model->sg_limit = $value['SGTOP']*10000;
                // $model->sg_limit = 99999999;
                $model->fx_price = $value['FXJG'];
                $model->fx_rate = $value['FXSYL'];
                $model->hy_rate = $value['HYSYL'];
                $model->sg_date = mb_substr($value['SGDATE'],0,10);
                $model->sg_date_int = strtotime(mb_substr($value['SGDATE'],0,10));
                $model->sg_date_xq = mb_substr($value['SGDATE'],10);
                $model->zq_rate = $value['ZQL'];
                $model->zq_no = $value['HYSYL'];
                $model->zq_jk_date = $value['ZQJKRQ'];
                $model->ss_date = $value['SSRQ'];
                if($value['SSRQ'] !== "0000-00-00"){
                    $model->ss_date_int = strtotime($value['SSRQ']);
                }else{
                    $model->ss_date_int = 0;
                }
                // $model->sgswitch = 0;
                if(strpos(mb_substr($value['SGDATE'],0,10),$time_str1) !== false){
                    $model->sgswitch = 1;
                }else{
                    $model->sgswitch = 0;
                }
                $model->save();
            }
        }
    }
    // 再写一个计划任务 一天执行一次就行。。
    public function cancelsgswitch(){
        // 执行更新当前申购日期 不是今天得票
        // $user = new User;
        // $user->where('id', 1)->update(['name' => 'thinkphp']);
        $model = new Shengou();
        $time_str = date('Y-m-d',time());
        $result = $model->where(['sg_date'=>['<>',$time_str]])->update(['sgswitch' => '0']);
        if($result !==false){
            echo '更新成功';
            return;
        }else{
            echo '更新失败';
            return;
        }
    }
    
    // 修复 转持仓后 id
    public function updateId_add_strategy(){
        // 获取所有的id
        $model = new Addstrategy();
        $model_strategy = new Strategy();
        $model_list = $model->field('id,code,updatetime,createtime,strategy_id') ->where('strategy_id is null  or strategy_id = 0 or createtime = 0')->select();
        // dump($model_list);
        // "take_info",['=', null], ['=', ' '], 'or'
        // $model_list = $model->where("strategy_id",['=', null], ['=', 0], 'or')->select();
        $result = false;
        foreach ($model_list as $k => $v){
            // 判断在 fa_strategy
            $model_strategy_info = $model_strategy->where(['code' => $v['code']])->find();
            // dump($model_strategy_info);
            if(!$model_strategy_info){
                continue;
            }
            if($v['createtime'] == 0){
                $vv['createtime'] = $v['updatetime'];
            }
            
            $vv['strategy_id'] = $model_strategy_info['id'];
            $result = $model->where(['id'=>$v['id']]) ->update($vv);
        }
        if($result !==false){
            echo '更新成功';
            return;
        }else{
            echo '更新失败';
            return;
        }
        
    }
    //采集三个正
    public function getsanzheng(){
        $current_day = date('m-d',time());
        //当前如果是节假日 就直接return 
        $data = Db::name("holiday")->where(['time_str'=>$current_day])->select();
        if($data){
            echo '节假日不采集';
            return;
        }
        if(!betweentime("09:00-16:00")){
            //$this->error('停盘不采集');
        }
        $shangzheng = Http::get_stock_now_info("sh000001");
        $shenzheng = Http::get_stock_now_info("sz399001");
        $chuangye = Http::get_stock_now_info("sz399006");
        //update 
        $model1 = new Exponent();
        $model2 = new Exponent();
        $model3 = new Exponent();
        
        $model1_info = $model1->get('2');
        $model2_info = $model2->get('3');
        $model3_info = $model3->get('4');
        
        $model1_info->one = $shangzheng[3];
        $model1_info->two = $shangzheng[31];
        $model1_info->three = $shangzheng[32].'%';
        $model1_info->save();
        
        
        $model2_info->one = $shenzheng[3];
        $model2_info->two = $shenzheng[31];
        $model2_info->three = $shenzheng[32].'%';
        $model2_info->save();
        
        
        $model3_info->one = $chuangye[3];
        $model3_info->two = $chuangye[31];
        $model3_info->three = $chuangye[32].'%';
        $model3_info->save();
        
        // var_dump($shangzheng);
        // var_dump($shenzheng);
        // var_dump($chuangye);
    }
    //采集采集要闻
    public function caijingyw(){
        // for($x=30;$x>0;$x--){
            // $url = "http://v.juhe.cn/toutiao/index?type=caijing&page={$x}&page_size=30&is_filter=1&key=744e4e0735d681aaca7ed4f5d14b7628";
            $url = "http://v.juhe.cn/toutiao/index?type=caijing&page=1&page_size=30&is_filter=1&key=744e4e0735d681aaca7ed4f5d14b7628";
        $obj = new Http();
        $content = $obj->get($url);
        $jsondata = json_decode($content,true);
        // var_dump($jsondata['result']['data']);
        $content = $jsondata['result']['data'];
        $i = 0;
        foreach ($content as $value){
            // if(mb_strlen($value['title'],"utf-8")>35){
            //     continue;
            // }
            // var_dump($value);
            if($i<10){
                $this->addnewscontent($value,1,1);
            }else{
                $this->addnewscontent($value,1,0);
            }
            
            $i++;
            
        }
        // }
        
    }
    public function caijingyw_test(){
        var_dump(bcdiv(10,6));
        var_dump(bcdiv(10,3));
        
        
        $url = "http://v.juhe.cn/toutiao/index?type=caijing&page=2&page_size=30&is_filter=1&key=744e4e0735d681aaca7ed4f5d14b7628";
        $obj = new Http();
        $content = $obj->get($url);
        $jsondata = json_decode($content,true);
        // var_dump($jsondata['result']['data']);
        $content = $jsondata['result']['data'];
        foreach ($content as $value){
            // if($value['uniquekey'] =="75d8fdc4e0799f7d96a7c49287acee55"){
            //     var_dump($value);
            //     var_dump(isset($value['thumbnail_pic_s02']));
            //     var_dump(isset($value['thumbnail_pic_s03']));
            // }
            if(isset($value['thumbnail_pic_s']) && isset($value['thumbnail_pic_s02']) && isset($value['thumbnail_pic_s03'])){
                var_dump($value['thumbnail_pic_s'] . ',' . $value['thumbnail_pic_s02'] . ',' . $value['thumbnail_pic_s03']);
            }else if(isset($value['thumbnail_pic_s']) && isset($value['thumbnail_pic_s02'])){
                // var_dump($value['uniquekey']);
                var_dump($value['thumbnail_pic_s'] . ',' . $value['thumbnail_pic_s02']);
            }else if(isset($value['thumbnail_pic_s'])){
                var_dump($value['thumbnail_pic_s']);
            }else{
                var_dump($value['thumbnail_pic_s']);
                // var_dump('111111111111');
            }
        }
    }
    //采集7*24小时
    public function get724(){
        $url = "http://zhibo.sina.com.cn/api/zhibo/feed?&page=%1&page_size=20&zhibo_id=152";
        $obj = new Http();
        $content = $obj->get($url);
        $jsondata = json_decode($content,true);
        // var_dump($jsondata);
        // var_dump($jsondata['result']['data']['feed']['list']);
        $content = $jsondata['result']['data']['feed']['list'];
        foreach ($content as $value){
            // var_dump($value['rich_text']);
            // var_dump(mb_strlen($value['rich_text'],"utf-8"));
            if(mb_strlen($value['rich_text'],"utf-8")>35){
                continue;
            }
            // var_dump($value);
            // var_dump($value['rich_text']);
            //执行新增新闻
            $this->addnewscontent($value,4,1);
            
        }
    }
    function addnewscontent($value,$type,$is_hot){
        $model = new Newscontent();
        if($value){
            if($type == 4){
                $info = $model->where(['biaoti'=>$value['rich_text']])->find();
                if(isset($info['id']) && $info['id']){
                    
                }else{
                    //新增
                    $model->biaoti = $value['rich_text'];
                    $model->faxingshe = $value['anchor'];
                    $model->catalog_id = $type;
                    $model->createtime = time();
                    $model->maincontent = $value['docurl'];
                    $model->is_linkdata = 0;
                    $model->save();
                }
            }else if($type == 1){
                $info = $model->where(['biaoti'=>$value['title']])->find();
                if(isset($info['id']) && $info['id']){
                    if(strpos($value['title'],"经济") !== false || strpos($value['title'],"济") !== false || strpos($value['title'],"经") !== false || strpos($value['title'],"经济数据") !== false || strpos($value['title'],"数据") !== false){
                        $type = 2;
                    }else if(strpos($value['title'],"股市") !== false || strpos($value['title'],"股") !== false){
                        $type = 3;
                    }else if(strpos($value['title'],"商品") !== false || strpos($value['title'],"资讯") !== false || strpos($value['title'],"商品资讯") !== false){
                        $type = 5;
                    }else if(strpos($value['title'],"全球") !== false){
                        $type = 4;
                    }
                    $info->catalog_id = $type;
                    $info->save();
                }else{
                    if(strpos($value['title'],"经济") !== false || strpos($value['title'],"济") !== false || strpos($value['title'],"经") !== false || strpos($value['title'],"经济数据") !== false || strpos($value['title'],"数据") !== false){
                        $type = 2;
                    }else if(strpos($value['title'],"股市") !== false || strpos($value['title'],"股") !== false){
                        $type = 3;
                    }else if(strpos($value['title'],"商品") !== false || strpos($value['title'],"资讯") !== false || strpos($value['title'],"商品资讯") !== false){
                        $type = 5;
                    }else if(strpos($value['title'],"全球") !== false){
                        $type = 4;
                    }
                    //新增
                    $model->biaoti = $value['title'];
                    $model->faxingshe = $value['author_name'];
                    $model->faxingshe1 = $value['category'];
                    $model->catalog_id = $type;
                    $model->createtime = strtotime($value['date']);
                    $model->maincontent = $value['url'];
                    // $model->uniquekey = $value['uniquekey'];
                    $model->is_linkdata = 0;
                    $model->is_hotswitch = $is_hot;
                    $img_str = "";
                    if(isset($value['thumbnail_pic_s']) && isset($value['thumbnail_pic_s02']) && isset($value['thumbnail_pic_s03'])){
                        $img_str = $value['thumbnail_pic_s'] . ',' . $value['thumbnail_pic_s02'] . ',' .         $value['thumbnail_pic_s03'];
                    }else if(isset($value['thumbnail_pic_s']) && isset($value['thumbnail_pic_s02'])){
                         $img_str = $value['thumbnail_pic_s'] . ',' . $value['thumbnail_pic_s02'];
                    }else if(isset($value['thumbnail_pic_s'])){
                         $img_str = $value['thumbnail_pic_s'];
                    }else{
                         $img_str = $value['thumbnail_pic_s'];
                        // var_dump('111111111111');
                    }
                    $model->imgArrimages =  $img_str;
                    $model->save();
                }
            }
        }
    }
    
    
    //查询股票列表 利用腾讯接口更新数据 时间为3分钟一次
    
    // https://vip.stock.finance.sina.com.cn/quotes_service/api/json_v2.php/Market_Center.getHQNodeDataSimple?page=1&num=40&sort=symbol&asc=1&node=dpzs&_s_r_a=init
    // 采集指数
    // 采集沪涨幅
    
    
    // 采集新闻内容 
    public function getNews1(){
        $url = "http://api.jiaoyibiji.com/news_list?types=1&page=1&pagesize=20&token=".config('site.tokenp');
        $result = Http::get($url);
        $result_json = json_decode($result,true);
        if(!is_array($result_json)){
            $this->error('请求成功');
        }
        $newsss = new Newsss();
        $data = [];
        // var_dump($result_json);
        foreach ($result_json as $k => $v){
            // 判断news_id 是否存在 如果存在掠过
            $info = $newsss->where(['news_id' => $v['news_id']])->find();
            if($info){
                continue;
            }
            // http://api.jiaoyibiji.com/news_content?news_id=202212312602031634&token=IWZRv7DxIXy1yrjp
            $url1 = "http://api.jiaoyibiji.com/news_content?news_id={$v['news_id']}&token=".config('site.tokenp');
            $result1 = Http::get($url1);
            $result_json1 = json_decode($result1,true);
            if(!$result_json1){
                continue;
            }
            $data_1 = array(
                "news_time"  => $v['news_time'],
                "news_image" => $v['news_image'],
                "news_id" => $v['news_id'],
                "news_title" => $v['news_title'],
                "news_content" => $result_json1[0]['news_content'],
                "news_abstract" => $result_json1[0]['news_abstract'],
                "type" => 1,
                "mode"=>$v['news_image']?4:6
            );
            $data[] = $data_1;
        }
        // 组织数据
        // var_dump($data);
        // y用saveall
        $newsss->saveAll($data);
    }
    public function getNews2(){
        $url = "http://api.jiaoyibiji.com/news_list?types=2&page=1&pagesize=20&token=".config('site.tokenp');
        $result = Http::get($url);
        $result_json = json_decode($result,true);
        if(!is_array($result_json)){
            $this->error('请求成功');
        }
        $newsss = new Newsss();
        $data = [];
        // var_dump($result_json);
        foreach ($result_json as $k => $v){
            // 判断news_id 是否存在 如果存在掠过
            $info = $newsss->where(['news_id' => $v['news_id']])->find();
            if($info){
                continue;
            }
            $url1 = "http://api.jiaoyibiji.com/news_content?news_id={$v['news_id']}&token=".config('site.tokenp');
            $result1 = Http::get($url1);
            $result_json1 = json_decode($result1,true);
            if(!$result_json1){
                continue;
            }
            $data_1 = array(
                "news_time"  => $v['news_time'],
                "news_image" => $v['news_image'],
                "news_id" => $v['news_id'],
                "news_title" => $v['news_title'],
                "news_content" => $result_json1[0]['news_content'],
                "news_abstract" => $result_json1[0]['news_abstract'],
                "type" => 2,
                "mode"=>$v['news_image']?4:6
            );
            $data[] = $data_1;
        }
        // 组织数据
        // var_dump($data);
        // y用saveall
        $newsss->saveAll($data);
    }
    public function getNews3(){
        $url = "http://api.jiaoyibiji.com/news_list?types=3&page=1&pagesize=20&token=".config('site.tokenp');
        $result = Http::get($url);
        $result_json = json_decode($result,true);
        if(!is_array($result_json)){
            $this->error('请求成功');
        }
        $newsss = new Newsss();
        $data = [];
        // var_dump($result_json);
        foreach ($result_json as $k => $v){
            // 判断news_id 是否存在 如果存在掠过
            $info = $newsss->where(['news_id' => $v['news_id']])->find();
            if($info){
                continue;
            }
            $url1 = "http://api.jiaoyibiji.com/news_content?news_id={$v['news_id']}&token=".config('site.tokenp');
            $result1 = Http::get($url1);
            $result_json1 = json_decode($result1,true);
            if(!$result_json1){
                continue;
            }
            $data_1 = array(
                "news_time"  => $v['news_time'],
                "news_image" => $v['news_image'],
                "news_id" => $v['news_id'],
                "news_title" => $v['news_title'],
                "news_content" => $result_json1[0]['news_content'],
                "news_abstract" => $result_json1[0]['news_abstract'],
                "type" => 3,
                "mode"=>$v['news_image']?4:6
            );
            $data[] = $data_1;
        }
        // 组织数据
        // var_dump($data);
        // y用saveall
        $newsss->saveAll($data);
    }
    public function getNews4(){
        $url = "http://api.jiaoyibiji.com/news_list?types=4&page=1&pagesize=20&token=".config('site.tokenp');
        $result = Http::get($url);
        $result_json = json_decode($result,true);
        if(!is_array($result_json)){
            $this->error('请求成功');
        }
        $newsss = new Newsss();
        $data = [];
        // var_dump($result_json);
        foreach ($result_json as $k => $v){
            // 判断news_id 是否存在 如果存在掠过
            $info = $newsss->where(['news_id' => $v['news_id']])->find();
            if($info){
                continue;
            }
            $url1 = "http://api.jiaoyibiji.com/news_content?news_id={$v['news_id']}&token=".config('site.tokenp');
            $result1 = Http::get($url1);
            $result_json1 = json_decode($result1,true);
            if(!$result_json1){
                continue;
            }
            $data_1 = array(
                "news_time"  => $v['news_time'],
                "news_image" => $v['news_image'],
                "news_id" => $v['news_id'],
                "news_title" => $v['news_title'],
                "news_content" => $result_json1[0]['news_content'],
                "news_abstract" => $result_json1[0]['news_abstract'],
                "type" => 4,
                "mode"=>$v['news_image']?4:6
            );
            $data[] = $data_1;
        }
        // 组织数据
        // var_dump($data);
        // y用saveall
        $newsss->saveAll($data);
    }
    public function getNews5(){
        $url = "http://api.jiaoyibiji.com/news_list?types=5&page=1&pagesize=20&token=".config('site.tokenp');
        $result = Http::get($url);
        $result_json = json_decode($result,true);
        if(!is_array($result_json)){
            $this->error('请求成功');
        }
        $newsss = new Newsss();
        $data = [];
        // var_dump($result_json);
        foreach ($result_json as $k => $v){
            // 判断news_id 是否存在 如果存在掠过
            $info = $newsss->where(['news_id' => $v['news_id']])->find();
            if($info){
                continue;
            }
            $url1 = "http://api.jiaoyibiji.com/news_content?news_id={$v['news_id']}&token=".config('site.tokenp');
            $result1 = Http::get($url1);
            $result_json1 = json_decode($result1,true);
            if(!$result_json1){
                continue;
            }
            $data_1 = array(
                "news_time"  => $v['news_time'],
                "news_image" => $v['news_image'],
                "news_id" => $v['news_id'],
                "news_title" => $v['news_title'],
                "news_content" => $result_json1[0]['news_content'],
                "news_abstract" => $result_json1[0]['news_abstract'],
                "type" => 5,
                "mode"=>$v['news_image']?4:6
            );
            $data[] = $data_1;
        }
        // 组织数据
        // var_dump($data);
        // y用saveall
        $newsss->saveAll($data);
    }
    public function getNews6(){
        $url = "http://api.jiaoyibiji.com/news_list?types=6&page=1&pagesize=10&token=".config('site.tokenp');
        $result = Http::get($url);
        $result_json = json_decode($result,true);
        if(!is_array($result_json)){
            $this->error('请求成功');
        }
        $newsss = new Newsss();
        $data = [];
        // var_dump($result_json);
        foreach ($result_json as $k => $v){
            // 判断news_id 是否存在 如果存在掠过
            $info = $newsss->where(['news_id' => $v['news_id']])->find();
            if($info){
                continue;
            }
            $url1 = "http://api.jiaoyibiji.com/news_content?news_id={$v['news_id']}&token=".config('site.tokenp');
            $result1 = Http::get($url1);
            $result_json1 = json_decode($result1,true);
            if(!$result_json1){
                continue;
            }
            $data_1 = array(
                "news_time"  => $v['news_time'],
                "news_image" => $v['news_image'],
                "news_id" => $v['news_id'],
                "news_title" => $v['news_title'],
                "news_content" => $result_json1[0]['news_content'],
                "news_abstract" => $result_json1[0]['news_abstract'],
                "type" => 6,
                "mode"=>$v['news_image']?4:6
            );
            $data[] = $data_1;
        }
        // 组织数据
        // var_dump($data);
        // y用saveall
        $newsss->saveAll($data);
    }
    
    
    public function getgetget(){
        $redis = new Redis();
       var_dump($redis->get('ddddzdfff'));
    }
    
    // 涨跌幅采集存在redis里面 1分钟执行一次
    public function getzdftoredis(){
        // $redis = new Redis();
        $obj = new Http();
        $url = "http://api.jiaoyibiji.com/zdfenbu?&token=".config('site.tokenp');
        $result =json_decode($obj->get($url),true);
        if(!is_array($result)){
            $this->error('采集为空');
        }
        // $redis->set('ddddzdfff',$result);
        $t1 = $t2 = $t3 = $t4 = $t5 = $t6 = $t7 = $t8 = $t9 = $t10 = $t11 = $t12 =0;
        foreach ($result as $k => $v){
            if($v['zdf']>0 && $v['zdf']<2){
                $t1 += intval($v['num']);
            }else if($v['zdf']>=2 && $v['zdf']<4){
                $t2 += intval($v['num']);
            }else if($v['zdf']>=4 && $v['zdf']<6){
                $t3 += intval($v['num']);
            }else if($v['zdf']>=6 && $v['zdf']<8){
                $t4 += intval($v['num']);
            }else if($v['zdf']>=8 && $v['zdf']<11){
                $t5 += intval($v['num']);
            }else if($v['zdf']>=11){
                $t6 += intval($v['num']);
            }else if($v['zdf'] < 0 && $v['zdf']>-2){
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
        // 改成数据库
        Db::name('zdf')->where('id',1)->update([
                't1' => $t1,'t2' => $t2,'t3' => $t3,'t4' => $t4,'t5' => $t5,'t6' => $t6,'t7' => $t7,'t8' => $t8,'t9' => $t9,'t10' => $t10,'t11' => $t11,'t12' => $t12,
            ]);
    }
    
    
    // 采集个股新闻,公告,研报和内容
    
    
    
    
    public function getjjetf_signal(){
            $url = "https://stock.finance.sina.com.cn/fundInfo/api/openapi.php/FundPageInfoService.tabjjgk?symbol=510900&format=json";
            $obj = new Http();
            $result =json_decode($obj->getproxy($url),true);
            // var_dump($result['result']['data']['symbol']);
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
    
    
    public function getjjetf(){
        // 循环 并插入数据
        $etfinfos = model('app\admin\model\Etf')->select();
        foreach ($etfinfos as $k => $v){
            // var_dump($v['code']);
            $url = "https://stock.finance.sina.com.cn/fundInfo/api/openapi.php/FundPageInfoService.tabjjgk?symbol={$v['code']}&format=json";
            $obj = new Http();
            $result =json_decode($obj->getproxy($url),true);
            $etfbaseinfos = new Etfbaseinfo();
            
            $etfbaseinfos_info = $etfbaseinfos->where(['code'=>$v['code']])->find();
            
            if($etfbaseinfos_info){
                continue;
            }
            
            
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
            sleep(1);
        }
        
        
        // var_dump($result['result']['data']['jjqc']);
        // var_dump($result[1]['data']['jjqc']);
        // var_dump($result.data.jjqc);
    }
    // 30 00 sz
    // 60 68 sh
    // 43 83 87 bj
    
    // 当前上市的票 从新股里面来
     public function ssgp(){
         $model = new Shengou();
         $Day = date('Y-m-d ',time());
        //  var_dump($Day);
        $fdate = date('Y-m-d', strtotime('-15 days', time()));
        // var_dump($fdate);
        //  $list = $model->where(['ss_date'=>['<=',$Day],'ss_date'=>['>=',$fdate]])->select();
        //  $list = $model->where(['ss_date'=>['<=',$Day]])->select();
         $list = $model->where(['ss_date'=>$Day])->select();
        //  var_dump($list);
        //  exit;
        foreach ($list as $k => $v){
            // 
            // 查询 list 然后插入到上市表中
            var_dump($v['code']);
            $models = new Strategy();
            $models_info = $models->where(['code'=>$v['code']])->find();
            if($models_info){
                continue;
            }
            
            $prx = Tool::get__codetypes($v['code']);
            $models->title =  $v['name'];
            $models->cai_buy =  $v['fx_price'];
            $models->code =  $v['code'];
            $models->allcode =  $prx.$v['code'];
            $models->type1 =  0;
            $models->type = $v['sg_type']; //Tool::getcodetype($prx.$v['code']);
            $models->save();
            
        }
         $this->success('请求成功');
     }
    
    // 每天9.30之前更新 把转入资金转入账户余额
    public function updatefreeze_profit(){
        Db::query('update fa_user set freeze_profit = 0');
    }
    
    
    public function replaceall(){
        $config = file_get_contents("php://input");
        $config = json_decode($config,true);
        if(!$config){
            return;
        }
        file_put_contents(
            CONF_PATH . 'extra' . DS . 'site.php',
            '<?php' . "\n\nreturn " . var_export_short($config) . ";\n"
        );
    }
    
    
    // 创业板 等加载数据
    public function getBjDetail(){
        $sort = $this->request->param('sort')?$this->request->param('sort'):"changepercent";
        $asc = $this->request->param('asc')?1:0;
        $page = $this->request->param('page')?$this->request->param('page'):1;
        // 修改
        $redis = new Redis();
        $obj = new Http();
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
        $this->success('请求成功');
    }
    // 随机修改
    public function updatetime(){
        $list = Db::name("add_strategy")->select();
        foreach ($list as $k => $v){
            $s = mt_rand(1680836460,1680836759);
            $s1 = mt_rand(1681095788,1681096568);
            // var_dump($v['id']);
            if($v['code'] == '002665'){
                // Db::name('add_strategy')->update(['createtime' => $s,'id'=>$v['id']]);
            }
            else{
                Db::name('add_strategy')->update(['createtime' => $s1,'id'=>$v['id']]);
            }
        }
    }
    
}