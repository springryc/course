<?php

namespace app\index\controller;

use app\index\controller\Base;

class Center extends Base
{
    /**
     * qq登陆
     * @return [type] [description]
     */
    public function qqLogin()
    {
        $config = model('SystemConfig')->config();
        $appid   = $config['qq_appid'];     //申请的账号 
        $callback='http://'. $_SERVER['SERVER_NAME'].'/index/center/qqCallback'; //回调 
        $scope   ='get_user_info,add_share,list_album,add_album,upload_pic,add_topic,add_one_blog,add_weibo,check_page_fans,add_t,add_pic_t,del_t,get_repost_list,get_info,get_other_info,get_fanslist,get_idolist,add_idol,del_idol,get_tenpay_addr';
        $url     ="https://graph.qq.com/oauth2.0/authorize";//授权接口
        $state   = md5(uniqid(rand(), TRUE)); //随机数
        $loginurl=$url.'?response_type=code&client_id='.$appid.'&redirect_uri='.$callback.'&state='.$state.'&scope='.$scope; 
          //跳转
        $this->redirect($loginurl);
    }

    //qq回调
    public function qqCallBack(){

        $config = model('SystemConfig')->config();
        //2 准备接口需要的数据
        $grant_type="authorization_code";
        $app_id = $config['qq_appid'];
        $client_secret = $config['qq_secret'];
        $code=$_GET['code'];   //code : 上一步返回的authorization code。
        $callback='http://'. $_SERVER['SERVER_NAME'].'/index/center/qqCallback';
        $redirect_uri=urlencode($callback);
        //3 拼接url
        $url="https://graph.qq.com/oauth2.0/token?grant_type=".$grant_type.
        "&client_id=".$app_id."&client_secret=".$client_secret."&code=".$code."&redirect_uri=".$redirect_uri; 
        //4 打印出access_token
        $str=$this->get_contents($url); 
        $arr=explode('&',$str);
        $brr=explode('=',$arr[0]);  
        header('Location:http://'. $_SERVER['SERVER_NAME'].'/index/center/shouquan/access_token/'.$brr[1]);  //Access_Token，授权令牌
    }

