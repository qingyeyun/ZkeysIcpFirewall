<?php
/**
 * Created by PhpStorm.
 * User: xiaoye
 * Email: 415907483@qq.com
 * Date: 2021/9/23
 * Time: 15:23
 */

namespace Plugin\IcpFirewall\Common;


use libs\Curl;

class SuQIan
{

    /**
     * 通道名称
     * @var string
     */
    public $name='宿迁机房通道';

    /**
     * 通道配置
     */
    public $configAdmin= [
        'requestname' =>[
            'name' =>'用户名',//后台展示的名称
            'show' =>true, //是否在后台页面展示
        ],
        'code'=>[
            'name'=>'请求秘钥',
            'show' =>true,
        ],
        'IcpAppID'=>[
            'name'=>'IcpAppID',
            'show' =>true,
        ],
        'IcpKey'=>[
            'name'=>'IcpKey',
            'show' =>true,
            'row-title'=>'注：使用该功能,可以防止未备案域名提交至接口,留空不开启,免费接口： https://www.icpapi.com/user.html'
        ],
    ];

    public $error;


    /**
     * 过白接口
     * @param $userId
     * @param $ConfigAdmin
     * @param $web_domain
     * @param $ip
     * @return int 0接口异常，下次任务继续执行 1成功 2失败
     */
    public function Bind($userId,$ConfigAdmin,$web_domain,$ip)
    {
        $requestname = $ConfigAdmin['requestname'];//用户名
        $code = $ConfigAdmin['code']; //请求秘钥.

        if (!$requestname || !$code){
            return 0;
        }
        //客户要求对接这个 备案查询
        if ($ConfigAdmin['IcpAppID'] && $ConfigAdmin['IcpKey']){
            $url = 'http://www.icpapi.com/api/v1';
            $params = $web_domain;//查询信息
            $appId = $ConfigAdmin['IcpAppID'] ;
            $key =  $ConfigAdmin['IcpKey'];
            $data = [
                'appid'=>$appId,
                'params'=>$params,
                'timestamp'=>time(),//时间戳 （时间超时为5分钟，注：务必保证服务器和请求端的时间一致）
            ];
            $data['sign']=$this->sign($data,$key);
            $res =  Curl::post($url,$data);
            $json = json_decode($res,true);
            if ($json['code'] ==1){
                if ($json['status']!=1){
                    $this->error='自动审核，未备案。';
                    return 2;
                }
            }
        }

        $encrypt = md5($requestname.$code); //加密数据，加密形式：md5($requestname.$code)
        $url =  "http://user.pgyidc.com/apihr/wl?requestname=$requestname&domain=$web_domain&ip=$ip&code=$code&encrypt=$encrypt";
        $res = Curl::get($url);

        $json['status'] = 4;//status：0成功 1失败 2非法访问 4超过当日最大提交次数（50次）
        $json = json_decode($res,true);
        if($json['status'] == 0){
            return 1;
        }elseif ($json['status']==4 || $json['status']==2){
            return 0;
        }

        $this->error='接口异常！';
        return 2;
    }

    /**
     *
     * @param $userId
     * @param $ConfigAdmin
     * @param $web_domain
     * @param $ip
     * @return bool
     */
    public function relieve($userId,$ConfigAdmin,$web_domain,$ip)
    {
//        没有删除接口 直接返回 true
//        return false;
        return true;
    }


    /**
     * sign算法
     * @param $params
     * @param $secret
     * @return string
     */
    private function sign($params, $secret)
    {
        $sign = $signstr = "";
        if (!empty($params)) {
            ksort($params);
            reset($params);
            foreach ($params AS $key => $val) {
                if ($key == 'sign') continue;
                if ($signstr != '') {
                    $signstr.= "&";
                }
                $signstr.= "$key=$val";
            }
            $sign = md5($signstr . $secret);
        }
        return $sign;
    }






}