<?php
namespace app\admin\controller;

use app\admin\controller\Base;

class Index extends Base
{
	/**
	 * 后台首页
	 * @return [type] [description]
	 */
    public function index()
    {
    	$kinds = model("Kind")->where('pid', 0)->select();

    	$this->assign('kinds', $kinds);
        return $this->fetch();
    }
}
