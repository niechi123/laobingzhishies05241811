<?php

namespace App\HttpController;

use App\Helper\UserAuth;
use App\Models\{AnswerBadgeModel,
    AnswerRecordModel,
    AnswerResultModel,
    QuestionCountModel,
    QuestionModel,
    QuestionRuleModel,
    UserBadgeModel,
    UserModel};
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;
use EasySwoole\Validate\Validate;

class Question extends Base
{
    protected function onRequest(?string $action): ?bool
    {
        if (parent::onRequest($action) === false) return false;
        if (!UserAuth::getInstance()->check()) {
            $this->writeJson('1000', '您还未授权微信信息');
            return false;
        }
        /*if (!UserAuth::getInstance()->getUser('head_img')) {
            $this->writeJson('350', '您还未授权微信信息');
            return false;
        }*/
        return true;
    }

    //不用了
    public function question_activity_content()
    {
        return $this->writeJson(200, '', QuestionRuleModel::question_activity_content());
    }

    public function question_config()
    {
//        $rule = QuestionRuleModel::question_config();
//        if (isset($rule['type_count'])) {
//            $rule['type_count'] = array_sum(explode(',', $rule['type_count']));
//        }
        return $this->writeJson(200, '', QuestionRuleModel::question_config());
    }

    public function question_list()
    {
        $questionData  = QuestionModel::getTypeAll();
        $questionCount = QuestionCountModel::getAll();
        $xuanze        = $two_one = $four_one = [];

        foreach ($questionCount as $val) {
            $oneData = $questionData[$val['type_id'] . '-' . $val['mode_id']] ?? [];
            mt_srand();
            shuffle($oneData);
            $oneRes = [];
            foreach ($oneData as $key => $item) {
                if ($key >= $val['count']) break;
                $oneRes[] = $item;
            }
            if ($val['type_id'] == 1) {
                $xuanze = array_merge($xuanze, $oneRes);
            } elseif ($val['type_id'] == 2) {
                $two_one = array_merge($two_one, $oneRes);
            } else {
                $four_one = array_merge($four_one, $oneRes);
            }
        }
        $this->handle_question($xuanze);
        $this->handle_question($two_one);
        $this->handle_question($four_one);
//        shuffle($xuanze);
//        shuffle($two_one);
//        shuffle($four_one);
        return $this->writeJson(200, '', array_merge($four_one, $two_one, $xuanze));
//        return $this->writeJson(200, '', [
//            'question_data'  => $questionData,
//            'question_count' => $questionCount,
//            'xuanze'         => $xuanze,
//            'one_two'        => $two_one,
//            'four_one'       => $four_one
//        ]);
    }

