<?php

namespace app\admin\model;

use think\Model;

class ResDesign extends Model
{
    // 设置返回数据集的对象名
    protected $resultSetType = 'collection';

    public function Kind()
    {
    	return $this->hasOne('kind', 'id', 'kind_id');
    }
}
