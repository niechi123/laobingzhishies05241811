<?php

namespace App\HttpController;

use App\Helper\FuncHelper;
use App\Models\AnswerBadgeModel;
use App\Models\AnswerResultModel;
use App\Models\UserBadgeModel;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;
use EasySwoole\Redis\Redis;
use hxhzyh\jwtAuth\Jwt;

class Test extends Base
{
    public function test()
    {
        $user_id    = 1;
        $count      = 40;
        $score      = 90;
        $time       = time();
        $haveBadge  = UserBadgeModel::create()->where(['user_id' => $user_id])->column('badge_id');
        $haveBadge  = $haveBadge ?: [];
        $typeData   = AnswerBadgeModel::getAll();
        $insertData = [];
        foreach (AnswerBadgeModel::getAll() as $val) {
            if (in_array($val['id'], $haveBadge)) continue;
            if ($val['type_id'] == 1) {
                if ($count < $val['count']) continue;
            } else {
                if ($score < $val['count']) continue;
            }
            $insertData[] = [
                'user_id'     => $user_id,
                'badge_id'    => $val['id'],
                'create_time' => $time,
            ];
        }
        $res = false;
        if ($insertData) {
            $res = DbManager::getInstance()->query((new QueryBuilder())->insert('bf_user_badge', $insertData));
        }
        var_dump($res);
        return $this->writeJson(200, '', [
            'badge_data'  => $typeData,
            'have_badge'  => $haveBadge,
            'insert_data' => $insertData,
            'res'         => $res
        ]);
        $score = $this->request()->getRequestParam('score');
        $res   = AnswerResultModel::getImg($score);
        return $this->writeJson(200, '', $res ?? 1);
        $arr  = [
            'name' => 'jack',
            'age'  => 11
        ];
        $name = $arr['name'];
        TaskManager::getInstance()->async(function () use ($name) {
//            for ($i=0;$i<200000000;$i++){}
//            var_dump($name);
        });

        return $this->writeJson(200, $name);
        $redis = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        if ($data = $redis->get('asdfasdfasdf')) {
            \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
            return $this->writeJson(200, 'redis', unserialize($data));
        }
        $data = UserModel::create()->field(['id', 'name'])->where('id', '1', '>')->all();
        $redis->setEx('asdfasdfasdf', 5, serialize($data));//同步登录处的时间和key
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
        return $this->writeJson(200, 'database', $data);


        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJleHAiOm51bGwsInN1YiI6bnVsbCwibmJmIjoxNjA5OTk4NDI3LCJhdWQiOiJCM2RmUFZnVEFTSUZOMUF5Vno5YlBGWWhCVFZUTkZCeERETUdFQVZsQUR3Q2NnZGxXeFklM0QiLCJpYXQiOjE2MDk5OTg0MjcsImp0aSI6IlpkMDQ5MTNCTlAiLCJpc3MiOiJzdGFyIiwic3RhdHVzIjoxLCJkYXRhIjpudWxsfQ.Q_3aW71h91LcOs7Pm1hqXvsXMxITIbgM4X0DIULCado';
        $obj   = Jwt::getInstance()->setGuard('admin')->setSecretKey(Config::getInstance()->getConf('secret'))->decode($token);
        return $this->writeJson(200, '', ['status' => $obj->getStatus(), 'aud' => $obj->getAud()]);
        $data = ['user_id' => 100];
        $obj  = Jwt::getInstance()->setGuard('admin')->setSecretKey(Config::getInstance()->getConf('secret'))->publish();
        $obj->setAlg('HMACSHA256');
//        $obj->setExp(7200+time());//暂时设定过期时间俩小时,后期可修改为永久，通过redis判断过期
        $obj->setAud($data);
        $token = $obj->getToken();
        return $this->writeJson(200, '', ['token' => $token, 'secret' => Config::getInstance()->getConf('secret')]);
    }
}