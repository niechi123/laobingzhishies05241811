<?php
namespace App\Models;

use EasySwoole\ORM\AbstractModel;

class AnswerBadgeModel extends AbstractModel
{
    protected $tableName = 'bf_answer_badge';
    protected $autoTimeStamp = false;

   /* public static function getTypeAll()
    {
        $cacheKey = 'laobingzhishi-answer-badge-type-cache';
        $redis    = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        $data     = $redis->get($cacheKey);
        if ($data) {
            $data = json_decode($data, true);
        } else{
            $data = [];
            $allData = self::getAll();
            foreach ($allData as $val)
                if($val['type_id'] == 1)
                    $data['count'][] = $val;
                else
                    $data['score'][] = $val;
            $redis->setex($cacheKey, 86400, json_encode($data));
        }
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);

        return $data;
    }*/

    /**
     * 获取所有数据
     * @return array
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public static function getAll()
    {
        $cacheKey = 'laobingzhishi-answer-badge-cache';
        $redis    = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        $data     = $redis->get($cacheKey);
        if (!$data) {
            $data = self::create()
                ->field(['id', 'type_id', 'count', 'have_img', 'no_have_img', 'notes'])
                ->where(['status' => 1])
                ->order('sort', 'desc')
                ->all()->toArray();
            $redis->setex($cacheKey, 86400, json_encode($data));
        } else {
            $data = json_decode($data, true);
        }
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
        return $data;
    }

}