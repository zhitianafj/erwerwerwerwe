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

class Person extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    //我要吐槽
    public function roast(){
        $c_details = $this->request->post('content');
        if(!$c_details){
            $this->error('参数异常');
        }
        $model = new Roast();
        $data = [
            'user_id' => $this->auth->id,
            'c_details' => $c_details
        ];
        $model->data($data);
        $result = $model->save();
        if($result!=false){
            $this->success('吐槽成功');
        }else{
            $this->error('吐槽失败');
        }
    }

    //开启消息通知
    public function inform(){
        $is_inform = $this->request->post('is_inform');
        if(!$is_inform){
            $is_inform = 0;
        }
        $result = Db::name('user')->where('id',$this->auth->id)->update([
            'is_inform' => $is_inform,
        ]);
        if($result!=false){
            $this->success('设置成功');
        }else{
            $this->error('设置失败');
        }
    }
}