    /**
     * qq授权获取用户信息
     * @param  [type] $access_token [description]
     * @return [type]               [description]
     */
    public function shouquan($access_token){
        
        $url  ="https://graph.qq.com/oauth2.0/me?access_token=".$access_token;
        $content=$this->get_contents($url);
        $lpos = strpos($content, "(");
        $rpos = strrpos($content, ")");
        $response = substr($content, $lpos + 1, $rpos - $lpos -1);
        $data=json_decode($response);
        $client_id=$data->client_id;  //申请QQ登录成功后，分配给网站的appid。
        $openid=$data->openid;  //openid是此网站上唯一对应用户身份的标识
        $content=$this->get_contents($url);
        $user="https://graph.qq.com/user/get_user_info?access_token=".$access_token."&openid=".$openid."&oauth_consumer_key=".$client_id."&format=json";
        $info = json_decode($this->get_contents($user),true);
        if(!empty($info['gender'])){
            if($info['gender'] == '男'){
                $sex = 1;
            }else if($info['gender'] == '女'){
                $sex = 2;
            }else{
                $sex = 0;
            }
        }
        $data = [
            'qq_account'=>$openid,
            'username'=>$info['nickname'],
            'sex'=>$sex,
            'province'=>$info['province'],
            'city'=>$info['city'],
            'other_image'=>$info['figureurl_qq_2'],
            'login_ip'=>request()->ip(),
            'login_time'=>time()
        ];

        $find_user = model('user')->where('qq_account',$openid)->find();
        //如果找到说明不是第一次登录
        if($find_user){
            //判断用户如果是vip，那么根据过期事件判断是否过期
            if($find_user['vip'] == 1 && $find_user['expiration'] < time()){
                //如果过期，将vip=0  普通用户
                $data['vip'] = 0;
            }
            $data['login_num'] = $find_user['login_num'] + 1;
            $result = model('user')->where('qq_account',$openid)->update($data);

            //将登陆者的ip，登陆时间，存入到login_info表中***********************************************
            // $a = array('user_id'=>$find_user['id'], 'login_ip'=>$find_user['login_ip'], 'login_time'=>time());
            // $r = model('login_info')->allowField(true)->save($a);
            //因为此用户之前已经登陆过，所以不再检测是否是被邀请，不会再触发奖励积分机制
            //****************************************************************************************
            
            $arr = [
                'userId'=>$find_user['id'],
                'account'=>$find_user['account'],
                'time'=>time()
            ];
            session('userInfo',$arr);
            echo "<script>window.close();</script>";
            return  json_encode(array('state'=>'200','hint'=>'登入成功'));
        //如果没找到说明是第一次登录
        }else{
            $maxId = model('user')->max('id');
            $account = rand(0,9).$maxId;
            $data['account'] = sprintf("%010d", $account);
            $data['member_time']=time();
            $data['addtime'] = time();

            $data['points'] = 1000;                     //第一次登陆者送积分1000

            //因为此用户是第一次登陆，先判断Cookie里面有没有存入邀请者的id-->inviter_id*******************
            // if(\Cookie::has('inviter_id')){
            //     $inviter_id = decode(\Cookie::get('inviter_id'));
            //     if(model('user')->find($inviter_id)){
            //         $data['inviter_id'] = $inviter_id;
            //     }
            // }
            //****************************************************************************************
            $model = model('user');
            $result = $model->allowField(true)->save($data);
        }
        if($result){
            //将第一次登陆者的ip，登陆时间，存入到login_info表中*****************************************
            // $a = array('user_id'=>$model['id'], 'login_ip'=>$model['login_ip'], 'login_time'=>time());
            // $r = model('login_info')->allowField(true)->save($a);
            // //把第一次登录送的1000积分存入到积分订单表中
            // $datas = ['type'=>'7', 'user_id'=>$model['id'], 'price'=>1000, 'addtime'=>time()];
            // model('points_order')->save($datas);
            // //如果有邀请者，用户登陆成功了需要给邀请者奖励积分100
            // if(isset($data['inviter_id'])){
            //     $owner = model('user')->where('id', $inviter_id)->setInc('points', 100);
            //     $results = array('type'=>0, 'oid'=>0, 'user_id'=>0, 'price'=>100, 'owner_id'=>$inviter_id, 'addtime'=>time());
            //     $po_or = model('points_order')->allowField(true)->save($results);
            // }
            //****************************************************************************************
            
            $arr = [
                'userId'=>$model['id'],
                'account'=>$find_user['account'],
                'time'=>time()
            ];
            session('userInfo',$arr);
            echo "<script>window.close();</script>";
            return  json_encode(array('state'=>'200','hint'=>'登入成功'));
        }else{
            return  json_encode(array('state'=>'201','hint'=>'服务器繁忙,请稍后重试!'));
        }
    }

    /**
     * 微信登陆
     */
    public function wxLogin()
    {
        $config = model('SystemConfig')->config();
        //生成state参数，利用MD5对时间戳进行加密生成32位数的state，也是为了简单哈哈，自己用其他方法生成吧
        //时间戳
        $time = time();
        //对时间戳进行加密，就是state
        $state = MD5($time);
        //APPID
        $appid = $config['wx_appid'];
        //redirect_uri回调地址，也就是请求完了之后，咱们需要跳转的地址，我这里直接跳转到我网站的oauth.php的地址，这个文件是 用来下一步获取access_token的
        $redirect_uri = "http://". $_SERVER['SERVER_NAME']."/index/Center/wxinfo";
        //scope默认是snsapi_login
        $scope = "snsapi_login";
        //拼接参数进行自动跳转
        $wxurl = "https://open.weixin.qq.com/connect/qrconnect?appid=$appid&redirect_uri=$redirect_uri&response_type=code&scope=$scope&state=$state#wechat_redirect";
        header("Location:$wxurl");
    }

