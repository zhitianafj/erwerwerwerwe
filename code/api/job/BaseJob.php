<?php

namespace app\api\job;

use think\queue\Job;


/**
 * BaseJob 基类
 */
class BaseJob
{

    public function failed($data){
        // 记录日志
    }

}