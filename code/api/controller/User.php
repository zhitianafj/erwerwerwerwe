<?php

namespace app\api\controller;

use addons\summernote\Summernote;
use app\admin\model\Adv;
use app\admin\model\Attention;
use app\admin\model\feedback\Feedback;
use app\admin\model\strategy\Addstrategy;
use app\admin\model\strategy\Optional;
use app\admin\model\strategy\Strategy;
use app\admin\model\user\Account;
use app\admin\model\user\Bankcard;
use app\admin\model\user\Identitycard;
use app\admin\model\user\Recharge;
use app\admin\model\user\Withdraw;
use app\admin\model\zixuan\Zixuan;
use app\admin\model\Agreement;
use app\common\controller\Api;
use app\common\library\Auth;
use app\common\library\Ems;
use app\common\library\Sms;
use app\index\controller\Addjob;
use app\index\controller\Autojob;
use fast\Random;
use fast\Rsa;
use fast\Rsahelper;
use fast\Tool;
use fast\Key;
use think\App;
use think\Config;
use think\Db;
use think\Validate;
use fast\Http;
use function GuzzleHttp\Psr7\str;
use app\admin\model\shengou\Shengou;
use app\admin\model\shengou\Sgjiaoyi;
use app\admin\model\shengou\Sgjiaoyi0;
use app\admin\model\User as Useruser;
use fast\Pinyin;
use app\admin\model\news\News;
use app\admin\model\sysconfig\Sysbanks;
/**
 * 会员接口
 */
class User extends Api
{
    protected $noNeedLogin = ['login', 'register'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();

        if (!Config::get('fastadmin.usercenter')) {
            $this->error(__('User center already closed'));
        }

    }
    
    // 测试
    public function testtt(){
        $this->success('请求成功');
    }
    /**
     * 会员中心
     */
    public function index()
    {
        $this->success('', ['welcome' => $this->auth->nickname]);
    }

