<?php
namespace App\Models;

use EasySwoole\ORM\AbstractModel;

class QuestionRuleModel extends AbstractModel
{
    protected $tableName = 'bf_question_rule';
    protected $autoTimeStamp = false;

    //不用了
    public static function question_activity_content()
    {
        $cacheKey = 'laobingzhishi-question-activity-content';
        $redis    = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        if($data = $redis->get($cacheKey)) {
            \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
            return $data;
        }
        $data = self::create()->where(['name' => 'activity_content'])->scalar('value');
        $redis->setex($cacheKey, 86400, $data);
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
        return $data;
    }

    public static function question_config()
    {
        $cacheKey = 'laobingzhishi-question-config';
        $redis    = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        if($data = $redis->get($cacheKey)) {
            \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
            return json_decode($data, true);
        }
        $data = self::create()->where('name', ['answer_time', 'one_fraction', 'one_day_answer_count', 'question_rule', 'activity_content'], 'in')->field(['name', 'value'])->all()->toArray();
        $data = array_column($data, 'value', 'name');
        $redis->setex($cacheKey, 86400, json_encode($data));
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
        return $data;
    }
}