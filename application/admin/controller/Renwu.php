<?php
namespace app\admin\controller;

use app\admin\model\Cat;
use app\admin\model\Demand;
use app\admin\model\Log;
use app\common\model\BookCategory;
use app\common\model\BookLose;
use app\model\Decorate;
use app\model\UserRw;
use function EasyWeChat\Payment\get_client_ip;
use think\Db;

class Renwu extends Base
{
    protected $model;
    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->model = Db::name('Renwu')->order("id desc")->order('create_time','desc')->select();

    }

    /**
     * @return \think\response\View
     * 任务列表首页
     */

    public function index(){
        $keyword = "";
        if(isset($_GET['keyword'])){
            $keyword = $_GET['keyword'];
        }

        $count = Db::name('Renwu')->count();

        $cat = Cat::where('level','1')->select();

        $whereIn = Cat::where('level',2)->column('id');
        if(!empty(input('keyword'))){
            $whereIn = Cat::where('p_id',input('keyword'))->column('id');
        }

        $data = \app\model\Renwu::order("id desc")->whereIn('rw_cat',$whereIn)->order('create_time','desc')->select();

        if(!empty($data)){
            foreach ($data as $v){
                $v->catName = Cat::where('id',$v->rw_cat)->value('cat_name');
            }
        }
        $data = \app\model\Renwu::getBackAll($data);
//
        $title = "任务列表";

        return view("Renwu/index", compact('count','cat','keyword','data','title'));
    }

    /**
     * @return \think\response\Json
     * 任务列表数据
     */

    public function renwu_ajax(){
        $whereIn = Cat::where('level',2)->column('id');
        if(!empty(input('keyword'))){
            $whereIn = Cat::where('p_id',input('keyword'))->column('id');
        }

        $data = \app\model\Renwu::order("id desc")->whereIn('rw_cat',$whereIn)->order('create_time','desc')->select();




        return json(['data'=>\app\model\Renwu::getBackAll($data)]);
    }

    /**
     * @return \think\response\Json
     * 推荐热门
     */

    public function remen(){
        $id     = input('id');
        $is_hot = input('is_hot');

        $s = Db::name('Renwu')->where(['id'=>$id])->update([
            'rw_hot'=>$is_hot
        ]);

        if($s){
            $msg['code'] = 200;
            $msg['msg']  = "操作成功";
        }else{
            $msg['code'] = 200;
            $msg['msg']  = "数据无变化";
        }

        return json($msg);
    }


    public function read(){
        $id = input('id');
        $data = Db::name('Renwu')->where(['id'=>$id])->find();

//        var_dump($data);die;
        $font = "style=\"font-size:14px\"";
        switch ($data['rw_status']){
            case 0:
                $status = "<span class=\"label label-defaunt radius\" $font>未支付佣金</span>";
                break;
            case 1:
                $status = "<span class=\"label label-success radius\" $font>已支付佣金</span>";
                break;
            case 2:
                $status = "<span class=\"label label-defaunt radius\" $font>未被接单</span>";
                break;
        }
        $data['rw_status'] = $status;

        switch ($data['rw_pass']){
                case 0:
                    $pass = "<span class=\"label label-defaunt radius\" $font>未审核</span>";
                    break;
                case 1:
                    $pass = "<span class=\"label label-defaunt radius\" $font>审核未通过</span>";
                    break;
                case 2:
                    $pass = "<span class=\"label label-success radius\" $font>审核已通过</span>";
                    break;
            }

        $data['rw_pass'] = $pass;
        $data['rw_img']  = json_decode($data['rw_img'],true);

        if(empty($data['rw_img'])) {
            $data['lunbojson'] = "";
        }else{
            $data['lunbojson'] = implode(',',$data['rw_img']);
        }

        $data['lunbocount'] = count($data['rw_img']);

        $data['create_time'] = date('Y-m-d',$data['create_time']);
        $data['start_time'] = date('Y-m-d',$data['start_time']);
        //省份
        $province = Db::name('HatProvince')->select();

        //城市
        $city = Db::name('HatCity')->where(['father'=>Db::name('HatCity')->where(['city'=>$data['rw_area']])->value('father')])->select();

        //任务属性
        $rw_cat = Db::name('Cat')->field('id,cat_name')->where(['level'=>2])->select();

        // 获取发布用户的数据
        $TaskUserId=  \app\model\Renwu::where('id',$id)->value('user_id');


        if(!empty($TaskUserId)){
            $phone = Demand::where('user_id',$TaskUserId)->value('phone');
            if(empty($phone)){
                $phone =  \app\model\User::where('id',$TaskUserId)->value('user_phone');
            }
        }else{
            $phone ="";
        }
        $data['pay_limit_time'] = $data['pay_limit_time'] / 1000;
        $data['bid_time'] = $data['bid_time'] / 1000;
//        dump($TaskUserId);die;
        $j = [
            'title'=>"任务详情",
            'data'=>$data,
            'province'=>$province,
            'rw_cat'=>$rw_cat,
            'city'=>$city,
            'phone'=>$phone
        ];
        return view('Renwu/add',$j);
    }



    /**
     * @return string
     * 操作任务 数据
     */

    public function add(){
        $id         = input('id');
        $data                    = input();
        $rw_province             = Db::name('HatProvince')->where(['provinceID'=>input('rw_province')])->value('province');
        $data['rw_area']         = $data['rw_city'];
        // 时间转为毫秒
        $data['pay_limit_time'] = $data['pay_limit_time'] * 1000;
        unset($data['rw_city']  );
        if (!is_numeric($data['rw_yj'])|| !is_numeric($data['rw_ding']) || !is_numeric($data['pay_limit_time']) )
        {
            return "<script>alert('请填写正确的任务金额或任务时间');setTimeout(function() {
                    history.go(-1)
                    },500)</script>";
        }
        $data['rw_province'] = $rw_province;
        $data['type'] = 1;
        // 判断任务类型 是否为装修前期
        $cat_id_pid = Cat::where('id',$data['rw_cat'])->value('p_id');
        $cat_id = Cat::where('id',$cat_id_pid)->value('id');
        if($cat_id == 1){
            // 设计任务
            $data['type'] = 2;
            $user_id = Demand::where(['phone'=>input('user_phone'),'called'=>0])->value('user_id');
            $data['user_id']                = empty($user_id) ?
                \app\model\User::where('user_phone',input('user_phone'))->value('id'):$user_id;
        }
        if ((request()->file('rw_cover')) != NULL) {
            $file = request()->file('rw_cover');
            // 移动到框架应用根目录/public/uploads/ 目录下
            $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');
            $data['rw_cover'] = '/uploads/' . $info->getSaveName();
        }

        // 处理任务图册
        if(!empty($data['lunbo'])){
            $data['rw_img'] = json_encode(explode(',',$data['lunbo']));
        }
        unset($data['city']);
        unset($data['lunbo']);

        $data['create_time'] = strtotime($data['create_time']);
        $data['start_time'] = strtotime($data['start_time']);


        if($data['rw_cat'] == 4){
            // 根据手机号查询
            $data['apply_id'] = Demand::where(['phone'=>$data['user_phone']])->whereIn('called',[0,3])->value('id');
            if(!empty($data['apply_id'])){
                // 查询用户的id
                $data['user_id'] = Demand::where(['phone'=>$data['user_phone']])->whereIn('called',[0,3])->value('user_id');
                // 改变申请状态  将审核通过的和审核中的订单 都改为已发布
                Demand::where('id',$data['apply_id'])->update(['called'=>1]);
            }else{
                unset($data['apply_id']);
            }

            $data['bid_time'] = $data['bid_time']*1000;
        }
        unset($data['user_phone']);
