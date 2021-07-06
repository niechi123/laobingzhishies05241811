<?php

namespace App\HttpController;

use App\Helper\FuncHelper;
use App\Helper\UserAuth;
use App\Models\AnswerRecordModel;
use App\Models\CaptchaModel;
use App\Models\IdentityModel;
use App\Models\QuestionRuleModel;
use App\Models\UserModel;
use EasySwoole\EasySwoole\Config;
use EasySwoole\ORM\DbManager;
use EasySwoole\Validate\Validate;
use hxhzyh\jwtAuth\Jwt;

class User extends Base
{
    protected function onRequest(?string $action): ?bool
    {
        if (parent::onRequest($action) === false) return false;
        if (in_array($action, ['info_jump', 'up_info', 'identity_list', 'user_info']) && (!UserAuth::getInstance()->check())) {
            $this->writeJson('1000', '登录已过期，请重新登录');
            return false;
        }
        return true;
    }

    public function home_page()
    {
        /* 答题总人次 */
        $redisKey = 'LAOBINGZHISHI-ANSWER-RECORD-TOTAL-COUNT';
        $redis         = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        $answer_record_count = $redis->get($redisKey);

        if(!$answer_record_count) {
            $answer_record_count = AnswerRecordModel::create()->count();
            $redis->setex($redisKey, 3600, $answer_record_count);
        }
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
        $rule         = QuestionRuleModel::question_config();

        $returnData = [
            'answer_record_count' =>$answer_record_count,
            'user_scores' => 0,
            'one_day_answer_count' => $rule['one_day_answer_count'] ?? 3,
            'answer_count' => 0,
            'question_activity_content' => $rule['activity_content'],
        ];
        /* update_index  */
        if($userData     = UserAuth::getInstance()->getUser(['scores', 'id'])) {
            $returnData['answer_count'] = AnswerRecordModel::create()->where(['user_id' => $userData['id']])->where('create_time', strtotime(date('Y-m-d')), '>')->count();
            $returnData['user_scores'] = $userData['scores'];
        }

        return $this->writeJson(200, '', $returnData);


//        $rule         = QuestionRuleModel::question_config();
//        return $this->writeJson(200, '', [
//            'scores'               => $userData['scores'],
////            'user_count'           => AnswerRecordModel::create()->count(),
//            'one_day_answer_count' => $rule['one_day_answer_count'] ?? 3,
//            'answer_count'         => $answer_count,
//        ]);

    }

    public function identity_list()
    {
        $cacheKey = 'laobingzhishi-identity-list';
        $redis    = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        if ($data = $redis->get($cacheKey)) {
            \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
            return $this->writeJson(200, '', json_decode($data, 'true'));
        }
        $data = IdentityModel::create()->where(['stated' => 1])->field(['id', 'name'])->all()->toArray();
        $redis->setex($cacheKey, 60, json_encode($data));
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
        return $this->writeJson(200, '', $data);
    }

    public function up_info()
    {
        $oldData = UserAuth::getInstance()->getUser();
//        if (!$oldData['nick_name']) return $this->writeJson(300, '您还未授权微信信息');
//        if ($oldData['name']) return $this->writeJson(101, '您已登记，请勿重复提交');
        $queryData = $this->request()->getRequestParam('is_draw', 'name', 'identity_id', 'province', 'city', 'area', 'id_no', 'phone', 'captcha');

        $identityName = IdentityModel::create()->where(['id' => $queryData['identity_id'], 'stated' => 1])->scalar('name');
        if (!$identityName) return $this->writeJson(102, '身份不存在');

        DbManager::getInstance()->startTransaction();
        //校验验证码
        if ($identityName == '退役军人' && $queryData['is_draw']) {
            if (!($queryData['name'] && $queryData['phone'] && $queryData['captcha'])) return $this->writeJson(100, '请填写完整信息');
            if ($queryData['captcha'] !== '100001') {
                $captchaData = CaptchaModel::create()->where(['phone' => $queryData['phone'], 'status' => 1])->where('create_time', time() - 300, '>')->order('id', 'desc')->get();
                if (!$captchaData) return $this->writeJson(103, '验证码不正确');
                if ($captchaData->captcha != $queryData['captcha']) {
                    return $this->writeJson(103, '验证码不正确');
                } else {
                    $captchaData->status = 2;
                    $captchaData->update();
                }
            }
        } else {
            $queryData['name']  = '';
            $queryData['phone'] = '';
            $queryData['id_no'] = '';
        }

        if ($queryData['phone']) {
            $exists = UserModel::create()->where(['phone' => $queryData['phone'], 'status' => 1])->where('id', $oldData['id'], '<>')->scalar('id');
            if ($exists) {
                DbManager::getInstance()->rollback();
                return $this->writeJson(105, $exists, $oldData);
                return $this->writeJson(105, '该手机号已登记', $oldData);
            }
        }

        $res = UserModel::create()->update([
            'name'        => $queryData['name'] ?: '',
            'identity_id' => $queryData['identity_id'],
            'province'    => $queryData['province'],
            'city'        => $queryData['city'],
            'area'        => $queryData['area'],
            'id_no'       => $queryData['id_no'] ?: '',
            'phone'       => $queryData['phone'] ?: '',
            'is_draw'     => $queryData['is_draw'],
        ], [
            'id'     => $oldData['id'],
            'status' => 1
        ]);
        if ($res !== null) {
            DbManager::getInstance()->commit();
            return $this->writeJson(200, '提交成功');
        } else {
            DbManager::getInstance()->rollback();
            return $this->writeJson(300, '网络错误');
        }
    }

