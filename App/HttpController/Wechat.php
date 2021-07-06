<?php

namespace App\HttpController;

use App\Models\CustomerLoginRecordModel;
use App\Models\CustomerModel;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\WeChat\Bean\OfficialAccount\JsAuthRequest;
use EasySwoole\WeChat\WeChat as WechatObj;
use hxhzyh\jwtAuth\Jwt;

class Wechat extends Base
{
    /**
     * 认证公众号 服务器配置
     * @return bool
     */
    public function check_signature()
    {
        $queryData = $this->request()->getRequestParam('signature', 'timestamp', 'nonce', 'echostr', 'echostr');
        if ((!$queryData['signature']) || (!$queryData['timestamp']) || (!$queryData['nonce'])) return false;
        $token  = 'biaofunhudong';
        $tmpArr = array($token, $queryData['timestamp'], $queryData['nonce']);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $queryData['signature']) {
            $this->response()->write($queryData['echostr']);
            return true;
        } else {
            return false;
        }
    }

    public function oauth()
    {
        $wechat        = $this->getWechat();
        $jsApi         = $wechat->officialAccount()->jsApi();
        $jsAuthRequest = new JsAuthRequest();
        $jsAuthRequest->setRedirectUri(Config::getInstance()->getConf('base_url') . '/esapi/wechat/oauth_call');
        $jsAuthRequest->setType($jsAuthRequest::TYPE_BASE);
        $link = $jsApi->auth()->generateURL($jsAuthRequest);
        return $this->response()->redirect($link);
    }

    public function oauth_call()
    {
        $wechat = $this->getWechat();
        $jsApi  = $wechat->officialAccount()->jsApi();
        $code   = $this->request()->getRequestParam('code');
        try {
            $snsAuthBean = $jsApi->auth()->codeToToken($code);
            $openid      = $snsAuthBean->getOpenid();
        } catch (\Exception $e) {
            return $this->response()->redirect(Config::getInstance()->getConf('base_url') . '/#/');
        }

        //验证当前openid是否是已绑定的客户
        $userData = CustomerModel::create()->where(['openid' => $openid, 'status' => 1])->get();
        if ($userData) {
            $obj = Jwt::getInstance()->setGuard('customer')->setSecretKey(Config::getInstance()->getConf('secret'))->publish();
            $obj->setAlg('HMACSHA256');
            $obj->setAud(['id' => $userData->id]);
            $token = $obj->getToken();
            $redis = \EasySwoole\Pool\Manager::getInstance()->get('redis')->getObj();
            $redis->setEx(Config::getInstance()->getConf('login_sign_one.customer_login_sign_one_key') . $userData->id, Config::getInstance()->getConf('login_sign_one.login_sign_one_expire'), md5($token));//同步登录处的时间和key
            \EasySwoole\Pool\Manager::getInstance()->get('redis')->recycleObj($redis);

            //修改用户登录记录
            $res = $userData->update([
                'last_login_time' => time(),
                'login_times'     => ++$userData->login_times
            ]);

            //异步记录登录
            TaskManager::getInstance()->async(function ()use($userData){
                CustomerLoginRecordModel::create([
                    'customer_id' => $userData->id,
                    'date' => date('Ym'),
                    'login_time' => time(),
                ])->save();
            });
            return $this->response()->redirect(Config::getInstance()->getConf('base_url') . '/#/pages/index/index?token=' . $token);
        } else {
            $url = Config::getInstance()->getConf('base_url') . '/#/?openid=' . $openid;
            return $this->response()->redirect($url);
        }
    }

    private function getWechat(): WechatObj
    {
        // 获取WeChat配置实例
        $weChatConfig = new \EasySwoole\WeChat\Config();
        // 设置全局缓存目录
        $weChatConfig->setTempDir(Config::getInstance()->getConf('TEMP_DIR'));
        $weChatConfig->officialAccount(Config::getInstance()->getConf('wechatConfig'));
        return new WechatObj($weChatConfig);
    }
}