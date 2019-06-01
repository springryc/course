<?php

namespace app\admin\controller;

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
        $adminData = '';

        //-----------------------------------------上传需要注释掉此区间代码
           $arr = [
               'adminId'=>1,
               'time'=>time()
           ];
           session('adminInfo',$arr);
        //-----------------------------------------
        
        $adminInfo = session('adminInfo');
        if(empty($adminInfo)){
            $this->redirect('/admin/login/index');
        }else{
            // $userData = model('Admin')
            //             ->field('id,username,sex')
            //             ->where('id',$adminInfo['adminId'])
            //             ->find();
            // $this->assign('adminData',$adminData);
        }

        

    }
}
