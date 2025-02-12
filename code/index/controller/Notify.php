<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use fast\Tool;
use think\Lang;
use think\Db;
use think\Response;

/**
 * Ajax异步请求接口
 * @internal
 */
class Notify 
{
    public function baopay(){
        $paramArray = input();
        if(!isset($paramArray["sign"]) ){
            // echo($domain = request()->host());
            echo "fail(sign not exists)";
            exit;
        }
        
        $resSign = $paramArray["sign"] ;
        

        //商户ID
        $mchId = 'M1728545689';
        if($mchId != $paramArray['mchNo']){
            echo "merch Error";
            exit;
        }
        //商户ID
        $appId = '6707839964dc127250ee4791';
        if($appId != $paramArray['appId']){
            echo "appId Error";
            exit;
        }
        
        if($paramArray['state'] != 2){
            echo 'SUCCESS';
            exit;
        }
        //商户秘钥
        $mchKey = 'GOEbp3cTT9cjGhOeGCf92oc5wZ8oW8IPT9xOgs84cgY6FM6EjcK7EjCy61MvtjFMHwtwcn9Osg4d6Dsc4GacdJtDLeyXGA9jAc8GvE0RcBgbRAPIWwgfbM7rZTxrqGet';
        // $sign = $paramArray['sign'];
        unset($paramArray['sign']);
        
        $sign_serv = $this->bao_sign($paramArray,$mchKey);  //签名
        
        if($resSign != $sign_serv){  //验签失败
            echo "fail(verify fail)";
            exit;
        }
        
        Db::name('notify_test') ->insert(['con_data'=>json_encode($paramArray)]);
        //处理业务
        $status = $paramArray['state'] == 2 ? 1 : 0;
        $res = $this->handleOrder($paramArray['payOrderId'],$paramArray["payAmt"],$status);
        if ($res) {
            echo "SUCCESS";
        } else {
            echo "fail";
        }
        
        
        exit;
    }
    
    public function hengtongtopay(){
        $paramArray = input();
        if(!isset($paramArray["sign"]) ){
            // echo($domain = request()->host());
            echo "fail(sign not exists)";
            exit;
        }
        
        $resSign = $paramArray["sign"] ;
        

        //商户ID
        $mchId = 'M1729141689';
        if($mchId != $paramArray['mchNo']){
            echo "merch Error";
            exit;
        }
        //商户ID
        $appId = '67109bbae4b01d83cb9f6509';
        if($appId != $paramArray['appId']){
            echo "appId Error";
            exit;
        }
        
        if($paramArray['state'] != 2){
            echo 'SUCCESS';
            exit;
        }
        //商户秘钥
        $mchKey = '4svvjibjjmzb3pep7sqlqwydyjiclfbkjwnxzp0pwur3aq7t9u2yit4zb4p6ywxz6hkcplrtd5urrr05dgqp090rk0mshomo7lwmrgowkugfv5gu4myzjtahw5ihqz9l';
        // $sign = $paramArray['sign'];
        unset($paramArray['sign']);
        
        $sign_serv = $this->bao_sign($paramArray,$mchKey);  //签名
        
        if($resSign != $sign_serv){  //验签失败
            echo "fail(verify fail)";
            exit;
        }
        
        $paramArray['sign'] = $sign_serv;
        Db::name('notify_test') ->insert(['con_data'=>json_encode($paramArray)]);
        //处理业务
        $status = $paramArray['state'] == 2 ? 1 : 0;
        $res = $this->handleOrder($paramArray['mchOrderNo'],$paramArray["amount"]/100,$status);
        if ($res) {
            echo "SUCCESS";
        } else {
            echo "fail";
        }
        
        
        exit;
    }
    
    private function paramArraySign($paramArray, $mchKey){
		
		ksort($paramArray);  //字典排序
		reset($paramArray);
	
		$md5str = "";
		foreach ($paramArray as $key => $val) {
			if( strlen($key)  && strlen($val) ){
				$md5str = $md5str . $key . "=" . $val . "&";
			}
		}
		$sign = strtoupper(md5($md5str . "key=" . $mchKey));  //签名
		
		return $sign;
		
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
	
	/**
	 * 处理充值到账逻辑
	 * @param $order_sn 订单流水号
	 * @param $amount 充值金额，单位分
	 * @param $status 充值状态
	 */
	private function handleOrder($order_sn,$amount,$status){
	    $info = Db::name('recharge')->where('order_sn', $order_sn)->find();
        if($info['is_pay']!=0){
            return true;
        }
        Db::startTrans();
        try {
            if($status == 1){
                $up_data = [
                    'is_pay' => 1,
                    'is_remit' => 1,
                    'paytime' => time(),
                    'updatetime' => time()
                ];
            }else{
                $up_data = [
                    'is_pay' => 2,
                    'is_remit' => 2,
                    'reject' => '已退款',
                    'updatetime' => time()
                ];
            }
            Db::name('recharge')->where('id', $info['id'])->update($up_data);

            if($up_data['is_pay'] == 1){ //审核通过
                $amount = round($amount,2);
                $beforeMoney = Tool::getUserBalance($info['user_id']);
                $userInfo = Db::name('user')->where('id',$info['user_id'])->find();
                $datainfo = [
                       "balance"=> round($amount+$userInfo['balance'],2)
                ];
                // Db::name('user')->where('id',$info['user_id'])->setInc('balance',$info['money']);
                Db::name('user')->where('id',$info['user_id'])->update($datainfo);
                Tool::addLog($info['user_id'],"充值",$beforeMoney,Tool::getUserBalance($info['user_id']),$amount,0);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return false;
        }
        return true;
	}
}