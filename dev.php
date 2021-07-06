<?php
return [
    'SERVER_NAME' => "laobingzhishi",
    'MAIN_SERVER' => [
        'LISTEN_ADDRESS' => '0.0.0.0',
        'PORT' => 9501,
        'SERVER_TYPE' => EASYSWOOLE_WEB_SERVER, //可选为 EASYSWOOLE_SERVER  EASYSWOOLE_WEB_SERVER EASYSWOOLE_WEB_SOCKET_SERVER
        'SOCK_TYPE' => SWOOLE_TCP,
        'RUN_MODEL' => SWOOLE_PROCESS,
        'SETTING' => [
            'worker_num' => 8,
            'reload_async' => true,
            'max_wait_time'=>3
        ],
        'TASK'=>[
            'workerNum'=>4,
            'maxRunningNum'=>128,
            'timeout'=>15
        ]
    ],
    /*'TEMP_DIR' => null,
    'LOG_DIR' => null,*/
    'TEMP_DIR'    => '/Tmp',
    'LOG_DIR'     => '/easyswoole/Log',
    'MYSQL'       => [
        'host'          => '127.0.0.1',
        'user'          => 'root',
        'password'      => 'root',
        'database'      => 'laobingzhishi_test',
        'timeout'       => 5,
        'charset'       => 'utf8mb4',
        'POOL_MAX_NUM'  => 20,
        'POOL_TIME_OUT' => 1
    ],

    /* redis 信息配置*/
    'REDIS'       => [
        'host'      => '0.0.0.0',
        'port'      => '6379',
        'auth'      => '',
        'db'        => '5',
        'serialize' => \EasySwoole\Redis\Config\RedisConfig::SERIALIZE_NONE,
    ],
];
