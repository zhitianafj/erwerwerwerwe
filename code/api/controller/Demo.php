<?php

namespace app\api\controller;

use app\common\controller\Api;
use fast\Rsa;
use fast\Rsahelper;
use fast\Tool;
use think\Db;
use think\Request;
use think\Config;
use simple_html_dom;
use fast\Http;
use fast\Random;
use ParagonIE\EasyRSA\EasyRSA;
use ParagonIE\EasyRSA\KeyPair;
/**
 * 示例接口
 */
class Demo extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['test', 'jia','getSkey','jia','jie','decryptByPublicKey','encryptByPrivatekey','test1','jk_dyfee','getxingudata','autosell','getnews','gettest','gettest111','gettttt','gettest222'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['test2'];

    public function jia(){
        $data = $this->request->post('param');
        $rsa = new Rsahelper();
        return $rsa->PublicEncrypt($data);
    }


    public function jie(){
        $data = $this->request->post('param');
        $data = urldecode($data);
        $rsa = new Rsahelper();
        return $rsa->PrivateDecrypt($data);
    }
    
    public function gettttt(){
        $result_json = json_decode(' ', true);
        // $obj = new Http();
        // $result = $obj->get_single_data_xl('sz000001');
        // var_dump($result);
    }
    
    
    public function gettest(){
        $obj = new Http();
        // $result = $obj->get_test_x('http://hq.sinajs.cn/list=sh601003,sh601001','http://finance.sina.com.cn');
        // var_dump($result);
        // https://hq.sinajs.cn/rn=bp9wq&list=hf_AHD,hf_BO,
        $r = Random::alnum(5);
        // $result = $obj->get_xl('https://hq.sinajs.cn/rn='.$r.'&list=hf_AHD,hf_BO',[],[],'https://finance.sina.com.cn');
        $result = $obj->get_xl('https://hq.sinajs.cn/rn='.$r.'&list=sz000001',[],[],'https://finance.sina.com.cn');
        var_dump($result);
    }
    
    public function gettest111(){
        $obj = new Http();
        $result = $obj->get_quanqiu_data("hf_AHD,hf_BO");
        // 进行数据格式化
        var_dump($result);
    }
    
    public function gettest222(){
        $obj = new Http();
        $result = $obj->get_quanqiu_data_ds("sz002411,sz301276");
        // 进行数据格式化
        var_dump($result);
    }
    /**
     * 测试方法
     *
     * @ApiTitle    (测试名称)
     * @ApiSummary  (测试描述信息)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/demo/test/id/{id}/name/{name})
     * @ApiHeaders  (name=token, type=string, required=true, description="请求的Token")
     * @ApiParams   (name="id", type="integer", required=true, description="会员ID")
     * @ApiParams   (name="name", type="string", required=true, description="用户名")
     * @ApiParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据返回")
     * @ApiReturn   ({
         'code':'1',
         'msg':'返回成功'
        })
     */
    //自动平仓
    public function autosell(){
        $result2 = false;
        var_dump(isset($result2));
    }
    public function getnews(){
        $obj = new Http();
        $content = $obj->get("https://mini.eastday.com/mobile/220325165959391253103.html");
        var_dump($content);
    }
    public function jk_dyfee(){
        //结算递延费 每天  
        //查询所有配资的 没有卖出的
        //判断今天是否是 节假日
        //打开 JSON 文件转成json数组 判断 是否是节假日
        // $json_string = file_get_contents('../public/2022_data.json');
        // $data = json_decode($json_string, true);
        // // var_dump($data);
        // foreach ($data as $k => $v){
        // }
        // $s = strtotime('2022-01-01 00:00:00');
        // $e = strtotime('2022-12-31 00:00:00');
        
        // for($i=$s; $i<=$e; $i+=86400){
        //     if(date('w', $i) == 0 || date('w', $i) == 6){
        //         Db::name("holiday")->insert(['time_str'=>date('m-d',$i)]);
        //     }
        // }
        $current_day = date('m-d',time());
        //当前如果是节假日 就直接return 
        $data = Db::name("holiday")->where(['time_str'=>$current_day])->select();
        if($data){
            echo '节假日不收取递延费';
            return;
        }
        //监控收取递延费
        $list = Db::name("add_strategy")->where(['buytype'=>3,'status'=>1])->select();
        foreach ($list as $k => $v) {
            $create_time = strtotime(date('Y-m-d',$v["createtime"]));
            $current_time = strtotime(date('Y-m-d',time()));

            $days=round(($current_time-$create_time)/3600/24) ;
            // var_dump($days);
            if($days<2){
                echo '当前订单ID:'.$v['id'].'小于2天';
                continue;
            }
            //收取递延费  当前市值 * config('site.dyf_fee')
            $addMoney = bcmul($v['cityValue'], config('site.dyf_fee'), 2);
            //收取递延费
            Tool::addLog($v['user_id'],"递延费",0,0,$addMoney,0,$v['id']);
            // var_dump($create_time);
            //获取当前时间-购买时间 相差两天
            Db::name('user')->where('id',$v['user_id'])->setDec('balance',$addMoney);
            
            $this->success('扣除成功');
        }
    }
    public function getxingudata1(){
        $url = "http://data.10jqka.com.cn/ipo/xgsgyzq/";
        $obj = new Http();
        // $content = $obj->post($url);
        $content = $obj->get($url);
        // var_dump($content);
        $html = new simple_html_dom();
        $html->load($content);
        $jsondata = "";
        // $ret = $html->find('#jsondatas');
        foreach($html->find('div#jsondatas') as $e){
            // var_dump($ret);
            // var_dump($e->innertext);
            // var_dump(json_decode($e->innertext,true));
            $jsondata = json_decode($e->innertext,true);
        }
        var_dump($jsondata);
        //记录数据库
        // foreach ($jsondata['data'] as $value) {
        //     pp($value);
        // }
        // $url = "http://data.10jqka.com.cn/ipo/xgsgyzq/board/all/field/SGDATE/page/1/order/desc/ajax/1/";
        // $url = "http://www.baidu.com";
        // $content = file_get_html($url);
        
    }
    function pp($arr){
         echo "<pre>";
         print_r($arr);
        echo "</pre>";
    }
    //采集解析新股数据
    public function getxingudata(){
        $url = "http://data.10jqka.com.cn/ipo/xgsgyzq/";
        $obj = new Http();
        // $content = $obj->post($url);
        $content = $obj->get($url);
        // var_dump($content);
        $html = new simple_html_dom();
        $html->load($content);
        $jsondata = "";
        // $ret = $html->find('#jsondatas');
        foreach($html->find('div#jsondatas') as $e){
            // var_dump($ret);
            // var_dump($e->innertext);
            // var_dump(json_decode($e->innertext,true));
            $jsondata = json_decode($e->innertext,true);
        }
        //记录数据库
        foreach ($jsondata['data'] as $value) {
            pp($value);
            //执行添加数据库
            
        }
        // $url = "http://data.10jqka.com.cn/ipo/xgsgyzq/board/all/field/SGDATE/page/1/order/desc/ajax/1/";
        // $url = "http://www.baidu.com";
        // $content = file_get_html($url);
        
    }
    
    
    public function test()
    {
        Tool::addLog(1,1,1,1,1,1);
    }

    /**
     * 无需登录的接口
     *
     */
    public function test1()
    {
        $this->success('返回成功', ['action' => 'test1']);
    }

    /**
     * 需要登录的接口
     *
     */
    public function test2()
    {
        $this->success('返回成功', ['action' => 'test2']);
    }

    /**
     * 需要登录且需要验证有相应组的权限
     *
     */
    public function test3()
    {
        $this->success('返回成功', ['action' => 'test3']);
    }

}
