<?php

namespace app\admin\controller;

use app\admin\controller\Base;

class Resource extends Base
{
    /**
     * it资源列表
     *
     * @return \think\Response
     */
    public function it()
    {
        //it资源库的所有数据
        $its = model('ResIt')->with('Kind')->paginate(1);
        //it资源库的所有分类
        $it_kinds = model('Kind')->where('pid', 1)->select();

        $this->assign('it_kinds', $it_kinds);
        $this->assign('its', $its);
        return $this->fetch();
    }

    public function it_edit()
    {
        if(request()->post()){
            $data = request()->post();
            $id = $data['id'];
            unset($data['id']);
            try {
                $bool = model('ResIt')->where('id', $id)->update($data);
                return json_encode(array('state'=>'200', 'hint'=>'修改成功！'));
            } catch (Exception $e) {
                return json_encode(array('state'=>'204', 'hint'=>'数据库异常了，修改失败!'));
            }
        }else{
            $id = input('id');

            //查询要编辑的数据
            $data = model('ResIt')->find($id);
            //根据要查询的数据的kind_id 查询他的pid
            $pid = model('Kind')->where('id', $data['kind_id'])->value('pid');
            //查询pid相同的分类
            $it_kinds = model('Kind')->where('pid', $pid)->select();

            $this->assign('it_kinds', $it_kinds);
            $this->assign('data', $data);
            return $this->fetch();
        }
    }

    public function changeStatus()
    {
        $data = request()->post();
        $status = model('ResIt')->where('id', $data['id'])->value('status');
        if($status != $data['status']){
            return json_encode(array('state'=>'204', 'hint'=>'状态有无，请刷新重试'));
        }
        if($status == 1){
            $s = 0;
        }else{
            $s = 1;
        }
        try {
            $bool = model('ResIt')->where('id', $data['id'])->update(['status'=>$s]);
            if($bool){
                return json_encode(array('state'=>'200', 'hint'=>'修改成功！'));
            }else{
                return json_encode(array('state'=>'204', 'hint'=>'修改失败！'));
            }
        } catch (Exception $e) {
            return json_encode(array('state'=>'204', 'hint'=>'数据库异常！'));
        }
        
    }

    /**
     * it资源列表
     *
     * @return \think\Response
     */
    public function operation()
    {
        return $this->fetch();
    }

    /**
     * it资源列表
     *
     * @return \think\Response
     */
    public function design()
    {
        return $this->fetch();
    }

    
}
