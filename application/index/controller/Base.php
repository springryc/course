<?php

namespace app\index\controller;

use think\Controller;
use think\Request;

class Base extends Controller
{
    /**
     * 初始化函数，构造函数
     * @return [type] [description]
     */
    public function initialize(){
        header('Access-Control-Allow-Origin: *');
        $userData = '';

        //-----------------------------------------上传需要注释掉此区间代码
           $arr = [
               'userId'=>1,
               'account'=>'0000000149',
               'vip'=>1,
               'time'=>time()
           ];
           session('userInfo',$arr);
        //-----------------------------------------
        
        $userInfo = session('userInfo');
        if(empty($userInfo)){
            //没有登陆
        }else{
            $userData = model('user')
                        ->field('id,username,image,other_image,account,sex,vip')
                        ->where('id',$userInfo['userId'])
                        ->find();
            $this->assign('userData',$userData);
        }

        

    }

    
}
