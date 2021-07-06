<?php
namespace App\Models;

use EasySwoole\ORM\AbstractModel;

class LuckDrawModel extends AbstractModel
{
    protected $tableName = 'bf_luck_draw';
    protected $autoTimeStamp = false;
}