<?php
namespace App\Models;

use EasySwoole\ORM\AbstractModel;

class QuestionModel extends AbstractModel
{
    protected $tableName = 'bf_question';
    protected $autoTimeStamp = false;

    public static function getTypeAll()
    {
        $cacheKey = 'laobingzhishi-question-all-cache';
        $redis    = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        $typeData     = $redis->get($cacheKey);
        if($typeData) {
            $typeData = json_decode($typeData, true);
        } else {
            $data = self::create()
                ->field(['id', 'type_id', 'mode_id', 'title', 'option_a', 'option_b', 'option_c', 'option_d', 'answer', 'source', 'analysis'])
                ->where(['status' => 1])
                ->all()->toArray();

            $typeData = [];
            foreach ($data as $val) {
                $typeData[$val['type_id'] . '-' . $val['mode_id']][] = $val;
            }
            $redis->setex($cacheKey, 86400, json_encode($typeData));
        }
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);

        return $typeData;
    }
}