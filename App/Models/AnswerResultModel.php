<?php

namespace App\Models;

use EasySwoole\ORM\AbstractModel;

class AnswerResultModel extends AbstractModel
{
    protected $tableName = 'bf_answer_result';
    protected $autoTimeStamp = false;

    public static function getImg(int $score): string
    {
        $cacheKey = 'laobingzhishi-answer-result-cache';
        $redis    = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        $data     = $redis->get($cacheKey);
        if ($data) {
            $data = json_decode($data, true);
        } else {
            $data = self::create()->field(['low_score', 'high_score', 'img'])
                ->where(['status' => 1])
                ->all()->toArray();
            $redis->setex($cacheKey, 86400, json_encode($data));
        }
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);

        $img = '';
        foreach ($data as $val) {
            if (($val['low_score'] <= $score && $val['high_score'] >= $score) || ($val['low_score'] >= $score && $val['high_score'] <= $score)) {
                $img = $val['img'];
                break;
            }
        }
        return $img;
    }
}