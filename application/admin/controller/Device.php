<?php
namespace app\admin\controller;

use app\admin\model\DeviceModel;
use app\admin\model\PosterModel;
use think\Db;
use think\Exception;
use think\facade\Log;
use think\Request;

class Device extends Base
{
    public function index(Request $request)
    {
        $deviceModel = new DeviceModel();
        if ($request->isPost()) {
            try{
                $limit = $request->param('limit', 10);
                $searchData = $request->param();
                $data = $deviceModel->alias("a")->where(function ($query) use ($searchData) {
                    //模糊搜索
                    if (isset($searchData['search']) && !empty($searchData['search'])) {
                        $query->where('a.dev_name', 'like', '%' . $searchData['search'] . '%');
                    }
                    if (isset($searchData['dev_type']) && $searchData['dev_type'] !== '') {
                        $query->where('a.dev_type', $searchData['dev_type']);
                    }
                    })->leftJoin("t_media_device_type b","b.dev_type=a.dev_type")
                    ->field("a.*,b.remark as dev_type_name")->paginate($limit);
                $data = json_decode(json_encode($data),true);
            } catch (Exception $exception) {
                Log::error('获取数据错误：'. $exception->getMessage());
                $data = ['total' => 0, 'rows' => []];
            }
            return [
                'total' => $data['total'],
                'rows' => $data['data']
            ];
        }
        $this->assign("dev_type", session("dev_type"));
        return $this->fetch('./device/index');
    }


    public function add(Request $request)
    {
        if ($request->isPost()) {
            Db::startTrans();
            try {
                $requestData = $this->validation($request->post(), 'DeviceVali');
                $deviceModel = new DeviceModel();
                $deviceModel->insert($requestData);
                Db::commit();
                return $this->responseToJson([],'添加成功');
            } catch (\Exception $e) { // 回滚事务
                Db::rollback();
                return $this->responseToJson([],'添加失败'.$e->getMessage() , 201);
            }
        }
        $this->assign("teacher", session("teachers"));
        $this->assign("dev_type", session("dev_type"));
        return $this->fetch('./device/add');
    }

    public function edit(Request $request)
    {
        if ($request->isPost()) {
            Db::startTrans();
            try {
                $requestData = $this->validation($request->post(), 'DeviceVali');
                $deviceModel = new DeviceModel();
                $id = $requestData['id'];unset($requestData['id']);
                $deviceModel->where("id", $id)->update($requestData);
                Db::commit();
                return $this->responseToJson([],'编辑成功');
            } catch (\Exception $e) { // 回滚事务
                Db::rollback();
                return $this->responseToJson([],'编辑失败'.$e->getMessage() , 201);
            }
        }
        $id = $request->param("id");
        $data = DeviceModel::where("id", $id)->findOrEmpty();
        if (!empty($data)) {
            $data['manager'] = explode(",", $data['manager']);
        }
        $this->assign("data", $data);
        $this->assign("teacher", session("teachers"));
        $this->assign("dev_type", session("dev_type"));
        return $this->fetch('./device/edit');
    }


    public function delete(Request $request)
    {
        if ($request->has("ids") && !empty($request->param("ids"))) {
            $ids = $request->param("ids");
            Db::startTrans();
            try{
                $data = is_array($ids) ? $ids : [$ids];
                $deviceModel = new DeviceModel();
                $deviceModel->whereIn("id", $data)->delete();
                // 提交事务
                Db::commit();
                return $this->responseToJson([],'删除成功' , 200);
            }catch (Exception $e) {
                // 回滚事务
                Db::rollback();
                return $this->responseToJson([],'删除失败'.$e->getMessage() , 201);
            }
        } else {
            return $this->responseToJson([],'相关参数未获取' , 201);
        }
    }

    public function validation($data, $name)
    {
        $valid = $this->validate($data, $name);
        if (true !== $valid) {
            exit($this->responseToJson([], $valid, 201, false));
        }
        $data['manager'] = implode(",", $data['manager']);
        $data['status'] = $data['status'] == 1 ? true : false;
        return $data;
    }
}