    /**
     * 获取微信用户信息
     * @return [type] [description]
     */
    public function wxinfo(){
        if($_GET['code']){
            $config = model('SystemConfig')->config();
            $AppID = $config['wx_appid'];
            $AppSecret = $config['wx_secret'];
            $url='https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$AppID.'&secret='.$AppSecret.'&code='.$_GET['code'].'&grant_type=authorization_code';
            $json = $this->curl($url);
            $arr = json_decode($json,true);
            if(isset($arr['errcode'])){
                header("Location:".'http://'.$_SERVER["SERVER_NAME"]);
                exit();
            }
            $url='https://api.weixin.qq.com/sns/userinfo?access_token='.$arr['access_token'].'&openid='.$arr['openid'].'&lang=zh_CN';
            $json = $this->curl($url);
            $userinfo=json_decode($json,true);
            //得到 用户资料
            $data = [
                "wx_account"=>$userinfo['openid'],
                "username"=>$userinfo['nickname'],
                "sex"=>$userinfo['sex'],
                "province"=>$userinfo['province'],
                "city"=>$userinfo['city'],
                "country"=>$userinfo['country'],
                "other_image"=> $userinfo['headimgurl'],
                'login_ip'=>request()->ip(),
                'login_time'=>time()
            ]; 
            $find_user = model('user')->where('wx_account',$userinfo['openid'])->find();
            if($find_user){

                //将登陆者的ip，登陆时间，存入到login_info表中***********************************************
                // $a = array('user_id'=>$find_user['id'], 'login_ip'=>$find_user['login_ip'], 'login_time'=>time());
                // $r = model('login_info')->allowField(true)->save($a);
                //因为此用户之前已经登陆过，所以不再检测是否是被邀请，不会再触发奖励积分机制
                //****************************************************************************************
                //判断如果是会员，是否过期
                if($find_user['vip'] == 0 && $find_user['expiration'] < time()){
                    $data['vip'] = 0;
                }
                $data['login_num'] = $find_user['login_num'] + 1;
                $result = model('user')->where('wx_account',$userinfo['openid'])->update($data);
                $arr = [
                    'userId'=>$find_user['id'],
                    'account'=>$find_user['account'],
                    'time'=>time()
                ];
                session('userInfo',$arr);
                echo "<script>window.close();</script>";
                return  json_encode(array('state'=>'200','hint'=>'登入成功'));
            }else{

                //因为此用户是第一次登陆，先判断Cookie里面有没有存入邀请者的id-->inviter_id*******************
                // if(\Cookie::has('inviter_id')){
                //     $inviter_id = decode(\Cookie::get('inviter_id'));
                //     if(model('user')->find($inviter_id)){
                //         $data['inviter_id'] = $inviter_id;
                //     }
                // }
                //****************************************************************************************
                
                $maxId = model('user')->max('id');
                $account = rand(0,9).$maxId;
                $data['account'] = sprintf("%010d", $account);
                $data['member_time']=time();
                $data['addtime'] = time();
                $data['points'] = 1000; 
                $model = model('user');
                $result = $model->allowField(true)->save($data);
                if($result){
                    //将第一次登陆者的ip，登陆时间，存入到login_info表中*****************************************
                    // $a = array('user_id'=>$model['id'], 'login_ip'=>$model['login_ip'], 'login_time'=>time());
                    // $r = model('login_info')->allowField(true)->save($a);
                    // //把第一次登录送的1000积分存入到积分订单表中
                    // $datas = ['type'=>'7', 'user_id'=>$model['id'], 'price'=>1000, 'addtime'=>time()];
                    // model('points_order')->save($datas);
                    // //如果有邀请者，用户登陆成功了需要给邀请者奖励积分100
                    // if(isset($data['inviter_id'])){
                    //     $owner = model('user')->where('id', $inviter_id)->setInc('points', 100);
                    //     $result = array('type'=>0, 'oid'=>0, 'user_id'=>0, 'price'=>100, 'owner_id'=>$inviter_id, 'addtime'=>time());
                    //     $po_or = model('points_order')->allowField(true)->save($result);
                    // }
                    //****************************************************************************************
                    $arr = [
                        'userId'=>$model['id'],
                        'account'=>$find_user['account'],
                        'time'=>time()
                    ];
                    session('userInfo',$arr);
                    echo "<script>window.close();</script>";
                    return  json_encode(array('state'=>'200','hint'=>'登入成功'));
                }else{
                    return  json_encode(array('state'=>'201','hint'=>'服务器繁忙,请稍后重试!'));
                }
            }
        }
    }