    /**
     * 不用了，出题规则变了
     * @return bool
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function question_list_back()
    {
        die;
        $questionConfig = QuestionRuleModel::question_config();
        $questionCount  = explode(',', $questionConfig['type_count']);
        $xuanze         = $two_one = $four_one = [];
        $query          = QuestionModel::create()->field(['id', 'type_id', 'title', 'option_a', 'option_b', 'option_c', 'option_d', 'answer', 'source', 'analysis'])
            ->where(['status' => 1])->order('rand()');
        $two_one_query  = clone $query;
        $four_one_query = clone $query;
        if ($questionCount[0] ?? 0) {
            $xuanze = $query->where(['type_id' => 1])
                ->limit($questionCount[0])
                ->all()->toArray();
        }
        if ($questionCount[1] ?? 0) {
            $two_one = $two_one_query->where(['type_id' => 2])
                ->limit($questionCount[1])
                ->all()->toArray();
            unset($two_one_query);
        }
        if ($questionCount[2] ?? 0) {
            $four_one = $four_one_query->where(['type_id' => 3])
                ->limit($questionCount[2])
                ->all()->toArray();
            unset($four_one_query);
        }
        $this->handle_question($xuanze);
        $this->handle_question($two_one);
        $this->handle_question($four_one);

//        $ids = array_merge(
//            array_column($xuanze, 'id'),
//            array_column($two_one, 'id'),
//            array_column($four_one, 'id')
//        );
//        if($ids) {
//            TaskManager::getInstance()->async(function ()use($ids){
//                QuestionModel::create()->where('id', $ids, 'in')->update(['reply_count' => QueryBuilder::inc(1)]);
//            });
//        }

        return $this->writeJson(200, '',
//             'xuanze'   => $xuanze,
//             'two_one'  => $two_one,
//             'four_one' => $four_one
            array_merge($four_one, $two_one, $xuanze)
        );
    }

    private function handle_question(&$arr)
    {
        foreach ($arr as $key => $val) {
            $option = [$val['option_a'], $val['option_b']];
            if ($val['option_c']) $option[] = $val['option_c'];
            if ($val['option_d']) $option[] = $val['option_d'];
            $arr[$key]['option'] = $option;
            unset($arr[$key]['option_a'], $arr[$key]['option_b'], $arr[$key]['option_c'], $arr[$key]['option_d']);
            $arr[$key]['answer']--;
        }
        mt_srand();
        shuffle($arr);
    }

    public function answer_submit()
    {
        $time           = time();
        $questionConfig = QuestionRuleModel::question_config();
        $user_id        = UserAuth::getInstance()->getUser('id');
//        if($userData->scores >= $questionConfig['full_marks']) return $this->writeJson(101, '您的分数已满，不可再次提交');
        $queryData = $this->request()->getRequestParam('right_ids', 'wrong_ids');
        if ((!$queryData['right_ids']) && (!$queryData['wrong_ids'])) return $this->writeJson(101, '提交内容不可为空');
        $userData = UserModel::create()->where(['id' => $user_id])->get();
        //判断每日限制次数
        $answer_count = AnswerRecordModel::create()->where(['user_id' => $userData->id])->where('create_time', strtotime(date('Y-m-d')), '>')->count();
        if ($answer_count >= $questionConfig['one_day_answer_count']) return $this->writeJson(102, '今日答题次数已满');

        $oneScores = count($queryData['right_ids']) * $questionConfig['one_fraction'];

//        if((count($queryData['right_ids']) + count($queryData['wrong_ids'])) != count(explode(',', $questionConfig['type_count']))) return $this->writeJson(102, '请提交所有题目答题结果');
        $oldScores = $userData->scores;
        $userData->answer_count++;

        DbManager::getInstance()->startTransaction();
        if ($queryData['right_ids']) {
//            if ($userData->scores < $questionConfig['full_marks']) {
//                $userData->scores         = ($total_scores > $questionConfig['full_marks']) ? $questionConfig['full_marks'] : $total_scores;
            $userData->scores         = $oneScores + $userData->scores;
            $userData->up_scores_time = $time;
//            }

            $res = QuestionModel::create()->where('id', $queryData['right_ids'], 'in')->update(['reply_count' => QueryBuilder::inc(1), 'right_count' => QueryBuilder::inc(1)]);
            if (!$res) {
                DbManager::getInstance()->rollback();
                return $this->writeJson(301, '网络错误1');
            }
        }

        if ($queryData['wrong_ids']) {
            $res = QuestionModel::create()->where('id', $queryData['wrong_ids'], 'in')->update(['reply_count' => QueryBuilder::inc(1), 'wrong_count' => QueryBuilder::inc(1)]);
            if (!$res) {
                DbManager::getInstance()->rollback();
                return $this->writeJson(302, '网络错误2');
            }
        }
        $res = $userData->update();
        if (!$res) {
            DbManager::getInstance()->rollback();
            return $this->writeJson(300, '网络错误');
        }

        $res = AnswerRecordModel::create([
            'user_id'     => UserAuth::getInstance()->getUser('id'),
            'scores'      => $oneScores,
            'right_count' => count($queryData['right_ids']),
            'wrong_count' => count($queryData['wrong_ids']),
//            'status'      => $oldScores < $questionConfig['full_marks'] ? 1 : 2,
            'status'      => 1,
            'create_time' => $time,
        ])->save();
        if (!$res) {
            DbManager::getInstance()->rollback();
            return $this->writeJson(303, '网络错误3');
        }


        $haveBadge       = UserBadgeModel::create()->where(['user_id' => $user_id])->column('badge_id');
        $haveBadge       = $haveBadge ?: [];
        $insertData      = $getNewBadgeData = [];
        $answerBadgeData = AnswerBadgeModel::getAll();
        foreach ($answerBadgeData as $val) {
            if (in_array($val['id'], $haveBadge)) continue;
            if ($val['type_id'] == 1) {
                if ($userData->answer_count < $val['count']) continue;
            } else {
                if ($oneScores < $val['count']) continue;
            }
            $insertData[]      = [
                'user_id'     => $user_id,
                'badge_id'    => $val['id'],
                'create_time' => $time,
            ];
            $getNewBadgeData[] = [
                'have_img' => $val['have_img'],
                'notes'    => $val['notes'],
            ];
        }
        $redis = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        if ($insertData) {
            $redis->del('LAOBINGZHISHI-USER-BADGE-LIST-' . $user_id);
            DbManager::getInstance()->query((new QueryBuilder())->insert('bf_user_badge', $insertData));
        }
        DbManager::getInstance()->commit();
        if($redis->exists('LAOBINGZHISHI-ANSWER-RECORD-TOTAL-COUNT'))
            $redis->incr('LAOBINGZHISHI-ANSWER-RECORD-TOTAL-COUNT');
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);

        return $this->writeJson(200, '提交成功', [
            'scores'    => $oneScores,
            'score_img' => AnswerResultModel::getImg($oneScores),
            'new_badge' => $getNewBadgeData,
//            'rank'      => $this->my_rank($userData->toArray()),
        ]);
    }

    public function ranking()
    {
        $userData   = UserAuth::getInstance()->getUser();
        $redisKey   = 'LAOBINGZHISHI-SCORES-RANKING';
        $redis      = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        $returnData = $redis->get($redisKey);
        if ($returnData) {
            $returnData = json_decode($returnData, true);
        } else {
            $redisKey1 = 'LAOBINGZHISHI-RANKING-LAST-COURSE-1';
            $lastScores = $redis->get($redisKey1);
            $queryBuild = new QueryBuilder();
            $queryBuild->raw("SELECT * FROM (SELECT scores,count(*) as cou FROM `bf_user` where scores >= ?  GROUP BY scores ORDER BY scores desc) as a LIMIT 0,1600", [$lastScores ?: 0]);
            $ranking = DbManager::getInstance()->query($queryBuild, true, 'default')->getResult();
            unset($queryBuild);
            $endRank = end($ranking);
            $redis->setex($redisKey1, 86400, $endRank['scores'] ?? 0);
            $myRank = 0;
            foreach ($ranking as $key => $val) {
                if ($val['scores'] == $userData['scores']) {
                    $myRank = ++$key;
                    break;
                }
            }
            $returnData = [
                'ranking'   => $ranking,
                'my_rank'   => $myRank,
//                'my_scores' => $userData['scores']
            ];
            $redis->setex($redisKey, 300, json_encode($returnData));
        }
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
        $returnData['my_scores'] = $userData['scores'];

        return $this->writeJson(200, '', $returnData);

//        if (!$myRank) {
//            $queryBuild = new QueryBuilder();
//            $queryBuild->raw("select count(*) as cou from  (SELECT * FROM bf_user WHERE scores > ? GROUP BY scores) as a", [$userData['scores']]);
//            $_myRank = DbManager::getInstance()->query($queryBuild, true, 'default')->getResultOne();
//            unset($queryBuild);
//            $myRank = isset($_myRank['cou']) ? (++$_myRank['cou']) : 0;
//        }
  //      return $this->writeJson(200, '', ['ranking' => $ranking, 'my_rank' => $myRank, 'my_scores' => $userData['scores']]);

        /* $userData = UserAuth::getInstance()->getUser();
 //        $userData = UserModel::create()->where(['id' => 5])->get()->toArray();
         $ranking = UserModel::create()->where(['status' => 1])
             ->where('scores', 0, '>')
             ->field(['id', 'nick_name', 'head_img', 'scores'])
             ->order('scores', 'desc')
             ->order('up_scores_time', 'asc')
             ->order('answer_count', 'asc')
             ->order('id', 'asc')
             ->limit(50)
             ->all()->toArray();
         $myRank  = 0;
         foreach ($ranking as $key => $val)
             if ($val['id'] == $userData['id'])
                 $myRank = ++$key;

         if ((!$myRank) && $userData['scores']) {
             $myRank = $this->my_rank($userData);
 //            if ($myRank <= 50) $myRank = 51;//防止程序出错
         }

         return $this->writeJson(200, '', [
             'ranking'   => $ranking,
             'my_rank'   => $myRank,
             'my_scores' => $userData['scores']
         ]);*/
    }

    public function area_rank()
    {
        $redisKey   = 'LAOBINGZHISHI-SCORES-AREA-RANKING';
        $redis      = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        $returnData = $redis->get($redisKey);
        if ($returnData) {
            $returnData = json_decode($returnData, true);
        } else {
            $queryBuild1 = new QueryBuilder();
            $queryBuild2 = clone $queryBuild1;
            $queryBuild1->raw("SELECT province, count(*) as cou FROM `bf_user` WHERE scores > 0 and province != '' GROUP BY province ORDER BY cou desc");
            $queryBuild2->raw("SELECT province, sum(scores) as cou FROM `bf_user` WHERE scores > 0 and province != '' GROUP BY province ORDER BY cou desc");
            $totalPeople = DbManager::getInstance()->query($queryBuild1, true, 'default')->getResult();
            $totalScores = DbManager::getInstance()->query($queryBuild2, true, 'default')->getResult();

            $avgPeople   = array_column($totalPeople, 'cou', 'province');
            $avgScores   = array_column($totalScores, 'cou', 'province');
            $allProvince = array_column($totalPeople, 'province');
            $avg_scores  = [];
            foreach ($allProvince as $val) {
                if (!(isset($avgPeople[$val]) && isset($avgScores[$val]))) continue;
                $avg_scores[] = [
                    'province' => $val,
                    'scores'   => round($avgScores[$val] / $avgPeople[$val])
                ];
            }
            array_multisort(array_column($avg_scores, 'scores'), SORT_DESC, $avg_scores);
            $returnData = [
                'total_people' => $totalPeople,
                'total_scores' => $totalScores,
                'avg_scores'   => $avg_scores
            ];
            $redis->setex($redisKey, 300, json_encode($returnData));
        }
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);

        return $this->writeJson(200, '', $returnData);
    }

    private function my_rank($userData)
    {
        die;//规则又改了
        $queryBuild = new QueryBuilder();
        $queryBuild->raw("SELECT count(*) as cou FROM `bf_user` WHERE `status` = 1  and ((scores > ?) or (scores = ? and up_scores_time < ?) or (scores = ? and up_scores_time = ? and answer_count < ?))", [$userData['scores'], $userData['scores'], $userData['up_scores_time'], $userData['scores'], $userData['up_scores_time'], $userData['answer_count']]);
        $data  = DbManager::getInstance()->query($queryBuild, true, 'default')->getResultOne();
        $count = $data['cou'] ?? 0;//默认个值，就取值数量吧

        //再查询 所有同等条件的人数
        $alikeData = UserModel::create()
            ->field(['id'])
            ->where(['status' => 1, 'scores' => $userData['scores'], 'up_scores_time' => $userData['up_scores_time'], 'answer_count' => $userData['answer_count']])
            ->order('id', 'asc')
            ->all()->toArray();
        $alikeCou  = 0;
        foreach ($alikeData as $key => $val)
            if ($val['id'] == $userData['id'])
                $alikeCou = ++$key;
        $myRank = $count + $alikeCou;
        return $myRank;
    }

    protected function validateRule(?string $action): ?Validate
    {
        $validate = null;
        switch ($action) {
            case 'answer_submit':
            {
                $validate = new Validate();
                $validate->addColumn('right_ids', '答对题数')->isArray()->lengthMax(1000);
                $validate->addColumn('wrong_ids', '答对题数')->isArray()->lengthMax(1000);
                break;
            }
        }
        return $validate;
    }

    /**
     * 徽章列表
     * @return bool
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function badge_list()
    {
        $user_id  = UserAuth::getInstance()->getUser('id');
        $redisKey = 'LAOBINGZHISHI-USER-BADGE-LIST-' . $user_id;
        $redis    = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        $myBadge  = $redis->get($redisKey);
        if ($myBadge) {
            $myBadge = json_decode($myBadge, true);
        } else {
            $myBadge = UserBadgeModel::create()->where(['user_id' => $user_id])->column('badge_id');
            $myBadge = $myBadge ?: [];
            $redis->setex($redisKey, 3600, json_encode($myBadge));
        }
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);

//        $myBadge  = UserBadgeModel::create()->where(['user_id' => $user_id])->column('badge_id');
        $allBadge = AnswerBadgeModel::getAll();
        $allBadge = array_map(function ($item) use ($myBadge) {
            $item['is_have'] = in_array($item['id'], $myBadge);
            return $item;
        }, $allBadge);
        return $this->writeJson(200, '', [
            'badge_list'  => $allBadge,
            'badge_count' => sizeof($myBadge)
        ]);
    }
}