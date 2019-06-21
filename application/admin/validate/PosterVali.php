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
class PosterVali extends Validate
{
    // 定义验证规则
    protected $rule = [
        "title|事件标题"      => "require|max:255|min:2",
        "on_date|媒体播放开始时间"       => "require",
        "off_date|媒体播放结束时间"       => "require",
        "dest_dev_type|设备类型"       => "require",
        "dest_unit_id|推送范围"       => "require",
    ];

    protected $message  =   [
        'title.require' => '标题描述不能为空',
        'title.max' => '事件标题不能超过255个字符',
        'title.min' => '事件标题不能少于2个字符',
        'on_date.require'  => '媒体播放开始时间不能为空',
        'off_date.require'  => '媒体播放结束时间不能为空',
        'dest_dev_type.require'  => '设备类型不能为空',
        'dest_unit_id.require'  => '推送范围不能为空',
    ];
}
