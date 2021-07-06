<?php

namespace App\Helper;

use Swoole\Coroutine;

class UserAuth
{
    private static $instance = [];
    protected $user;
    protected static $status;

    static function getInstance(...$args): self
    {
        $cid = Coroutine::getCid();
        if (!isset(self::$instance[$cid])) {
            self::$instance[$cid] = new static(...$args);
            /*
             * 兼容非携程环境
             */
            if ($cid > 0) {
                Coroutine::defer(function () use ($cid) {
                    unset(self::$instance[$cid]);
                });
            }
        }
        return self::$instance[$cid];
    }

    public function __construct()
    {
    }

    public function __clone()
    {
    }

    public function __get($name)
    {
        return (self::$$name) ?? null;
    }

    public function destroy(int $cid = null)
    {
        if ($cid === null) {
            $cid = Coroutine::getCid();
        }
        unset(self::$instance[$cid]);
    }

    /**
     * 检验是否登录
     * @return bool
     */
    public function check(): bool
    {
        return $this->user ? true : false;
    }


    public function setUser(array $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @param null $col
     * @return array|null|string
     */
    public function getUser($col = null)
    {
        if ($this->user === null) return null;
        if ($col === null) {
            return $this->user;
        }
        if (is_string($col)) {
            return $this->user[$col] ?? null;
        }
        if (is_array($col)) {
            $returnData = [];
            foreach ($col as $v) {
                if (!is_string($v)) continue;
                $returnData[$v] = $this->user[$v] ?? null;
            }
            return $returnData;
        }
        return $this->user;
    }

    public function getToken()
    {
        return $this->token;
    }

    /*  private static function checkToken()
      {
          $_token = (new Request());
          var_dump($_token);
  //        Logger::getInstance()->info('aaaaaa'.$_token. 'ssssss', date('Ymd'));
          self::$token = $_token;
          if (!$_token) return null;
          try {
              $obj = Jwt::getInstance()->setSecretKey(Config::getInstance()->getConf('jwt.secret'))->decode($_token);
          } catch (\Exception $e) {
              return null;
          }
          self::$status = $obj->getStatus();
          if (self::$status != 1) return null;
          $userData  = UserModel::create()->where(['id' => $obj->getAud()['id'] ?? null, 'status' => 1])->get();
          if (!$userData) return null;
          Logger::getInstance()->info(json_encode($userData), date('Ymd'));
          self::$user = $userData;
          return null;
      }*/


}