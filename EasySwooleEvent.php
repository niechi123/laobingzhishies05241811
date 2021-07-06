<?php

namespace EasySwoole\EasySwoole;

use App\Pool\RedisPool;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\EasySwoole\Config as GlobalConfig;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\ORM\Db\Config;
use EasySwoole\ORM\Db\Connection;
use EasySwoole\ORM\DbManager;
use EasySwoole\Utility\File;

class EasySwooleEvent implements Event
{
    public static function initialize()
    {
//        \EasySwoole\Component\Di::getInstance()->set(\EasySwoole\EasySwoole\SysConst::HTTP_GLOBAL_ON_REQUEST,function (\EasySwoole\Http\Request $request, \EasySwoole\Http\Response $response){
//            $response->withHeader('Access-Control-Allow-Origin', '*');
//            $response->withHeader('Access-Control-Allow-Methods', 'GET, POST');
//            $response->withHeader('Access-Control-Allow-Credentials', 'true');
//            $response->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
//            $response->withHeader('Access-Control-Expose-Headers', 'authorization');
//            return true;
//        });
        date_default_timezone_set('Asia/Shanghai');
        $config = new Config(GlobalConfig::getInstance()->getConf("MYSQL"));
        $config->setReturnCollection(true);
        DbManager::getInstance()->addConnection(new Connection($config));

        //加载自己定义的配置文件
        self::loadConf();
    }

    public static function mainServerCreate(EventRegister $register)
    {

        $register->add($register::onWorkerStart, function () {
            //数据库链接预热
            DbManager::getInstance()->getConnection()->__getClientPool()->keepMin();
        });

        //redis 链接
        $config      = new \EasySwoole\Pool\Config();
        $redisConfig = new \EasySwoole\Redis\Config\RedisConfig(\EasySwoole\EasySwoole\Config::getInstance()->getConf('REDIS'));
        \EasySwoole\Pool\Manager::getInstance()->register(new RedisPool($config, $redisConfig), 'redis');
    }

    /**
     * 加载自定义配置文件
     */
    public static function loadConf()
    {
        //遍历目录中的文件
        $files = File::scanDirectory(EASYSWOOLE_ROOT . '/App/Conf');
        if (is_array($files)) {
            foreach ($files['files'] as $file) {
                $fileNameArr = explode('.', $file);
                $fileSuffix = end($fileNameArr);

                if ($fileSuffix == 'php') {
//                    \EasySwoole\EasySwoole\Config::getInstance()->loadFile($file, false);//引入之后,文件名自动转为小写,成为配置的key
                    $data = require_once $file;
                    \EasySwoole\EasySwoole\Config::getInstance()->setConf(basename($file, $fileSuffix), $data);

                }
            }
        }
    }
}