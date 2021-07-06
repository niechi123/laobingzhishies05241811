<?php
namespace App\Models;

use EasySwoole\ORM\AbstractModel;

class UserModel extends AbstractModel
{
    protected $tableName = 'bf_user';
    protected $autoTimeStamp = false;
}