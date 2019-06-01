<?php

namespace app\index\model;

use think\Model;

class SystemConfig extends Model
{
    // 设置返回数据集的对象名
    protected $resultSetType = 'collection';

    //获取登陆需要的appid,secret
    public function config(){
    	$config = model('system_config')->where('id',1)->find();
    	return $config;
    }
}
