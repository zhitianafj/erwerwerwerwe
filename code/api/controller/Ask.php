<?php

namespace app\api\controller;

use app\admin\model\Admin;
use app\admin\model\Adv;
use app\admin\model\Agreement;
use app\admin\model\Attention;
use app\admin\model\Banner;
use app\admin\model\Beizhu;
use app\admin\model\Cai;
use app\admin\model\feedback\Problem;
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
use app\admin\model\Ggyb;
use app\admin\model\Ggnotice;
use app\admin\model\Companyjk;
use app\admin\model\Etf;

class Ask extends Api
{
    protected $noNeedLogin = ['newslst','newsdetail','gglst','gglx','ggdetail','yblst','ybdetail','jj'];
    protected $noNeedRight = ['*'];


    public function lock($key,$expTime){
        $isLock = $this->redis->setnx($key,time()+$expTime);
        if($isLock){
            return true;
        }else{
            $val = $this->redis->get($key);
            if($val && $val<time()){
                $this->redis->del($key);
                return $this->redis->setnx($key,time()+$expTime);
            }
            return false;
        }
    }

    //个股新闻列表
    public function newslst()
    {
        // 传入allcode  根据 news_time 对比当前时间 如果当前时间没有 就再采集 并存入数据
        $allcode = $this->request->param('allcode');
        $code = $this->request->param('code');
        $page = $this->request->param('page') ? $this->request->param('page') : 1;
        // 只要当前时间过了零点，就点击采集一次，然后再查询获取所有的

        $codeCai = model('app\admin\model\Cai')->where(['allcode' => $allcode, 'type' => 'xw'])->find();
        $twelve_time = date('Y-m-d', time()) . ' 00:00:00';
        // if (time() > strtotime($twelve_time)) {
        //     if($this->lock('new_cache',5)) {
        //         if (!$codeCai || ($codeCai['createtime'] < strtotime($twelve_time))) {
        //             $url = "http://api.jiaoyibiji.com/stockinfo_list?code={$code}&page=1&token=" . config('site.tokenp');
        //             $result = Http::get($url);
        //             if(!$result){
        //                 $result_json = json_decode($result, true);
        //                 if (is_array($result_json)) {
        //                 $data = [];
        //                 foreach ($result_json as $k => $v) {
        //                     $ggnews = new Ggnews();
        //                     // 判断news_id 是否存在 如果存在掠过
        //                     $info = $ggnews->where('news_id', $v['news_id'])->find();
        //                     if ($info) {
        //                         continue;
        //                     }
        //                     $url1 = "http://api.jiaoyibiji.com/stockinfo_content?news_id={$v['news_id']}&token=" . config('site.tokenp');
        //                     $result1 = Http::get($url1);
        //                     $result_json1 = json_decode($result1, true);
        //                     $data_1 = array(
        //                         "allcode" => $allcode,
        //                         "code" => $code,
        //                         "news_time" => $v['news_time'],
        //                         "news_id" => $v['news_id'],
        //                         "news_title" => $v['news_title'],
        //                         "art_title" => $result_json1[0]['art_title'],
        //                         "art_content" => $result_json1[0]['art_content'],
        //                         "art_abstract" => $result_json1[0]['art_abstract'],
        //                         "art_time" => $result_json1[0]['art_time']
        //                     );
        //                     $data[] = $data_1;
        //                 }
        //                 $ggnews = new Ggnews();
        //                 $ggnews->saveAll($data);
        //                 if (!$codeCai) {
        //                     $codeCaiModel = new Cai();
        //                     $codeCaiModel->data([
        //                         'allcode' => $allcode,
        //                         'type' => 'xw',
        //                         'createtime' => time()
        //                     ]);
        //                     $codeCaiModel->save();
        //                 } else {
        //                     model('app\admin\model\Cai')->where('id', $codeCai['id'])->update([
        //                         'createtime' => time()
        //                     ]);
        //                 }
        //             }
        //             }

        //         }
        //     }
        // }
        //查询新闻列表
        $ggnewsModel = new Ggnews();
        $list = $ggnewsModel
            ->where('allcode', $allcode)
            ->page($page, 20)
            // ->order('id desc')
            ->select();

        $this->success('请求成功', $list);
    }

