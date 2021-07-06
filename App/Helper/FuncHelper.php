<?php

namespace App\Helper;

use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;

class FuncHelper
{
    /**
     * 返回编号，自带前缀
     * @param string $prefix 可选前缀
     * @return string
     */
    public static function getRandNO($prefix = '')
    {
        $yCode = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
        mt_srand();
        $orderSn = $yCode[intval(date('Y')) - 2021] . strtoupper(dechex(date('m'))) . date('d') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%03d', mt_rand(0, 999));
        return strtoupper((string)$prefix) . $orderSn;
    }

    /**
     * 发送验证码
     * @param string $phone
     * @param int $code
     * @return bool
     */
    public static function sendSms(string $phone, int $code)
    {
        $config = Config::getInstance()->getConf('app.send_sms');
        $body_json = array(
            'sid'        => $config['sid'],
            'token'      => $config['token'],
            'appid'      => $config['appid'],
            'templateid' => $config['templateid'],
            'param'      => $code,
            'mobile'     => $phone,
            'uid'        => '',
        );
        $body      = json_encode($body_json);
        $data      = self::send_curl($config['url'], $body, 'post');
        $data = json_decode($data, true);
        if(($data['code'] ?? 'null') == '000000'){
            return true;
        } else {
           Logger::getInstance()->info('短信发送失败了，返回结果:' . json_encode($data), date("Y-m"));
            return false;
        }
    }
    private static function send_curl($url, $body,$method = 'post')
    {
        if (function_exists("curl_init")) {
            $header = array(
                'Accept:application/json',
                'Content-Type:application/json;charset=utf-8',
            );
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            if($method == 'post'){
                curl_setopt($ch,CURLOPT_POST,1);
                curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
            }
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $result = curl_exec($ch);
            curl_close($ch);
        } else {
            $opts = array();
            $opts['http'] = array();
            $headers = array(
                "method" => strtoupper($method),
            );
            $headers[]= 'Accept:application/json';
            $headers['header'] = array();
            $headers['header'][]= 'Content-Type:application/json;charset=utf-8';

            if(!empty($body)) {
                $headers['header'][]= 'Content-Length:'.strlen($body);
                $headers['content']= $body;
            }

            $opts['http'] = $headers;
            $result = file_get_contents($url, false, stream_context_create($opts));
        }
        return $result;
    }

    /**
     * 发送curl 请求
     *
     * @param string $url 月份
     * @param $data string 参数  参数为空 自动使用get方式请求
     * @return array
     */
    private static function curlRequest($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        var_dump(curl_error($curl));
        curl_close($curl);
        return $output;
    }

    public static function encrypt(string $content): string
    {
        $key   = Config::getInstance()->getConf('secret');
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-=+";
        mt_srand();
        $nh    = mt_rand(0, 64);
        $ch    = $chars[$nh];
        $mdKey = md5($key . $ch);
        $mdKey = substr($mdKey, $nh % 8, $nh % 8 + 7);
        $txt   = base64_encode($content);
        $tmp   = '';
        $i     = 0;
        $j     = 0;
        $k     = 0;
        for ($i = 0; $i < strlen($txt); $i++) {
            $k   = $k == strlen($mdKey) ? 0 : $k;
            $j   = ($nh + strpos($chars, $txt[$i]) + ord($mdKey[$k++])) % 64;
            $tmp .= $chars[$j];
        }
        return urlencode($ch . $tmp);
    }

    public static function decrypt(string $content): string
    {
        $key   = Config::getInstance()->getConf('secret');
        $txt   = urldecode($content);
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-=+";
        $ch    = $txt[0];
        $nh    = strpos($chars, $ch);
        $mdKey = md5($key . $ch);
        $mdKey = substr($mdKey, $nh % 8, $nh % 8 + 7);
        $txt   = substr($txt, 1);
        $tmp   = '';
        $i     = 0;
        $j     = 0;
        $k     = 0;
        for ($i = 0; $i < strlen($txt); $i++) {
            $k = $k == strlen($mdKey) ? 0 : $k;
            $j = strpos($chars, $txt[$i]) - $nh - ord($mdKey[$k++]);
            while ($j < 0) $j += 64;
            $tmp .= $chars[$j];
        }
        return base64_decode($tmp);
    }
}