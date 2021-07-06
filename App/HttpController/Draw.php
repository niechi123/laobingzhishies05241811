<?php


namespace App\HttpController;


use App\Models\LuckDrawModel;
use App\Models\UserModel;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;
use EasySwoole\Validate\Validate;

class Draw extends Base
{
    public function luck_draw()
    {
        $data = LuckDrawModel::create()
            ->field(['id', 'draw_name', 'count', 'prize_name', 'prize_image', 'prize_content', 'is_draw'])
            ->all()->toArray();
        return $this->writeJson(200, '', $data);
    }

    public function draw_user()
    {
        $id       = $this->request()->getRequestParam('id');
        $drawData = LuckDrawModel::create()->where(['id' => $id])->get();
        if (!$drawData) return $this->writeJson(101, '奖项不存在');
        $returnData = [
            'user_list' => [],
            'draw_list' => []
        ];
        if ($drawData['is_draw']) {
            $returnData['draw_list'] = UserModel::create()
                ->where(['draw_id' => $id, 'status' => 1])
                ->field(['id', 'name', 'phone'])
                ->limit($drawData['count'])
                ->all()->toArray();
            $this->handle_phone($returnData['draw_list']);
        } else {
            $score                   = [$drawData['score_start'], $drawData['score_end']];
            $returnData['user_list'] = UserModel::create()
                ->where(['status' => 1, 'draw_id' => 0])
                ->where('scores', [min($score), max($score)], 'between')
                ->where('name', '', '<>')
                ->field(['id', 'name', 'phone'])
                ->all()->toArray();
            $this->handle_phone($returnData['user_list']);
        }
        return $this->writeJson(200, '', $returnData);
    }

    public function win_draw()
    {
        $queryData = $this->request()->getRequestParam('draw_id', 'user_ids');
        $drawData  = LuckDrawModel::create()->where(['id' => $queryData['draw_id']])->get();
        if (!$drawData) return $this->writeJson(102, '奖项不存在');
        if ($drawData['is_draw']) return $this->writeJson(103, '抽奖已经完成');
        $userIDs = explode(',', $queryData['user_ids']);
        if (sizeof($userIDs) > $drawData['count']) return $this->writeJson(104, '中奖人数大于抽奖人数');
        $score     = [$drawData['score_start'], $drawData['score_end']];
        $userCount = UserModel::create()
            ->where('id', $userIDs, 'in')
            ->where('scores', [min($score), max($score)], 'between')
            ->where('name', '', '<>')
            ->where(['draw_id' => 0])
            ->count();
        if(sizeof($userIDs) != $userCount) return $this->writeJson(105, '中奖用户信息不匹配');

        $queryBuild = new QueryBuilder();
        $queryBuild->raw('update bf_user set draw_id = ' . $queryData['draw_id'] . ' where id in (' . $queryData['user_ids'] . ')');
        $result = DbManager::getInstance()->query($queryBuild, true, 'default')->getResult();
        unset($queryBuild);

        $drawData->is_draw = 1;
        $drawData->update();

        return $this->writeJson(200, '抽奖成功');
    }

    private function handle_phone(array &$data): void
    {
        foreach ($data as $key => $val) {
            if (!isset($val['phone'])) continue;
            $data[$key]['phone'] = substr($val['phone'], 0, 3) . '****' . substr($val['phone'], -4);
        }
    }

    protected function validateRule(?string $action): ?Validate
    {
        $validate = null;
        switch ($action) {
            case 'draw_user':
            {
                $validate = new Validate();
                $validate->addColumn('id', 'ID')->required()->integer();
                break;
            }
            case 'win_draw':
            {
                $validate = new Validate();
                $validate->addColumn('user_ids', '中奖人')->required();
                $validate->addColumn('draw_id', '奖项ID')->required()->integer();
                break;
            }
        }
        return $validate;
    }
}