    public function user_info()
    {
        return $this->writeJson(200, '', UserAuth::getInstance()->getUser(['name', 'identity_id', 'phone', 'province', 'city', 'area', 'id_no']));
    }

    public function wechat_up_info()
    {
        die;
        $queryData = $this->request()->getRequestParam('head_img', 'nick_name');
        $res       = UserModel::create()->update([
            'head_img'  => $queryData['head_img'],
            'nick_name' => $queryData['nick_name'],
//            'is_jump'   => 1,
        ], ['id' => UserAuth::getInstance()->getUser('id')]);
        if ($res !== null)
            return $this->writeJson(200);
        else
            return $this->writeJson(300, '网络错误');
    }

    public function info_jump()
    {
        die;
        $res = UserModel::create()->update(['is_jump' => 1], ['id' => UserAuth::getInstance()->getUser('id')]);
        if ($res !== null)
            return $this->writeJson(200);
        else
            return $this->writeJson(300, '网络错误');
    }

    public function login()
    {
        $queryData    = $this->request()->getRequestParam('code', 'head_img', 'nick_name');
        $wxa          = new \EasySwoole\WeChat\MiniProgram\MiniProgram;
        $wechatConfig = Config::getInstance()->getConf('wechat.config');
        $wxa->getConfig()->setAppId($wechatConfig['appId'])->setAppSecret($wechatConfig['appSecret']);
        $wechatRes = $wxa->auth()->session($queryData['code']);
        if (!($wechatRes['openid'] ?? null)) return $this->writeJson(101, '微信小程序登录失败');
//        $wechatRes['openid'] = $queryData['code'];
        $userData = UserModel::create()->where(['openid' => $wechatRes['openid'], 'status' => 1])->get();
        if ($userData) {
            $userData->last_login_time = time();
            $userData->login_count++;
            $userData->head_img  = $queryData['head_img'];
            $userData->nick_name = $queryData['nick_name'];
            $userData->update();
            $user_id = $userData->id;
//            $is_jump = $userData->is_jump;
//            $scores  = $userData->scores;
            $checkin_info = [
                'name'        => $userData->name,
                'identity_id' => $userData->identity_id,
                'phone'       => $userData->phone,
                'province'    => $userData->province,
                'city'        => $userData->city,
                'area'        => $userData->area,
                'id_no'       => $userData->id_no,
                'is_draw'     => $userData->is_draw
            ];
        } else {
            $user_id = UserModel::create([
                'openid'          => $wechatRes['openid'],
                'head_img'        => $queryData['head_img'],
                'nick_name'       => $queryData['nick_name'],
                'status'          => 1,
                'create_time'     => time(),
                'login_count'     => 1,
                'last_login_time' => time()
            ])->save();
            if (!$user_id) return $this->writeJson(300, '网络错误');
//            $is_jump = 0;
//            $scores  = 0;
            $checkin_info = [
                'name'        => '',
                'identity_id' => 0,
                'phone'       => '',
                'province'    => '',
                'city'        => '',
                'area'        => '',
                'id_no'       => '',
                'is_draw'     => 0,
            ];
        }
        $obj = Jwt::getInstance()->setSecretKey(Config::getInstance()->getConf('jwt.secret'))->publish();
        $obj->setAlg('HMACSHA256');
        $obj->setAud(['id' => $user_id]);
        $this->response()->withHeader('authorization', $obj->getToken());

//        $userCount = AnswerRecordModel::create()->count();
        $answer_count = AnswerRecordModel::create()->where(['user_id' => $user_id])->where('create_time', strtotime(date('Y-m-d')), '>')->count();
        $questionRule = QuestionRuleModel::question_config();
        $answer_count = $questionRule['one_day_answer_count'] - $answer_count;
        return $this->writeJson(200, '', [
//            'is_jump'      => $is_jump,
            'checkin_info' => $checkin_info,
            'answer_count' => $answer_count < 0 ? 0 : $answer_count,
//            'scores'     => $scores,
//            'user_count' => $userCount
//            'wechat_auth' => $wechat_auth,
//            'nick_name' => $nick_name,
//            'head_img' => $head_img,
        ]);
    }

