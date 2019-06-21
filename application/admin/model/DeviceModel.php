<?php
/**
 * Created by PhpStorm.
 * User: id_orange
 * Date: 2019/2/15
 * Time: 13:43
 */

namespace app\admin\model;


use think\Model;
use think\model\concern\SoftDelete;

class DeviceModel extends BaseModel
{
//    use SoftDelete;
    protected $table = "t_media_device";
//    protected $dateFormat = 'Y-m-d H:i:s';
//    protected $autoWriteTimestamp = 'datetime';
//    protected $deleteTime = 'deleted_at';

}