    //新闻详情
    public function newsdetail(){
        $id = $this->request->param('id');
        $ggnewsModel = new Ggnews();
        $info = $ggnewsModel->find($id);
        $this->success('请求成功',$info);
    }



    //广告列表
    public function gglst()
    {
        // 传入allcode  根据 news_time 对比当前时间 如果当前时间没有 就再采集 并存入数据
        $allcode = $this->request->param('allcode');
        $code = $this->request->param('code');
        $page = $this->request->param('page')?$this->request->param('page'):1;
        $column_name = $this->request->param('column_name');
        $where = [];
        if($column_name){
            $where['column_name'] = $column_name;
        }
        // 只要当前时间过了零点，就点击采集一次，然后再查询获取所有的
        $codeCai = model('app\admin\model\Cai')->where(['allcode'=>$allcode,'type'=>'gg'])->find();
        $twelve_time = date('Y-m-d',time()).' 00:00:00';
        // if(time() > strtotime($twelve_time)) {
        //     if (!$codeCai || ($codeCai['createtime'] < strtotime($twelve_time))) {
        //         $ggnews = new Ggnotice();
        //         $url = "http://api.jiaoyibiji.com/stocknotice_list?code={$code}&page=1&token=" . config('site.tokenp');
        //         $result = Http::get($url);
        //         if(!$result){
        //             $result_json = json_decode($result, true);
        //             if (is_array($result_json)) {
        //             $data = [];
        //             foreach ($result_json as $k => $v) {
        //                 // 判断news_id 是否存在 如果存在掠过
        //                 $info = $ggnews->where(['notice_id' => $v['notice_id']])->find();
        //                 if ($info) {
        //                     continue;
        //                 }
        //                 $url1 = "http://api.jiaoyibiji.com/stocknotice_content?notice_id={$v['notice_id']}&token=" . config('site.tokenp');
        //                 $result1 = Http::get($url1);
        //                 $result_json1 = json_decode($result1, true);
        //                 $data_1 = array(
        //                     "allcode" => $allcode,
        //                     "code" => $code,
        //                     "notice_id" => $v['notice_id'],
        //                     "notice_date" => $v['notice_date'],
        //                     "notice_title" => $v['notice_title'],
        //                     "column_name" => $v['column_name'],
        //                     "post_id" => $result_json1[0]['post_id'],
        //                     "notice_content" => $result_json1[0]['notice_content'],
        //                     "notice_time" => $result_json1[0]['notice_time'],
        //                     "notice_pdf_url" => $result_json1[0]['notice_pdf_url'],
        //                 );
        //                 $data[] = $data_1;
        //             }
        //             $ggnews->saveAll($data);
        //             if(!$codeCai){
        //                 $codeCaiModel = new Cai();
        //                 $codeCaiModel->data([
        //                     'allcode' => $allcode,
        //                     'type' => 'gg',
        //                     'createtime' => time()
        //                 ]);
        //                 $codeCaiModel->save();
        //             }else{
        //                 model('app\admin\model\Cai')->where('id',$codeCai['id'])->update([
        //                     'createtime' => time()
        //                 ]);
        //             }
        //         }
        //         }

        //     }
        // }

        //查询新闻列表
        $ggnewsModel = new Ggnotice();
        $list = $ggnewsModel
            ->where('allcode',$allcode)
            ->where($where)
            ->page($page,20)
            // ->order('id desc')
            ->select();
        $this->success('请求成功',$list);
    }