//        dump($data);die;
        if(empty($id)){

            $data['rw_status'] = 2;

            // 如果是设计任务添加申请id

            $s = Db::name('Renwu')->insert($data);

            if($s){
                return "<script>alert('添加成功');setTimeout(function() {
                    location.href = '/admin/renwu/index';
},500)</script>";
            }else{
                return "<script>alert('添加失败');setTimeout(function() {
                    history.go(-1)
},500)</script>";
            }
        }else{

            $data['user_id'] = Decorate::where('id',\app\model\Renwu::where('id',$id)->value('apply_id'))->value('user_id');

            if(is_numeric($data['rw_area'])){
                $data['rw_area'] = Db::name('hat_city')->where('cityID',$data['rw_area'])->value('city');
            }
            $status = Db::name('Renwu')->where(['id'=>$id])->value('rw_status');

            if($status != 2){
                return "<script>alert('任务已被接单 无法修改');setTimeout(function() {
                    history.go(-1)
},500)</script>";
            }else{
                $s = Db::name('Renwu')->where(['id'=>$id])->update($data);

                if($s){
                    return "<script>alert('修改成功');setTimeout(function() {
                   location.href = '/admin/renwu/index';
},500)</script>";
                }else{
                    return "<script>alert('修改失败');setTimeout(function() {
                    history.go(-1)
},500)</script>";
                }
            }
        }
    }



    /**
     * @return \think\response\View
     * 新增任务页面
     */

    public function add_rw(){
        $font = "style=\"font-size:14px\"";
        $status = "<span class=\"label label-defaunt radius\" $font>未支付佣金</span>";
        $data['rw_status'] = $status;

        $pass = "<span class=\"label label-defaunt radius\" $font>未审核</span>";
        $data['rw_pass'] = $pass;
        $data['rw_img']         = array();
        $data['rw_title']       = "";
        $data['rw_yj']          = "";
        $data['rw_ding']        = "";
        $data['rw_cat']         = "";
        $data['rw_main']        = "";
        $data['rw_province']    = "";
        $data['abstract']    = "";
        $data['rw_area']        = "";
        $data['rw_cover']       = "";
        $data['create_time']    = date('Y-m-d',time());
        $data['start_time']     = date('Y-m-d',time());
        $data['pay_limit_time']  = "";
        $data['id']  = "";
        $data['lunbojson']  = "";
        $data['lunbocount']  = 0;
        $data['bid_time']  = 0;
        $province = Db::name('HatProvince')->select();

        $city = Db::name('HatCity')->where(['father'=>Db::name('HatCity')->where(['city'=>$data['rw_area']])->value('father')])->select();

        $rw_cat = Db::name('Cat')->field('id,cat_name')->where(['level'=>2])->select();
        $j = [
            'title'     =>"新增任务",
            'data'      =>$data,
            'province'  =>$province,
            'rw_cat'    =>$rw_cat,
            'city'      =>$city,
            'phone'     =>''
        ];
        return view('Renwu/add',$j);
    }


    /**
     * @return \think\response\Json
     * 删除任务
     */

    public function rw_del(){
        $id = input('id');
        $s = Db::name('Renwu')->where(['id'=>$id])->delete();
        if($s){
            $code = 200;
        }else{
            $code = 404;
        }

        return json($code);
    }

    /**
     * @return \think\response\Json
     * 任务发布
     */

    public function fabu(){
        $id = input('id');
        $is_pass = Db::name('Renwu')->where(['id'=>$id])->value('is_show') == 1 ? 0 : 1;

        $s = Db::name('Renwu')->where(['id'=>$id])->update(['is_show'=>$is_pass,'create_time'=>time()]);
        if($s){
            $code = 200;
        }else{
            $code = 404;
        }


        return json($code);
    }

    public function area(){
        $father = input('father');
        $data = Db::name('HatCity')->where(['father'=>$father])->field('city,cityID')->select();

        return json($data);
    }


    public function cat(){
        $j = [
            'title'=>'任务分类'
        ];

        return view('Renwu/cat',$j);
    }

    public function cat_ajax(){
        $data = Db::name('Cat')->where(['level'=>1])->select();
        foreach($data as $k=>$v){
            $id                         = $v['id'];
            $data[$k]['cat_type']       = "一级分类";
            $img                        = $v['cat_img'];
            $data[$k]['cat_img']        = "<img src='$img' style='width: 80px;height: 80px'>";


            $data[$k]['caozuo'] = "<a onclick='cat_sec($id)'>查看二级分类</a> | <a onclick='cat_update($id)'><i class=\"Hui-iconfont\" >&#xe6df;</i></a> | 
 <a style=\"text-decoration:none\" class=\"ml-5\" onClick=\"cat_del(this,$id)\" href=\"javascript:;\" title=\"删除\"><i class=\"Hui-iconfont\">&#xe6e2;</i></a>";

        }

        return json(['data'=>$data]);
    }


    /**
     * @return \think\response\Json
     * 删除任务分类属性
     */
    public function cat_del(){
        $id = input('id');
        Log::create([
            'msg'=>get_client_ip(),
            'data'=>\think\Request::instance()->controller()
        ]);
        $check = Db::name('Cat')->where(['p_id'=>$id])->find();
        if(empty($check)){
            $s = Db::name('Cat')->where(['id'=>$id])->delete();
            if($s){
                $code = 200;
            }
        }else{
            $code = 404;
        }



        return json($code);
    }

    /**
     * @return \think\response\View
     * 二级分类
     */

    public function sec(){
        $id     = input('id');
        $data   = Db::name('Cat')->where(['p_id'=>$id])->field('cat_name,id')->select();
        $f_name = Db::name('Cat')->where(['id'=>$id])->value('cat_name');
        $j = [
            'title'=>'二级分类',
            'data'=>$data,
            'f_name'=>$f_name
        ];

        return view('Renwu/sec',$j);
    }


    public function fir(){
        $id = input('id');
        $data = Db::name('Cat')->where(['id'=>$id])->find();

        $j= [
            'title'=>'编辑分类',
            'data'=>$data
        ];

        return view('Renwu/edit',$j);
    }


    public function save(){
        $id                 = input('id');
        $data               = input('post.');

//         var_dump($data);die;
        if($id ==0){
            $data['create_time']        = time();
            $data['pro_img']            = session('cat_img');
            $s = Db::name('Cat')->insert($data);
        }else{
            $data['cat_img']    = session('cat_img') ==  " " ? Db::name('Cat')->where(['id'=>$id])->value('cat_img') : session('cat_img');
            $s = Db::name('Cat')->where(['id'=>$id])->update([
                'cat_name'=>$data['cat_name'],
                'cat_img'=>$data['cat_img'],
            ]);

        }
        if($s){
            $code = 200;
        }else{
            $code = 404;
        }
        return json($code);
    }


    /**
     * @return \think\response\Json
     * 删除二级分类
     */

    public function sec_del(){
        $id = input('id/a');
        if($id == NULL){
            $msg['code'] = 405;
            $msg['msg']  = "该分类下没有二级分类,删除失败";
        }else{
            foreach($id as $k=>$v){
                $check = Db::name('Renwu')->where(['rw_cat'=>$v])->find();
                if(empty($check)){
                    $code = 200;
                    continue;
                }else{
                    $code = 403;
                    break;
                }
            }

            if($code == 200){
                foreach($id as $k=>$v){
                    Db::name('Cat')->where(['id'=>$v])->delete();
                }
                $msg['code'] = 200;
                $msg['msg']  = "删除成功";
            }else{
                $msg['code'] = 403;
                $msg['msg']  = "该属性下有任务,删除失败";
            }
        }

        return json($msg);
    }


    public function add_cat(){
        $id = input('id');
        if($id == 0){
            $fir['id']          = 0;
            $fir['cat_name']    = "";
            $fir['cat_img']     = "";
            $sec[0]['id']       = 0;
            $sec[0]['cat_name'] = "";
            $sec[0]['p_id']     = 0;
            $f = 0;
        }else{
            $fir = Db::name('Cat')->field('id,cat_name,cat_img')->where(['id'=>$id])->find();
            $sec = Db::name('Cat')->field('id,cat_name,p_id')->where(['p_id'=>$id])->select();

            if(empty($sec)){
                $sec[0]['id']       = 0;
                $sec[0]['cat_name'] = "";
                $sec[0]['p_id']     = $fir['id'];
            }
            $f = 1;
        }
        $data = Db::name('Cat')->where(['level'=>1])->select();

        $j = [
            'title'=>'添加属性',
            'data'=>$data,
            'fir'=>$fir,
            'sec'=>$sec,
            'f'=>$f
        ];

        return view('Renwu/cat_add',$j);
    }


    public function cat_save(){
        $data = input('post.');

        return Cat::saveCat($data);
    }

    public function time(){
        $data = "a";
        $a = explode(' ',$data);
        dump($a);
    }
}
