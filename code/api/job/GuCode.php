<?php

namespace app\api\job;

use think\queue\Job;
use think\Db;


class GuCode extends BaseJob
{

    public function autoCode(Job $job, $data){
        try {
            $data = $data['data'];

            \think\Db::transaction(function () use ($data) {
               Db::name('test_queue')->insert($data);
            });

            // 删除 job
            $job->delete();
        } catch (\Exception $e) {
            // 队列执行失败
            \think\Log::write('queue-' . get_class() . '-autoCode' . '：执行失败，错误信息：' . $e->getMessage());
        }
    }

}