    //广告类型
    public function gglx()
    {
        // 传入allcode  根据 news_time 对比当前时间 如果当前时间没有 就再采集 并存入数据
        $allcode = $this->request->param('allcode');
        $code = $this->request->param('code');
        $page = $this->request->param('page')?$this->request->param('page'):1;
        // 只要当前时间过了零点，就点击采集一次，然后再查询获取所有的
        $codeCai = model('app\admin\model\Cai')->where(['allcode'=>$allcode,'type'=>'gg'])->find();
        $twelve_time = date('Y-m-d',time()).' 00:00:00';
        // if(time() > strtotime($twelve_time)) {
        //     if (!$codeCai || ($codeCai['createtime'] < strtotime($twelve_time))) {
        //         $ggnews = new Ggnotice();
        //         $url = "http://api.jiaoyibiji.com/stocknotice_list?code={$code}&page=1&token=" . config('site.tokenp');
        //         $result = Http::get($url);
        //         if(!$result){
        //             $result_json = json_decode($result, true);
        //             if (is_array($result_json)) {
        //                 $data = [];
        //                 foreach ($result_json as $k => $v) {
        //                     // 判断news_id 是否存在 如果存在掠过
        //                     $info = $ggnews->where(['notice_id' => $v['notice_id']])->find();
        //                     if ($info) {
        //                         continue;
        //                     }
        //                     $url1 = "http://api.jiaoyibiji.com/stocknotice_content?notice_id={$v['notice_id']}&token=" . config('site.tokenp');
        //                     $result1 = Http::get($url1);
        //                     $result_json1 = json_decode($result1, true);
        //                     $data_1 = array(
        //                         "allcode" => $allcode,
        //                         "code" => $code,
        //                         "notice_id" => $v['notice_id'],
        //                         "notice_date" => $v['notice_date'],
        //                         "notice_title" => $v['notice_title'],
        //                         "column_name" => $v['column_name'],
        //                         "post_id" => $result_json1[0]['post_id'],
        //                         "notice_content" => $result_json1[0]['notice_content'],
        //                         "notice_time" => $result_json1[0]['notice_time'],
        //                         "notice_pdf_url" => $result_json1[0]['notice_pdf_url'],
        //                     );
        //                     $data[] = $data_1;
        //                 }
        //                 $ggnews->saveAll($data);
        //                 if(!$codeCai){
        //                     $codeCaiModel = new Cai();
        //                     $codeCaiModel->data([
        //                         'allcode' => $allcode,
        //                         'type' => 'gg',
        //                         'createtime' => time()
        //                     ]);
        //                     $codeCaiModel->save();
        //                 }else{
        //                     model('app\admin\model\Cai')->where('id',$codeCai['id'])->update([
        //                         'createtime' => time()
        //                     ]);
        //                 }
        //             }
        //         }

        //     }
        // }


        $ggnewsModel = new Ggnotice();
        $list = $ggnewsModel
            ->where('allcode',$allcode)
            ->field('column_name')
            ->page($page,20)
            ->order('id desc')
            ->select();
        $this->success('请求成功',$list);
    }


    //广告详情
    public function ggdetail(){
        $id = $this->request->param('id');
        $ggnewsModel = new Ggnotice();
        $info = $ggnewsModel->find($id);
        $this->success('请求成功',$info);
    }


