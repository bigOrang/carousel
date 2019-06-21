<?php
namespace app\admin\controller;

use app\admin\model\AssignModel;
use app\admin\model\PosterModel;
use think\Db;
use think\Exception;
use think\facade\Log;
use think\Request;

class Poster extends Base
{
    public function index(Request $request)
    {
        $posterModel = new PosterModel();
        if ($request->isPost()) {
            try{
                $limit = $request->param('limit', 10);
                $searchData = $request->param();
                $data = $posterModel->alias("a")->where(function ($query) use ($searchData) {
                    //模糊搜索
                    if (isset($searchData['search']) && !empty($searchData['search'])) {
                        $query->where('a.title', 'like', '%' . $searchData['search'] . '%');
                    }
                    if (isset($searchData['type']) && $searchData['type'] !== '') {
                        $query->where('a.type', $searchData['type']);
                    }
                    if (isset($searchData['is_top']) && $searchData['is_top'] !== '') {
                        $query->where('a.is_top', $searchData['is_top']);
                    }
                    if (isset($searchData['enable']) && $searchData['enable'] !== '') {
                        $query->where('a.enable', $searchData['enable']);
                    }
                    Log::error(!empty($searchData['enable']));
                    Log::error($searchData);
                    })->paginate($limit);
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
        return $this->fetch('./poster/index');
    }


    public function add(Request $request)
    {
        if ($request->isPost()) {
            Db::startTrans();
            try {
                $requestData = $this->validation($request->post(), 'PosterVali');
                $posterModel = new PosterModel();
                if ($requestData['dest_unit_id'] == "partial") {
                    $assignModel = new AssignModel();
                    $targets = $requestData['dest_dev_type'];
                    unset($requestData['dest_dev_type']);
                    $posterId = $posterModel->insertGetId($requestData);
                    $assignId = $assignModel->insertGetId([
                        'poster_id' => $posterId,
                        'targets' => $targets
                    ]);
                    $posterModel->where("id", $posterId)->update(
                        ['dest_dev_type' => $assignId]
                    );
                } else {
                    $posterModel->insert($requestData);
                }
                Db::commit();
                return $this->responseToJson([],'添加成功');
            } catch (\Exception $e) { // 回滚事务
                Db::rollback();
                return $this->responseToJson([],'添加失败'.$e->getMessage() , 201);
            }
        }
        $this->assign("teacher", session("teachers"));
        $this->assign("dev_type", session("dev_type"));
        $this->assign("devices", session("devices"));
        $this->assign("types", config('config.posterType'));
        return $this->fetch('./poster/add');
    }

    public function edit(Request $request)
    {
        if ($request->isPost()) {
            Db::startTrans();
            try {
                $requestData = $this->validation($request->post(), 'PosterVali');
                $posterModel = new PosterModel();
                if ($requestData['dest_unit_id'] == "partial") {
                    $assignModel = new AssignModel();
                    $targets = $requestData['dest_dev_type'];
                    unset($requestData['dest_dev_type']);
                    $posterModel->where("id", $requestData['id'])->update($requestData);
                    $assignModel->where("poster_id", $requestData['id'])->delete();
                    $assignId = $assignModel->insertGetId([
                        'poster_id' => $requestData['id'],
                        'targets' => $targets
                    ]);
                    $posterModel->where("id", $requestData['id'])->update([
                        'dest_dev_type' => $assignId
                    ]);
                } else {
                    $posterModel->where("id", $requestData['id'])->update($requestData);
                }
                Db::commit();
                return $this->responseToJson([],'编辑成功');
            } catch (\Exception $e) { // 回滚事务
                Db::rollback();
                return $this->responseToJson([],'编辑失败'.$e->getMessage() , 201);
            }
        }
        if ($request->has("id")) {

            $id = $request->param("id");
            $posterModel = new PosterModel();
            $data = $posterModel->where("id", $id)->findOrEmpty();
            if (!empty($data)) {
                $data['managers'] = explode(",", $data['managers']);
                if ($data['dest_unit_id'] === 'partial') {
                    $targets = AssignModel::where("id", $data['dest_dev_type'])->value("targets");
                    $data['dest_dev_type'] = empty($targets)?[]:explode(",", $targets);
                } else {
                    $data['dest_dev_type'] = [$data['dest_dev_type']];
                }
            }
            $this->assign("teacher", session("teachers"));
            $this->assign("dev_type", session("dev_type"));
            $this->assign("devices", session("devices"));
            $this->assign("types", config('config.posterType'));
            $this->assign("data", $data);
            return $this->fetch('./poster/edit');
        } else {
            exit($this->alertInfo("相关参数未获取"));
        }
    }

    public function delete(Request $request)
    {
        if ($request->has("ids") && !empty($request->param("ids"))) {
            $ids = $request->param("ids");
            Db::startTrans();
            try{
                $value = PosterModel::where("id", $ids)->value("dest_unit_id");
                if ($value === 'partial') {
                    PosterModel::where("id", $ids)->delete();
                    AssignModel::where("poster_id", $ids)->delete();
                } else {
                    PosterModel::where("id", $ids)->delete();
                }
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
        if (isset($data['time'])) {
            $time = explode(" - ", $data['time']);
            $data['on_date'] = $time[0];
            $data['off_date'] = $time[1];
            unset($data['time']);
        } else {
            exit($this->responseToJson([], '未获取到事件时间', 201, false));
        }
        if ($data['dest_unit_id'] == "partial") {
            $data['dest_dev_type'] = implode(",", $data['dest_dev_type_arr']);
        }
        if (isset($data['dest_dev_type_arr'])) {
            unset($data['dest_dev_type_arr']);
        }
        $valid = $this->validate($data, $name);
        if (true !== $valid) {
            exit($this->responseToJson([], $valid, 201, false));
        }
        $data['managers'] = implode(",", $data['managers']);
        $data['is_top'] = $data['is_top'] == 1 ? true : false;
        $data['enable'] = $data['enable'] == 1 ? true : false;
        return $data;
    }
}
