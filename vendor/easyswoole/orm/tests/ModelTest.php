<?php
/**
 * 基本测试 ddl 等
 * User: Tioncico
 * Date: 2019/10/22 0022
 * Time: 15:08
 */

namespace EasySwoole\ORM\Tests;


use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\Db\Config;
use EasySwoole\ORM\Db\Connection;
use EasySwoole\ORM\DbManager;
use EasySwoole\ORM\Utility\Schema\Table;
use PHPUnit\Framework\TestCase;


use EasySwoole\ORM\Tests\models\TestUserModel;

class ModelTest extends TestCase
{
    /**
     * @var $connection Connection
     */
    protected $connection;

    protected $tableName = 'test_user_model';

    protected function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        $config = new Config(MYSQL_CONFIG);
        $this->connection = new Connection($config);

        DbManager::getInstance()->addConnection($this->connection);
        $connection = DbManager::getInstance()->getConnection();
        $this->assertTrue($connection === $this->connection);
        $this->createTestTable();
    }

    function createTestTable()
    {
        $query = new QueryBuilder();
        $tableDDL = new Table($this->tableName);
        $tableDDL->colInt('id', 11)->setIsPrimaryKey()->setIsAutoIncrement();
        $tableDDL->colVarChar('name', 255);
        $tableDDL->colTinyInt('age', 1);
        $tableDDL->colDateTime('addTime');
        $tableDDL->colTinyInt('state', 1);
        $tableDDL->setIfNotExists();
        $sql = $tableDDL->__createDDL();
        $query->raw($sql);
        $data = $this->connection->defer()->query($query);
        $this->assertTrue($data->getResult());
    }


    function testGetSchemaInfo()
    {
        $testUserModel = new TestUserModel();
        $schemaInfo = $testUserModel->schemaInfo();
        $this->assertTrue($schemaInfo instanceof Table);
    }

    function testAdd()
    {
        $testUserModel = new TestUserModel();
        $testUserModel->state = 1;
        $testUserModel->name = '仙士可';
        $testUserModel->age = 100;
        $testUserModel->addTime = date('Y-m-d H:i:s');
        $data = $testUserModel->save();
        $this->assertIsInt($data);
    }

    /**
     * @depends testAdd
     * testUpdate
     * @author Tioncico
     * Time: 15:41
     */
    function testUpdate()
    {
        $testUserModel = new TestUserModel();

        /**
         * @var $user TestUserModel
         */
        $user = $testUserModel->get();
        $user->name = '仙士可2号';
        $result = $user->update();
        $this->assertTrue($result);

        $user = $testUserModel->get(['id' => $user->id]);
        $this->assertEquals('仙士可2号', $user->name);
    }

    function testGetAll()
    {
        $testUserModel = new TestUserModel();
        $data = $testUserModel->all();
        $this->assertIsArray($data);
    }

    function testDelete()
    {
        $testUserModel = new TestUserModel();

        /**
         * @var $user TestUserModel
         */
        $user = $testUserModel->get();
        $result = $user->destroy();
        $this->assertEquals(1, $result);

        $user = $user->get(['id' => $user->id]);
        $this->assertNull($user);
    }

}