    //研报列表
    public function yblst()
    {
        // 传入allcode  根据 news_time 对比当前时间 如果当前时间没有 就再采集 并存入数据
        $allcode = $this->request->param('allcode');
        $code = $this->request->param('code');
        $page = $this->request->param('page')?$this->request->param('page'):1;
        // 只要当前时间过了零点，就点击采集一次，然后再查询获取所有的
        $codeCai = model('app\admin\model\Cai')->where(['allcode'=>$allcode,'type'=>'yb'])->find();
        $twelve_time = date('Y-m-d',time()).' 00:00:00';
        // if(time() > strtotime($twelve_time)) {
        //     if (!$codeCai || ($codeCai['createtime'] < strtotime($twelve_time))) {
        //         $ggnews = new Ggyb();
        //         $url = "http://api.jiaoyibiji.com/report_list?code={$code}&page=1&pagesize=10&token=" . config('site.tokenp');
        //         $result = Http::get($url);
        //         if(!$result){
                    
        //             $result_json = json_decode($result, true);
        //             if (is_array($result_json)) {
        //             $data = [];
        //             foreach ($result_json as $k => $v) {
        //                 // 判断news_id 是否存在 如果存在掠过
        //                 $info = $ggnews->where(['report_id' => $v['report_id']])->find();
        //                 if ($info) {
        //                     continue;
        //                 }
        //                 $url1 = "http://api.jiaoyibiji.com/report_content?report_id={$v['report_id']}&token=" . config('site.tokenp');
        //                 $result1 = Http::get($url1);
        //                 $result_json1 = json_decode($result1, true);
        //                 $data_1 = array(
        //                     "allcode" => $allcode,
        //                     "code" => $code,
        //                     "report_id" => $v['report_id'],
        //                     "report_title" => $v['report_title'],
        //                     "report_time" => $v['report_time'],
        //                     "pingji" => $v['pingji'],
        //                     "source" => $v['source'],
        //                     "report_url" => $result_json1[0]['report_url'],
        //                 );
        //                 $data[] = $data_1;
        //             }
        //             $ggnews->saveAll($data);
        //             if(!$codeCai){
        //                 $codeCaiModel = new Cai();
        //                 $codeCaiModel->data([
        //                     'allcode' => $allcode,
        //                     'type' => 'yb',
        //                     'createtime' => time()
        //                 ]);
        //                 $codeCaiModel->save();
        //             }else{
        //                 model('app\admin\model\Cai')->where('id',$codeCai['id'])->update([
        //                     'createtime' => time()
        //                 ]);
        //             }
        //         }
        //         }
        //     }
        // }

        //查询新闻列表
        $ggnewsModel = new Ggyb();
        $list = $ggnewsModel
            ->where('allcode',$allcode)
            ->page($page,20)
            // ->order('id desc')
            ->select();
        $this->success('请求成功',$list);
    }



    //研报详情
    public function ybdetail(){
        $id = $this->request->param('id');
        $ggnewsModel = new Ggyb();
        $info = $ggnewsModel->find($id);
        $this->success('请求成功',$info);
    }

    //个股简介
    public function jj()
    {
        // 传入allcode  根据 news_time 对比当前时间 如果当前时间没有 就再采集 并存入数据
        $allcode = $this->request->param('allcode');
        $code = $this->request->param('code');


        // 只要当前时间过了零点，就点击采集一次，然后再查询获取所有的
        $codeCai = model('app\admin\model\Cai')->where(['allcode'=>$allcode,'type'=>'jj'])->find();
        $twelve_time = date('Y-m-d',time()).' 00:00:00';
        if(time() > strtotime($twelve_time)) {
        // if(false) {
            if (!$codeCai || ($codeCai['createtime'] < strtotime($twelve_time))) {
                $ggnews = new Companyjk();
                $url = "http://api.jiaoyibiji.com/company_profile?code={$code}&token=" . config('site.tokenp');
                $result = Http::get($url);
                if(!$result){
                    $result_json = json_decode($result, true);
                    if (is_array($result_json)) {
                    $onlineInfo = $ggnews->where('allcode',$allcode)->find();
                    $data = [
                        'allcode' => $allcode,
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
                        $codeCaiModel = new Cai();
                        $codeCaiModel->data([
                            'allcode' => $allcode,
                            'type' => 'jj',
                            'createtime' => time()
                        ]);
                        $codeCaiModel->save();
                    }else{
                        model('app\admin\model\Cai')->where('id',$codeCai['id'])->update([
                            'createtime' => time()
                        ]);
                    }

                }
                }

            }
        }
        $ggnewsModel = new Companyjk();
        $info = $ggnewsModel
            ->where('allcode',$allcode)
            ->find();
        // 判断当前股票 是否持仓 否则点击卖出无效
        $this->success('请求成功',$info);
    }

