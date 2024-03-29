<?php
namespace app\admin\controller;

use app\admin\model\DeviceModel;
use app\admin\model\DeviceTypeModel;
use think\Controller;
use think\Db;
use think\facade\Log;
use think\facade\Session;

class Base extends Controller
{
    public function initialize()
    {
        $user_id = input('get.user_id');
        $school_id = input('get.school_id');
        $client_id = input('get.client_id');
        if (!empty($user_id) && !empty($school_id) && !empty($client_id)) {
            Session::clear();
            session('auth_status', 0);
            session('user_id', $user_id);
            session('client_id', $client_id);
            session('school_id', $school_id);
            session("prefix_key", '47');
            $prefix_key = session("prefix_key");
            session("prefix", config("api.prefix.{$prefix_key}"));
            $res = $this->checkIsManager($user_id, $school_id, $client_id);
            if (empty($res) || $res['error_code'] != 1000) {
                exit($this->fetch('./common/403',[
                    'msg' => '身份认证失败'
                ]));
            }
            if ($res['extra']['status'] == 1) {
                session('auth_status', $res['extra']['status']);
                session('teachers', $this->getTeacher());
                session('userDetail', $this->getUserDetail());
                session('dev_type', DeviceTypeModel::select());
                session('devices', DeviceModel::select());
            } else {
               exit($this->fetch('./common/403',[
                   'msg' => '没有权限'
               ]));
            }
        } else {
            if (empty(session('auth_status'))) {
                exit($this->fetch('./common/403',[
                    'msg' => '身份过期，请重新登陆!'
                ]));
            }
        }
//        if (empty($school_id)) {
//            $school_id = session('school_id');
//        }
//        $db = Db::table('t_sys_mod_biz_db')->where('school_id', $school_id)->find();
//        if ($db) {
//            $config = [
//                'type'            => 'mysql',
//                'hostname'        => $db['db_server'],
//                'database'        => $db['db_name'],
//                'username'        => $db['db_user'],
//                'password'        => $db['db_pass'],
//                'hostport'        => $db['db_port'],
//                'dsn'             => '',
//                'params'          => [],
//                'charset'         => 'utf8',
//                'prefix'          => '',
//            ];
//            session('db-config_'.$school_id, $config);
//        } else {
//            exit($this->fetch('./common/404',[
//                'msg' => '初始化数据失败!'
//            ]));
//        }
    }
    /**
     * @param array $data
     * @param string $msg
     * @param int $code
     * @param bool $default
     * @return array|string
     */
    public function responseToJson($data = [], $msg = 'ok', $code = 200, $default = true) {
        if ($default) {
            return [
                'code' => $code,
                'msg' => $msg,
                'data' => $data,
            ];
        }
        return json_encode([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 判断是否为管理员
     * @param $user_id
     * @param $school_id
     * @param $client_id
     * @return array|mixed
     */
    public function checkIsManager($user_id, $school_id, $client_id)
    {
        $isManagerService = config('api.isManager');
        $url = session("prefix").$isManagerService['url'];
        $url = sprintf($url, $user_id, $client_id);
        $header = [
            'Content-Type:' . $isManagerService['header']['Content-Type'],
            'school_id:' . $school_id,
            'mdc_value:' . $isManagerService['header']['mdc_value'],
        ];
        return $this->curlRequest($url, $isManagerService['method'], $header,'');
    }


    /**
     * 消息提示模板
     * @param string $msg
     * @return mixed
     */
    protected function alertInfo($msg = '')
    {
        return $this->fetch('./common/alert-info', [
            'msg' => $msg,
        ]);
    }

    public function getToken()
    {
        $client_id = session("client_id");
        $tokenService = config('api.getToken');
        $basicHeader[] = "Authorization: Basic ".base64_encode("{$client_id}:{$tokenService['basic']['password']}");
        //添加头，在name和pass处填写对应账号密码
        $tokenHeader = ['school_id:' . session("school_id")];
        $tokenHeader = array_merge($tokenHeader, $basicHeader);
        $tokenService['body']['username'] = session("user_id");
        $url = session("prefix"). $tokenService['url'];
        $token = $this->curlRequest($url, $tokenService['method'], $tokenHeader, $tokenService['body']);
        if (!isset($token['access_token'])) {
            Log::error($token);
            exit($this->fetch('./common/500',[
                'msg' => '获取token失败'
            ]));
        } else {
            session("token", $token);
            return true;
        }
    }


    public function getTeacher()
    {
        //获取token
        $prefix = session("prefix");
        $prefix_key = session("prefix_key");
        if (!Session::has("token")) {
            $this->getToken();
        }
        $token = session("token");
        $teacherService = config('api.getTeacher');
        $bearerHeader[] = "Authorization: Bearer ".$token['access_token'];
        $teacherHeader = [
            'school_id:' . session("school_id"),
            'mdc_value:' . $teacherService['header']['mdc_value'],
            'master_key:' . $teacherService["master_key_{$prefix_key}"],
        ];
        $teacherHeader = array_merge($teacherHeader, $bearerHeader);
        $url = $prefix. $teacherService['url'];
        $res = $this->curlRequest($url, $teacherService['method'], $teacherHeader, $teacherService['body']);
        if (isset($res['error_code']) && $res['error_code'] == 1000) {
            return $res['extra'];
        } else {
            exit($this->fetch('./500',[
                'msg' => '获取教师记录失败'
            ]));
        }
    }

    public function getUserDetail()
    {
        $token = Session::has("token") ? session("token") : $this->getToken();
        $userDetailService = config('api.getUserDetail');
        $bearerHeader[] = "Authorization: Bearer ".$token['access_token'];
        $userDetailHeader = [
            'school_id:' . session("school_id"),
            'mdc_value:' . $userDetailService['header']['mdc_value'],
            'client_id:' . session("client_id"),
        ];
        $userDetailHeader = array_merge($userDetailHeader, $bearerHeader);
        $url = session("prefix"). $userDetailService['url'];
        $res = $this->curlRequest($url, $userDetailService['method'], $userDetailHeader, $userDetailService['body']);
        if (isset($res['wid'])) {
            return $res;
        } else {
            exit($this->fetch('./500',[
                'msg' => '获取用户详情失败'
            ]));
        }
    }


    function curlRequest($url, $method = 'POST', $header = [], $data = '')
    {
        // setting the curl parameters.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        // turning off the server and peer verification(TrustManager Concept).
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        // setting the POST FIELD to curl
        $method = strtoupper($method);
        switch ($method) {
            case"GET" :
                curl_setopt($ch, CURLOPT_HTTPGET, 1);
                break;
            case "POST":
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;
            case "PUT" :
                curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;
            case "DELETE":
                curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;
        }
        // getting response from server
        $response = curl_exec($ch);

        //close the connection
        curl_close($ch);
        //return the response
        if (stristr($response, 'HTTP 404') || $response == '') {
            return [
                'error_code' => '1001',
                'error_msg' => '请求错误'
            ];
        }

        $response = is_string($response) ? json_decode($response, true) : $response;
        return $response;
    }
    /**
     * 导出CSV
     * @param $num
     * @param $header
     * @param $sql
     * @param $dataRow
     * @param string $fileName
     */
    public function exportAllCsv($num, $header, $sql, $dataRow, $fileName = 'CSV文件')
    {
        $limit = 10000;
        $offset = 0;
        //将数据写入 导出文档
        header('Content-Type:application/csv');
        header('Content-Disposition:attachment;filename='.$fileName.'.csv');
        $fp = fopen('php://output', 'w');
        ob_start();
        foreach ($header as $key => $val) {
            $header[$key] = iconv('UTF-8', 'GBK//IGNORE', $val);
        }
        fputcsv($fp, $header);
        //循环获取导出数据并写入，一次最多取10000条
        for (; $offset < $num; $offset += $limit) {
            $sql = sprintf($sql, $limit, $offset);
            $data = Db::query($sql);
//            //循环写入
            foreach ($data as $row) {
                $line = array();
                $line[$dataRow[0]] = $row[$dataRow[0]];
                $line[$dataRow[1]] = $row[$dataRow[1]];
                $line[$dataRow[2]] = $row[$dataRow[2]];
                $line[$dataRow[3]] = $row[$dataRow[3]];
                $line[$dataRow[4]] = $row[$dataRow[4]];
                foreach ($line as $key => $val) {
                    $line[$key] = trim(mb_convert_encoding($val, 'gbk'));
                }
                fputcsv($fp, $line);
            }
        }
        fclose($fp);
        echo ob_get_clean();
    }


    /**
     * 递归解析节点进行排序
     * @param array $data
     * @param int $pid
     */
    protected function parseNode($data = [], $pid = 0)
    {
        $sort   = 1;
        $result = [];
        foreach ($data as $item) {
            $result[] = [
                'id'   => (int)$item['id'],
                't_parent_id'  => (int)$pid,
                'sort' => $sort,
            ];
            if (isset($item['children'])) {
                $result = array_merge($result, $this->parseNode($item['children'], $item['id']));
            }
            $sort ++;
        }
        return $result;
    }


    protected function updateBatch($tableName = "", $multipleData = array()){

        if( $tableName && !empty($multipleData) ) {
            // column or fields to update
            $updateColumn = array_keys($multipleData[0]);
            $referenceColumn = $updateColumn[0]; //e.g id
            unset($updateColumn[0]);
            $whereIn = "";

            $q = "UPDATE ".$tableName." SET ";
            foreach ( $updateColumn as $uColumn ) {
                $q .=  $uColumn." = CASE ";

                foreach( $multipleData as $data ) {
                    $q .= "WHEN ".$referenceColumn." = ".$data[$referenceColumn]." THEN '".$data[$uColumn]."' ";
                }
                $q .= "ELSE ".$uColumn." END, ";
            }
            foreach( $multipleData as $data ) {
                $whereIn .= "'".$data[$referenceColumn]."', ";
            }
            $q = rtrim($q, ", ")." WHERE ".$referenceColumn." IN (".  rtrim($whereIn, ', ').")";
            // Update
            return Db::connect(session('db-config_' . session("school_id")))->execute(DB::raw($q));
        } else {
            return false;
        }
    }


    /**
     * 推送消息配置
     * @param string $type
     * @param string $time
     * @param $cron
     * @param $title
     * @param string $id
     * @return mixed
     */
    protected function taskCalendar($type = "add", $time = "", $cron, $title, $id = '')
    {
        switch ($type) {
            case "add":
                $taskService = config("api.taskAddTest");
                $taskService['body']['cronJobDesc'] = $title;
                $taskService['body']['cronJobExp'] = $cron;
                $taskService['body']['cronJobTimeShow'] = $time;
                break;
            case "edit":
                $taskService = config("api.taskEditTest");
                $taskService['url'] = sprintf($taskService['url'], $id);
                break;
            case "delete":
                $taskService = config("api.taskDeleteTest");
                $taskService['url'] = sprintf($taskService['url'], $id);
                break;
        }
        $taskService['body']['schoolId'] = session("school_id");
        $taskService['body'] = json_encode($taskService['body'], 320);
        $taskHeader = ['Content-Type:application/json'];
        $task = $this->curlRequest($taskService['url'], $taskService['method'], $taskHeader, $taskService['body']);
        if (!isset($task['error_code']) && $task['error_code'] !== 1000) {
            Log::error("taskCalendar---error--- : ". $task);
            exit($this->fetch('./500',[
                'msg' => '编辑推送内容失败'
            ]));
        } else {
            if (isset($task['extra']))
                return $task['extra'];
            else
                return true;
        }
    }
}