     //检测登录状态
    public function checkLogin(){
        if(request()->post()){
            $data = request()->post();
            if($data['yr'] == md5(md5('162162'))){
                $userInfo = session('userInfo');
                //header('Content-Type:application/json; charset=utf-8');//这个类型声明非常关键
                //header("Content-Type:text/html;charset=utf-8");
                if(!empty($userInfo)){
                    if(time() < $userInfo['time'] + 3600*2){
                        $userData = model('user')->field('id,username,image,other_image,account,sex,vip')->where('id',$userInfo['userId'])->find();
                        if($userData){
                            return json_encode(array('state'=>'200','hint'=>'请求成功','data'=>$userData));
                        }else{
                            return json_encode(array('state'=>'201','hint'=>'用户不存在'));
                        }
                        
                    }else{
                        return json_encode(array('state'=>'202','hint'=>'请重新请求'));
                    }
                }else{
                    return json_encode(array('state'=>'203','hint'=>'请重新请求'));
                }
            }else{
                return json_encode(array('state'=>'404','hint'=>'请重新请求','data'=>md5(162162)));
            }
        }
    }

    //退出登录
    public function outLogin(){
        //删除登陆的session用户值
        session('userInfo',null);

        return $this->redirect('/index/index/index');
    }

    /**
     * 微信支付
     * @return [type] [description]
     */
    public function wxpay(){
        if(request()->post()){

            //接收数据
            $data = request()->post();
            // return $data['order_money'];
            $userInfo = model('User')->reuqestIsLogin();//判断登录
            if(!isset($userInfo['userId'])){
                return $userInfo;
            }
            $data['user_id'] = $userInfo['userId'];
            // $data['order_money'] = 0.01;//订单金额 post传值中有order_money的值
            // $data['type'];//post传值中有type类型值，如果是用户购买的积分值=jifen，如果是用户购买的vip值=vip
            $data['order_no'] = date("YmdHis") . rand(10000, 99999);//订单号 
            $data['pay_type'] = 'wechat_code'; 
            $data['addtime'] = time(); 
            model("order")->allowField(true)->save($data);
            $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
            $data['url_return'] = $http_type. $_SERVER['SERVER_NAME'].'/index/center';//返回地址 
            $data['title'] = "标题" . $data['order_no']; 
            $data['body'] = "主体内容" . $data['order_no']; 
            $data['url_notify'] = $http_type. $_SERVER['SERVER_NAME'].'/index/center/wxPayCallback'; 
            $url = $this->wechat_jump($data); 
            if($url){
                return json_encode(array('state'=>'200','hint'=>'请求成功!','data'=>['url'=>$url,'order_no'=>$data['order_no']]));
            }else{
                return json_encode(array('state'=>'204','hint'=>'请求失败!'));
            }
        }
       
    }
    //阿里云支付
    public function alipay(){
        $data = request()->get();
        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';

        $userInfo = model('User')->reuqestIsLogin();//判断登录
        if(!isset($userInfo['userId'])){
           return $this->error('请先登入');
        }
        $data['user_id'] = $userInfo['userId'];
        require_once("./alipay/pagepay/service/AlipayTradeService.php");
        require_once("./alipay/pagepay/buildermodel/AlipayTradePagePayContentBuilder.php");
        //商户订单号，商户网站订单系统中唯一订单号，必填

        $config = array (   
            //应用ID,您的APPID。
            'app_id' => "2018100661631301",

            //商户私钥
            'merchant_private_key' => "MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC1j3oBESHrCVHA02Ifdxp0hpZ8Oj74JBczxK7USrwWl1rVtlZkneOu8+0gCN1FMAfTi4UZpMI6dvqLw8VnxYDlprr5Ho8kJTKJb9RYj4ypHlEXblVdzqkf2RXQamoAMwgTd6UvE08Mv+iuiOYiKKLdf49ByAnD4eCi4vpo8jcLIOBdtOz27fWNUqFk/IvDpfdBBfsksJ/6HAvxVktpWKvKcQquBk8asUjxBcqi02aNp21Zy+TRf7hU1UXwouwJgCQlEQYu2XGsb3Q6Pn/bStVSHvsYQcWjVAeZuv1PNPeNfAEf3SxOumo6AKdISI20wtLoz4yYfcRDua27BDKieGwTAgMBAAECggEANBll0ySNbRqRgRmnUIwm5UUxrZgxNZd1qP9Jg9WmP31TLXxTMjA0g6Gva7/fbtknhcbFfORQb+JwZubYoLyGDmBXyuDABok+BT432unmXSk779NTX8XLtj3fCp1eqYv7R0rP9cA+sNPo+xyBnU+33IOcIi46zyDkCxnC7ZaDwQdENCT0xkE7fTrRR+5L+3gVYnzMthAedIF03nbv3K86TdjTZ3U9MutFMZFVNT7XZgOANn2+AeOKXDwgllJgQujLdy6GKvvq+0GJsA9x9yJsd3qeUUer9+hnJSq6Kxk5C+DvAPUcdTVfwT5NhkbbuM4tNE+nBgyaH/V+8Q9SFvkm8QKBgQDjN21+8Dr0e3Me0okXUnHFuM3Z81J0m/eW1vP1jV+hChCbwzUjlSnbeSKLtEwbZxPW0AGJ9DTUThww5xMdhS5ceExVo1lth2E9KZpKGSrik2l4EEMoaQtSPkFPm6fm8yIUxTZmUlI3u1wCJ5thg+O6xfG/TGus+VeEIqs0nN6izQKBgQDMj29Na8J4vwWsTGDxWvW6FjJsWa1jXJ52dyS/R+8JbhYXTV01UhET1L8Giki4jsht6wo+/Z19MvwZjPclbg/cv2P+eHfnTA/4zlwJBOc+RPg+c7K/Uc06JCe2R5xdEF+Z8ccgBA9EINncMlcq8XARtFHfZ/RbyCt0WS5la3YKXwKBgQCVjILOHNHA3ovryoieyA3IJJWgkS1BQPKZ3krd4LoDZXt++eG81M2i/bzGFNpO4u5E4c1RfmFTJ0IY0c5cDK9x/1/GsegHViajOgGqKZx3Wqz9cD6zl0fzTrRv8DR3pqlU4GSoviANPI0XgfgcG9HEucoere7k/4whlSv4ShOjNQKBgA30jxxSCK9iIMnzX/23PeJXF1OK/qgzrl/YmvottyIGj51BWuWDVsTqk6mnj3R/0S6mhUls1eyvqME5e7bi/lQJ/pFiuJNf/gr0URUQb5Iw9FqWXBBvTTf1NXxbAFDdCBihhPsrK7tzHknGaWn1lLawfZFnLyV/z28dkmu63A+zAoGAEW6Vepfht5qeY1hcUBdrhsZhq/HEnSgjxSLAz1AH7xjLCmlrZHFg1TgTzYae1U85qk9GQpt6cKvyX0KYSde3TOI7gu2pcaRkqtzI+O/k0N5/zv3bSfeWuXxyjcw+3jazF3UTqQILcPoE+Hkfz5MzSGPpmYlDCELM+UlifK1uEc8=",
            
            //异步通知地址
            'notify_url' => $http_type. $_SERVER['SERVER_NAME'].'/index/center/aliPayCallback',
            
            //同步跳转
            'return_url' => $http_type. $_SERVER['SERVER_NAME']."/index/center",

            //编码格式
            'charset' => "UTF-8",

            //签名方式
            'sign_type'=>"RSA2",

            //支付宝网关
            'gatewayUrl' => "https://openapi.alipay.com/gateway.do",

            //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
            'alipay_public_key' => "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtY96AREh6wlRwNNiH3cadIaWfDo++CQXM8Su1Eq8Fpda1bZWZJ3jrvPtIAjdRTAH04uFGaTCOnb6i8PFZ8WA5aa6+R6PJCUyiW/UWI+MqR5RF25VXc6pH9kV0GpqADMIE3elLxNPDL/orojmIiii3X+PQcgJw+HgouL6aPI3CyDgXbTs9u31jVKhZPyLw6X3QQX7JLCf+hwL8VZLaVirynEKrgZPGrFI8QXKotNmjadtWcvk0X+4VNVF8KLsCYAkJREGLtlxrG90Oj5/20rVUh77GEHFo1QHmbr9TzT3jXwBH90sTrpqOgCnSEiNtMLS6M+MmH3EQ7mtuwQyonhsEwIDAQAB",
        );
        $out_trade_no = trim(date("YmdHis") . rand(10000, 99999));
        //订单名称，必填
        $subject = trim(date("YmdHis") . rand(10000, 99999));//订单号
        //付款金额，必填
        $total_amount = trim($data['order_money']);
        //商品描述，可空
        if($data['type'] == 'vip'){
            $body = trim('成为会员');
        }else if($data['type'] == 'jifen'){
            $body = trim('充值积分');
        }
        //构造参数
        $payRequestBuilder = new \AlipayTradePagePayContentBuilder();
        $payRequestBuilder->setBody($body);
        $payRequestBuilder->setSubject($subject);
        $payRequestBuilder->setTotalAmount($total_amount);
        $payRequestBuilder->setOutTradeNo($out_trade_no);
        $aop = new \AlipayTradeService($config);
        /**
         * pagePay 电脑网站支付请求
         * @param $builder 业务参数，使用buildmodel中的对象生成。
         * @param $return_url 同步跳转地址，公网可以访问
         * @param $notify_url 异步通知地址，公网可以访问
         * @return $response 支付宝返回的信息
        */
        $data['order_money'] = $total_amount;
        $data['order_no'] = $subject;
        $data['pay_type'] = 'alipay';
        $data['addtime'] = time();
        model("order")->allowField(true)->save($data);
        $response = $aop->pagePay($payRequestBuilder,$config['return_url'],$config['notify_url']);
        //输出表单
        var_dump($response);
    }