    //加入自选 个股
    public function addzx(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $allcode = Rsa::check($paramInfo['allcode']);
        $code = Rsa::check($paramInfo['code']);

        //$allcode = $this->request->param('allcode');
        //$code = $this->request->param('code');
        if(!$allcode || !$code){
            $this->error('参数异常');
        }
        $guInfo = model('app\admin\model\strategy\Strategy')->where([
            'allcode' => $allcode,
            'code' => $code
        ])->find();
        if(!$guInfo){
            $this->error('个股不存在');
        }
        $zxInfo = model('app\admin\model\Zixuannew')->where([
            'allcode' => $allcode,
            'code' => $code,
            'user_id' => $this->auth->id,
        ])->find();
        if($zxInfo){
            $this->success('加入成功');
        }
        $model = new Zixuannew();
        $model->data([
            'allcode' => $allcode,
            'code' => $code,
            'user_id' => $this->auth->id,
            'type' => $guInfo['type'],
        ]);
        if($model->save()!=false){
            $this->success('加入成功');
        }else{
            $this->error('加入失败');
        }
    }
    // etf加入自选
    public function addzxetf(){
        $allcode = $this->request->param('allcode');
        $code = $this->request->param('code');
        if(!$allcode || !$code){
            $this->error('参数异常');
        }
        $guInfo = model('app\admin\model\Etf')->where([
            'allcode' => $allcode,
            'code' => $code
        ])->find();
        if(!$guInfo){
            $this->error('ETF不存在');
        }
        $zxInfo = model('app\admin\model\Zixuannew')->where([
            'allcode' => $allcode,
            'code' => $code,
            'user_id' => $this->auth->id,
        ])->find();
        if($zxInfo){
            $this->success('加入成功');
        }
        $model = new Zixuannew();
        $model->data([
            'allcode' => $allcode,
            'code' => $code,
            'user_id' => $this->auth->id,
            'type' => $guInfo['type'],
        ]);
        if($model->save()!=false){
            $this->success('加入成功');
        }else{
            $this->error('加入失败');
        }
    }
    //删除自选
    public function delzx(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $allcode = Rsa::check($paramInfo['allcode']);

        //$allcode = $this->request->param('allcode');
        if(!$allcode){
            $this->error('参数异常');
        }
        $guInfo = model('app\admin\model\strategy\Strategy')->where([
            'allcode' => $allcode,
        ])->find();
        if(!$guInfo){
            $this->error('个股不存在');
        }
        $zxInfo = model('app\admin\model\Zixuannew')->where([
            'allcode' => $allcode,
            'user_id' => $this->auth->id
        ])->find();
        if(!$zxInfo){
            $this->error('尚未加入');
        }
        $result = model('app\admin\model\Zixuannew')->where('id',$zxInfo['id'])->delete();
        if($result!=false){
            $this->success('删除成功');
        }else{
            $this->error('删除失败');
        }
    }
    
    //删除自选
    public function delzxetf(){
        $allcode = $this->request->param('allcode');
        if(!$allcode){
            $this->error('参数异常');
        }
        $guInfo = model('app\admin\model\Etf')->where([
            'allcode' => $allcode,
        ])->find();
        if(!$guInfo){
            $this->error('ETF不存在');
        }
        $zxInfo = model('app\admin\model\Zixuannew')->where([
            'allcode' => $allcode,
            'user_id' => $this->auth->id
        ])->find();
        if(!$zxInfo){
            $this->error('尚未加入');
        }
        $result = model('app\admin\model\Zixuannew')->where('id',$zxInfo['id'])->delete();
        if($result!=false){
            $this->success('删除成功');
        }else{
            $this->error('删除失败');
        }
    }
    //自选编辑分组
    public function upfzzx()
    {
        $allcode = $this->request->param('allcode');
        $fenzu_id = $this->request->param('fenzu_id');
        if (!$allcode || !$fenzu_id) {
            $this->error('参数异常');
        }
        $guInfo = model('app\admin\model\Zixuannew')->where([
            'allcode' => $allcode,
            'user_id' => $this->auth->id
        ])->find();
        if (!$guInfo) {
            $this->error('个股不存在');
        }
        $zxfzInfo = model('app\admin\model\Zxfenzu')->find($fenzu_id);
        if (!$zxfzInfo) {
            $this->error('分组不存在');
        }
        if ($zxfzInfo['user_id'] != $this->auth->id) {
            $this->error('选择的不是自己的分组');
        }
        $guInfo->fenzu_id = $fenzu_id;
        $result = $guInfo->save();
        if ($result != false) {
            $this->success('编辑成功');
        } else {
            $this->error('编辑失败');
        }
    }

