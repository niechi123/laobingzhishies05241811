<?php
namespace App\Models;

use EasySwoole\ORM\AbstractModel;

class CaptchaModel extends AbstractModel
{
    protected $tableName = 'bf_captcha';
    protected $autoTimeStamp = false;
}