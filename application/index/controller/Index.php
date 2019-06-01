<?php
namespace app\index\controller;

use app\index\controller\Base;

class Index extends Base
{
	//首页
    public function index()
    {
    	//获取顶级分类
    	$kinds = model('Kind')->where('pid', 0)->select();
    	$this->assign('kinds', $kinds);
        return $this->fetch();
    }
}
