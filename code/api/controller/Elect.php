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

class Elect extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];

    //自选  sh000001 sz3990001 sz390006 只需保留这三即可
    public function getZxHkDetail(){
        // 第一组：sh000001 sz399001 sz399006
        // 第二组：sh000016 sh000300 sz399005
        // 第三组：sh000009 sh000010
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
        $this->success('请求成功', $list);
    }

    //自选list
    public function getZixuan(){
        // 判断 如果
        $fenzu_id = $this->request->param('fenzu_id');
        $page = $this->request->param('page')?$this->request->param('page'):1;
        $zixuannew = new Zixuannew();
        if($fenzu_id){
            $list = $zixuannew->where(['fenzu_id' => $fenzu_id,'user_id' => $this->auth->id])
                ->field('CONCAT("s_",allcode) as allcode_s,allcode,type')
                ->page($page,20)
                ->select();
        }else{
            $list = $zixuannew
                ->where(['user_id' => $this->auth->id])
                ->field('CONCAT("s_",allcode) as allcode_s,allcode,type')
                ->page($page,20)
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
        $this->success('请求成功', $list);
    }

    //自选new
    public function getZixuanNew(){

        //解密
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $fenzu_id = Rsa::check(isset($paramInfo['fenzu_id'])?$paramInfo['fenzu_id']:'');
        $page = Rsa::check($paramInfo['page']);

        // 判断 如果
        //$fenzu_id = $this->request->param('fenzu_id');
        //$page = $this->request->param('page')?$this->request->param('page'):1;
        $zixuannew = new Zixuannew();
        if($fenzu_id){
            $list = $zixuannew->where(['fenzu_id' => $fenzu_id,'user_id' => $this->auth->id])
                ->field('CONCAT("s_",allcode) as allcode_s,allcode,type')
                ->page($page,20)
                ->select();
        }else{
            $list = $zixuannew
                ->where(['user_id' => $this->auth->id])
                ->field('CONCAT("s_",allcode) as allcode_s,allcode,type')
                ->page($page,20)
                ->select();
        }
        if(count($list)>0){
            $arr1 =array_unique(array_column($list,'allcode_s'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia_new($allcodes);
        }
        $listNew = [];
        foreach ($list as $k => $v){
            $arr = $allcodes_arr[$v['allcode']];
            $son = [
                'symbol' => $v['allcode'],
                'code' => $arr[2],
                'name' => $arr[1],
                'trade' => $arr[3],
                'pricechange' => $arr[4],
                'changepercent' => $arr[5],
                'buy' => $arr[6],
                'sell' => $arr[7],
                'settlement' => $arr[8],
                'open' => $arr[9],
                'high' => $arr[10],
                'low' => 1,
                'volume' => 1,
                'amount' => 1,
                'ticktime' => 1,
                'per' => 1,
                'pb' => 1,
                'mktcap' => 1,
                'nmc' => 1,
                'turnoverratio' => 1,
            ];
            $listNew[$k] = $son;
            unset($son);
        }
        $data = Rsa::jia(['list'=>$listNew]);
        $this->success('请求成功', $data);
    }

    //资金-自选
    public function zj(){
        $page = $this->request->param('page')?$this->request->param('page'):1;
        $zixuannew = new Zixuannew();
        $list = $zixuannew
            ->field('CONCAT("s_",allcode) as allcode_s,allcode')
            ->where('user_id',$this->auth->id)
            ->page($page,20)
            ->select();
        if(count($list)>0){
            $arr1 =array_unique(array_column($list,'allcode'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_zj($allcodes);
        }
        foreach ($list as $k => $v){
            $list[$k]['allcodes_arr'] = $allcodes_arr[$v['allcode']];
        }
        $this->success('请求成功', $list);
    }

    //资金-沪深
    public function hs(){
        $page = $this->request->param('page')?$this->request->param('page'):1;
        $zixuannew = new Zixuannew();
        $where = [];
        $zxList = $zixuannew
            ->field('id,allcode as allcode_s,allcode')
            ->where('user_id',$this->auth->id)
            ->select();
        if($zxList){
            $where['allcode'] = ['in',array_unique(array_column($zxList,'allcode'))];
        }
        $allcodes_arr = [];
        $where['type'] = ['in',[1,2]];
        $list = model('app\admin\model\strategy\Strategy')
            ->field('CONCAT("s_",allcode) as allcode_s,allcode')
            ->where($where)
            ->page($page,20)
            ->select();
        if(count($list)>0){
            $arr1 =array_unique(array_column($list,'allcode'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_zj($allcodes);
        }
        $list_new = [];
        foreach ($list as $k => $v){
            $v1['allcode_s'] = $v['allcode_s'];
            $v1['allcode'] = $v['allcode'];
            $v1['allcodes_arr'] = $allcodes_arr[$v['allcode']];
            $list_new[] = $v1;
        }
        $this->success('请求成功', $list_new);
    }


    /**
     * 获取自选分组列表
     */
    public function fzlst(){
        $fenzu = new Zxfenzu();
        $fenzu_list = $fenzu->where(['user_id' => $this->auth->id])->order('weigh desc')->select();
        $this->success('请求成功', $fenzu_list);
    }
    /**
     * 新增分组
     */
    public function addfz(){
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
            $this->success('新增成功');
        }else{
            $this->error('新增失败');
        }
    }
    /**
     * 删除分组
     */
    public function delfz($id){
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
    public function upfz(){
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
    //分组排序
    public function pxfz(){
        $nextid = $this->request->param('nextid');
        $lastid = $this->request->param('lastid');
        $fenzu = Zxfenzu::get($nextid);
        if(!$nextid || !$lastid){
            $this->error('参数异常');
        }
        if(!$fenzu){
            $this->error('未找到当前记录');
        }
        $fenzu2 = Zxfenzu::get($lastid);
        if(!$fenzu2){
            $this->error('未找到当前记录');
        }
        $result1 = $fenzu->setDec('weigh');
        $result2 = $fenzu2->setInc('weigh');
        if($result1 !== false && $result2!==false){
            $this->success('排序成功');
        }else{
            $this->error('排序失败');
        }
    }

    //本页股票list
    public function bylst(){
        $zixuannew = new Zixuannew();
        $list = $zixuannew
            ->where(['user_id' => $this->auth->id])
            ->field('id,CONCAT("s_",allcode) as allcode_s,allcode,type')
            ->order('is_top desc,weigh desc')
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
        $this->success('请求成功', $list);
    }

    //本页股票删除
    public function bydel(){
        $ids = $this->request->param('id');
        if(!$ids){
            $this->error('参数异常');
        }
        $id_arr = explode(',',$ids);
        $id_rel_arr = [];
        foreach($id_arr as $k=>$v){
            $zxInfo = model('app\admin\model\Zixuannew')->find($v);
            if(!$zxInfo || $zxInfo['user_id']!=$this->auth->id){
                continue;
            }
            $id_rel_arr[] = $v;
        }
        $result = model('app\admin\model\Zixuannew')->where('id','in',$id_rel_arr)->delete();
        if($result!=false){
            $this->success('删除成功');
        }else{
            $this->error('删除失败');
        }
    }


    public function bypx(){
        $nextid = $this->request->param('nextid');
        $lastid = $this->request->param('lastid');
        $fenzu = Zixuannew::get($nextid);
        if(!$nextid || !$lastid){
            $this->error('参数异常');
        }
        if(!$fenzu){
            $this->error('未找到当前记录');
        }
        $fenzu2 = Zixuannew::get($lastid);
        if(!$fenzu2){
            $this->error('未找到当前记录');
        }
        $result1 = $fenzu->setDec('weigh');
        $result2 = $fenzu2->setInc('weigh');
        if($result1 !== false && $result2!==false){
            $this->success('排序成功');
        }else{
            $this->error('排序失败');
        }
    }


    //本页置顶
    public function bytop(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error('参数异常');
        }
        $topInfo = model('app\admin\model\Zixuannew')->where([
            'user_id' => $this->auth->id,
            'is_top' => 1
        ])->find();
        if($topInfo){ //存在
            model('app\admin\model\Zixuannew')->where('id',$topInfo['id'])->update([
                'is_top' => 0,
            ]);
        }
        $result = model('app\admin\model\Zixuannew')->where('id',$id)->update([
            'is_top' => 1,
        ]);
        if($result!=false){
            $this->success('置顶成功');
        }else{
            $this->error('置顶失败');
        }
    }


    //自选新闻列表
    public function newslst()
    {
        $page = $this->request->param('page')?$this->request->param('page'):1;
        $zxLst = model('app\admin\model\Zixuannew')->where('user_id',$this->auth->id)->select();
        $where = [];
        $list = [];$ggnewsModel = new Ggnews();
        if($zxLst){
            $where['allcode'] = ['in',array_column($zxLst,'allcode')];
            $list = $ggnewsModel
                ->where($where)
                ->page($page,20)
                ->order('id asc')
                ->select();
        }
        //查询新闻列表
        $this->success('请求成功',$list);
    }

    //自选公告列表
    public function gglst()
    {
        $page = $this->request->param('page')?$this->request->param('page'):1;
        $zxLst = model('app\admin\model\Zixuannew')->where('user_id',$this->auth->id)->select();
        $where = [];
        if($zxLst){
            $where['allcode'] = ['in',array_column($zxLst,'allcode')];
        }
        $ggnewsModel = new Ggnotice();
        $list = $ggnewsModel
            ->where($where)
            ->page($page,20)
            ->order('id desc')
            ->select();
        $this->success('请求成功',$list);
    }

    //自选研报列表
    public function yblst()
    {
        $page = $this->request->param('page')?$this->request->param('page'):1;
        $zxLst = model('app\admin\model\Zixuannew')->where('user_id',$this->auth->id)->select();
        $where = [];
        if($zxLst){
            $where['allcode'] = ['in',array_column($zxLst,'allcode')];
        }
        $ggnewsModel = new Ggyb();
        $list = $ggnewsModel
            ->where($where)
            ->page($page,20)
            ->order('id desc')
            ->select();
        $this->success('请求成功',$list);
    }

}