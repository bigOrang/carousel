<?php
// +----------------------------------------------------------------------
// | 海豚PHP框架 [ DolphinPHP ]
// +----------------------------------------------------------------------
// | 版权所有 2016~2017 河源市卓锐科技有限公司 [ http://www.zrthink.com ]
// +----------------------------------------------------------------------
// | 官方网站: http://dolphinphp.com
// +----------------------------------------------------------------------
// | 开源协议 ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------

namespace app\admin\validate;

use think\Validate;

/**
 * 客服验证器
 * @package app\cms\validate
 * @author 蔡伟明 <314013107@qq.com>
 */
class DeviceVali extends Validate
{
    // 定义验证规则
    protected $rule = [
        "dev_name|设备名称"      => "require|max:55|min:2",
        "manager|管理员"       => "require",
        "status|是否启用"       => "require",
        "dev_type|设备类型"       => "require",
        "dev_code|设备MAC地址"       => "require",
        "location|设备地址"       => "require",
    ];

    protected $message  =   [
        'dev_name.require' => '设备名称不能为空',
        'dev_name.max' => '设备名称不能超过255个字符',
        'dev_name.min' => '设备名称不能少于2个字符',
        'manager.require'  => '管理员不能为空',
        'status.require'  => '是否启用不能为空',
        'dev_code.require'  => '设备MAC地址不能为空',
        'dev_type.require'  => '设备类型不能为空',
        'location.require'  => '设备地址不能为空',
    ];
}
