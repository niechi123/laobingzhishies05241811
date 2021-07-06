<?php
namespace App\Models;

use EasySwoole\ORM\AbstractModel;

class QuestionCountModel extends AbstractModel
{
    protected $tableName = 'bf_question_count';
    protected $autoTimeStamp = false;

    public static function getAll()
    {
        $cacheKey = 'laobingzhishi-question-count-all-cache';
        $redis    = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        $data     = $redis->get($cacheKey);
        if($data) {
            $data = json_decode($data, true);
        } else {
            $data = self::create()
                ->field([ 'type_id', 'mode_id', 'count'])
                ->where(['status' => 1])
                ->all()->toArray();
            $redis->setex($cacheKey, 86400, json_encode($data));
        }
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
        return $data;
    }
}