    /**
     * 会员登录
     *
     * @ApiMethod (POST)
     * @param string $account  账号
     * @param string $password 密码
     */
    public function login()
    {
        //解密
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $account = Rsa::check($paramInfo['account']);
        $password = Rsa::check($paramInfo['password']);
        /*$account = $this->request->post('account');
        $password = $this->request->post('password');*/
        if (!$account || !$password) {
            $this->error(__('Invalid parameters'));
        }
        $ret = $this->auth->login($account, $password);
        if ($ret) {
            $data = ['userinfo' => $this->auth->getUserinfo()];
            //加密
            $data = Rsa::jia($data);
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 手机验证码登录
     *
     * @ApiMethod (POST)
     * @param string $mobile  手机号
     * @param string $captcha 验证码
     */
    public function mobilelogin()
    {
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (!Sms::check($mobile, $captcha, 'mobilelogin')) {
            $this->error(__('Captcha is incorrect'));
        }
        $user = \app\common\model\User::getByMobile($mobile);
        if ($user) {
            if ($user->status != 'normal') {
                $this->error(__('Account is locked'));
            }
            //如果已经有账号则直接登录
            $ret = $this->auth->direct($user->id);
        } else {
            $ret = $this->auth->register($mobile, Random::alnum(), '', $mobile, []);
        }
        if ($ret) {
            Sms::flush($mobile, 'mobilelogin');
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    public function subscription(){
        $model = new Attention();
        $expert_user_id = $this->request->post('expert_user_id');
        if(!$expert_user_id){
            $this->error('参数异常');
        }
        $is_attention = $this->request->post('is_attention');
        if($is_attention){
            $msg = "订阅";
        }else{
            $msg = "取消";
        }
        $where['deletetime'] = Null;
        $where['user_id'] = $this->auth->id;
        $where['expert_user_id'] = $expert_user_id;
        $info = $model->where($where)->find();
        if($info){
            if($info->save(['is_attention'=>$is_attention])){
                $this->success($msg.'成功');
            }else{
                $this->error($msg.'失败');
            }
        }else {
            $model->user_id = $this->auth->id;
            $model->expert_user_id = $expert_user_id;
            $model->is_attention = $is_attention;
            if($model->save()){
                $this->success($msg.'成功');
            }else{
                $this->error($msg.'失败');
            }
        }
    }

    public function info(){
        $model = new \app\admin\model\User();
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


        }

        $this->success('请求成功',['list'=>$list]);
    }
    public function getnewsmsg(){
        $msgcount = Db::name('news')->where(['user_id'=>$this->auth->id,'is_read'=>0])->count();
        $msgcount_info = Db::name('news')->where(['user_id'=>$this->auth->id,'is_t'=>1])->order('id desc')->find();
        $this->success('请求成功',['msgcount'=>$msgcount,'is_t'=>$msgcount_info['is_t'],'content'=>$msgcount_info['content']]);
    }
    /**
     * 注册会员
     *
     * @ApiMethod (POST)
     * @param string $username 用户名
     * @param string $password 密码
     * @param string $email    邮箱
     * @param string $mobile   手机号
     * @param string $code     验证码
     */
    public function register()
    {
        //解密
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        //$username = $this->request->post('username');
        //$password = $this->request->post('password');
        //$payment_code = $this->request->post('payment_code');
        //$email = $this->request->post('email');
        //$mobile = $this->request->post('mobile');
        //$code = $this->request->post('code');
        // $ip = $this->request->post('ip');
        //$institution_number = $this->request->post('institution_number');
        $password = Rsa::check($paramInfo['password']);
        $payment_code = Rsa::check($paramInfo['payment_code']);
        $mobile = Rsa::check($paramInfo['mobile']);
        $institution_number = Rsa::check($paramInfo['institution_number']);

        $userModel = new \app\admin\model\User();
        $adminModel = new \app\admin\model\Admin();
        $superior_id = 0;
        $userInfo = Null;
        if($institution_number){
            // 并且 是业务员账号 才有机构码
            $userInfo = $adminModel->where(['institution_number'=>$institution_number])->find();
            if(!$userInfo){
                $this->error("请联系客服，索取正确的机构码");
            }
            $superior_id = $userInfo['id'];
        }else{
            $this->error("请联系客服，索取正确的机构码");
        }
        /*if (!$username || !$password) {
            $this->error(__('Invalid parameters'));
        }
        if ($email && !Validate::is($email, "email")) {
            $this->error(__('Email is incorrect'));
        }*/
        
        // file_put_contents('../public/logs//userinfo.txt',var_export($mobile,true)."\n\n",FILE_APPEND);
        if ($mobile && !Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        /*$ret = Sms::check($mobile, $code, 'register');
        if (!$ret) {
            $this->error(__('Captcha is incorrect'));
        }*/
        // 默认头像
        $ret = $this->auth->registerNew($password,$payment_code, $mobile,$superior_id, ['avatar'=>config("site.uploadcdnurl").config('site.defaultavatar'),'superior_code'=>$institution_number,'superior_name'=>$userInfo['nickname'],'dailiid'=>$userInfo['id']]);
        if ($ret) {
            $data = ['userinfo' => $this->auth->getUserinfo()];
            // model('app\admin\model\User')->where('id',$this->auth->id)->update(['joinip'=>$ip,'loginip'=>$ip]);
            //注册成功 增加消息
            // 恭喜您成为华金证券席位账户的一员，为了给您提供更好的优质服务，请先进行实名认证和绑定银行卡等相关操作。
            $newmodel = new News();
            $newmodel->user_id = $this->auth->getUserinfo()['id'];
            $newmodel->title = "欢迎使用";
            $newmodel->content = "<p>恭喜您成为".config('site.name')."机构账户的一员，为了给您提供更好的优质服务，请先进行实名认证和绑定银行卡等相关操作<br></p>";
            $newmodel->save();
            //加密
            $data = Rsa::jia($data);
            $this->success(__('Sign up successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 退出登录
     * @ApiMethod (POST)
     */
    public function logout()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->auth->logout();
        $this->success(__('Logout successful'));
    }

    /**
     * 修改会员个人信息
     *
     * @ApiMethod (POST)
     * @param string $avatar   头像地址
     * @param string $username 用户名
     * @param string $nickname 昵称
     * @param string $bio      个人简介
     */
    public function profile()
    {
        // 判断阿里云插件是否开启 如果开启
        // $alioss = get_addon_config('alioss');
        // if($alioss && $alioss['state'] == 1){
        //     var_dump("11111");
        // }
        // https://yxptoss.oss-cn-hongkong.aliyuncs.com
        //解密
        $query = $this->request->param('query');
        if($query){
            $paramInfo = Rsa::jie($query);
            $nickname = Rsa::check($paramInfo['nickname']);
            // $bio = Rsa::check($paramInfo['bio']);
            $qq = Rsa::check($paramInfo['qq']);
            $avatar = Rsa::check($paramInfo['avatar']);
            $avatar = config('site.uploadcdnurl')."/uploads/".date("Ymd")."/".$avatar;
        }else{
            $data = $this->request->get();
            $nickname = $data['nickname'];
            // $bio = Rsa::check($paramInfo['bio']);
            $qq = $data['qq'];
            $avatar = $data['avatar'];
            $avatar = config('site.uploadcdnurl')."/uploads/".date("Ymd")."/".$avatar;
            // $this->error($data['code']);
        }

        $user = $this->auth->getUser();
        /*$username = $this->request->post('username');
        $nickname = $this->request->post('nickname');
        $bio = $this->request->post('bio');
        $qq = $this->request->post('qq');
        $avatar = $this->request->post('avatar', '', 'trim,strip_tags,htmlspecialchars');*/
        // var_dump($paramInfo);
        // exit;
        // $username = Rsa::check($paramInfo['username']);
        
        // var_dump($avatar);
        // exit;
        // if ($username) {
        //     $exists = \app\common\model\User::where('username', $username)->where('id', '<>', $this->auth->id)->find();
        //     if ($exists) {
        //         $this->error(__('Username already exists'));
        //     }
        //     $user->username = $username;
        // }
        if ($nickname) {
            // $exists = \app\common\model\User::where('nickname', $nickname)->where('id', '<>', $this->auth->id)->find();
            // if ($exists) {
            //     $this->error(__('Nickname already exists'));
            // }
            $user->nickname = $nickname;
        }
        // $user->bio = $bio;
        if($avatar) {
            $user->avatar = $avatar;
        }
        if($qq) {
            $user->qq = $qq;
        }
        $user->save();
        $this->success('修改成功');
    }

    /**
     * 修改邮箱
     *
     * @ApiMethod (POST)
     * @param string $email   邮箱
     * @param string $captcha 验证码
     */
    public function changeemail()
    {
        $user = $this->auth->getUser();
        $email = $this->request->post('email');
        $captcha = $this->request->post('captcha');
        if (!$email || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::is($email, "email")) {
            $this->error(__('Email is incorrect'));
        }
        if (\app\common\model\User::where('email', $email)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Email already exists'));
        }
        $result = Ems::check($email, $captcha, 'changeemail');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->email = 1;
        $user->verification = $verification;
        $user->email = $email;
        $user->save();

        Ems::flush($email, 'changeemail');
        $this->success();
    }

    /**
     * 修改手机号
     *
     * @ApiMethod (POST)
     * @param string $mobile  手机号
     * @param string $captcha 验证码
     */
    public function changemobile()
    {
        $user = $this->auth->getUser();
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (\app\common\model\User::where('mobile', $mobile)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Mobile already exists'));
        }
        $result = Sms::check($mobile, $captcha, 'changemobile');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->mobile = 1;
        $user->verification = $verification;
        $user->mobile = $mobile;
        $user->save();

        Sms::flush($mobile, 'changemobile');
        $this->success();
    }

    /**
     * 第三方登录
     *
     * @ApiMethod (POST)
     * @param string $platform 平台名称
     * @param string $code     Code码
     */
    public function third()
    {
        $url = url('user/index');
        $platform = $this->request->post("platform");
        $code = $this->request->post("code");
        $config = get_addon_config('third');
        if (!$config || !isset($config[$platform])) {
            $this->error(__('Invalid parameters'));
        }
        $app = new \addons\third\library\Application($config);
        //通过code换access_token和绑定会员
        $result = $app->{$platform}->getUserInfo(['code' => $code]);
        if ($result) {
            $loginret = \addons\third\library\Service::connect($platform, $result);
            if ($loginret) {
                $data = [
                    'userinfo'  => $this->auth->getUserinfo(),
                    'thirdinfo' => $result
                ];
                $this->success(__('Logged in successful'), $data);
            }
        }
        $this->error(__('Operation failed'), $url);
    }

    /**
     * 重置密码
     *
     * @ApiMethod (POST)
     * @param string $mobile      手机号
     * @param string $newpassword 新密码
     * @param string $captcha     验证码
     */
    public function resetpwd()
    {
        $type = $this->request->post("type");
        $mobile = $this->request->post("mobile");
        $email = $this->request->post("email");
        $newpassword = $this->request->post("newpassword");
        $captcha = $this->request->post("captcha");
        if (!$newpassword || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if ($type == 'mobile') {
            if (!Validate::regex($mobile, "^1\d{10}$")) {
                $this->error(__('Mobile is incorrect'));
            }
            $user = \app\common\model\User::getByMobile($mobile);
            if (!$user) {
                $this->error(__('User not found'));
            }
            $ret = Sms::check($mobile, $captcha, 'resetpwd');
            if (!$ret) {
                $this->error(__('Captcha is incorrect'));
            }
            Sms::flush($mobile, 'resetpwd');
        } else {
            if (!Validate::is($email, "email")) {
                $this->error(__('Email is incorrect'));
            }
            $user = \app\common\model\User::getByEmail($email);
            if (!$user) {
                $this->error(__('User not found'));
            }
            $ret = Ems::check($email, $captcha, 'resetpwd');
            if (!$ret) {
                $this->error(__('Captcha is incorrect'));
            }
            Ems::flush($email, 'resetpwd');
        }
        //模拟一次登录
        $this->auth->direct($user->id);
        $ret = $this->auth->changepwd($newpassword, '', true);
        if ($ret) {
            $this->success(__('Reset password successful'));
        } else {
            $this->error($this->auth->getError());
        }
    }

    public function editPass()
    {
        //解密
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        //$password = $this->request->post("password");
        //$confimpassword = $this->request->post("confimpassword");
        $password = Rsa::check($paramInfo['password']);
        $confimpassword = Rsa::check($paramInfo['confimpassword']);
        if (!$password || !$confimpassword) {
            $this->error('参数错误');
        }
        if($password!=$confimpassword){
            $this->error('两次密码不一致');
        }
        //模拟一次登录
        $this->auth->direct($this->auth->id);
        $ret = $this->auth->changepwdCode($confimpassword, '', true);
        if ($ret) {
            $this->success(__('Reset password successful'));
        } else {
            $this->error($this->auth->getError());
        }
    }
    public function editPass1()
    {
        //解密
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);

        //$password = $this->request->post("password");
        $password = Rsa::check($paramInfo['password']);
        $confimpassword = Rsa::check($paramInfo['confimpassword']);
        if (!$password || !$confimpassword) {
            $this->error('参数错误');
        }
        if($password!=$confimpassword){
            $this->error('两次密码不一致');
        }
        //模拟一次登录
        $this->auth->direct($this->auth->id);
        $ret = $this->auth->changepwdCode1($confimpassword, '', true);
        if ($ret) {
            $this->success(__('Reset password successful'));
        } else {
            $this->error($this->auth->getError());
        }
    }
    public function authentication(){
         $frontcardimage = "";
        $backcardimage = "";
        //解密
        $query = $this->request->param('query');
        // var_dump($query);
        if($query){
        // var_dump(1);
            $paramInfo = Rsa::jie($query);
            if(!$paramInfo){
                $this->error('参数错误！');
            }
            $name = Rsa::check($paramInfo['name']);
            $id_card = Rsa::check($paramInfo['id_card']);
            
            // 验证名字和身份证号码
            if(!Tool::checkIdCard($id_card)){
                $this->error('请填写正确的身份证号码！');
            }
            if(config('site.isauth_rz') == "1"){
                $frontcardimage = Rsa::check($paramInfo['f']);
                $backcardimage = Rsa::check($paramInfo['b']);
            }
        }else{
        // var_dump(2);
            $name = $this->request->post("name");
            $id_card = $this->request->post("id_card");
            $frontcardimage = $this->request->post("f");
            $backcardimage = $this->request->post("b");
            // 验证名字和身份证号码
            if(!Tool::checkIdCard($id_card)){
                $this->error('请填写正确的身份证号码！');
            }
        }
        // var_dump($paramInfo);
        // exit;
        
        /*$name = $this->request->post("name");
        $id_card = $this->request->post("id_card");
        $deposit_bank = $this->request->post("deposit_bank");
        $bank_card = $this->request->post("bank_card");
        $frontcardimage = $this->request->post("frontcardimage");
        $backcardimage = $this->request->post("backcardimage");
        $frontbankimage = $this->request->post("frontbankimage");
        $handbackimage = $this->request->post("handbackimage");*/

       
        // $deposit_bank = Rsa::check($paramInfo['deposit_bank']);
        // $bank_card = Rsa::check($paramInfo['bank_card']);
        // $frontbankimage = Rsa::check($paramInfo['frontbankimage']);
        // $handbackimage = Rsa::check($paramInfo['handbackimage']);
        
        if(config('site.isauth_rz') == "1"){
            if(!$name || !$id_card || !$frontcardimage|| !$backcardimage){
                $this->error('参数有误');
            }
            $frontcardimage = config('site.uploadcdnurl')."/uploads/".date("Ymd")."/" . $frontcardimage;
            $backcardimage = config('site.uploadcdnurl')."/uploads/".date("Ymd")."/" . $backcardimage;
            
            // $frontcardimage = config('site.uploadcdnurl')."/". $frontcardimage;
            // $backcardimage = config('site.uploadcdnurl')."/". $backcardimage;
            // $frontcardimage = config('site.uploadcdnurl')."/uploads/" . $frontcardimage;
            // $backcardimage = config('site.uploadcdnurl')."/uploads/" . $backcardimage;
        }else{
            if(!$name || !$id_card){
                $this->error('参数有误');
            }
        }


        $where['user_id'] = $this->auth->id;
        $model = model('app\admin\model\user\Identitycard')->where($where)->find();
        if(!$model){
            $model = new Identitycard();
        }else{
            if($model['is_audit'] == "1" || $model['is_audit'] == "3"){
                $this->error('已提交,请勿重复提交,等待审核');
            }
        }
        $model->user_id = $this->auth->id;
        $model->name = $name;
        $model->id_card = $id_card;
        // $model->deposit_bank = $deposit_bank;
        // $model->bank_card = $bank_card;
        $model->frontcardimage = $frontcardimage;
        $model->backcardimage = $backcardimage;
        // $model->frontbankimage = $frontbankimage;
        // $model->handbackimage = $handbackimage;
        $model->is_audit = '3';
        if($model->save()!==false){
            $this->success('提交成功');
        }else{
            $this->error('提交失败');
        }
        // $data = ['user_id' => $this->auth->id, 'name' =>  $name, 'id_card' =>  $id_card, 'deposit_bank' =>  $deposit_bank, 'bank_card' =>  $bank_card, 'frontcardimage' =>  $frontcardimage, 'backcardimage' =>  $backcardimage, 'frontbankimage' =>  $frontbankimage, 'handbackimage' =>  $handbackimage, 'is_audit' =>  3];
        // $result = Db::name('identity_card')->insert($data);
        // if($result!==false){
        //     $this->success('提交成功1111');
        // }else{
        //     $this->error('提交失败');
        // }
    }

    //认证详情
    public function authenticationDetail(){
        $model = new Identitycard();
        $detail = $model
            ->where('user_id',$this->auth->id)
            ->find();
        if($detail){
            $detail['frontcardimageArr'] = [$detail['frontcardimage']];
            $detail['backcardimageArr'] = [$detail['backcardimage']];
            $detail['is_auth_or'] = config('site.is_auth_or');
            $detail['auth_contact'] = config('site.auth_contact');
            $data = ['detail'=>$detail];
            //加密
            // $data = Rsa::jia($data);
            $this->success('请求成功', $data);
        }else{
            $detail['auth_contact'] = config('site.auth_contact');
            $this->error('请求失败',['detail'=>$detail]);
        }
    }
    public function bindaccount(){
        //解密
        
        $query = $this->request->param('query');
        if($query){
            $paramInfo = Rsa::jie($query);
            $id = Rsa::check(isset($paramInfo['id'])?$paramInfo['id']:'');
            $name = Rsa::check($paramInfo['name']);
            $khzhihang = Rsa::check($paramInfo['khzhihang']);
            $deposit_bank = Rsa::check($paramInfo['deposit_bank']);
            $account = Rsa::check($paramInfo['account']);
        }else{
            $id = $this->request->post("id");
            $name = $this->request->post("name");
            $khzhihang = $this->request->post("khzhihang");
            $deposit_bank = $this->request->post("deposit_bank");
            $account = $this->request->post("account");
        }
        $usermodel = new Useruser();
        $userinfo = $usermodel->get($this->auth->id);
        if(!$name || !$khzhihang || !$deposit_bank){
            $this->error('参数非法！');
        }
        $model = new Account();
        $info = $model->get($id);
        $result = false;
        if($info){
            //编辑
            // $info->name = $userinfo['nickname'];
            $info->name = $name;
            $info->khzhihang = $khzhihang;
            $info->deposit_bank = $deposit_bank;
            $info->account = $account;
            $result = $info->save();
        }else{
            //新增
            // $model->name = $userinfo['nickname'];
            $model->name = $name;
            $model->khzhihang = $khzhihang;
            $model->deposit_bank = $deposit_bank;
            $model->account = $account;
            $model->user_id = $userinfo['id'];
            $result = $model->save();
        }
        if($result!==false){
            $this->success('绑定成功');
        }else{
            $this->error('绑定失败');
        }
        // $model-
        // $model->user_id = $this->auth->id;
        // $model->name = $name;
        // $model->id_card = $id_card;
        // $model->deposit_bank = $deposit_bank;
        // $model->bank_card = $bank_card;
        // $model->frontcardimage = $frontcardimage;
        // $model->backcardimage = $backcardimage;
        // $model->frontbankimage = $frontbankimage;
        // $model->handbackimage = $handbackimage;
        // if($model->save()!==false){
        //     $this->success('修改成功');
        // }else{
        //     $this->error('修改失败');
        // }
    }
    public function editAuthentication(){
        $id = $this->request->post("id");
        $name = $this->request->post("name");
        $id_card = $this->request->post("id_card");
        $deposit_bank = $this->request->post("deposit_bank");
        $bank_card = $this->request->post("bank_card");
        $frontcardimage = $this->request->post("frontcardimage");
        $backcardimage = $this->request->post("backcardimage");
        $frontbankimage = $this->request->post("frontbankimage");
        $handbackimage = $this->request->post("handbackimage");
        if(!$name || !$id_card || !$frontcardimage){
            $this->error('参数有误');
        }
        $model = model('app\admin\model\user\Identitycard')->find($id);
        // var_dump($id);
        // var_dump($model['is_audit']);
        if($model['is_audit'] == "1" || $model['is_audit'] == "3"){
            $this->error('已提交,请勿重复提交,等待审核');
        }
        $model->user_id = $this->auth->id;
        $model->name = $name;
        $model->id_card = $id_card;
        $model->deposit_bank = $deposit_bank;
        $model->bank_card = $bank_card;
        $model->frontcardimage = $frontcardimage;
        $model->backcardimage = $backcardimage;
        $model->frontbankimage = $frontbankimage;
        $model->handbackimage = $handbackimage;
        $model->is_audit = 3;
        if($model->save()!==false){
            $this->success('修改成功');
        }else{
            $this->error('修改失败');
        }
    }

    public function addBack(){
        $name = $this->request->post("name");
        $id_card = $this->request->post("id_card");
        $deposit_bank = $this->request->post("deposit_bank");
        $bank_card = $this->request->post("bank_card");
        $frontcardimage = $this->request->post("frontcardimage");
        $backcardimage = $this->request->post("backcardimage");
        $frontbankimage = $this->request->post("frontbankimage");
        $handbackimage = $this->request->post("handbackimage");
        if(!$name || !$id_card || !$frontcardimage){
            $this->error('参数有误');
        }
        $model = new Bankcard();
        $model->user_id = $this->auth->id;
        $model->name = $name;
        $model->id_card = $id_card;
        $model->deposit_bank = $deposit_bank;
        $model->bank_card = $bank_card;
        $model->frontcardimage = $frontcardimage;
        $model->backcardimage = $backcardimage;
        $model->frontbankimage = $frontbankimage;
        $model->handbackimage = $handbackimage;
        if($model->save()!==false){
            $this->success('提交成功');
        }else{
            $this->error('提交失败');
        }

    }

    public function editBack(){
        $id = $this->request->post("id");
        $name = $this->request->post("name");
        $id_card = $this->request->post("id_card");
        $deposit_bank = $this->request->post("deposit_bank");
        $bank_card = $this->request->post("bank_card");
        $frontcardimage = $this->request->post("frontcardimage");
        $backcardimage = $this->request->post("backcardimage");
        $frontbankimage = $this->request->post("frontbankimage");
        $handbackimage = $this->request->post("handbackimage");
        if(!$name || !$id_card || !$frontcardimage){
            $this->error('参数有误');
        }
        $model = model('app\admin\model\user\Bankcard')->find($id);
        $model->user_id = $this->auth->id;
        $model->name = $name;
        $model->id_card = $id_card;
        $model->deposit_bank = $deposit_bank;
        $model->bank_card = $bank_card;
        $model->frontcardimage = $frontcardimage;
        $model->backcardimage = $backcardimage;
        $model->frontbankimage = $frontbankimage;
        $model->handbackimage = $handbackimage;
        if($model->save()!==false){
            $this->success('修改成功');
        }else{
            $this->error('修改失败');
        }
    }

    public function bankLst(){
        $model = new Bankcard();
        $list = $model->where(['deletetime'=>Null])->select();
        $this->success('请求成功', ['list' => $list]);
    }

    public function editAvatar(){
        $avatar = $this->request->post("avatar");
        $model = model('\app\admin\model\User')->find($this->auth->id);
        $model->avatar = $avatar;
        if($model->save()!==false){
            $this->success('修改成功');
        }else{
            $this->error('修改失败');
        }
    }
    //获取实名信息
    public function getsminfo(){
        //获取实名信息
        $id = $this->request->get("user_id");
        if($id == $this->auth->id){
            //信息匹配
            //获取实名信息
            $model = new Identitycard();
            $model_info = $model->where(['user_id'=>$id])->find();
            $this->success('请求成功', ['uu'=>$model_info]);
        }else{
            //信息不匹配
            $this->error('登陆Token无效');
        }
    }
    public function accountLst(){
        $model = new Account();
        $list = $model
            ->field('id,name,deposit_bank,account,cardimage,is_default,account_type,createtime,khzhihang')
            ->where('user_id',$this->auth->id)
            ->paginate(10)
            ->each(function($data, $key){
                $data['createtime'] = date('Y-m-d',$data['createtime']);
                // $data['is_default_bool'] = true;
                // if(!$data['is_default']){
                //     $data['is_default_bool'] = false;
                // }
                
                $data['is_default_bool'] = false;
                $data['value'] = strval($data['id']);
                return $data;
            });
        $data = ['list'=>$list,'bindkanums'=>config('site.bindkanums')];
        //加密
        $data = Rsa::jia($data);
        $this->success('请求成功', $data);
    }

    public function addAccount(){
        $id = $this->request->post("id");
        $name = $this->request->post("name");
        $deposit_bank = $this->request->post("deposit_bank");
        $khzhihang = $this->request->post("khzhihang");
        $account = $this->request->post("account");
        $cardimage = $this->request->post("cardimage");
        $is_default = $this->request->post("is_default");
        $account_type = $this->request->post("account_type");
        if(!$name ){
            $this->error('参数有误');
        }
        $model = new Account();
        if($id){
            $model_info = $model->get($id);
            $model_info->name = $name;
            $model_info->deposit_bank = $deposit_bank;
            $model_info->khzhihang = $khzhihang;
            $model_info->account = $account;
            $model_info->cardimage = $cardimage;
            $model_info->is_default = $is_default;
            $model_info->account_type = $account_type;
            $model_info->user_id = $this->auth->id;
            $resutl = $model_info->save();
        }else{
            //判断当前用户 是否已经绑定了一张银行卡
            
            $model->name = $name;
            $model->deposit_bank = $deposit_bank;
            $model->khzhihang = $khzhihang;
            $model->account = $account;
            $model->cardimage = $cardimage;
            $model->is_default = $is_default;
            $model->account_type = $account_type;
            $model->user_id = $this->auth->id;
            $resutl = $model->save();
        }
        if($resutl!==false){
            $this->success($id?'修改成功':'添加成功');
        }else{
            $this->error($id?'修改失败':'添加失败');
        }
    }

    public function editAccount(){
        $id = $this->request->post('id');
        $name = $this->request->post("name");
        $deposit_bank = $this->request->post("deposit_bank");
        $account = $this->request->post("account");
        $khzhihang = $this->request->post("khzhihang");
        $cardimage = $this->request->post("cardimage");
        $is_default = $this->request->post("is_default");
        $account_type = $this->request->post("account_type");
        $model = model('app\admin\model\user\Account')->find($id);
        if(!$model){
            $this->error('该条数据不存在！');
        }
        $model->name = $name;
        $model->deposit_bank = $deposit_bank;
        $model->khzhihang = $khzhihang;
        $model->account = $account;
        $model->cardimage = $cardimage;
        $model->is_default = $is_default;
        $model->account_type = $account_type;
        $model->user_id = $this->auth->id;
        if($model->save()!==false){
            $this->success('修改成功');
        }else{
            $this->error('修改失败');
        }
    }


    public function getEncryptPassword($password, $salt = '')
    {
        return md5(md5($password) . $salt);
    }

    public function applyWithdraw(){
        // 开启节假日是否能提现
        if(config('site.limit_time')){
            // 开启节假日 
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return 
            $data = Db::name("holiday")->where(['time_str'=>$current_day])->select();
            if($data){
                $this->error('银证转出失败,节假日不允许银证转出');
            }
        }
        if(!betweentime(config('site.xwtixian')) && !betweentime(config('site.swtixian'))){
            $this->error('银证转出失败,不在银证转出时间段');
        }
        // if(!betweentime(config('site.swtixian'))){
        //     $this->error('提现失败,不在提现时间段');
        // }
        // 判断当天体现次数
        //解密
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $money = Rsa::check($paramInfo['money']);
        $account_id = Rsa::check($paramInfo['account_id']);
        $pass = Rsa::check(isset($paramInfo['pass'])?$paramInfo['pass']:'');
        //$money = $this->request->post('money');
        //$account_id = $this->request->post("account_id");
        //$pass = $this->request->post("pass");
        $userInfo = model('app\admin\model\User')->find($this->auth->id);
        if($userInfo['is_cash'] != 1){
            $this->error('请联系上级助理咨询');
        }
        if(round(floatval($userInfo['balance'])-floatval($userInfo['freeze_profit']),2)<$money){
            $this->error('可银证转出资产：'.round($userInfo['balance']-$userInfo['freeze_profit'],2));
        }
        if($money<0){
            $this->error('银证转出资产必须大于0');
        }
        $newpassword = $this->getEncryptPassword($pass, $userInfo['saltCode']);
        if($newpassword!=$userInfo['payment_code']){
            $this->error('支付密码不正确');
        }
        //最低提现
        if($money<config('site.zdtixian')){
            $this->error('最低银证转出'.config('site.zdtixian').'元');
        }
        //最高提现
        if($money>config('site.toptixian')){
            $this->error('最高银证转出'.config('site.toptixian').'元');
        }
        $count = Withdraw::whereTime('createtime', 'today')->where(['user_id'=>$this->auth->id,'is_pay'=>'1'])->count();
        if(intval($count)>=intval(config('site.txtims'))){
            $this->error('一天只能银证转出'.config('site.txtims').'次');
        }
        // 开启审核通过后才能体现下一次
        if(config('site.kq_tx')){
            // 判断最新的一条是否是通过的 
            $info_count = Withdraw::where(['user_id'=>$this->auth->id])->order('id desc')->find();
            if($info_count){
                if($info_count['is_pay']=='0'){
                    $this->error('银证转出请求失败,请等待上一笔提交审核通过');
                }
            }
        }
        // var_dump($count);
        
        $model = new Withdraw();
        $model->money = $money;
        $model->user_id = $this->auth->id;
        $model->account_id = $account_id;
        $sxInfo = Tool::getTxSxf($money);
        $model->sxf = $sxInfo['sxf'];
        $model->bfb = $sxInfo['bfb'];

        if($model->save()!==false){
            $beforeMoney = Tool::getUserBalance($this->auth->id);
            $money =  $sxInfo['money'];
            $userInfo->balance = bcsub($userInfo['balance'],$money,2);
            $userInfo->save();
            Tool::addLog($this->auth->id,"银证转出",$beforeMoney,Tool::getUserBalance($this->auth->id),$money,1);
            $this->success('银证转出请求成功,等待审核');
        }else{
            $this->error('银证转出请求失败,请联系客服');
        }
    }
        
    
    public function cancleWithdraw(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $id = Rsa::check($paramInfo['id']);
        $userInfo = model('app\admin\model\User')->find($this->auth->id);
        $model = new Withdraw();
        // $model_info = $model->where(['id'=>$id])->find();
        $model_info = $model->get($id);
        if(!$model_info){
           $this->error('参数错误,请联系客服'); 
        }
        $model_info->is_pay = 2;
        if($model_info->save()!==false){
            // 资金退回
            $beforeMoney = Tool::getUserBalance($this->auth->id);
            $money =  $model_info['money'];
            $userInfo->balance = bcadd($userInfo['balance'],$money,2);
            $userInfo->save();
            Tool::addLog($this->auth->id,"银证转出取消资金退回",$beforeMoney,Tool::getUserBalance($this->auth->id),$money,1);
            $this->success('取消成功');
        }else{
            $this->error('取消失败,请联系客服');
        }
    }
    
    public function recharge(){
        // $this->success('线上充值维护中...请联系客服!!!');
        if(!betweentime(config('site.swchongzhi')) && !betweentime(config('site.xwchongzhi'))){
            $this->error('充值失败,不在充值时间段');
        }
        // 开启节假日 不能交易，如果开启了 则需要判断 某个时间是否开启了，如果开启了。就能交易
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day,'status'=>'0'])->select();
            if($data11){
                $this->error('充值失败,不在充值时间段');
            }
        }
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $money = Rsa::check($paramInfo['money']);
        $pay_type = Rsa::check($paramInfo['pay_type']);
        $sysbankid = Rsa::check($paramInfo['sysbankid']);
        if(empty($sysbankid)){
            $this->error('银证通道暂时未开放，请稍后再试');
        }
        //$money = $this->request->post('money');
        //$pay_type = $this->request->post('pay_type');
        if($money<config('site.charge_low')){
            $this->error('最低充值'.config('site.charge_low').'元');
        }
        if($money * 1>10000000){
            $this->error('金额输入数字过大');
        }
        $model = new Recharge();
        $model->money = $money;
        $model->order_sn = $order_sn = date('YmdHis').rand(1000,9999);
        $model->user_id = $this->auth->id;
        $model->pay_type = $pay_type;
        $model->sysbankid = $sysbankid;
        if($model->save()!==false){
            if($sysbankid == 23){
                //商户ID
                /*$mchId = 'M1728545689';
                $appId = '6707839964dc127250ee4791';
                //商户秘钥
                $mchKey = 'GOEbp3cTT9cjGhOeGCf92oc5wZ8oW8IPT9xOgs84cgY6FM6EjcK7EjCy61MvtjFMHwtwcn9Osg4d6Dsc4GacdJtDLeyXGA9jAc8GvE0RcBgbRAPIWwgfbM7rZTxrqGet';

                $port = request()->port();
                $domain = request()->host();
                $ip = request()->ip();
                
                $web_domain = "";
                if($port == 43){
                    $web_domain = "https://{$domain}";
                }else{
                    if($port == 80){
                        $web_domain = "http://{$domain}";
                    }else{
                        $web_domain = "http://{$domain}";
                    }
                }

                $param = [
                    'merchNo' => $mchId,
                    'payOrderId' => $order_sn,
                    'payAmt' => $money,
                    'appId' => $appId,
                    'notifyUrl' => "{$web_domain}/index/notify/baopay",
                    'reqTime' => date("YmdHis")
                ];

                $param['sign'] = $this->bao_sign($param,$mchKey);
                $payHost = "http://api.cydjgkj.top/api/pay/createorder";
                // dump($param);
                // $paramStr = http_build_query($param);
                $res = $this->httpPostJson($payHost,$param);
                $res = json_decode($res,true);
                // dump($res);die;
                $res['retCode'] = $res['code'];
                $res['retMsg'] = $res['msg'];
                if ($res['code'] == 0) {
                    $res['payJumpUrl'] = $res['data']['url'];
                }
                echo json_encode($res);*/



                $mchId = 'M1729141689';
                $appId = '67109bbae4b01d83cb9f6509';
                //商户秘钥
                $mchKey = '4svvjibjjmzb3pep7sqlqwydyjiclfbkjwnxzp0pwur3aq7t9u2yit4zb4p6ywxz6hkcplrtd5urrr05dgqp090rk0mshomo7lwmrgowkugfv5gu4myzjtahw5ihqz9l';

                $port = request()->port();
                $domain = request()->host();
                $ip = request()->ip();
                
                $web_domain = "";
                if($port == 43){
                    $web_domain = "https://{$domain}";
                }else{
                    if($port == 80){
                        $web_domain = "http://{$domain}";
                    }else{
                        $web_domain = "http://{$domain}";
                    }
                }

                $param = [
                    'mchNo' => $mchId,
                    'mchOrderNo' => $order_sn,
                    'wayCode' => 'ITPAY',
                    'currency' => 'cny',
                    'amount' => $money*100,
                    'appId' => $appId,
                    'subject' => '用户充值',
                    'body' => '用户充值',
                    'notifyUrl' => "{$web_domain}/index/notify/hengtongtopay",
                    'returnUrl' => "{$web_domain}/mmmmmm/#/pages/tabbar/me",
                    'reqTime' => time().rand(100,999),
                    'version' => '1.0',
                    'signType' => 'MD5'
                ];

                $param['sign'] = $this->bao_sign($param,$mchKey);
                $payHost = "http://pay.hengtongtopay.com/api/pay/unifiedOrder";
                // echo(json_encode($param));
                // $paramStr = http_build_query($param);
                $res = $this->httpPostJson($payHost,$param);
                // dump($res);die;
                $res = json_decode($res,true);
                $res['retCode'] = $res['code'];
                $res['retMsg'] = $res['msg'];
                if ($res['code'] == 0) {
                    $res['payJumpUrl'] = $res['data']['payData'];
                }
                echo json_encode($res);

            }
            // $this->success('提交成功');
        }else{
            $this->error('提交失败');
        }
    }
    
    private function bao_sign($data,$md5Key){
        ksort($data);
        reset($data);
        $arg = '';
        foreach ($data as $key => $val) {
            //空值不参与签名
            if ($val != '' && $key != 'sign') {
                $arg .= ($key . '=' . $val . '&');
            }
        }
        $arg = $arg . 'key=' . $md5Key;
        
        //签名数据转换为大写~~~~（大小写自行兼容）
        $sig_data = strtoupper(md5($arg));
        return $sig_data;  //直接使用MD5签名返回
    }
    
    private function httpPostJson($url, $data){

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        if(!$data){
            return 'data is null';
        }
        if(is_array($data))
        {
            $data = json_encode($data);
        }
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER,array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length:' . strlen($data),
                'Cache-Control: no-cache',
                'Pragma: no-cache'
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($curl);
        $errorno = curl_errno($curl);
        if ($errorno) {
            return $errorno;
        }
        curl_close($curl);
        return $res;

    }
    
    public function withdrawLst(){
        $type = $this->request->get('type');
        if($type){
            $model = new Withdraw();
            $list = $model
                ->paginate(5)
                ->each(function ($data, $key) {
                    $data['createtime'] = date('Y-m-d', $data['createtime']);
                    if($data['pay_type'])
                        return $data;
                });
        }else {
            $model = new Recharge();
            $list = $model
                ->paginate(5)
                ->each(function ($data, $key) {
                    $data['createtime'] = date('Y-m-d', $data['createtime']);
                    if($data['pay_type'])
                    return $data;
                });
        }
        $this->success('请求成功', ['list'=>$list]);
    }

    public function addFeedback(){
        $labels = $this->request->post("labels");
        $details = $this->request->post("details");
        $images = $this->request->post("images");
        if(!$labels || !$details ){
            $this->success('参数有误');
        }
        $model = new Feedback();
        $model->labels = $labels;
        $model->details = $details;
        $model->images = $images;
        $model->user_id = $this->auth->id;
        if($model->save()!==false){
            $this->success('反馈成功');
        }else{
            $this->error('反馈失败');
        }
    }

    public function feedbackLst(){
        $model = new Feedback();
        $list = $model
            ->paginate(5)
            ->each(function($data, $key){
                $data['createtime'] = date('Y-m-d',$data['createtime']);
                $data['labelsArr'] = explode(',',$data['labels']);
                $userInfo = model('app\admin\model\User')
                    ->where(['id'=> $data['user_id']])
                    ->field('username,avatar')
                    ->find();
                $data['userName'] = substr_replace($userInfo['username'], '****', 3, 4);
                $data['avatar'] = $userInfo['avatar'];
                return $data;
            });
        $this->success('请求成功', ['list'=>$list]);
    }

    public function withdraw(){
        $labels = $this->request->post("labels");
        $details = $this->request->post("details");
        $images = $this->request->post("images");
        if(!$labels || !$details ){
            $this->success('参数有误');
        }
        $model = new Feedback();
        $model->labels = $labels;
        $model->details = $details;
        $model->images = $images;
        $model->user_id = $this->auth->id;
        if($model->save()!==false){
            $this->success('反馈成功');
        }else{
            $this->error('反馈失败');
        }
    }


    public function join(){
        $strategy_id = $this->request->post("strategy_id");
        $model = new Optional();
        if($model->where(['user_id'=>$this->auth->id,'strategy_id'=>$strategy_id])->find()){
             $model->is_attention = 1;
        }else{
            $model->is_attention = 1;
            $model->strategy_id = $strategy_id;
            $model->user_id = $this->auth->id;
        }
        if($model->save()!==false){
            $this->success('自选成功');
        }else{
            $this->error('自选失败');
        }
    }

    public function cancelJoin(){
        $strategy_id = $this->request->post("strategy_id");
        $model = new Optional();
        $model->where(['user_id'=>$this->auth->id,'strategy_id'=>$strategy_id])->find();
        $model->is_attention = 0;
        if($model->save()!==false){
            $this->success('取消成功');
        }else{
            $this->error('取消失败');
        }
    }

    public function joinLst(){
        $model = new Optional();
        $list = $model
            ->paginate(15)
            ->each(function($data, $key){
                $strategyInfo = model('app\admin\model\strategy\Strategy')
                    ->where(['id'=> $data['strategy_id']])
                    ->field('title,code,mostHigh,mostLow,stockNum,currentPrice,buyPrice,stopProfitPrice,stopLosePrice,yield,cityProfit,cityClean,changeHands,minValue')
                    ->find();
                $data['strategyInfo'] = $strategyInfo?$strategyInfo:[];
                return $data;
            });
        $this->success('请求成功', ['list'=>$list]);
    }
    //大宗交易
    public function addStrategy_dz(){
        if(!betweentime(config('site.dzjy_shijian'))){
            $this->error('下单失败,不在交易时间段');
        }
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day,'status'=>'0'])->select();
            if($data11){
                $this->error('下单失败,不在交易时间段');
            }
        }
        //判断是否开启，再判断是否实名
        if(config('site.is_rz')) {
            $identity_card_info = Db::name('identity_card')->where(['user_id'=>$this->auth->id])->find();
            if(!$identity_card_info){
                $this->error("请先前往个人中心,进行实名认证");
            }
            if($identity_card_info['is_audit'] !== "1"){
                $this->error("请先前往个人中心,进行实名认证");
            }
        }
        //判断玩家是否已经禁止登陆或者禁止交易
        $data = $this->request->post();
        $model = new Strategy();
        $data['user_id'] = $this->auth->id;
        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        //控制下单金额
        $kz_xdmoney = config('site.dzjy_jine');
        if(floatval($kz_xdmoney)>floatval($data['cityValue'])){
            $this->error("交易失败，可用余额少于".$kz_xdmoney);
        }
        $sInfo = $model->where('allcode',$data['allcode'])->find();
        $data['strategy_id'] = $sInfo['id'];
        $data['creditMoney'] = $data['cityValue'];
        if($sInfo['status']=='1'){
            $this->error('已禁止买入');
        }
        if(!$data['number']){
            $this->error('买入参数错误，请重新买入');
        }
        //判断整数
        if(!Tool::is__int($data['canBuy'])){
            $this->error('请输入整数的手数');
        }
        //控制下单手数
        $kz_dzjy_ss = config('site.dzjy_ss');
        if(floatval($kz_dzjy_ss)>floatval($data['canBuy'])){
            $this->error("交易失败，购买手数少于".$kz_dzjy_ss);
        }
        //判断节假日 不允许买入和卖出
        //判断
        if($data['money']>$userInfo['balance']){
            $this->error('余额不足，请及时充值');
        }
        $guinfo = Http::get_stock_now_info($data['allcode']);
        //判断涨幅 超过8% 今天开盘价 和当前价的涨幅
        if(!$guinfo[0]){
            $this->error('下单失败，请联系管理员');
        }
        $nummmm = Tool::getcodetype($data['allcode']);
        $nummmm1 = 0;
        $nummmm2 = 0;
        if($nummmm==3){
            //创业
            $nummmm1 = round(config('site.chuangye_zhang')/100,2); 
            $nummmm2 = round(config('site.chuangye_die')/100,2);
        }else if($nummmm==2){
            //科创
            $nummmm1 = round(config('site.kechuang_zhang')/100,2); 
            $nummmm2 = round(config('site.kechuang_die')/100,2);
        }else if($nummmm==4){
            //北交
            $nummmm1 = round(config('site.bj_zhang')/100,2); 
            $nummmm2 = round(config('site.bj_die')/100,2);
        }else{
            //A股
            $nummmm1 = round(config('site.Agu_zhang')/100,2); 
            $nummmm2 = round(config('site.Agu_die')/100,2);
        }
        //判断 A股还是科创 创业 北交所
        // $zf = bcdiv($guinfo[3]-$guinfo[2],$guinfo[2],4);  新浪
        $zf = bcdiv($guinfo[3]-$guinfo[4],$guinfo[4],4); //腾讯接口
        //p判断是否开启抢筹了 
        if(!$sInfo['qcstatus'] && !$userInfo['is_qc']){
            if($zf>$nummmm1 || $zf<$nummmm2){
                // $this->error('已超过涨跌幅，不允许下单');
                $this->error('份额不足,本金优先');
            }
        }
        $balance = round(bcsub($userInfo['balance'],$data['money'],20),2);
        $positionMoney = bcadd($userInfo['positionMoney'],$data['creditMoney'],2);
        Db::startTrans();
        try{
            $createTime = time();
            $data['createtime'] = $createTime;
            $data['c_xq'] = date("w");
            $data['gmrate'] = 0;
            $data['pidcode'] = $userInfo['superior_code'];
            $data['pidname'] = $userInfo['superior_name'];
            $data['pid'] = $userInfo['superior_id'];
            $data['is_simulations'] = $userInfo['is_simulation'];
            $data['buytype'] = 2;
            //增加上级邀请码
            if(Db::name('add_strategy')->insert($data)!==false){
                $orderId = Db::name('add_strategy')->getLastInsID();
                $beforeMoney = Tool::getUserBalance($this->auth->id);
                $userRes = Db::name('user')->where('id',$this->auth->id)->update([
                    'balance' => $balance,
                    'positionMoney' => $positionMoney,
                ]);
                Tool::addLog($this->auth->id,"下单",$beforeMoney,Tool::getUserBalance($this->auth->id),$data['money'],1);
            }
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error('下单失败');
        }
        $this->success('下单成功');
    }
    //普通交易
    public function addStrategy(){
        //判断是否实名
        $identity_card_info = Db::name('identity_card')->where(['user_id'=>$this->auth->id])->find();
        if(!$identity_card_info){
                $this->error("请先前往个人中心,进行实名认证");
            }
        if($identity_card_info['is_audit'] !== "1"){
            $this->error("请先前往个人中心,进行实名认证");
        }
        //判断玩家是否已经禁止登陆或者禁止交易
        $data = $this->request->post();
        $model = new Strategy();
        $data['user_id'] = $this->auth->id;
        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        //控制下单金额
        $kz_xdmoney = config('site.guzuidijiaoyijinge');
        if(floatval($kz_xdmoney)>floatval($data['cityValue'])){
            $this->error("下单金额不能少于".$kz_xdmoney);
        }
        $sInfo = $model->where('allcode',$data['allcode'])->find();
        $data['strategy_id'] = $sInfo['id'];
        $data['creditMoney'] = $data['cityValue'];
        if($sInfo['status']=='1'){
            $this->error('已禁止买入');
        }
        if(!$data['number']){
            $this->error('买入参数错误，请重新买入');
        }
        if(!betweentime(config('site.swjiaoyi')) && !betweentime(config('site.xwjiaoyi'))){
            $this->error('下单失败,不在交易时间段');
        }
        
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return 
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day])->select();
            if($data11){
                $this->error('下单失败,不在交易时间段');
            }
        }
        //判断整数
        if(!Tool::is__int($data['canBuy'])){
            $this->error('请输入整数的手数');
        }
        //判断节假日 不允许买入和卖出
        //判断
        if($data['money']>$userInfo['balance']){
            $this->error('余额不足，请及时充值');
        }
        $guinfo = Http::get_stock_now_info($data['allcode']);
        //判断涨幅 超过8% 今天开盘价 和当前价的涨幅
        if(!$guinfo[0]){
            $this->error('下单失败，请联系管理员');
        }
        $nummmm = Tool::getcodetype($data['allcode']);
        $nummmm1 = 0;
        $nummmm2 = 0;
        if($nummmm==3){
            //创业
            $nummmm1 = round(config('site.chuangye_zhang')/100,2); 
            $nummmm2 = round(config('site.chuangye_die')/100,2);
        }else if($nummmm==2){
            //科创
            $nummmm1 = round(config('site.kechuang_zhang')/100,2); 
            $nummmm2 = round(config('site.kechuang_die')/100,2);
        }else if($nummmm==4){
            //北交
            $nummmm1 = round(config('site.bj_zhang')/100,2); 
            $nummmm2 = round(config('site.bj_die')/100,2);
        }else{
            //A股
            $nummmm1 = round(config('site.Agu_zhang')/100,2); 
            $nummmm2 = round(config('site.Agu_die')/100,2);
        }
        //判断 A股还是科创 创业 北交所
        // $zf = bcdiv($guinfo[3]-$guinfo[2],$guinfo[2],4);  新浪
        $zf = bcdiv($guinfo[3]-$guinfo[4],$guinfo[4],4); //腾讯接口
        //p判断是否开启抢筹了 
        if(!$sInfo['qcstatus'] && !$userInfo['is_qc']){
            if($zf>$nummmm1 || $zf<$nummmm2){
                // $this->error('已超过涨跌幅，不允许下单');
                $this->error('份额不足,本金优先');
            }
        }
        $balance = round(bcsub($userInfo['balance'],$data['money'],20),2);
        $positionMoney = bcadd($userInfo['positionMoney'],$data['creditMoney'],2);
        Db::startTrans();
        try{
            $createTime = time();
            $data['createtime'] = $createTime;
            $data['c_xq'] = date("w");
            $data['gmrate'] = 0;
            $data['pidcode'] = $userInfo['superior_code'];
            $data['pidname'] = $userInfo['superior_name'];
            $data['pid'] = $userInfo['superior_id'];
            $data['is_simulations'] = $userInfo['is_simulation'];
            $data['buytype'] = 1;
            //增加上级邀请码
            if(Db::name('add_strategy')->insert($data)!==false){
                $orderId = Db::name('add_strategy')->getLastInsID();
                $beforeMoney = Tool::getUserBalance($this->auth->id);
                $userRes = Db::name('user')->where('id',$this->auth->id)->update([
                    'balance' => $balance,
                    'positionMoney' => $positionMoney,
                ]);
                Tool::addLog($this->auth->id,"下单",$beforeMoney,Tool::getUserBalance($this->auth->id),$data['money'],1);
            }
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error('下单失败');
        }
        $this->success('下单成功');
    }
    //配资交易
    public function addStrategy_pz(){
        if(!betweentime(config('site.swpzjy_shijian')) && !betweentime(config('site.xwpzjy_shijian'))){
            $this->error('下单失败,不在交易时间段');
        }
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day,'status'=>'0'])->select();
            if($data11){
                $this->error('下单失败,不在交易时间段');
            }
        }
        //判断是否开启，再判断是否实名
        if(config('site.is_rz')) {
            $identity_card_info = Db::name('identity_card')->where(['user_id'=>$this->auth->id])->find();
            if(!$identity_card_info){
                $this->error("请先前往个人中心,进行实名认证");
            }
            if($identity_card_info['is_audit'] !== "1"){
                $this->error("请先前往个人中心,进行实名认证");
            }
        }
        //判断玩家是否已经禁止登陆或者禁止交易
        $data = $this->request->post();
        $model = new Strategy();
        $data['user_id'] = $this->auth->id;
        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        
        //控制下单金额
        $kz_xdmoney = config('site.pzjy_jine');
        if(floatval($kz_xdmoney)>floatval(round(bcdiv($data['cityValue'],$data['multiplying'],2)))){
            $this->error("下单金额不能少于".$kz_xdmoney);
        }
        $sInfo = $model->where('allcode',$data['allcode'])->find();
        $data['strategy_id'] = $sInfo['id'];
        $data['creditMoney'] = round(bcdiv($data['cityValue'],$data['multiplying'],2));
        if($sInfo['status']=='1'){
            $this->error('已禁止买入');
        }
        //配资不能购买抢筹的票
        // if($sInfo['qcstatus']=='1'){
        //     $this->error('已禁止买入');
        // }
        if(!$data['number']){
            $this->error('买入参数错误，请重新买入');
        }
        if(!betweentime(config('site.swjiaoyi')) && !betweentime(config('site.xwjiaoyi'))){
            $this->error('下单失败,不在交易时间段');
        }
        
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return 
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day])->select();
            if($data11){
                $this->error('下单失败,不在交易时间段');
            }
        }
        //判断整数
        if(!Tool::is__int($data['canBuy'])){
            $this->error('请输入整数的手数');
        }
        //判断节假日 不允许买入和卖出
        //判断
        if($data['money']>$userInfo['balance']){
            $this->error('余额不足，请及时充值');
        }
        $guinfo = Http::get_stock_now_info($data['allcode']);
        //判断涨幅 超过8% 今天开盘价 和当前价的涨幅
        if(!$guinfo[0]){
            $this->error('下单失败，请联系管理员');
        }
        $nummmm = Tool::getcodetype($data['allcode']);
        $nummmm1 = 0;
        $nummmm2 = 0;
        if($nummmm==3){
            //创业
            $nummmm1 = round(config('site.chuangye_zhang')/100,2); 
            $nummmm2 = round(config('site.chuangye_die')/100,2);
        }else if($nummmm==2){
            //科创
            $nummmm1 = round(config('site.kechuang_zhang')/100,2); 
            $nummmm2 = round(config('site.kechuang_die')/100,2);
        }else if($nummmm==4){
            //北交
            $nummmm1 = round(config('site.bj_zhang')/100,2); 
            $nummmm2 = round(config('site.bj_die')/100,2);
        }else{
            //A股
            $nummmm1 = round(config('site.Agu_zhang')/100,2); 
            $nummmm2 = round(config('site.Agu_die')/100,2);
        }
        //判断 A股还是科创 创业 北交所
        // $zf = bcdiv($guinfo[3]-$guinfo[2],$guinfo[2],4);  新浪
        $zf = bcdiv($guinfo[3]-$guinfo[4],$guinfo[4],4); //腾讯接口
        //p判断是否开启抢筹了 
        if(!$sInfo['qcstatus'] && !$userInfo['is_qc']){
            if($zf>$nummmm1 || $zf<$nummmm2){
                // $this->error('已超过涨跌幅，不允许下单');
                $this->error('份额不足,本金优先');
            }
        }
        $balance = round(bcsub($userInfo['balance'],$data['money'],20),2);
        $positionMoney = bcadd($userInfo['positionMoney'],$data['creditMoney'],2);
        Db::startTrans();
        try{
            $createTime = time();
            $data['createtime'] = $createTime;
            $data['c_xq'] = date("w");
            $data['gmrate'] = 0;
            $data['pidcode'] = $userInfo['superior_code'];
            $data['pidname'] = $userInfo['superior_name'];
            $data['pid'] = $userInfo['superior_id'];
            $data['is_simulations'] = $userInfo['is_simulation'];
            $data['buytype'] = 3;
            //增加上级邀请码
            if(Db::name('add_strategy')->insert($data)!==false){
                $orderId = Db::name('add_strategy')->getLastInsID();
                $beforeMoney = Tool::getUserBalance($this->auth->id);
                $userRes = Db::name('user')->where('id',$this->auth->id)->update([
                    'balance' => $balance,
                    'positionMoney' => $positionMoney,
                ]);
                Tool::addLog($this->auth->id,"下单",$beforeMoney,Tool::getUserBalance($this->auth->id),$data['money'],1);
            }
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error('下单失败');
        }
        $this->success('下单成功');
    }
    //指数交易
    public function addStrategy_zs(){
        //判断是否实名
        $identity_card_info = Db::name('identity_card')->where(['user_id'=>$this->auth->id])->find();
        if(!$identity_card_info){
                $this->error("请先前往个人中心,进行实名认证");
            }
        if($identity_card_info['is_audit'] !== "1"){
            $this->error("请先前往个人中心,进行实名认证");
        }
        //判断玩家是否已经禁止登陆或者禁止交易
        $data = $this->request->post();
        $model = new Strategy();
        $data['user_id'] = $this->auth->id;
        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        
        //控制下单金额
        $kz_xdmoney = config('site.zszuidijiaoyijinge');
        if(floatval($kz_xdmoney)>floatval(round(bcdiv($data['cityValue'],$data['multiplying'],2)))){
            $this->error("下单金额不能少于".$kz_xdmoney);
        }
        $sInfo = $model->where('allcode',$data['allcode'])->find();
        $data['strategy_id'] = $sInfo['id'];
        $data['creditMoney'] = round(bcdiv($data['cityValue'],$data['multiplying'],2));
        if($sInfo['status']=='1'){
            $this->error('已禁止买入');
        }
        //配资不能购买抢筹的票
        // if($sInfo['qcstatus']=='1'){
        //     $this->error('已禁止买入');
        // }
        if(!$data['number']){
            $this->error('买入参数错误，请重新买入');
        }
        if(!betweentime(config('site.zsswjiaoyi')) && !betweentime(config('site.zsxwjiaoyi'))){
            $this->error('下单失败,不在交易时间段');
        }
        
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return 
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day])->select();
            if($data11){
                $this->error('下单失败,不在交易时间段');
            }
        }
        //判断整数
        if(!Tool::is__int($data['canBuy'])){
            // $this->error('请输入整数的手数');
        }
        //判断节假日 不允许买入和卖出
        //判断
        if($data['money']>$userInfo['balance']){
            $this->error('余额不足，请及时充值');
        }
        $guinfo = Http::get_stock_now_info($data['allcode']);
        //判断涨幅 超过8% 今天开盘价 和当前价的涨幅
        if(!$guinfo[0]){
            $this->error('下单失败，请联系管理员');
        }
        $nummmm = Tool::getcodetype($data['allcode']);
        $nummmm1 = 0;
        $nummmm2 = 0;
        if($nummmm==3){
            //创业
            $nummmm1 = round(config('site.chuangye_zhang')/100,2); 
            $nummmm2 = round(config('site.chuangye_die')/100,2);
        }else if($nummmm==2){
            //科创
            $nummmm1 = round(config('site.kechuang_zhang')/100,2); 
            $nummmm2 = round(config('site.kechuang_die')/100,2);
        }else if($nummmm==4){
            //北交
            $nummmm1 = round(config('site.bj_zhang')/100,2); 
            $nummmm2 = round(config('site.bj_die')/100,2);
        }else{
            //A股
            $nummmm1 = round(config('site.Agu_zhang')/100,2); 
            $nummmm2 = round(config('site.Agu_die')/100,2);
        }
        //判断 A股还是科创 创业 北交所
        // $zf = bcdiv($guinfo[3]-$guinfo[2],$guinfo[2],4);  新浪
        $zf = bcdiv($guinfo[3]-$guinfo[4],$guinfo[4],4); //腾讯接口
        //p判断是否开启抢筹了 
        if(!$sInfo['qcstatus'] && !$userInfo['is_qc']){
            if($zf>$nummmm1 || $zf<$nummmm2){
                // $this->error('已超过涨跌幅，不允许下单');
                $this->error('份额不足,本金优先');
            }
        }
        $balance = round(bcsub($userInfo['balance'],$data['money'],20),2);
        $positionMoney = bcadd($userInfo['positionMoney'],$data['creditMoney'],2);
        Db::startTrans();
        try{
            $createTime = time();
            $data['createtime'] = $createTime;
            $data['c_xq'] = date("w");
            $data['gmrate'] = 0;
            $data['pidcode'] = $userInfo['superior_code'];
            $data['pidname'] = $userInfo['superior_name'];
            $data['pid'] = $userInfo['superior_id'];
            $data['is_simulations'] = $userInfo['is_simulation'];
            $data['buytype'] = 4;
            $data['pingday'] = 0;
            //增加上级邀请码
            if(Db::name('add_strategy')->insert($data)!==false){
                $orderId = Db::name('add_strategy')->getLastInsID();
                $beforeMoney = Tool::getUserBalance($this->auth->id);
                $userRes = Db::name('user')->where('id',$this->auth->id)->update([
                    'balance' => $balance,
                    'positionMoney' => $positionMoney,
                ]);
                Tool::addLog($this->auth->id,"下单",$beforeMoney,Tool::getUserBalance($this->auth->id),$data['money'],1);
            }
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error('下单失败');
        }
        $this->success('下单成功');
    }
    //当前指数持仓
    public function getNowWarehouse_zs_lishi(){
        $status = $this->request->get('status');
        $buytype = $this->request->get('buytype');
        if($status == 2){
            $status = 3;
        }
        
        $model = new Addstrategy();
        $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('createtime desc')->select();
        if($status == 3){
            $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('outtime desc')->select();
        }
        if($list){
            foreach($list as $k=>$v){
                $list[$k]['title'] = $v['title'];
                $list[$k]['allcode'] = $v['allcode'];
                $list[$k]['cai_buy'] = $v['sellprice'];
                $list[$k]['createtime_name'] = date('Y-m-d H:i:s',$v['createtime']);
                $list[$k]['outtime_name'] = date('Y-m-d H:i:s',$v['outtime']);
                $list[$k]['creditMoney'] = round($v['creditMoney'],2);
                $list[$k]['number'] = round($v['number']); 
                $list[$k]['multiplying'] = $v['multiplying']; 
                $list[$k]['profitLose'] = $v['profitLose'];
                $list[$k]['profitLose_rate'] = number_format(round(bcdiv($v['profitLose'],$v['creditMoney'],20),3)*100,2);
                
            }
        }
        $this->success('请求成功', ['list'=>$list]);
    }
    // 当前指数
    public function getNowWarehouse_zs(){
        $status = $this->request->get('status');
        $buytype = $this->request->get('buytype');
        if($status == 2){
            $status = 3;
        }
        
        $model = new Addstrategy();
        $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('createtime desc')->select();
        if($status == 3){
            $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('outtime desc')->select();
        }
        $total_city_value = 0;
        $total_position_money = 0;
        if($list){
            $arr1 =array_unique(array_column($list,'allcode'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia($allcodes);
            foreach($list as $k=>$v){
                $sModel = new Strategy();
                $sInfo = $sModel->where(['allcode'=>$v['allcode']])->find();
                $list[$k]['title'] = $v['title'];
                $list[$k]['allcode'] = $v['allcode'];
                $list[$k]['cai_buy'] = $allcodes_arr[$v['allcode']];
                $list[$k]['createtime_name'] = date('Y-m-d H:i:s',$v['createtime']);
                $list[$k]['outtime_name'] = date('Y-m-d H:i:s',$v['outtime']);
                $list[$k]['creditMoney'] = round($v['creditMoney'],2);
                $list[$k]['number'] = round($v['number']); 
                $list[$k]['multiplying'] = $v['multiplying'];
                $list[$k]['cvalue'] = round(bcmul($v['creditMoney'],$v['multiplying'],20),2);
                $list[$k]['profitLose'] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number']*$v['multiplying'],2);
                $list[$k]['profitLose_rate'] = number_format(((bcdiv(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['buyprice'],4))*100),2)."%";
                // 判断当前持仓 买涨买跌
                if(round($allcodes_arr[$v['allcode']]-$v['buyprice'],4)>0){
                    // 涨了
                    if($list[$k]['buttontype_s'] == '1'){
                        // 买涨  
                        $list[$k]['profitLose'] = bcmul(abs($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number']*$v['multiplying'],2);
                        $list[$k]['profitLose_rate'] = number_format(((bcdiv(abs($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['buyprice'],4))*100),2)."%";
                    }else{
                        // 买跌
                        $list[$k]['profitLose'] = bcmul(abs($allcodes_arr[$v['allcode']]-$v['buyprice']) * -1,$v['number']*$v['multiplying'],2);
                        $list[$k]['profitLose_rate'] = number_format(((bcdiv(abs($allcodes_arr[$v['allcode']]-$v['buyprice']) * -1,$v['buyprice'],4))*100),2)."%";
                    }
                }else{
                    // 跌了
                    if($list[$k]['buttontype_s'] == '1'){
                        // 买涨
                        $list[$k]['profitLose'] = bcmul(abs($allcodes_arr[$v['allcode']]-$v['buyprice']) * -1,$v['number']*$v['multiplying'],2);
                        $list[$k]['profitLose_rate'] = number_format(((bcdiv(abs($allcodes_arr[$v['allcode']]-$v['buyprice']) * -1,$v['buyprice'],4))*100),2)."%";
                    }else{
                        $list[$k]['profitLose'] = bcmul(abs($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number']*$v['multiplying'],2);
                        $list[$k]['profitLose_rate'] = number_format(((bcdiv(abs($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['buyprice'],4))*100),2)."%";
                    }
                }
                
                
            }
            $total_city_value = round(array_sum(array_column($list,'cvalue')),2);
            $total_position_money = round(array_sum(array_column($list,'profitLose')),2);
        }
        $this->success('请求成功', ['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
    }
    //配资平仓
    public function closeOut_zs(){
        if(!betweentime(config('site.zsswjiaoyi')) && !betweentime(config('site.zsxwjiaoyi'))){
            $this->error('平仓失败,不在交易时间段');
        }
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return 
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day])->select();
            if($data11){
                $this->error('下单失败,不在交易时间段');
            }
        }
        $id = $this->request->post('id');
        $allcode = $this->request->post('allcode');
        $waystatus = $this->request->post('waystatus');
        $model = new Addstrategy();
        $sModel = new Strategy();
        $info = $model->where(['id'=>$id])->find();
        $sInfo = $sModel->where(['allcode'=>$allcode])->find();
        if($info['status'] == "3"){
            $this->error('该票已卖出，等待刷新');
        }
        $todayStime = strtotime(date('Y-m-d',time()).' 00:00:00');
        $todayEtime = strtotime(date('Y-m-d',time()).' 23:59:59');
        $pingday = $info['pingday'];
        $date_current_addone = date('Y-m-d',strtotime("+{$pingday}day",$info['createtime']));
        // $this->error($date_current_addone);
        //卖出的当前时间 就是大于第二天开盘 就OK
        $time = explode('-',config('site.swjiaoyi'))[0];
        // $this->error($time);
        $todayStime_1 = strtotime($date_current_addone.' '.$time);//当前卖出股的第二天9点才能卖
        if(!$sInfo['currentstatus']){
            //未开启当天平仓 就要计算下平仓时间
            if(time()<$todayStime_1){
                $this->error("T+{$pingday}平仓");
            }
        }else{
            //如果开启当天平仓 就不受时间限制
        }
        // file_put_contents('../public/logs/hhhh.txt',var_export($id,true)."\n\n",FILE_APPEND);
        //判断
        $obj = new Http();
        $nowGu1 = $obj->get_stock_now_info($info['allcode']);
        // file_put_contents('../public/logs/hhhh.txt',var_export($nowGu1,true)."\n\n",FILE_APPEND);
        $ff = bcmul($info['number'],$info['multiplying'],2);
        $num3 = bcmul(($nowGu1[3]-$info['buyprice']),$ff,2);
        //收益 也就是
        // $profitLose = $num3>=$info['creditMoney']?$info['creditMoney']:$num3;
        $profitLose = $num3;
        // 判断买账买跌 1涨2跌
        if(round($nowGu1[3]-$info['buyprice'],4)>0){
            // 涨
            if($info['buttontype_s'] =="1"){
                // 买涨
                $profitLose = abs($num3);
            }else{
                // 买跌
                $profitLose = abs($num3) * -1;
            }
        }else{
            // 跌
            if($info['buttontype_s'] =="1"){
                // 买涨
                $profitLose =abs($num3) * -1;
            }else{
                // 买跌
                $profitLose = abs($num3);
            }
        }
        $info->waystatus = 1;
        $info->status = 3;
        $info->profitLose = $profitLose;
        $info->outtime = time();
        $info->sellprice = $nowGu1[3];//$sInfo['cai_buy'];
        //卖出手续费
        $info->yhfee = round($info['creditMoney'] * $info['multiplying'] * config('site.yh_fee'),2);
        $info->sxfee = round($info['creditMoney'] * $info['multiplying'] * config('site.maic_fee'),2);
        
        //卖出收印花税
        if($info->save()!=='false'){
            $userModel = new \app\admin\model\User();
            $beforeMoney = Tool::getUserBalance($info['user_id']);
            $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
            $money = round(bcadd($userInfo['balance']+$info['creditMoney']-$info['yhfee']-$info['sxfee'],$profitLose,20),2);
            $userInfo->balance = $money<=0?0:$money;
            $userInfo->save();
            $addMoney = bcadd($info['creditMoney'],$profitLose,2);
            Tool::addLog($this->auth->id,"平仓收益",$beforeMoney,Tool::getUserBalance($this->auth->id),$addMoney,0,$id);
        }
        Tool::addLog($this->auth->id,"费印花税",0,0,$info['yhfee'],1,$id);
        Tool::addLog($this->auth->id,"平仓手续",0,0,$info['sxfee'],1,$id);
        $this->success('平仓成功');
    }
    //交易记录
    public function capitalLog_jy(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $type = Rsa::check($paramInfo['type']);
        //$type = $this->request->get('type');
        $model = new Addstrategy();
        if($type==0){ //普通交易
            $list = $model->where(['user_id'=>$this->auth->id,'buytype'=>$type])->field('id,user_id,allcode,createtime,creditMoney,status')->order('id desc')->select();
        }elseif($type==1){ //大宗交易
            $list = $model->where(['user_id'=>$this->auth->id,'buytype'=>$type])->field('id,user_id,allcode,createtime,creditMoney,status')->order('id desc')->select();
        }elseif($type==2){ //信用交易
            $list = $model->where(['user_id'=>$this->auth->id,'buytype'=>$type])->field('id,user_id,allcode,createtime,creditMoney,status')->order('id desc')->select();
        }
        //加密
        $data = Rsa::jia(['list'=>$list]);
        $this->success('请求成功', $data);
    }
    public function capitalLog_tabs(){
        // $list = ['普通交易','大宗交易','信用交易','指数交易'];
        $list = "[{name:'普通交易'},{name:'大宗交易'},{name:'信用交易'},{name:'指数交易'}]";
        $this->success('请求成功', ['list'=>$list]);
    }
    //交易记录
    public function capitalLog_jys(){
        $type = $this->request->get('type');
        $model = new Addstrategy();
        if($type==1){ //普通交易
            $list = $model->where(['user_id'=>$this->auth->id,'buytype'=>$type])->field('id,user_id,title,allcode,allMoney,yhfee,sxfee,tuifee,cczr,createtime,creditMoney,status,cczr_type')->order('id desc')->select();
            foreach ($list as $k => $v) {
                if($v['cczr']=='1'){
                    if($v['status'] == '1'){
                        //持仓
                        $txt = "新股申购";
                        if($v['cczr_type'] == "1"){
                            $txt = "线下配售";
                        }elseif($v['cczr_type'] == "2"){
                            $txt = "新股申购";
                        }
                        $list[$k]['codejson'] = array(
                            "text"=>$txt,
                            "color"=>"#ed3f14",
                            "size"=>28
                        );
                    }else{
                        //卖出
                        $list[$k]['codejson'] = array(
                            "text"=>"卖出",
                            "color"=>"#007AFF",
                            "size"=>28
                        );
                    }
                    $list[$k]['createtime_txt'] = date('Y-m-d H:i:s',$v['createtime']);
                }else{
                    if($v['status'] == '1'){
                        //持仓
                        $list[$k]['codejson'] = array(
                            "text"=>"买入",
                            "color"=>"#ed3f14",
                            "size"=>28
                        );
                    }else{
                        //卖出
                        $list[$k]['codejson'] = array(
                            "text"=>"卖出",
                            "color"=>"#007AFF",
                            "size"=>28
                        );
                    }
                    $list[$k]['createtime_txt'] = date('Y-m-d H:i:s',$v['createtime']);
                }
            }
        }elseif($type==2){ //大宗交易
            $list = $model->where(['user_id'=>$this->auth->id,'buytype'=>$type])->field('id,user_id,title,allcode,allMoney,yhfee,sxfee,tuifee,cczr,createtime,creditMoney,status,cczr_type')->order('id desc')->select();
            foreach ($list as $k => $v) {
                if($v['status'] == '1'){
                    //持仓
                    $list[$k]['codejson'] = array(
                        "text"=>"买入",
                        "color"=>"#ed3f14",
                        "size"=>28
                    );
                }else{
                    //卖出
                    $list[$k]['codejson'] = array(
                        "text"=>"卖出",
                        "color"=>"#007AFF",
                        "size"=>28
                    );
                }
                $list[$k]['createtime_txt'] = date('Y-m-d H:i:s',$v['createtime']);
            }
        }elseif($type==3){ //信用交易
            $list = $model->where(['user_id'=>$this->auth->id,'buytype'=>$type])->field('id,user_id,title,allcode,allMoney,yhfee,sxfee,tuifee,cczr,createtime,creditMoney,status,cczr_type')->order('id desc')->select();
            foreach ($list as $k => $v) {
                if($v['status'] == '1'){
                    //持仓
                    $list[$k]['codejson'] = array(
                        "text"=>"买入",
                        "color"=>"#ed3f14",
                        "size"=>28
                    );
                }else{
                    //卖出
                    $list[$k]['codejson'] = array(
                        "text"=>"卖出",
                        "color"=>"#007AFF",
                        "size"=>28
                    );
                }
                $list[$k]['createtime_txt'] = date('Y-m-d H:i:s',$v['createtime']);
            }
        }elseif($type==4){ //信用交易
            $list = $model->where(['user_id'=>$this->auth->id,'buytype'=>$type])->field('id,user_id,title,allcode,allMoney,yhfee,sxfee,tuifee,cczr,createtime,creditMoney,status,cczr_type')->order('id desc')->select();
            foreach ($list as $k => $v) {
                if($v['status'] == '1'){
                    //持仓
                    $list[$k]['codejson'] = array(
                        "text"=>"买入",
                        "color"=>"#ed3f14",
                        "size"=>28
                    );
                }else{
                    //卖出
                    $list[$k]['codejson'] = array(
                        "text"=>"卖出",
                        "color"=>"#007AFF",
                        "size"=>28
                    );
                }
                $list[$k]['createtime_txt'] = date('Y-m-d H:i:s',$v['createtime']);
            }
        }
        $this->success('请求成功', ['list'=>$list]);
    }
    //资金记录
    public function capitalLog(){
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $type = Rsa::check($paramInfo['type']);
        //$type = $this->request->get('type');
        if($type==1){ //提现
            $model = new Withdraw();
            $where['deletetime'] = Null;
            $where['user_id'] = $this->auth->id;
            $list = $model->where($where)->field('id,money,pay_type,is_pay,createtime,reject')->order('id desc')->select();
            if($list){
                foreach($list as $k=>$v){
                    if($v['pay_type']==1){
                        $list[$k]['pay_type_name'] = '微信';
                    }else{
                        $list[$k]['pay_type_name'] = '银证转出';
                    }
                    if($v['is_pay']==1){
                        $list[$k]['is_pay_name'] = '转出成功';
                        // $list[$k]['is_pay_name'] = '成功';
                        $list[$k]['is_pay_fill'] = 'circle-fill';
                        $list[$k]['txtcolor'] = 'green';
                        $list[$k]['biz'] = '人民币';
                    }else if($v['is_pay']==2){
                        // $list[$k]['is_pay_name'] = '失败';
                        $list[$k]['is_pay_name'] = '处理失败';
                        if(!$list[$k]['reject']){
                            $list[$k]['is_pay_name'] = '已取消';
                        }
                        $list[$k]['is_pay_fill'] = 'close-fill';
                        $list[$k]['txtcolor'] = 'red';
                        $list[$k]['biz'] = '人民币';
                    }else{
                        // $list[$k]['is_pay_name'] = '审核中';
                        $list[$k]['is_pay_name'] = '银证转出中';
                        $list[$k]['is_pay_fill'] = 'time-fill';
                        $list[$k]['txtcolor'] = 'blue';
                        $list[$k]['biz'] = '人民币';
                    }
                }
            }
        }elseif($type==0){ //充值
            $model = new Recharge();
            $where['deletetime'] = Null;
            $where['user_id'] = $this->auth->id;
            $list = $model->where($where)->field('id,money,pay_type,is_pay,createtime,reject')->order('id desc')->select();
            if($list){
                foreach($list as $k=>$v){
                    if($v['pay_type']==1){
                        $list[$k]['pay_type_name'] = '微信';
                    }else if($v['pay_type']==2){
                        $list[$k]['pay_type_name'] = '支付宝';
                    }else{
                        $list[$k]['pay_type_name'] = '银证转入';
                    }
                    if($v['is_pay']==1){
                        $list[$k]['is_pay_name'] = '转入成功';
                        // $list[$k]['is_pay_name'] = '成功';
                        $list[$k]['is_pay_fill'] = 'circle-fill';
                        $list[$k]['txtcolor'] = 'green';
                        $list[$k]['biz'] = '人民币';
                    }else if($v['is_pay']==2){
                        // $list[$k]['is_pay_name'] = '失败';
                        $list[$k]['is_pay_name'] = '处理失败';
                        $list[$k]['is_pay_fill'] = 'close-fill';
                        $list[$k]['txtcolor'] = 'red';
                        $list[$k]['biz'] = '人民币';
                    }else{
                        // $list[$k]['is_pay_name'] = '审核中';
                        $list[$k]['is_pay_name'] = '银证转入中';
                        $list[$k]['is_pay_fill'] = 'time-fill';
                        $list[$k]['txtcolor'] = 'blue';
                        $list[$k]['biz'] = '人民币';
                    }
                    // if($v['is_pay']==1){
                    //     $list[$k]['is_pay_name'] = '成功';
                    //     $list[$k]['is_pay_fill'] = 'circle-fill';
                    //     $list[$k]['txtcolor'] = 'green';
                    // }else if($v['is_pay']==2){
                    //     $list[$k]['is_pay_name'] = '失败';
                    //     $list[$k]['is_pay_fill'] = 'close-fill';
                    //     $list[$k]['txtcolor'] = 'red';
                    // }else{
                    //     $list[$k]['is_pay_name'] = '审核中';
                    //     $list[$k]['is_pay_fill'] = 'time-fill';
                    //     $list[$k]['txtcolor'] = 'yellow';
                    // }
                    // if($v['is_pay']){
                    //     $list[$k]['is_pay_name'] = '充值成功';
                    // }else{
                    //     $list[$k]['is_pay_name'] = '待审核';
                    // }
                }
            }
        }elseif($type==2){
            $list = Db::name('capital_log')->where('user_id',$this->auth->id)->order('id desc')->limit(20)->select();
            if($list){
                foreach($list as $k=>$v){
                    if($v['type']){
                        $typeName = '(-)';
                    }else{
                        $typeName = '(+)';
                    }
                    if($v['content']=="延迟费用"){
                        // $list[$k]['pay_type_name']
                        $list[$k]['createtime'] =strtotime(date('Y-m-d',$v['createtime'])." 14:30:00");
                    }
                    // $list[$k]['pay_type_name'] = $v['content'].$typeName;
                    $list[$k]['pay_type_name'] = $v['content'];

                }
            }
        }
        // 根据代理加载出银行卡,'dailiid'=>$this->auth->dailiid
        // 加载出平台银行卡的信息
        $bankmodel = new Sysbanks();
        // $bank_list = $bankmodel->where(['status'=>1,'dailiid'=>$this->auth->dailiid])->select();
        $bank_list =[];
        // if($this->auth->id == 1){
            $bank_list = $bankmodel->where(['status'=>1])->select();
        // }
        // 加载出个人信息
        $userInfo = model('app\admin\model\User')->find($this->auth->id);
        
        $agreement = new Agreement();
        $agreement_info = $agreement->get(2);
        $data = Rsa::jia(['list'=>$list,'bank_list'=>$bank_list,'userInfo'=>$userInfo,'kq_cancle'=>config('site.kq_cancle'),'yhxy'=>$agreement_info['guanbicontent']]);
        $this->success('请求成功', $data);
    }
    
    //当前持仓_历史
    public function getNowWarehouse_lishi(){
        $status = $this->request->get('status');
        $buytype = $this->request->get('buytype');
        if($status == 2){
            $status = 3;
        }
        
        $model = new Addstrategy();
        $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('createtime desc')->limit(10)->select();
        if($status == 3){
            $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('outtime desc')->limit(10)->select();
        }
        if($list){
            foreach($list as $k=>$v){
                $list[$k]['title'] = $v['title'];
                $list[$k]['allcode'] = $v['allcode'];
                $list[$k]['cai_buy'] = $v['sellprice'];
                $list[$k]['createtime_name'] = date('Y-m-d H:i:s',$v['createtime']);
                $list[$k]['outtime_name'] = date('Y-m-d H:i:s',$v['outtime']);
                $list[$k]['creditMoney'] = round($v['creditMoney'],2);
                $list[$k]['number'] = round($v['number']); 
                $list[$k]['profitLose'] = $v['profitLose'];
                $list[$k]['profitLose_rate'] = number_format(round(bcdiv($v['profitLose'],$v['creditMoney'],20),3)*100,2);
                
            }
        }
        $this->success('请求成功', ['list'=>$list]);
    }
    //当前持仓
    public function getNowWarehouse(){
        $status = $this->request->get('status');
        $buytype = $this->request->get('buytype');
        if($status == 2){
            $status = 3;
        }
        $total_city_value = 0;
        $total_position_money = 0;
        $model = new Addstrategy();
        $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('createtime desc')->select();
        if($status == 3){
            $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('outtime desc')->select();
        }
        if($list){
            $arr1 =array_unique(array_column($list,'allcode'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia($allcodes);
            foreach($list as $k=>$v){
                $sModel = new Strategy();
                $sInfo = $sModel->where(['allcode'=>$v['allcode']])->find();
                $list[$k]['title'] = $v['title'];
                $list[$k]['allcode'] = $v['allcode'];
                $list[$k]['cai_buy'] = $allcodes_arr[$v['allcode']];
                $list[$k]['createtime_name'] = date('Y-m-d H:i:s',$v['createtime']);
                $list[$k]['outtime_name'] = date('Y-m-d H:i:s',$v['outtime']);
                $list[$k]['creditMoney'] = round($v['creditMoney'],2);
                $list[$k]['citycc'] = bcmul(($allcodes_arr[$v['allcode']]),$v['number'],2);
                $list[$k]['number'] = round($v['number']);
                $list[$k]['profitLose'] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number'],2);
                $list[$k]['profitLose_rate'] = number_format(((bcdiv(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['buyprice'],4))*100),2)."%";
                
            }
            $total_city_value = round(array_sum(array_column($list,'citycc')),2);
            $total_position_money = round(array_sum(array_column($list,'profitLose')),2);
        }
        $this->success('请求成功', ['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
    }
    
    //当前配资持仓
    public function getNowWarehouse_pz_lishi(){
        $status = $this->request->get('status');
        $buytype = $this->request->get('buytype');
        if($status == 2){
            $status = 3;
        }
        
        $model = new Addstrategy();
        $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('createtime desc')->select();
        if($status == 3){
            $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('outtime desc')->select();
        }
        if($list){
            foreach($list as $k=>$v){
                $list[$k]['title'] = $v['title'];
                $list[$k]['allcode'] = $v['allcode'];
                $list[$k]['cai_buy'] = $v['sellprice'];
                $list[$k]['createtime_name'] = date('Y-m-d H:i:s',$v['createtime']);
                $list[$k]['outtime_name'] = date('Y-m-d H:i:s',$v['outtime']);
                $list[$k]['creditMoney'] = round($v['creditMoney'],2);
                $list[$k]['number'] = round($v['number']); 
                $list[$k]['multiplying'] = $v['multiplying']; 
                $list[$k]['profitLose'] = $v['profitLose'];
                $list[$k]['profitLose_rate'] = number_format(round(bcdiv($v['profitLose'],$v['creditMoney'],20),3)*100,2);
                
            }
        }
        $this->success('请求成功', ['list'=>$list]);
    }
    //当前配资持仓
    public function getNowWarehouse_pz(){
        $status = $this->request->get('status');
        $buytype = $this->request->get('buytype');
        if($status == 2){
            $status = 3;
        }
        
        $model = new Addstrategy();
        $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('createtime desc')->select();
        if($status == 3){
            $list = $model->where(['deletetime'=>Null,'user_id'=>$this->auth->id,'status'=>$status,'buytype'=>$buytype])->order('outtime desc')->select();
        }
        $total_city_value = 0;
        $total_position_money = 0;
        if($list){
            $arr1 =array_unique(array_column($list,'allcode'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia($allcodes);
            foreach($list as $k=>$v){
                $sModel = new Strategy();
                $sInfo = $sModel->where(['allcode'=>$v['allcode']])->find();
                $list[$k]['title'] = $v['title'];
                $list[$k]['allcode'] = $v['allcode'];
                $list[$k]['cai_buy'] = $allcodes_arr[$v['allcode']];
                $list[$k]['createtime_name'] = date('Y-m-d H:i:s',$v['createtime']);
                $list[$k]['outtime_name'] = date('Y-m-d H:i:s',$v['outtime']);
                $list[$k]['creditMoney'] = round($v['creditMoney'],2);
                $list[$k]['number'] = round($v['number']); 
                $list[$k]['multiplying'] = $v['multiplying'];
                $list[$k]['cvalue'] = round(bcmul($v['creditMoney'],$v['multiplying'],20),2);
                $list[$k]['profitLose'] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number']*$v['multiplying'],2);
                $list[$k]['profitLose_rate'] = number_format(((bcdiv(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['buyprice'],4))*100),2)."%";
                
            }
            $total_city_value = round(array_sum(array_column($list,'cvalue')),2);
            $total_position_money = round(array_sum(array_column($list,'profitLose')),2);
        }
        $this->success('请求成功', ['list'=>$list,'total_city_value'=>$total_city_value,'position_money'=>$total_position_money]);
    }
    
    //大宗平仓
    public function closeOut_dz(){
        if(!betweentime(config('site.dzjy_shijian'))){
            $this->error('平仓失败,不在交易时间段');
        }
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return 
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day])->select();
            if($data11){
                $this->error('下单失败,不在交易时间段');
            }
        }
        $id = $this->request->post('id');
        $allcode = $this->request->post('allcode');
        $waystatus = $this->request->post('waystatus');
        $model = new Addstrategy();
        $sModel = new Strategy();
        $info = $model->where(['id'=>$id])->find();
        $sInfo = $sModel->where(['allcode'=>$allcode])->find();
        if($info['status'] == "3"){
            $this->error('该票已卖出，等待刷新');
        }
        $todayStime = strtotime(date('Y-m-d',time()).' 00:00:00');
        $todayEtime = strtotime(date('Y-m-d',time()).' 23:59:59');
        $pingday = $info['pingday'];
        $date_current_addone = date('Y-m-d',strtotime("+{$pingday}day",$info['createtime']));
        //卖出的当前时间 就是大于第二天开盘 就OK
        $time = explode('-',config('site.dzjy_shijian'))[0];
        // $this->error($time);
        $todayStime_1 = strtotime($date_current_addone.' '.$time);//当前卖出股的第二天9点才能卖
        if(!$sInfo['currentstatus']){
            // $this->error($date_current_addone);
            //未开启当天平仓 就要计算下平仓时间
            if(time()<$todayStime_1){
                // $this->error('T+1平仓');
                $this->error("T+{$pingday}平仓");
            }
        }else{
            
            // $this->error('222222222');
            //如果开启当天平仓 就不受时间限制
        }
        // file_put_contents('../public/logs/hhhh.txt',var_export($id,true)."\n\n",FILE_APPEND);
        //判断
        $obj = new Http();
        $nowGu1 = $obj->get_stock_now_info($info['allcode']);
        // file_put_contents('../public/logs/hhhh.txt',var_export($nowGu1,true)."\n\n",FILE_APPEND);
        $num3 = bcmul(($nowGu1[3]-$info['buyprice']),$info['number'],2);
        //收益 也就是
        $profitLose = $num3;
        $info->waystatus = 1;
        $info->status = 3;
        $info->profitLose = $profitLose;
        $info->outtime = time();
        $info->sellprice = $nowGu1[3];//$sInfo['cai_buy'];
        //卖出手续费
        $info->yhfee = round($info['creditMoney'] * config('site.yh_fee'),2);
        $info->sxfee = round($info['creditMoney'] * config('site.maic_fee'),2);
        
        //卖出收印花税
        if($info->save()!=='false'){
            $userModel = new \app\admin\model\User();
            $beforeMoney = Tool::getUserBalance($info['user_id']);
            $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
            $money = round(bcadd($userInfo['balance']+$info['creditMoney']-$info['yhfee']-$info['sxfee'],$profitLose,20),2);
            $userInfo->balance = $money;
            $userInfo->save();
            $addMoney = bcadd($info['creditMoney'],$profitLose,2);
            Tool::addLog($this->auth->id,"平仓收益",$beforeMoney,Tool::getUserBalance($this->auth->id),$addMoney,0,$id);
        }
        Tool::addLog($this->auth->id,"费印花税",0,0,$info['yhfee'],1,$id);
        Tool::addLog($this->auth->id,"平仓手续",0,0,$info['sxfee'],1,$id);
        $this->success('平仓成功');
    }
    //平仓
    public function closeOut(){
        if(!betweentime(config('site.swjiaoyi')) && !betweentime(config('site.xwjiaoyi'))){
            $this->error('平仓失败,不在交易时间段');
        }
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return 
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day])->select();
            if($data11){
                $this->error('下单失败,不在交易时间段');
            }
        }
        $id = $this->request->post('id');
        $allcode = $this->request->post('allcode');
        $waystatus = $this->request->post('waystatus');
        $model = new Addstrategy();
        $sModel = new Strategy();
        $info = $model->where(['id'=>$id])->find();
        $sInfo = $sModel->where(['allcode'=>$allcode])->find();
        if($info['status'] == "3"){
            $this->error('该票已卖出，等待刷新');
        }
        $todayStime = strtotime(date('Y-m-d',time()).' 00:00:00');
        $todayEtime = strtotime(date('Y-m-d',time()).' 23:59:59');
        $pingday = $info['pingday'];
        $date_current_addone = date('Y-m-d',strtotime("+{$pingday}day",$info['createtime']));
        // $this->error($date_current_addone);
        //卖出的当前时间 就是大于第二天开盘 就OK
        $time = explode('-',config('site.swjiaoyi'))[0];
        // $this->error($time);
        $todayStime_1 = strtotime($date_current_addone.' '.$time);//当前卖出股的第二天9点才能卖
        if(!$sInfo['currentstatus']){
            //未开启当天平仓 就要计算下平仓时间
            if(time()<$todayStime_1){
                $this->error("T+{$pingday}平仓");
            }
        }else{
            //如果开启当天平仓 就不受时间限制
        }
        // file_put_contents('../public/logs/hhhh.txt',var_export($id,true)."\n\n",FILE_APPEND);
        //判断
        $obj = new Http();
        $nowGu1 = $obj->get_stock_now_info($info['allcode']);
        // file_put_contents('../public/logs/hhhh.txt',var_export($nowGu1,true)."\n\n",FILE_APPEND);
        $num3 = bcmul(($nowGu1[3]-$info['buyprice']),$info['number'],2);
        //收益 也就是
        $profitLose = $num3;
        $info->waystatus = 1;
        $info->status = 3;
        $info->profitLose = $profitLose;
        $info->outtime = time();
        $info->sellprice = $nowGu1[3];//$sInfo['cai_buy'];
        //卖出手续费
        $info->yhfee = round($info['creditMoney'] * config('site.yh_fee'),2);
        $info->sxfee = round($info['creditMoney'] * config('site.maic_fee'),2);
        
        // 记录两个值，
        $info->fxmoney = round(bcadd($info['creditMoney']-$info['yhfee']-$info['sxfee'],$profitLose,20),2);
        $info->fxsatus = config('site.fx_pc');
        
        //卖出收印花税
        if($info->save()!=='false'){
            $userModel = new \app\admin\model\User();
            $beforeMoney = Tool::getUserBalance($info['user_id']);
            $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
            $money = round(bcadd($userInfo['balance']+$info['creditMoney']-$info['yhfee']-$info['sxfee'],$profitLose,20),2);
            $userInfo->balance = $money;
            if(config('site.fx_pc')){
                $userInfo->save();
            }
            $addMoney = bcadd($info['creditMoney'],$profitLose,2);
            Tool::addLog($this->auth->id,"平仓收益",$beforeMoney,Tool::getUserBalance($this->auth->id),$addMoney,0,$id);
        }
        Tool::addLog($this->auth->id,"费印花税",0,0,$info['yhfee'],1,$id);
        Tool::addLog($this->auth->id,"平仓手续",0,0,$info['sxfee'],1,$id);
        $this->success('平仓成功');
    }
    
    //平仓
    public function closeOut_1(){
        if(!betweentime(config('site.swjiaoyi')) && !betweentime(config('site.xwjiaoyi'))){
            $this->error('平仓失败,不在交易时间段');
        }
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return 
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day])->select();
            if($data11){
                $this->error('下单失败,不在交易时间段');
            }
        }
        // 当前记录ID
        $id = $this->request->post('id');
        // 股票代码全程
        $allcode = $this->request->post('allcode');
        // 手数
        $canbuy = $this->request->post('canBuy');
        // 固定1
        $waystatus = $this->request->post('waystatus');
        $model = new Addstrategy();
        $sModel = new Strategy();
        $info = $model->where(['id'=>$id])->find();
        $sInfo = $sModel->where(['allcode'=>$allcode])->find();
        if($info['status'] == "3"){
            $this->error('该票已卖出，等待刷新');
        }
        $todayStime = strtotime(date('Y-m-d',time()).' 00:00:00');
        $todayEtime = strtotime(date('Y-m-d',time()).' 23:59:59');
        $pingday = $info['pingday'];
        $date_current_addone = date('Y-m-d',strtotime("+{$pingday}day",$info['createtime']));
        // $this->error($date_current_addone);
        //卖出的当前时间 就是大于第二天开盘 就OK
        $time = explode('-',config('site.swjiaoyi'))[0];
        // $this->error($time);
        $todayStime_1 = strtotime($date_current_addone.' '.$time);//当前卖出股的第二天9点才能卖
        if(!$sInfo['currentstatus']){
            //未开启当天平仓 就要计算下平仓时间
            if(time()<$todayStime_1){
                $this->error("T+{$pingday}平仓");
            }
        }else{
            //如果开启当天平仓 就不受时间限制
        }
        // file_put_contents('../public/logs/hhhh.txt',var_export($id,true)."\n\n",FILE_APPEND);
        //判断 判断当前传入的手数和代码里面数是否一致 如果一致就是直接全部平仓
        if(intval($canBuy) == $info['canBuy']){
            $obj = new Http();
            $nowGu1 = $obj->get_stock_now_info($info['allcode']);
            // file_put_contents('../public/logs/hhhh.txt',var_export($nowGu1,true)."\n\n",FILE_APPEND);
            $num3 = bcmul(($nowGu1[3]-$info['buyprice']),$info['number'],2);
            //收益 也就是
            $profitLose = $num3;
            $info->waystatus = 1;
            $info->status = 3;
            $info->profitLose = $profitLose;
            $info->outtime = time();
            $info->sellprice = $nowGu1[3];//$sInfo['cai_buy'];
            //卖出手续费
            $info->yhfee = round($info['creditMoney'] * config('site.yh_fee'),2);
            $info->sxfee = round($info['creditMoney'] * config('site.maic_fee'),2);
            
            //卖出收印花税
            if($info->save()!=='false'){
                $userModel = new \app\admin\model\User();
                $beforeMoney = Tool::getUserBalance($info['user_id']);
                $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
                $money = round(bcadd($userInfo['balance']+$info['creditMoney']-$info['yhfee']-$info['sxfee']    ,$profitLose,20),2);
                $userInfo->balance = $money;
                $userInfo->save();
                $addMoney = bcadd($info['creditMoney'],$profitLose,2);
                Tool::addLog($this->auth->id,"平仓收益",$beforeMoney,Tool::getUserBalance($this->auth->id),$addMoney    ,0,$id);
            }
            Tool::addLog($this->auth->id,"费印花税",0,0,$info['yhfee'],1,$id);
            Tool::addLog($this->auth->id,"平仓手续",0,0,$info['sxfee'],1,$id);
            $this->success('平仓成功');
        }else{
            // 将其中一条拆分成两条，修改原来的数据 ，新增现在的数据
            // 需要改的 creditMoney  allMoney canBuy cityValue money recommendnum number
            //新增的 creditMoney = $canbuy(手数) *100 *股价(原来)
            // 新增的 canBuy = $canbuy(手数)
            // 新增 allMoney(买入手续费) = creditMoney * 费率
            // 新增的 cityValue = $canbuy(手数) *100 *股价(原来)
            // 新增的 money = creditMoney + allMoney
            // 新增的 recommendnum = $canbuy(手数) *100 *股价(原来)
            // 新增的 number = $canbuy(手数) *100
            $new_info_act = new Addstrategy();
            $new_info = $info;
            unset($new_info->id);
            $new_info['creditMoney'] = round($canbuy * 100 * $info['buyprice']);
            $new_info['allMoney'] = round($canbuy * 100 * $info['buyprice'] * config('site.mai_fee'),2);
            $new_info['canBuy'] = $canbuy;
            // 在其他地方应该是按照最新价来计算市值，现在这个地方就按照买入价
            $new_info['cityValue'] = round($canbuy * 100 * $info['buyprice'],2);
            $new_info['money'] =bcadd($new_info['creditMoney'], $new_info['allMoney'],2);
            $new_info['recommendnum'] = round($canbuy * 100 * $info['buyprice']);
            $new_info['number'] = $canbuy * 100;
            // 将ID过滤 新增成一条已经卖出的记录。并且要对user表进行修改
            $obj = new Http();
            $nowGu1 = $obj->get_stock_now_info($new_info['allcode']);
            // file_put_contents('../public/logs/hhhh.txt',var_export($nowGu1,true)."\n\n",FILE_APPEND);
            $num3 = bcmul(($nowGu1[3]-$new_info['buyprice']),$new_info['number'],2);
            //收益 也就是
            $profitLose = $num3;$new_info->waystatus = 1;$new_info->status = 3;$new_info->profitLose = $profitLose;$new_info->outtime = time();$new_info->sellprice = $nowGu1[3];//$sInfo['cai_buy'];
            //卖出手续费
            $new_info->yhfee = round($new_info['creditMoney'] * config('site.yh_fee'),2);
            $new_info->sxfee = round($new_info['creditMoney'] * config('site.maic_fee'),2);
            //卖出收印花税
            $new_info_act->data($new_info);
            if($new_info_act->save()!=='false'){
                $userModel = new \app\admin\model\User();
                $beforeMoney = Tool::getUserBalance($info['user_id']);
                $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
                $money = round(bcadd($userInfo['balance']+$new_info['creditMoney']-$new_info['yhfee']-$new_info['sxfee'],$profitLose,20),2);
                $userInfo->balance = $money;
                $userInfo->save();
                $addMoney = bcadd($new_info['creditMoney'],$profitLose,2);
                Tool::addLog($this->auth->id,"平仓收益",$beforeMoney,Tool::getUserBalance($this->auth->id),$addMoney,0,$id);
            }
            Tool::addLog($this->auth->id,"费印花税",0,0,$new_info['yhfee'],1,$new_info_act->id);
            Tool::addLog($this->auth->id,"平仓手续",0,0,$new_info['sxfee'],1,$new_info_act->id);
            
            
            
            
            // 修改老的记录
            $info['creditMoney'] = $info['creditMoney'] - $new_info['creditMoney'];
            $info['allMoney'] = $info['allMoney'] - $new_info['allMoney'];
            $info['canBuy'] = $info['canBuy'] - $new_info['canBuy'];
            $info['cityValue'] = $info['cityValue'] - $new_info['cityValue'];
            $info['money'] = $info['money'] - $new_info['money'];
            $info['recommendnum'] = $info['recommendnum'] - $new_info['recommendnum'];
            $info['number'] = $info['number'] - $new_info['number'];
            // $info 做修改
            $info->save();
            
            $this->success('平仓成功');
            
            
            
            
        }
        
    }
    
    //配资平仓
    public function closeOut_pz(){
        if(!betweentime(config('site.swjiaoyi')) && !betweentime(config('site.xwjiaoyi'))){
            $this->error('平仓失败,不在交易时间段');
        }
        if(config('site.limit_time')){
            $current_day = date('m-d',time());
            //当前如果是节假日 就直接return 
            $data11 = Db::name("holiday")->where(['time_str'=>$current_day])->select();
            if($data11){
                $this->error('下单失败,不在交易时间段');
            }
        }
        $id = $this->request->post('id');
        $allcode = $this->request->post('allcode');
        $waystatus = $this->request->post('waystatus');
        $model = new Addstrategy();
        $sModel = new Strategy();
        $info = $model->where(['id'=>$id])->find();
        $sInfo = $sModel->where(['allcode'=>$allcode])->find();
        if($info['status'] == "3"){
            $this->error('该票已卖出，等待刷新');
        }
        $todayStime = strtotime(date('Y-m-d',time()).' 00:00:00');
        $todayEtime = strtotime(date('Y-m-d',time()).' 23:59:59');
        $pingday = $info['pingday'];
        $date_current_addone = date('Y-m-d',strtotime("+{$pingday}day",$info['createtime']));
        // $this->error($date_current_addone);
        //卖出的当前时间 就是大于第二天开盘 就OK
        $time = explode('-',config('site.swjiaoyi'))[0];
        // $this->error($time);
        $todayStime_1 = strtotime($date_current_addone.' '.$time);//当前卖出股的第二天9点才能卖
        if(!$sInfo['currentstatus']){
            //未开启当天平仓 就要计算下平仓时间
            if(time()<$todayStime_1){
                $this->error("T+{$pingday}平仓");
            }
        }else{
            //如果开启当天平仓 就不受时间限制
        }
        // file_put_contents('../public/logs/hhhh.txt',var_export($id,true)."\n\n",FILE_APPEND);
        //判断
        $obj = new Http();
        $nowGu1 = $obj->get_stock_now_info($info['allcode']);
        // file_put_contents('../public/logs/hhhh.txt',var_export($nowGu1,true)."\n\n",FILE_APPEND);
        $ff = bcmul($info['number'],$info['multiplying'],2);
        $num3 = bcmul(($nowGu1[3]-$info['buyprice']),$ff,2);
        //收益 也就是
        // $profitLose = $num3>=$info['creditMoney']?$info['creditMoney']:$num3;
        $profitLose = $num3;
        $info->waystatus = 1;
        $info->status = 3;
        $info->profitLose = $profitLose;
        $info->outtime = time();
        $info->sellprice = $nowGu1[3];//$sInfo['cai_buy'];
        //卖出手续费
        $info->yhfee = round($info['creditMoney'] * $info['multiplying'] * config('site.yh_fee'),2);
        $info->sxfee = round($info['creditMoney'] * $info['multiplying'] * config('site.maic_fee'),2);
        
        //卖出收印花税
        if($info->save()!=='false'){
            $userModel = new \app\admin\model\User();
            $beforeMoney = Tool::getUserBalance($info['user_id']);
            $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
            $money = round(bcadd($userInfo['balance']+$info['creditMoney']-$info['yhfee']-$info['sxfee'],$profitLose,20),2);
            $userInfo->balance = $money<=0?0:$money;
            $userInfo->save();
            $addMoney = bcadd($info['creditMoney'],$profitLose,2);
            Tool::addLog($this->auth->id,"平仓收益",$beforeMoney,Tool::getUserBalance($this->auth->id),$addMoney,0,$id);
        }
        Tool::addLog($this->auth->id,"费印花税",0,0,$info['yhfee'],1,$id);
        Tool::addLog($this->auth->id,"平仓手续",0,0,$info['sxfee'],1,$id);
        $this->success('平仓成功');
    }
    //修改止和损
    public function editPrice(){
        $id = $this->request->post('id');
        $type = $this->request->post('type');
        $is_auto_money = $this->request->post('is_auto_money');
        $editPrice = $this->request->post('editPrice');
        $model = new Addstrategy();
        $info = $model->where(['id'=>$id])->find();
        //判断止盈止亏 沪深 跌10% 涨10%  创业 跌20% 涨60%  根据买入价  buyprice
        if(getbanbycode($info['allcode'])){
            //创业 
            if($type==1){
                if(floatval($editPrice)<floatval($info['buyprice'])){
                    $this->error('设置错误，不能低于当前价');
                }
                if(floatval($editPrice)>floatval($info['buyprice']*1.6)){
                    $this->error('止盈设置高于60%');
                }
            }else if($type==2){
                //  $this->error('止亏设置11111太低')
                if(floatval($editPrice)>floatval($info['buyprice'])){
                    $this->error('设置错误，不能高于当前价');
                }
                if(floatval($editPrice)<bcmul($info['buyprice'],0.8,2) ){
                    $this->error('止亏设置太低，低于20%');
                }
            }
        }else{
            //沪深
            if($type==1){
                if(floatval($editPrice)<floatval($info['buyprice'])){
                    $this->error('设置错误，不能低于当前价');
                }
                if(floatval($editPrice)>floatval($info['buyprice']*1.3)){
                    $this->error('止盈设置高于30%');
                }
            }else if($type==2){
                //  $this->error('止亏设置11111太低')
                if(floatval($editPrice)>floatval($info['buyprice'])){
                    $this->error('设置错误，不能高于当前价');
                }
                if(floatval($editPrice)<bcmul($info['buyprice'],0.9,2) ){
                    $this->error('止亏设置太低，低于10%');
                }
            }
        }
        if($type==1){
           $info->profitPrice = $editPrice;
        }else if($type==2){
            $info->losePrice = $editPrice;
        }else if($type==3){
            $info->is_auto_money = $is_auto_money;
        }
        $info->save();
        $this->success('操作成功');
    }

    public function count($num1,$num2,$num3,$num4){
        $money = bcsub($num1,$num2,20);
        $money = bcmul($money,$num3,20);
        $money = round(bcmul($money,$num4,20),2);
        return $money;
    }
    
    //配资盈亏
    public function getUserPrice_pz(){
        $buytype = $this->request->get('buytype');
        $city_value = 0;
        $position_money = 0;
        $yesterday_profit = 0;
        $property_money = 0;
        $property_money_total = 0;
        $model = new Addstrategy();
        $strModel = new Strategy();
        //卖出的不计入持仓
        $info = $model->where(['user_id'=>$this->auth->id,'status'=>1,'buytype'=>$buytype])->select();
        if($info) {
            $arr1 =array_unique(array_column($info,'allcode'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia($allcodes);
            foreach ($info as $k => $v) {
                $city_value_arr[] = $v['cityValue'];
                $position_money_arr[] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number']*$v['multiplying'],2);
            }
            $city_value = round(array_sum($city_value_arr),2);
            $position_money = round(array_sum($position_money_arr),2);
            //登陆持仓+可用金额+
        }
        $infoinfo = Db::name('user')->where(['id'=>$this->auth->id])->find();
        $property_money = round(floatval($city_value)+floatval($infoinfo['balance']),2);
        //登陆持仓+可用金额+申购冻结
        // $property_money_total = round(floatval($city_value)+floatval($infoinfo['balance'])+floatval($infoinfo['sg_freeze_money']),2);
        $property_money_total = round(floatval($city_value)+floatval($infoinfo['sg_freeze_money']),2);
        $data=  [
            'city_value' => $city_value,
            'position_money' => $position_money,
            'yesterday_profit' => 0,
            'property_money' => $property_money,
            "property_money_total" => $property_money_total
        ];

        $this->success('操作成功',['list'=>$data]);

    }
    //zhishu盈亏
    public function getUserPrice_zs(){
        $buytype = $this->request->get('buytype');
        $city_value = 0;
        $position_money = 0;
        $yesterday_profit = 0;
        $property_money = 0;
        $property_money_total = 0;
        $model = new Addstrategy();
        $strModel = new Strategy();
        //卖出的不计入持仓
        $info = $model->where(['user_id'=>$this->auth->id,'status'=>1,'buytype'=>$buytype])->select();
        if($info) {
            $arr1 =array_unique(array_column($info,'allcode'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia($allcodes);
            foreach ($info as $k => $v) {
                $city_value_arr[] = $v['cityValue'];
                $position_money_arr[] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number']*$v['multiplying'],2);
            }
            $city_value = round(array_sum($city_value_arr),2);
            $position_money = round(array_sum($position_money_arr),2);
            //登陆持仓+可用金额+
        }
        $infoinfo = Db::name('user')->where(['id'=>$this->auth->id])->find();
        $property_money = round(floatval($city_value)+floatval($infoinfo['balance']),2);
        //登陆持仓+可用金额+申购冻结
        // $property_money_total = round(floatval($city_value)+floatval($infoinfo['balance'])+floatval($infoinfo['sg_freeze_money']),2);
        $property_money_total = round(floatval($city_value)+floatval($infoinfo['sg_freeze_money']),2);
        $data=  [
            'city_value' => $city_value,
            'position_money' => $position_money,
            'yesterday_profit' => 0,
            'property_money' => $property_money,
            "property_money_total" => $property_money_total
        ];

        $this->success('操作成功',['list'=>$data]);

    }
    //盈亏
    public function getUserPrice(){
        $buytype = $this->request->get('buytype');
        $city_value = 0;
        $position_money = 0;
        $yesterday_profit = 0;
        $property_money = 0;
        $property_money_total = 0;
        $model = new Addstrategy();
        $strModel = new Strategy();
        //卖出的不计入持仓
        $info = $model->where(['user_id'=>$this->auth->id,'status'=>1,'buytype'=>$buytype])->select();
        if($info) {
            $arr1 =array_unique(array_column($info,'allcode'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia($allcodes);
            foreach ($info as $k => $v) {
                $city_value_arr[] = $v['cityValue'];
                $position_money_arr[] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number'],2);
            }
            $city_value = round(array_sum($city_value_arr),2);
            $position_money = round(array_sum($position_money_arr),2);
            //登陆持仓+可用金额+
        }
        $infoinfo = Db::name('user')->where(['id'=>$this->auth->id])->find();
        $property_money = round(floatval($city_value)+floatval($infoinfo['balance']),2);
        //登陆持仓+可用金额+申购冻结
        // $property_money_total = round(floatval($city_value)+floatval($infoinfo['balance'])+floatval($infoinfo['sg_freeze_money']),2);
        $property_money_total = round(floatval($city_value)+floatval($infoinfo['sg_freeze_money']),2);
        $data=  [
            'city_value' => $city_value,
            'position_money' => $position_money,
            'yesterday_profit' => 0,
            'property_money' => $property_money,
            "property_money_total" => $property_money_total
        ];

        $this->success('操作成功',['list'=>$data]);

    }

    
    //普通交易的盈亏
    public function getUserPrice_all1(){
        // 这边只算普通交易
        $totalyk = 0;
        $fdyk = 0;
        $city_value1 = 0;
        $city_value2 = 0;
        $position_money = 0;
        $yesterday_profit = 0;
        $property_money = 0;
        $xingu_total0 = 0;
        $xingu_total = 0;
        $zhanyongzj = 0;
        $weituozj = 0;
        $property_money_total = 0;
        $model = new Addstrategy();
        $strModel = new Strategy();
        // file_put_contents('../public/logs//loggg.txt',var_export($this->auth->id,true)."\n\n",FILE_APPEND);
        //卖出的不计入持仓
        $info = $model->where(['user_id'=>$this->auth->id,'status'=>"1",'buytype'=>1])->select();
        // 当前是卖出 但是没有返回的 也算占用资金
        $info_new = $model->where(['user_id'=>$this->auth->id,'status'=>"3",'fxsatus'=>"0",'buytype'=>1])->select();
        $info = array_merge($info,$info_new);
        // file_put_contents('../public/logs//loggg.txt',var_export($info,true)."\n\n",FILE_APPEND);
        if($info) {
            //$allcodes = "";//格式 sh600000,sh600000
            $arr1 =array_unique(array_column($info,'allcode'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia($allcodes);
            foreach ($info as $k => $v) {
                // $city_value_arr[] = $v['cityValue'];
                $zhanyongzj_arr[] = $v['creditMoney'];
                // file_put_contents('../public/logs//loggg.txt',var_export($v['creditMoney'],true)."\n\n",FILE_APPEND);
                if($v['buytype'] == 3 || $v['buytype'] == 4){
                    // $fdyk_arr[] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number']*$v['multiplying'],2);
                    // $city_value_arr[] =  bcmul($allcodes_arr[$v['allcode']],$v['number']*$v['multiplying'],2);
                }else if($v['buytype'] == 1){
                    $fdyk_arr[] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number'],2);
                    $city_value_arr[] = bcmul($allcodes_arr[$v['allcode']],$v['number'],2);
                }
            }
            $city_value1 = round(array_sum($city_value_arr),2);
            $zhanyongzj = round(array_sum($zhanyongzj_arr),2);
            $fdyk = round(array_sum($fdyk_arr),2);
            //登陆持仓+可用金额+
        }
        $infoinfo = Db::name('user')->where(['id'=>$this->auth->id])->find();
        //登陆持仓+可用金额+申购冻结
        // $property_money_total = round(floatval($city_value)+floatval($infoinfo['balance'])+floatval($infoinfo['sg_freeze_money']),2);
        // $property_money_total = round(floatval($city_value)+floatval($infoinfo['sg_freeze_money']),2);
        
        $info = $model->where(['user_id'=>$this->auth->id,'status'=>3,'buytype'=>1])->select();
        if($info) {
            foreach ($info as $k => $v) {
                // $city_value_arr1[] = $v['cityValue'];
                $totalyk_arr[] = $v['profitLose'];
            }
            // $city_value2 = round(array_sum($city_value_arr1),2);
            $totalyk = round(array_sum($totalyk_arr),2);
        }
        $sgjiaoyi_model = new Sgjiaoyi();//先扣钱的
        $sgjiaoyi0_model = new Sgjiaoyi0();
        
        //中签的 未转入持仓的
        $info = $sgjiaoyi_model->where(['user_id'=>$this->auth->id,'status'=>['in',[0,1]],'is_cc'=>0])->select();
        if($info) {
            foreach ($info as $k => $v) {
                if($v['is_tz'] == 1){
                    $xingu_total_arr[] = $v['zq_money'];
                }else{
                    $xingu_total_arr[] = $v['dj_money'];
                }
            }
            $xingu_total = round(array_sum($xingu_total_arr),2);
        }
        $info = $sgjiaoyi0_model->where(['user_id'=>$this->auth->id,'status'=>1,'is_cc'=>0,'renjiao'=>1])->select();
        if($info) {
            foreach ($info as $k => $v) {
                $xingu_total0_arr[] = $v['zq_money'];
            }
            $xingu_total0 = round(array_sum($xingu_total0_arr),2);
        }
        
        // 计算委托 只有A股的
        $weituozj = round($model->where(['user_id'=>$this->auth->id,'status'=>"2",'buytype'=>"1"])->sum('money'),2);
        $data=  [
            'city_value' => round(bcadd($city_value1,$city_value2,2),2),
            'yesterday_profit' => 0,
            // 'zhanyongzj' => round($zhanyongzj+$infoinfo['freeze_profit'],2),
            'zhanyongzj' => round($zhanyongzj,2),
            'totalyk' => round($totalyk,2),
            'fdyk' => round($fdyk,2),
            'xingu_total' => round(bcadd($xingu_total,$xingu_total0,2),2),
            // 'property_money_total' =>round($zhanyongzj+ $infoinfo['freeze_profit']  + $infoinfo['balance'] + bcadd($xingu_total,$xingu_total0,2),2)
            'property_money_total' =>round($zhanyongzj+ $infoinfo['balance'] + $weituozj + bcadd($xingu_total,$xingu_total0,2),2),
            'balance' => $infoinfo['balance'],
            'freeze_profit' => $infoinfo['freeze_profit'],
            'weituozj' => $weituozj
        ];
        $datas = ['list'=>$data];
        //加密
        $datas = Rsa::jia($datas);
        $this->success('操作成功',['list'=>$data]);

    }
    //个人中心的盈亏
    public function getUserPrice_all(){
        $totalyk = 0;
        $fdyk = 0;
        $city_value1 = 0;
        $city_value2 = 0;
        $position_money = 0;
        $yesterday_profit = 0;
        $property_money = 0;
        $xingu_total0 = 0;
        $xingu_total = 0;
        $zhanyongzj = 0;
        $property_money_total = 0;
        $city_value_qh = 0;
        $fdyk_qh = 0;
        $zhanyongzj_qh = 0;
        $weituozj = 0;
        $model = new Addstrategy();
        $strModel = new Strategy();
        // file_put_contents('../public/logs//loggg.txt',var_export($this->auth->id,true)."\n\n",FILE_APPEND);
        //卖出的不计入持仓
        $info = $model->where(['user_id'=>$this->auth->id,'status'=>"1",'buytype'=>['<>','5']])->select();
        // 当前是卖出 但是没有返回的 也算占用资金
        $info_new = $model->where(['user_id'=>$this->auth->id,'status'=>"3",'fxsatus'=>"0",'buytype'=>['<>','5']])->select();
        $info = array_merge($info,$info_new);
        // file_put_contents('../public/logs//loggg.txt',var_export($info,true)."\n\n",FILE_APPEND);
        if($info) {
            //$allcodes = "";//格式 sh600000,sh600000
            $arr1 =array_unique(array_column($info,'allcode'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_stock_now_info_pjzuixinjia($allcodes);
            foreach ($info as $k => $v) {
                // $city_value_arr[] = $v['cityValue'];
                $zhanyongzj_arr[] = $v['creditMoney'];
                // file_put_contents('../public/logs//loggg.txt',var_export($v['creditMoney'],true)."\n\n",FILE_APPEND);
                if($v['buytype'] == 3 || $v['buytype'] == 4 || $v['buytype'] == 8){
                    $fdyk_arr[] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number']*$v['multiplying'],2);
                    // $city_value_arr[] =  bcmul($allcodes_arr[$v['allcode']],$v['number']*$v['multiplying'],2);
                    $city_value_arr[] =  bcmul($allcodes_arr[$v['allcode']],$v['number'],2);
                }else{
                    $fdyk_arr[] = bcmul(($allcodes_arr[$v['allcode']]-$v['buyprice']),$v['number'],2);
                    $city_value_arr[] = bcmul($allcodes_arr[$v['allcode']],$v['number'],2);
                }
            }
            $city_value1 = round(array_sum($city_value_arr),2);
            $zhanyongzj = round(array_sum($zhanyongzj_arr),2);
            $fdyk = round(array_sum($fdyk_arr),2);
            //登陆持仓+可用金额+
        }
        // 期货得单独算
        
        $info_qh = $model->where(['user_id'=>$this->auth->id,'status'=>"1",'buytype'=>"5"])->select();
        // 当前是卖出 但是没有返回的 也算占用资金
        $info_new_qh = $model->where(['user_id'=>$this->auth->id,'status'=>"3",'fxsatus'=>"0",'buytype'=>"5"])->select();
        $info_qh = array_merge($info_qh,$info_new_qh);
        if($info_qh) {
            //$allcodes = "";//格式 sh600000,sh600000
            $arr1 =array_unique(array_column($info_qh,'xl_symbol'));
            $allcodes = implode(',',$arr1);
            $obj = new Http();
            $allcodes_arr = $obj->get_quanqiu_data($allcodes);
            foreach ($info_qh as $k => $v) {
                // $city_value_arr[] = $v['cityValue'];
                $zhanyongzj_arr_qh[] = $v['creditMoney'];
                $fdyk_arr_qh[] = bcmul(($allcodes_arr[$v['xl_symbol']][0]-$v['buyprice']),$v['number'],2);
                $city_value_arr_qh[] = bcmul($allcodes_arr[$v['xl_symbol']][0],$v['number'],2);
            }
            $city_value_qh = round(array_sum($city_value_arr_qh),2);
            $zhanyongzj_qh = round(array_sum($zhanyongzj_arr_qh),2);
            $fdyk_qh = round(array_sum($fdyk_arr_qh),2);
            //登陆持仓+可用金额+
        }
        
        
        
        $infoinfo = Db::name('user')->where(['id'=>$this->auth->id])->find();
        //登陆持仓+可用金额+申购冻结
        // $property_money_total = round(floatval($city_value)+floatval($infoinfo['balance'])+floatval($infoinfo['sg_freeze_money']),2);
        // $property_money_total = round(floatval($city_value)+floatval($infoinfo['sg_freeze_money']),2);
        
        $info = $model->where(['user_id'=>$this->auth->id,'status'=>3])->select();
        if($info) {
            foreach ($info as $k => $v) {
                // $city_value_arr1[] = $v['cityValue'];
                $totalyk_arr[] = $v['profitLose'];
            }
            // $city_value2 = round(array_sum($city_value_arr1),2);
            $totalyk = round(array_sum($totalyk_arr),2);
        }
        $sgjiaoyi_model = new Sgjiaoyi();//先扣钱的
        $sgjiaoyi0_model = new Sgjiaoyi0();
        
        //中签的 未转入持仓的
        $info = $sgjiaoyi_model->where(['user_id'=>$this->auth->id,'status'=>['in',[0,1]],'is_cc'=>0])->select();
        if($info) {
            foreach ($info as $k => $v) {
                if($v['is_tz'] == 1){
                    $xingu_total_arr[] = $v['zq_money'];
                }else{
                    $xingu_total_arr[] = $v['dj_money'];
                }
            }
            $xingu_total = round(array_sum($xingu_total_arr),2);
        }
        // $info = $sgjiaoyi0_model->where(['user_id'=>$this->auth->id,'status'=>1,'is_cc'=>0,'renjiao'=>1])->select();
        // 认缴和未认缴都显示
        $info = $sgjiaoyi0_model->where(['user_id'=>$this->auth->id,'status'=>1,'is_cc'=>0])->select();
        if($info) {
            foreach ($info as $k => $v) {
                if($v["renjiao"] == 1){
                    $xingu_total0_arr[] = $v['zq_money'];
                }else{
                    // 未认缴 但是也扣钱了
                    $xingu_total0_arr[] = round(bcsub($v['zq_money'],$v['sy_renjiao'],20),2);
                }
            }
            $xingu_total0 = round(array_sum($xingu_total0_arr),2);
        }
        
        
        $weituozj = round($model->where(['user_id'=>$this->auth->id,'status'=>"2",'buytype'=>['in',[1,7]]])->sum('money'),2);
        // 大宗
        $data=  [
            // 'city_value' => round(bcadd($city_value1,$city_value2,2)+$city_value_qh,2),
            'city_value' => round(bcadd($city_value1,$city_value2,2),2),
            'yesterday_profit' => 0,
            // 'zhanyongzj' => round($zhanyongzj+$zhanyongzj_qh+$infoinfo['freeze_profit'],2),
            'zhanyongzj' => round($zhanyongzj+$zhanyongzj_qh,2),
            'totalyk' => round($totalyk,2),
            'fdyk' => round($fdyk+$fdyk_qh,2),
            'xingu_total' => round(bcadd($xingu_total,$xingu_total0,2),2),
            // 'property_money_total' =>round($zhanyongzj + $infoinfo['freeze_profit'] + $zhanyongzj_qh + $infoinfo['balance'] + bcadd($xingu_total,$xingu_total0,2),2)
            'property_money_total' =>round($zhanyongzj + $zhanyongzj_qh + $weituozj + $infoinfo['balance'] + bcadd($xingu_total,$xingu_total0,2),2),
            'balance' => $infoinfo['balance'],
            'freeze_profit' => $infoinfo['freeze_profit'],
            'is_rq' => $this->auth->is_rq
        ];

        $datas = ['list'=>$data];
        //加密
        $datas = Rsa::jia($datas);
        $this->success('操作成功',$datas);

    }
    public function getHistoryDetail(){
        $id = $this->request->get('id');
        $model = new Addstrategy();
        $info = $model->where(['id'=>$id])->find();

        // $nowGu = getOneGu($info['allcode']);
        $profitLose = $info['profitLose'];//bcmul($info['cityValue'],$nowGu['increase'],2);

        $backMoney = bcadd($info['creditMoney'],$profitLose,2);
        $data = [
            'creditMoney' => $info['creditMoney'],
            'profitLose' => $profitLose,
            'backMoney' => $backMoney,
        ];

        $this->success('返回成功',['list'=>$data]);
    }
    
    public function addzixuan(){
        $allcode = $this->request->post('allcode');
        $flag = $this->request->post('flag');
        $user_id = $this->auth->id;
        // file_put_contents('../public/logs//hhhh.txt',var_export($allcode,true)."\n\n",FILE_APPEND);
        // file_put_contents('../public/logs//hhhh.txt',var_export($user_id,true)."\n\n",FILE_APPEND);
        $allcode_arr = explode(".",$allcode);
        $allcode = $allcode_arr[1].$allcode_arr[0];
        $model = new Zixuan();
        $result = false;
        $result1 = false;
        if($flag){
            $onlineInfo = $model->where(['allcode'=>$allcode,'user_id'=>$user_id])->find();
            if(isset($onlineInfo['id']) && $onlineInfo['id']){
                
            }else{
                $model->allcode = $allcode;
                $model->user_id = $user_id;
                $result = $model->save();
            }
            if($result){
                $this->success('加入成功', []);
            }else{
                $this->success('加入失败', []);
            }
        }
        else{
            $result1 = $model->where(['allcode'=>$allcode,'user_id'=>$user_id])->delete();
            if($result1){
                $this->success('移除成功', []);
            }else{
                $this->success('移除失败', []);
            }
        }
        
    }
    
    
    //增加申购记录
    public function addsgjiaoyi(){
        //判断是否实名
        $identity_card_info = Db::name('identity_card')->where(['user_id'=>$this->auth->id])->find();
        if(!$identity_card_info){
                $this->error("请先前往个人中心,进行实名认证");
            }
        if($identity_card_info['is_audit'] !== "1"){
            $this->error("请先前往个人中心,进行实名认证");
        }
        
        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        
        // if($userInfo['status'] == "forbidden"){
        //     $this->error("当前账户异常,禁止交易");
        // }
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        //申购时间设置
        if(!betweentime(config('site.swshengou')) && !betweentime(config('site.xwshengou'))){
            $this->error('下单失败,不在交易时间段');
        }
        
        //申购时间设置
        $code = $this->request->post('code');
        $sg_nums = $this->request->post('sg_nums');//手数、签数
        $money = $this->request->post('money');
        $dj_money = $this->request->post('dj_money');
        $total_nums = $this->request->post('total_nums');
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
        Db::startTrans();
        $model = new Sgjiaoyi();
        $model->user_id = $this->auth->id;
        $model->code = $code;
        $model->sg_num = $sg_nums;
        $model->sg_nums = round($total_nums,0);
        $model->money = $money;
        $model->dj_money = $dj_money;
        $model->shengouid = $info['id'];
        $model->name = $info['name'];
        $model->sg_fx_price = $info['fx_price'];
        $model->sg_hy_rate = $info['hy_rate'];
        $model->sg_sg_date = $info['sg_date'];
        $model->sg_zq_jk_date = $info['zq_jk_date'];
        $model->sg_ss_date = $info['ss_date'];
        $result = $model->save();
        //冻结账户 冻结金额 并且账户余额扣除支付金额
        $usermodel = new Useruser();
        $userinfo = $usermodel->where(["id"=>$this->auth->id])->find();
        if($userinfo->balance<$money){
            Db::rollback();
            $this->error('账户余额不足');
        }
        $userinfo->id = $this->auth->id;
        $userinfo->money = round($userinfo['money']-$money,2);
        $userinfo->balance = round($userinfo['balance']-$money,2);
        $userinfo->sg_freeze_money = round($userinfo['sg_freeze_money']+$money,2);
        $result1 = $userinfo->save();
        //增加资金明细
        
	    Tool::addLog($this->auth->id,'新股申购冻结金额',0,0,$dj_money,0,0);
        if($result !== false && $result1 !== false){
            Db::commit();
            $this->success('申购成功');
        }else{
            Db::rollback();
            $this->error('申购失败');
        }
    }
    
    
    //增加0申购记录
    public function addsgjiaoyi0(){
        //判断是否实名
        $identity_card_info = Db::name('identity_card')->where(['user_id'=>$this->auth->id])->find();
        if(!$identity_card_info){
                $this->error("请先前往个人中心,进行实名认证");
            }
        if($identity_card_info['is_audit'] !== "1"){
            $this->error("请先前往个人中心,进行实名认证");
        }
        
        $userModel = new \app\admin\model\User();
        $userInfo = $userModel->where(['id'=>$this->auth->id])->find();
        
        // if($userInfo['status'] == "forbidden"){
        //     $this->error("当前账户异常,禁止交易");
        // }
        if($userInfo['jingzhijiaoyi']){
            $this->error("当前账户异常,禁止交易");
        }
        //申购时间设置
        if(!betweentime(config('site.swshengou')) && !betweentime(config('site.xwshengou'))){
            $this->error('下单失败,不在交易时间段');
        }
        
        $code = $this->request->post('code');
        $sg_nums = $this->request->post('sg_nums');
        $total_nums = $this->request->post('total_nums');
        
        $psjy0_ss = config('site.psjy0_ss');
        // //判断最低交易金额
        // $kz_xdmoney = config('site.psjy_jine');
        // if(floatval($kz_xdmoney)>floatval($money)){
        //     $this->error("交易失败，交易金额少于".$kz_xdmoney);
        // }
        
        $modelinfo = new Shengou();
        $info = $modelinfo->where(['code'=>$code])->find();
        if(!$info){
            $this->error('申购参数错误,请重试');
        }
        if(config('site.gqpeizhi')){
            if(intval($psjy0_ss)> intval($sg_nums)){
                $this->error("交易失败，交易签数少于".$psjy0_ss);
            }
            // if(!Tool::is__int($sg_nums)){
            //     $this->error('请输入正确的签数');
            // }
        }else{
            if(intval($psjy0_ss)> intval($sg_nums)){
                $this->error("交易失败，交易手数少于".$psjy0_ss);
            }
            // if(!Tool::is__int($sg_nums)){
            //     $this->error('请输入正确的手数');
            // }
        }
        //判断当前玩家下了多少次 和订单上限对比
        if(intval(round($total_nums,0))>intval($info['sg_limit'])){
            // $this->error("该新股最大申购上限为".$info['sg_limit'].",请重新下单");
            $this->error("下单不能超过申购上限");
        }
        //判断订单数 当前用户的订单数
        $totalcount = Db::name('sgjiaoyi0')->where(['user_id'=>$this->auth->id,"shengouid"=>$info['id']])->count();
        if($totalcount>=intval($info['dd_limit'])){
            $this->error("超过最大订单下单数");
        }
        Db::startTrans();
        $model = new Sgjiaoyi0();
        $model->user_id = $this->auth->id;
        $model->code = $code;
        $model->sg_num = $sg_nums;
        $model->sg_nums = round($total_nums,0);
        $model->money = round(round($total_nums,0)*$info['fx_price'],2);
        // $model->dj_money = $dj_money;
        $model->shengouid = $info['id'];
        $model->name = $info['name'];
        $model->sg_fx_price = $info['fx_price'];
        $model->sg_hy_rate = $info['hy_rate'];
        $model->sg_sg_date = $info['sg_date'];
        $model->sg_zq_jk_date = $info['zq_jk_date'];
        $model->sg_ss_date = $info['ss_date'];
        $result = $model->save();
        
        //增加资金明细
        
	   // Tool::addLog($this->auth->id,'新股申购冻结金额',0,0,$dj_money,0,0);
        if($result !== false){
            Db::commit();
            $this->success('申购成功');
        }else{
            Db::rollback();
            $this->error('申购失败');
        }
    }
    
    // 加载申购
    public function getnewgu(){
        //加载今日申购代码 
        $time_str = date('Y-m-d',time());
        if(!$this->auth->id){
            $this->error('参数错误,请重新登陆');
        }
        $model = new Shengou();
        // $jrsg_list = $model->where(['sg_date'=>$time_str,'sgswitch'=>1])->select();
        // $jrss_list = $model->where(['ss_date'=>$time_str,'sgswitch'=>1])->select();
        // $jrzc_list = $model->where(['zq_jk_date'=>$time_str,'sgswitch'=>1])->select();
        // $jjfb_list = $model->where(['sg_date'=>['>',$time_str],'sgswitch'=>1])->select();
        $jrsg_list = $model->where(['sgswitch'=>1])->select();
        $jrss_list = $model->where(['ss_date'=>$time_str,'sgswitch'=>1])->select();
        $jrzc_list = $model->where(['zq_jk_date'=>$time_str,'sgswitch'=>1])->select();
        $jjfb_list = $model->where(['sg_date'=>['>',$time_str],'sgswitch'=>1])->select();
        $this->success('返回成功',['jrsg_list'=>$jrsg_list,"jrss_list"=>$jrss_list,'jrzc_list'=>$jrzc_list,'jjfb_list'=>$jjfb_list]);
    }
    // 加载线下配售
    public function getnewgu_xx(){
        //加载今日申购代码 
        $time_str = date('Y-m-d',time());
        if(!$this->auth->id){
            $this->error('参数错误,请重新登陆');
        }
        $model = new Shengou();
        // $jrsg_list = $model->where(['sg_date'=>$time_str,'sgswitch'=>1])->select();
        // $jrss_list = $model->where(['ss_date'=>$time_str,'sgswitch'=>1])->select();
        // $jrzc_list = $model->where(['zq_jk_date'=>$time_str,'sgswitch'=>1])->select();
        // $jjfb_list = $model->where(['sg_date'=>['>',$time_str],'sgswitch'=>1])->select();
        $jrsg_list = $model->where(['xxswitch'=>1])->select();
        $jrss_list = $model->where(['ss_date'=>$time_str,'xxswitch'=>1])->select();
        $jrzc_list = $model->where(['zq_jk_date'=>$time_str,'xxswitch'=>1])->select();
        $jjfb_list = $model->where(['sg_date'=>['>',$time_str],'xxswitch'=>1])->select();
        $this->success('返回成功',['jrsg_list'=>$jrsg_list,"jrss_list"=>$jrss_list,'jrzc_list'=>$jrzc_list,'jjfb_list'=>$jjfb_list]);
    }
    //加载申购记录
    public function getsgnewgu(){
        $model = new Sgjiaoyi();
        if(!$this->auth->id){
            $this->error('参数错误,请重新登陆');
        }
        $dxlog_list = $model->where(['user_id'=>$this->auth->id])->where(['is_cc'=>['neq',1]])->order('createtime desc')->select();
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
        $this->success('返回成功',['dxlog_list'=>$dxlog_list]);
    }
    
    //加载0申购记录
    public function getsgnewgu0(){
        $model = new Sgjiaoyi0();
        if(!$this->auth->id){
            $this->error('参数错误,请重新登陆');
        }
        $dxlog_list = $model->where(['user_id'=>$this->auth->id])->where(['is_cc'=>['neq',1]])->order('createtime desc')->select();
        foreach ($dxlog_list as $k => $v) {
            $dxlog_list[$k]['codejson'] = array(
                    "text"=>$dxlog_list[$k]['name'],
                    "color"=>"#ed3f14",
                    "size"=>28
                );
            $color = "#ed3f14";
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
        $this->success('返回成功',['dxlog_list'=>$dxlog_list]);
    }
    
    public function getsgHistoryDetail(){
        $id = $this->request->get('id');
        $model = new Sgjiaoyi();
        $modelinfo = $model->where(['id'=>$id])->find();
        if($modelinfo['status']=="0"){
                $modelinfo['status_txt'] = "申购中";
            }else if($v['status']=="1"){
                $modelinfo['status_txt'] = "中签".$modelinfo['zq_num']."股";
            }else if($v['status']=="2"){
                $modelinfo['status_txt'] = "未中签";
            }else if($v['status']=="3"){
                $modelinfo['status_txt'] = "已弃购";
            }
        $this->success('返回成功',['list'=>$modelinfo]);
    }
    
    //认缴 是需要扣除余额的 等上市就转持仓
    public function renjiao_act(){
        $id = $this->request->post('id');
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
        if(floatval($userinfo['balance'])<$row['zq_money']){
            Db::rollback();
            $this->error('本金不足,充值成功后,再申请认缴');
        }
        
        Tool::addLog($row['user_id'],"新股认缴",$userinfo['balance'],round($userinfo['balance'] - $row['zq_money'],2),$row['zq_money'],1);
        $userinfo->balance =round($userinfo['balance'] - $row['zq_money'],2);
        $userinfo->sg_freeze_money = round($userinfo['sg_freeze_money'] +$row['zq_money'],2);
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
    //获取交易手续费和印花税
    public function getjiaoyifee(){
        $this->success('请求成功', ['maic_fee'=>config('site.maic_fee'),'yh_fee'=>config('site.yh_fee')]);
    }
    
    //搜索股票
    public function searchstrategy(){
        //解密
        $query = $this->request->param('query');
        $paramInfo = Rsa::jie($query);
        $key = Rsa::check($paramInfo['key']);
        $page = Rsa::check($paramInfo['page']);

        //$key = $this->request->post('key');
        //$page = $this->request->post('page')?$this->request->post('page'):1;
        $where['vipqcstatus'] = 0;
        $where['type1'] = 0;
        // $where['code|title|allcode'] = ['like','%'.$key.'%'];
        $where['code|title'] = ['like','%'.$key.'%'];
        // $where['code'] = ['like','%'.$key.'%'];
        $stratemodel = new Strategy();
        // $list = $stratemodel->where($map)->where($map1)->where($map2)->select();
        $list = $stratemodel
        ->where($where)
        ->order('id desc')->page($page,20)->select();
        foreach ($list as $k => $v){
            $list[$k]['symbol'] = substr($v['allcode'],2,6).".".substr($v['allcode'],0,2);
            $list[$k]['name'] = $v['title'];
            $list[$k]['letter'] = Pinyin::get($v['title']);
        }
        if($list){
            //加密
            $data = Rsa::jia(['list'=>$list]);
            $this->success('请求成功', $data);
        }else{
            $this->error("未找到当前票");
        }
    }
    
    public function getgameapi(){
        $info = Db::name('gameapi')->where(['status'=>'1'])->find();
        if(!$info){
            $this->error('API接口加载失败,请联系管理员');
        }
        $this->success('请求成功', ['title'=>$info['title'],'gameapi'=>$info['apiurl']]);
    }
    
    //获取实时当前用户信息
    public function getcurrentuserinfo(){
        $id = $this->auth->id;
        $model_user = new Useruser();
        $info = $model_user->get($id);
        $this->success('请求成功', ['info'=>$info]);
    }
    
    
    
    public function getnewgu0(){
        //判断当前用户是否0申购中签成功 并且只查一条信息
        $model = new Sgjiaoyi0();
        $sgmodel = new Shengou();
        $list = $model->where(['user_id'=>$this->auth->id,'status'=>1,'renjiao'=>0])->select();
        if($list){
            $sgmodel_info = $sgmodel->where(['code'=>$list[0]['code']])->find();
            $list[0]['gqpeizhi'] = config('site.gqpeizhi');
            $list[0]['content'] = $sgmodel_info['content'];
            $this->success('请求成功', ['info'=>$list[0]]);
        }else{
            $this->error('请求失败');
        }
    }
    
    // 加载
    
    
    
}
