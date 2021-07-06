<?php
/* JWT 配置文件 */
return[
    'alg' => 'HMACSHA256',//加密方式
    'exp' => '99999999',//token 失效时间
    'iss' => 'laobingzhishi',
    'nbf' => '99999999',//临时过期时间,暂时还没想好怎么设置过期token不可用，先和exp同等吧
    'secret' => '0mS6trj310FgVOakNHLRCuMHydQDoaCmWRjnquUc81xqRsWlMbXp6OnN0mCTmQk6',
    'login_sign_one' => [
        'login_sign_one_key'    => 'LAOBINGZHISHI-LOGIN-SIGN-ONE-LIMIT',
        'login_sign_one_expire' => 14400,
    ],
];