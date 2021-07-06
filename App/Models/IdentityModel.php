<?php
namespace App\Models;

use EasySwoole\ORM\AbstractModel;

class IdentityModel extends AbstractModel
{
    protected $tableName = 'bf_identity';
    protected $autoTimeStamp = false;
}