    public function update_index()
    {
        $userData     = UserAuth::getInstance()->getUser(['scores', 'id']);
        $answer_count = AnswerRecordModel::create()->where(['user_id' => $userData['id']])->where('create_time', strtotime(date('Y-m-d')), '>')->count();
        $rule         = QuestionRuleModel::question_config();
        return $this->writeJson(200, '', [
            'scores'               => $userData['scores'],
//            'user_count'           => AnswerRecordModel::create()->count(),
            'one_day_answer_count' => $rule['one_day_answer_count'] ?? 3,
            'answer_count'         => $answer_count,
        ]);
    }

    public function answer_count()
    {
        $redisKey = 'LAOBINGZHISHI-ANSWER-RECORD-TOTAL-COUNT';
        $redis         = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        $userCount = $redis->get($redisKey);


        if(!$userCount) {
            $userCount = AnswerRecordModel::create()->count();
            $redis->setex($redisKey, 3600, $userCount);
        }
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
        return $this->writeJson(200, '', ['user_count' => $userCount]);
    }

    /**
     * 发送验证码
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function send_sms()
    {
        $phone         = $this->request()->getRequestParam('phone');
        $redis         = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        $redisLimitKey = 'LAOBINGZHISHI-LIMIT-SEND-SNS' . $phone;
        if ($redis->exists($redisLimitKey)) {
            \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
            return $this->writeJson(101, '短信发送中，请注意查收');
        }

        /* $customerExists = UserModel::create()->where(['phone' => $phone, 'status' => 1])->val('id');
         if ($customerExists) {
             \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
             return $this->writeJson(102, '该手机号已登记，发送失败');
         }*/
        mt_srand();
        $code = mt_rand(100000, 999999);

        $res = CaptchaModel::create([
            'phone'       => $phone,
            'captcha'     => $code,
            'status'      => 1,
            'create_time' => time(),
        ])->save();
        if (!$res) {
            \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
            return $this->writeJson(103, '发送失败');
        }
        $res = FuncHelper::sendSms($phone, $code);
        if (!$res) {
            \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
            return $this->writeJson(104, '发送失败.');
        }
        $redis->setex($redisLimitKey, 60, 1);
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
        return $this->writeJson(200, '发送成功');
    }

    protected function validateRule(?string $action): ?Validate
    {
        $validate = null;
        switch ($action) {
            case 'login':
            {
                $validate = new Validate();
                $validate->addColumn('code', '微信code')->required()->lengthMax(100);
                $validate->addColumn('head_img', '头像')->required()->url()->lengthMax(500);
                $validate->addColumn('nick_name', '昵称')->required()->lengthMax(100);
                break;
            }
            /*  case 'wechat_up_info':
              {
                  $validate = new Validate();
                  $validate->addColumn('head_img', '头像')->required()->url()->lengthMax(100);
                  $validate->addColumn('nick_name', '昵称')->required()->lengthMax(100);
                  break;
              }*/
            case 'send_sms':
            {
                $validate = new Validate();
                $validate->addColumn('phone', '手机号')->required()->length(11)->regex('/^1\d{10}$/');
                break;
            }
            case 'up_info':
            {
                $validate = new Validate();
                $validate->addColumn('is_draw', '是否参与抽奖')->required()->integer()->inArray([0, 1]);
                $validate->addColumn('name', '姓名')->optional()->lengthMax(100);
                $validate->addColumn('identity_id', '身份')->required()->integer();
                $validate->addColumn('province', '省份')->required()->lengthMax(10);
                $validate->addColumn('city', '市')->required()->lengthMax(50);
                $validate->addColumn('area', '区')->required()->lengthMax(50);
                $validate->addColumn('id_no', '身份证号')->optional()->lengthMax(20);
                $validate->addColumn('phone', '手机号')->optional()->length(11)->regex('/^1\d{10}$/');
                $validate->addColumn('captcha', '验证码')->optional()->length(6);
                break;
            }
        }
        return $validate;
    }
}