    //添加个股备注页面首次加载
    public function addbzfirst(){
        $allcode = $this->request->param('allcode');
        if (!$allcode) {
            $this->error('参数异常');
        }
        $obj = new Http();
        $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia_new($allcode);
        $caiInfo = $allcodes_arr[$allcode];
        $data = [
            'dqj' => $caiInfo[3],
            'zdf' => $caiInfo[31],
        ];
        $this->success('请求成功',$data);
    }

    //添加备注
    public function addbz(){
        $allcode = $this->request->param('allcode');
        $code = $this->request->param('code');
        $content = $this->request->param('content');
        if (!$allcode || !$content || !$code) {
            $this->error('参数异常');
        }
        $codeInfo = model('app\admin\model\strategy\Strategy')->where('allcode',$allcode)->find();
        if(!$codeInfo){
            $this->error('个股不存在');
        }
        if($codeInfo['code'] != $code){
            $this->error('code参数有误');
        }
        $model = new Beizhu();
        $online = $model->where(['allcode'=>$allcode,'user_id'=>$this->auth->id])->find();
        if($online){
            $this->error('已备注');
        }
        $data = [
            'allcode' => $allcode,
            'code' => $code,
            'user_id' => $this->auth->id,
            'content' => $content,
        ];
        $model->data($data);
        $result = $model->save();
        if($result!=false){
            $this->success('添加成功');
        }else{
            $this->error('添加失败');
        }
    }

    //备注列表
    public function bzlst(){
        $page = $this->request->param('page');
        $model = new Beizhu();
        $list =  $model
            ->where('user_id',$this->auth->id)
            ->page($page,20)
            ->select();
        if($list){
            foreach($list as $k=>$v) {
                $codeInfo = model('app\admin\model\strategy\Strategy')->where('allcode',$v['allcode'])->find();
                $list[$k]['title'] = $codeInfo['title'];
                $obj = new Http();
                $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia_new($v['allcode']);
                $caiInfo = $allcodes_arr[$v['allcode']];
                $list[$k]['dqj'] = $caiInfo[3];
                $list[$k]['zdf'] = $caiInfo[31];
            }
        }
        $this->success('请求成功',$list);
    }

    //编辑备注
    public function upbz(){
        $id = $this->request->param('id');
        $content = $this->request->param('content');
        if (!$id || !$content) {
            $this->error('参数异常');
        }
        $model = new Beizhu();
        $bzInfo = $model->find($id);
        if(!$bzInfo){
            $this->error('数据不存在');
        }
        if($bzInfo['user_id'] != $this->auth->id){
            $this->error('无权修改他人内容');
        }
        $bzInfo->content = $content;
        $result = $bzInfo->save();
        if($result!=false){
            $this->success('编辑成功');
        }else{
            $this->error('编辑失败');
        }
    }

    //删除备注
    public function delbz(){
        $id = $this->request->param('id');
        if (!$id) {
            $this->error('参数异常');
        }
        $model = new Beizhu();
        $bzInfo = $model->find($id);
        if(!$bzInfo){
            $this->error('数据不存在');
        }
        if($bzInfo['user_id'] != $this->auth->id){
            $this->error('无权删除他人内容');
        }
        $result = $bzInfo->delete();
        if($result!=false){
            $this->success('删除成功');
        }else{
            $this->error('删除失败');
        }
    }

    //备注详情
    public function bzdetail(){
        $id = $this->request->param('id');
        if (!$id) {
            $this->error('参数异常');
        }
        $model = new Beizhu();
        $bzInfo = $model->find($id);
        if($bzInfo){
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia_new($bzInfo['allcode']);
            $caiInfo = $allcodes_arr[$bzInfo['allcode']];
            $bzInfo['dqj'] = $caiInfo[3];
            $bzInfo['zdf'] = $caiInfo[31];
        }
        $this->success('成功',$bzInfo);
    }


}