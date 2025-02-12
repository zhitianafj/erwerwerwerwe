<?php
namespace app\api\controller;

use app\admin\model\Admin;
use app\admin\model\Adv;
use app\admin\model\Agreement;
use app\admin\model\Banner;
use app\admin\model\Nav;
use app\admin\model\strategy\Strategy;
use app\common\controller\Api;
use fast\Http;
use fast\Rsa;
use fast\Objtojson;
use app\admin\model\Bankuai;
use app\admin\model\Bankuaicf;

class News extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    public function index(){
        $model = new \app\admin\model\news\News();
        $list = $model
            ->field('id,title,createtime,is_read')->order('id desc')->where(['user_id'=>$this->auth->id])
            ->paginate(50)
            ->each(function($data, $key){
                $data['createtime'] = date('Y-m-d',$data['createtime']);
                return $data;
            });
        $this->success('请求成功', ['list'=>$list]);
    }
    public function index1(){
        $model = new \app\admin\model\news\News();
        $info = $model
            ->field('id,title,createtime,is_read,content')->order('id desc')
            ->where(['user_id'=>$this->auth->id,'is_t'=>1,'is_read'=>0])
            ->find();
        $this->success('请求成功', ['list'=>$info]);
    }
    public function detail(){
        //解密
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $id = Rsa::check($paramInfo['id']);
        $model = new \app\admin\model\news\News();
        $detail = $model
            ->field('content')
            ->where('id',$id)
            ->find();
        //并且修改状态
        $detailm = $model::get($id);
        $detailm->is_read = 1;
        $detailm->save();
        $data = Rsa::jia(['detail'=>$detail['content']]);
        $this->success('请求成功', $data);
    }


    public function allread(){
        $model = new \app\admin\model\news\News();
        $list = $model->where('is_read',0)->select();
        foreach($list as $k=>$v){
            $detailm = $model::get($v['id']);
            $detailm->is_read = 1;
            $detailm->save();
        }
        $this->success('已读成功');
    }
    
    
    
    // -------------------------------------------------
    // 采集板块信息到数据库中
    public function caijibankuai_info(){
        $url = "http://api.jiaoyibiji.com/bksbmlst?&token=NJKOZpcxTF8OvTmn";
        $result = Http::get($url);
        $result_json = json_decode($result,true);
        // var_dump($result_json);
        foreach ($result_json as $k => $v){
            // var_dump($v['key']);
            // var_dump($v['datas']);
            foreach ($v['datas'] as $kk => $vv){
                // var_dump($vv);
                // 执行修改语句
                $bankuai           = new Bankuai;
                $bankuai->key     = $v['key'];
                $bankuai->bkcode    = $vv['bkcode'];
                $bankuai->bkname    = $vv['bkname'];
                $bankuai->save();
            }
        }
        $this->success('请求成功');
        // var_dump($result);
    }
    
    // 采集板块下的成分股
    public function  caijibankuai_info_cf(){
        // 先所有的加载第一页
        // $url = "http://api.jiaoyibiji.com/bkitemlst?code=BK1015&page=1&fid=zdf&px=1&token=NJKOZpcxTF8OvTmn";
        // $result = Http::get($url);
        // var_dump($result);
        // $result_json = json_decode($result,true);
        $bankuai = new Bankuai;
        $list = $bankuai->where('id','>',438)->select();
        // var_dump($list->toArray());
        // var_dump(Objtojson::object2array($list));
        $list_json = Objtojson::object2array($list);
        foreach ($list_json as $k => $v){
            // var_dump($v['bkcode']);
            $url = "http://api.jiaoyibiji.com/bkitemlst?code={$v['bkcode']}&page=1&fid=zdf&px=1&token=NJKOZpcxTF8OvTmn";
            $result = Http::get($url);
            if(strpos($result,'500 Internal Server Error') !== false){ 
                continue;
            }
            $result_json = json_decode($result,true);
            if(!is_array($result_json)){
                continue;
            }
            foreach ($result_json as $kk => $vv){
                // var_dump($vv);
                // 执行修改语句
                $bankuaicf           = new Bankuaicf;
                $bankuaicf->bankcode     = $v['bkcode'];
                $bankuaicf->stockcode    = $vv['stockcode'];
                $bankuaicf->stockname    = $vv['stockname'];
                
                $bankuaicf->zdf     = $vv['zdf'];
                $bankuaicf->close    = $vv['close'];
                $bankuaicf->zhangdie    = $vv['zhangdie'];
                
                $bankuaicf->zongshou     = $vv['zongshou'];
                $bankuaicf->jin_e    = $vv['jin_e'];
                $bankuaicf->zhenfu    = $vv['zhenfu'];
                
                $bankuaicf->huanshoulv     = $vv['huanshoulv'];
                $bankuaicf->liangbi    = $vv['liangbi'];
                $bankuaicf->year_zdf    = $vv['year_zdf'];
                $bankuaicf->save();
            }
        }
        $this->success('请求成功');
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}