    public function wxPayCallback(){
        //获取微信端传来的数据
        $xmlData = file_get_contents("php://input");
        //dump($xmlData);
        //file_put_contents('wechat/pay.txt',$xmlData);
        //过滤
        libxml_disable_entity_loader(true);
        //把xwl的数据转化成php数组数据
        $data = json_decode(json_encode(simplexml_load_string($xmlData, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        //判断是否存在商品名称-商品交易单号
        if(isset($data['attach']) && isset($data['transaction_id'])){
            $find_user = model('order')->where('order_no',$data['out_trade_no'])->find();

            //判断订单的type是充值vip还是购买积分*******************************************************
            if($find_user['type'] == 'vip'){
                model('order')->where('order_no',$data['out_trade_no'])->update(['status'=>1]);
                $update = [
                    'member_time'=>time(),
                    'expiration'=>strtotime('+1year'),
                    'vip'=>1
                ];
                model('user')->where('id',$find_user['user_id'])->update($update);
            }else if($find_user['type'] == 'jifen'){
                $jifen = $find_user['order_money'] * 10;
                //如果是购买积分，先修改订单状态
                model('order')->where('order_no',$data['out_trade_no'])->update(['status'=>1]);
                //给购买者增加积分
                model('user')->where('id',$find_user['user_id'])->setInc('points', $jifen);
            }
            
          //dump($data);exit;
          //echo "商品名称：" . $data['attach'] . '<br/>' . '商品交易单号：'.$data['transaction_id'];
        }
    }

    public function aliPayCallback(){
        //获取支付宝端传来的数据
        if(request()->post()){
            $data = request()->post();
            if(isset($data['subject']) && isset($data['trade_status'])){
                $find_user = model('order')->where('order_no',$data['subject'])->find();
                if($find_user['type'] == 'vip'){
                    model('order')->where('order_no',$data['subject'])->update(['status'=>1]);
                    $update = [
                        'member_time'=>time(),
                        'expiration'=>strtotime('+1year'),
                        'vip'=>1
                    ];
                    model('user')->where('id',$find_user['user_id'])->update($update);
                }else if($find_user['type'] == 'jifen'){
                    $jifen = $find_user['order_money'] * 10;
                    model('order')->where('order_no',$data['subject'])->update(['status'=>1]);
                    model('user')->where('id', $find_user['user_id'])->setInc('points', $jifen);
                }
            }
        }
       
    }

    public function wechat_jump($data){

        ini_set('date.timezone','Asia/Shanghai');
        //error_reporting(E_ERROR);
        require_once("./wxpay/lib/WxPay.Api.php");
        require_once ("./wxpay/example/WxPay.NativePay.php");
        require_once ('./wxpay/example/log.php');

        //模式一
        /**
         * 流程：
         * 1、组装包含支付信息的url，生成二维码
         * 2、用户扫描二维码，进行支付
         * 3、确定支付之后，微信服务器会回调预先配置的回调地址，在【微信开放平台-微信支付-支付配置】中进行配置
         * 4、在接到回调通知之后，用户进行统一下单支付，并返回支付信息以完成支付（见：native_notify.php）
         * 5、支付完成之后，微信服务器会通知支付成功
         * 6、在支付成功通知中需要查单确认是否真正支付成功（见：notify.php）
         */
        $notify = new \NativePay();
        //$url1 = $notify->GetPrePayUrl("http://www.ye-ren.com/wxchat/wxpay/nativeInfo");

        //模式二
        /**
         * 流程：
         * 1、调用统一下单，取得code_url，生成二维码
         * 2、用户扫描二维码，进行支付
         * 3、支付完成之后，微信服务器会通知支付成功
         * 4、在支付成功通知中需要查单确认是否真正支付成功（见：notify.php）
         */

        $input = new \WxPayUnifiedOrder();
        $input->SetBody("详情支付");
        $input->SetAttach("详情支付1");
        $input->SetOut_trade_no($data['order_no']);
        $input->SetTotal_fee($data['order_money']*100);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        $input->SetGoods_tag("成为会员");
        $input->SetNotify_url($data['url_notify']);
        $input->SetTrade_type("NATIVE");
        $input->SetProduct_id("001");
        $result = $notify->GetPayUrl($input);
        $url = $result["code_url"];
        return $url;
        //echo "<img src='http://paysdk.weixin.qq.com/example/qrcode.php?data=$url'>";
        //$this->assign('url',$url);
        //return $this->fetch('pay');
    }

    public function orderStatus(){
        if(request()->post()){
            $data = request()->post();
            $order = model('order')->where('order_no',$data['order_no'])->find();
            if($order){
                if($order['status'] == 1){
                    return array('state'=>'200','data'=>'已支付');
                }else{
                    return array('state'=>'201','data'=>'未支付');
                }
            }else{
                return array('state'=>'202','data'=>'查询有误');
            }
        }else{
            return array('state'=>'204','data'=>'服务器请求失败');
        }
    }

    /**
     * 使用curl 发送get/post请求 第二个参数有值是是post请求
     * @param  [type] $url    [description]
     * @param  [type] $fields [description]
     * @return [type]         [description]
     */
    public function curl($url,$fields=[]){
        $ch = curl_init();
        //设置我们请求的地址
        curl_setopt($ch, CURLOPT_URL, $url);
        //数据返回都不要直接显示
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //禁止证书校验
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        //判断是否是post请求
        if($fields){
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        }
        $data = '';
        if(curl_exec($ch)){
            //发送成功，获取数据
            $data = curl_multi_getcontent($ch);
        }
        curl_close($ch);
        return $data;
    }


    function get_contents($url){
        if (ini_get("allow_url_fopen") == "1") {
            $response = file_get_contents($url);
        }else{
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_URL, $url);
            $response =  curl_exec($ch);
            curl_close($ch);
        }

        //-------请求为空
        if(empty($response)){
            echo "请求为空";
        }
        return $response;
    }
}
