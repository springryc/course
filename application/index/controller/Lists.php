<?php

namespace app\index\controller;

use app\index\controller\Base;

class Lists extends Base
{
    /**
     * 显示详情的列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $kind_id = input('kind_id');
        $pid = model('Kind')->where('id', $kind_id)->value('pid');

        $model = model('Kind')->where('id', $pid)->value('model');

        $data = model($model)->paginate(20);
        $this->assign('kind', $pid);
        $this->assign('data', $data);
        return $this->fetch();
    }
    /**
     * 资源详情展示页
     * @return [type] [description]
     */
    public function detail()
    {
        $id = input('id');
        $kind_id = input('kind');
        $model = model('Kind')->where('id', $kind_id)->value('model');
        // dump($kind_id);die;
        $data = model($model)->find($id);

        $this->assign('data', $data);
        return $this->fetch();
    }
    
}
