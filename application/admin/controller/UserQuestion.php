<?php
namespace app\admin\controller;

use app\common\model\Kefu;
use think\Db;
//use app\model\userQuestion;
use app\admin\model\userQuestion as P;
use think\Request;


class UserQuestion extends Base
{
    protected $userQuestion;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->userQuestion = new P();
    }

    public function index()
    {

        $title = "用户提问";
        return view("PcWeb/userQuestion", compact('title'));
    }


    /**
     * @return \think\response\Json
     * author hongwenyang
     * method description : 获取数据列表
     */
    public function GetList(){

        $data = [];
        $data = $this->userQuestion->select();

        foreach ($data as $v){
            $v->suolve = "<img class='layui-upload-img product_suolve' id='demo1' src='$v->suolve'>";
            $v->caozuo = "<a style=\"text-decoration:none\" onclick=\"hot(this,$v->id)\">推荐</a>
 | <a href=\"read?id=$v->id\" title=\"编辑\"><i class=\"Hui-iconfont\" >&#xe6df;</i></a> | 
 <a style=\"text-decoration:none\" class=\"ml-5\" onClick=\"del(this,$v->id)\" href=\"javascript:;\" title=\"删除\"><i class=\"Hui-iconfont\">&#xe6e2;</i></a>";
        }
        return json(['data'=>$data]);
    }


    /**
     * @return \think\response\Json
     * author hongwenyang
     * method description : 修改为是否推荐
     */

    public function handel(){
        $Get = input('post.');

        $s = $this->userQuestion->where('id',$Get['id'])->update([
            'is_hot'=>$Get['is_hot']
        ]);

        return returnStatus($s);
    }


    /**
     * author hongwenyang
     * method description : 详情
     */

    public function read(){
        $id = input('id');
        $title = '产品展示操作';
        if(isset($id)){
            $data = $this->userQuestion->find($id);
            if(!empty($data->lunbo)){
                $data->lunbojson = implode(',',$data->lunbo);
            }else{
                $data->lunbojson = "";
            }
        }else{
            $data = json_decode("{}");
            $data->suolve = "";
            $data->lunbo = "";
            $data->lunbojson = "";
            $data->id = "";
        }
        return view('PcWeb/product',compact('data','title'));
    }

    /**
     * author hongwenyang
     * method description : 保存返回图片路径
     * @return string
     */

    public function saveImg(){
        return json_encode(makeImg());
    }

    /**
     * * author hongwenyang
     * method description : 保存数据
     * @return mixed
     */

    public function save(){
        $data = input('post.');
        if(!empty($data['lunbo'])){
            $lunbo = explode(',',$data['lunbo']);
            if($lunbo[0] == ""){
                unset($lunbo[0]);
            }
            sort($lunbo);
            $data['lunbo'] = json_encode($lunbo);
        }
        if(!empty($data['id'])){
            // 修改
            $map['id'] = $data['id'];
            unset($data['id']);
            $s = $this->userQuestion->where($map)->update($data);
        }else{
            // 新增
            $s = $this->userQuestion->insert($data);
        }
        return returnStatus($s);
    }

    public function del(){
        $id = input('id');
        $s = $this->userQuestion->where('id',$id)->delete();
        return returnStatus($s);
    }
}
