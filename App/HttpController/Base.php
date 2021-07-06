<?php

namespace App\HttpController;

use App\Helper\UserAuth;
use App\Models\UserModel;
use EasySwoole\EasySwoole\Config;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\Validate\Validate;
use hxhzyh\jwtAuth\Jwt;

class Base extends Controller
{
    public function index()
    {
        parent::index();
    }

    protected function onRequest(?string $action): ?bool
    {
        if (parent::onRequest($action) === false) return false;
        $this->userAuth();
        if ($v = $this->validateRule($action)) {
            if (($v->validate($this->request()->getRequestParam())) == false) {
                $this->writeJson(100, "{$v->getError()->getErrorRuleMsg()}", null);
                return false;
            }
        }

        $redis = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
        //这个单纯的就是统计下总请求量
        if ($redis->incr('LAOBINGZHISHI-ACCESS-STATISTICS') > 9223372036854775800) {//最大值9223372036854775807
            $redis->set('LAOBINGZHISHI-ACCESS-STATISTICS', 1);
        }

        //这个统计每个接口的总请求量
        $uri = $this->request()->getUri()->getPath();
        if ($redis->hincrby('LAOBINGZHISHI-ACCESS-STATISTICS-ONE-URL', $uri, 1) > 9223372036854775800) {
            $redis->hset('LAOBINGZHISHI-ACCESS-STATISTICS-ONE-URL', $uri, 0);
        }
        unset($uri);
        \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);

        return true;
    }

    protected function afterAction(?string $string): void
    {
        parent::afterAction($string);
        //删除对应auth对象
        UserAuth::getInstance()->destroy();
    }


    protected function validateRule(?string $action): ?Validate
    {
        return null;
    }


    private function userAuth(): void
    {
        $token = $this->request()->getHeaderLine('authorization');
        if(!$token) return;
        try{
            $jwtObject = Jwt::getInstance()->setSecretKey(Config::getInstance()->getConf('jwt.secret'))->decode($token);
            if($jwtObject->getStatus() != 1) return;
            $userData = $jwtObject->getAud();
           /* $redis = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
            $redisToken = $redis->get(Config::getInstance()->getConf('login_sign_one.login_sign_one_key') . $userData['id']);
            if(!$redisToken){
                \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
                return ;
            }
            if($redisToken != md5($token)) {
                \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);
                return ;
            }
            $redis->expire(Config::getInstance()->getConf('login_sign_one.login_sign_one_key') . $userData['id'], Config::getInstance()->getConf('login_sign_one.login_sign_one_expire'));//同步登录处的时间和key
            \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);*/
            $user = UserModel::create()->where(['id' => $userData['id'] ,'status' => 1])->get();
            if(!$user) return;
            UserAuth::getInstance()->setUser($user->toArray());
        }catch ( \Exception $e) {
            return;
        }
    }


}