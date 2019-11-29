<?php

declare(strict_types=1);

require_once __DIR__ . '/FoobarUser.php';
require_once __DIR__ . '/FoobarContact.php';
require_once __DIR__ . '/FoobarUserContactJoin.php';

use tests\FoobarContact;
use tests\FoobarUser;
use tests\FoobarUserContactJoin;
use voku\db\DB;

/**
 * Class ActiveRecordTest
 *
 * @internal
 */
final class ActiveRecordTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var DB
     */
    private $db;

    protected function setUp()
    {
        parent::setUp();

        $this->db = DB::getInstance(
            'localhost',
            'root',
            '',
            'mysql_test',
            3306,
            'utf8',
            false,
            true
        );
    }

    public function testInit()
    {
        $result = [];

        $result[] = $this->db->query(
            'CREATE TABLE IF NOT EXISTS user (
                id INTEGER NOT NULL AUTO_INCREMENT,
                name TEXT,
                password TEXT,
                PRIMARY KEY (id)
            );'
        );

        $result[] = $this->db->query(
            'CREATE TABLE IF NOT EXISTS contact (
                id INTEGER NOT NULL AUTO_INCREMENT,
                user_id INTEGER,
                email TEXT,
                address TEXT,
                PRIMARY KEY (id)
            );'
        );

        static::assertNotContains(false, $result);
    }

    /**
     * @depends testInit
     */
    public function testInsertUser(): FoobarUser
    {
        $user = new FoobarUser();
        $user->name = 'demo';
        $user->password = \md5('demo');

        static::assertSame('demo', $user->get('name'));
        static::assertSame('demo', $user->name);

        $id = $user->insert();

        static::assertGreaterThan(0, $user->id);
        static::assertGreaterThan(0, $id);
        static::assertSame($id, $user->getPrimaryKey());

        static::assertSame('demo', $user->get('name'));
        static::assertSame('demo', $user->name);

        return $user;
    }

    /**
     * @depends testInit
     */
    public function testInsertUserV2(): FoobarUser
    {
        $user = FoobarUser::fetchEmpty();
        $user->name = 'demo';
        $user->password = \md5('demo');

        static::assertSame('demo', $user->get('name'));
        static::assertSame('demo', $user->name);

        $id = $user->insert();

        static::assertGreaterThan(0, $user->id);
        static::assertGreaterThan(0, $id);
        static::assertSame($id, $user->getPrimaryKey());

        static::assertSame('demo', $user->get('name'));
        static::assertSame('demo', $user->name);

        return $user;
    }

    /**
     * @depends testInit
     */
    public function testInsertUserTypeFail()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Invalid type: expected "password" to be of type {string}, instead got value "0" (0) with type {integer}.');

        $user = FoobarUser::fetchEmpty();
        $user->name = 'demo';
        $user->password = 0;

        static::assertSame('demo', $user->get('name'));
        static::assertSame('demo', $user->name);

        $user->insert();
    }

    /**
     * @depends testInsertUser
     *
     * @param FoobarUser $user
     *
     * @return FoobarUser
     */
    public function testEditUser($user): FoobarUser
    {
        $user->name = 'demo1';
        $user->password = \md5('demo1');
        $user->update();
        static::assertGreaterThan(0, $user->id);

        return $user;
    }

    /**
     * @depends testInsertUser
     *
     * @param FoobarUser $user
     *
     * @return FoobarContact
     */
    public function testInsertContact($user): FoobarContact
    {
        $contact = new FoobarContact();
        $contact->address = 'test';
        $contact->email = 'test@demo.com';
        $contact->user_id = $user->id;
        $contact->insert();
        static::assertGreaterThan(0, $contact->id);

        return $contact;
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     *
     * @return FoobarContact
     */
    public function testEditContact($contact): FoobarContact
    {
        $contact->address = 'test1';
        $contact->email = 'test1@demo.com';
        $contact->update();
        static::assertGreaterThan(0, $contact->id);

        return $contact;
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     *
     * @return mixed
     */
    public function testRelations($contact)
    {
        static::assertSame($contact->user->id, $contact->user_id);
        static::assertSame($contact->user->contact->id, $contact->id);
        static::assertSame($contact->user->contacts[0]->id, $contact->id);
        static::assertGreaterThan(0, \count($contact->user->contacts));

        return $contact;
    }

    /**
     * @depends testRelations
     *
     * @param FoobarContact $contact
     *
     * @return mixed
     */
    public function testRelationsBackRef($contact)
    {
        static::assertNotSame($contact->user->contact, $contact);
        static::assertSame($contact->user_with_backref->contact, $contact);
        $user = $contact->user;
        static::assertNotSame($user->contacts[0]->user, $user);
        /** @noinspection PhpNonStrictObjectEqualityInspection */
        static::assertEquals($user->contacts_with_backref[0]->user, $user);

        return $contact;
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     */
    public function testJoin($contact)
    {
        $user = new FoobarUserContactJoin();
        $user->select('*, c.email, c.address')->join('contact as c', 'c.user_id = ' . $contact->user_id)->fetch();

        // email and address will stored in user data array.
        static::assertSame($contact->user_id, $user->id);
        static::assertSame($contact->email, $user->email);
        static::assertSame($contact->address, $user->address);
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     */
    public function testFetch($contact)
    {
        $user = new FoobarUser();
        /** @noinspection UnusedFunctionResultInspection */
        $user->fetch($contact->user_id);
        static::assertInstanceOf(FoobarUser::class, $user);

        // name etc. will stored in user data array.
        static::assertSame($contact->user_id, $user->id);
        static::assertSame($contact->user_id, $user->getPrimaryKey());
        static::assertSame('demo1', $user->name);
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     */
    public function testFetchAll($contact)
    {
        $users = (new FoobarUser())->fetchAll();

        $found = false;
        $userForTesting = null;

        foreach ($users as $userTmp) {
            if ($userTmp->getPrimaryKey() === $contact->user_id) {
                $found = true;
                $userForTesting = clone $userTmp;
            }
        }

        // repeat the loop (test the generator re-usage)
        foreach ($users as $userTmp) {
            if ($userTmp->getPrimaryKey() === $contact->user_id) {
                $found = true;
                $userForTesting = clone $userTmp;
            }
        }

        $names = $users->getColumn('name')->getArray();
        static::assertSame(['demo1', 'demo'], $names);

        // name etc. will stored in user data array.
        static::assertTrue($found);
        static::assertSame($contact->user_id, $userForTesting->id);
        static::assertSame($contact->user_id, $userForTesting->getPrimaryKey());
        static::assertSame('demo1', $userForTesting->name);
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     */
    public function testFetchOneByQuery($contact)
    {
        $user = new FoobarUser();
        $sql = 'SELECT * FROM user WHERE id = ' . (int) $contact->user_id;
        /** @noinspection UnusedFunctionResultInspection */
        $user->fetchOneByQuery($sql);
        static::assertInstanceOf(FoobarUser::class, $user);

        // name etc. will stored in user data array.
        static::assertSame($contact->user_id, $user->id);
        static::assertSame($contact->user_id, $user->getPrimaryKey());
        static::assertSame('demo1', $user->name);

        // ---

        $user = new FoobarUser();
        $sql = 'SELECT * FROM user WHERE id = ' . -1;
        $newUser = $user->fetchOneByQuery($sql);
        static::assertInstanceOf(FoobarUser::class, $user);
        static::assertNull($newUser);
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     */
    public function testFetchOneByQueryOrThrowException($contact)
    {
        $user = new FoobarUser();
        $sql = 'SELECT * FROM user WHERE id = ' . (int) $contact->user_id;
        /** @noinspection UnusedFunctionResultInspection */
        $user->fetchOneByQueryOrThrowException($sql);
        static::assertInstanceOf(FoobarUser::class, $user);

        // name etc. will stored in user data array.
        static::assertSame($contact->user_id, $user->id);
        static::assertSame($contact->user_id, $user->getPrimaryKey());
        static::assertSame('demo1', $user->name);
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     */
    public function testFetchOneByQueryOrThrowExceptionFail($contact)
    {
        $this->expectException(\voku\db\exceptions\FetchOneButFoundNone::class);

        $user = new FoobarUser();
        $sql = 'SELECT * FROM user WHERE id = ' . -1;
        $newUser = $user->fetchOneByQueryOrThrowException($sql);
        static::assertInstanceOf(FoobarUser::class, $user);
        static::assertNull($newUser);
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     */
    public function testFetchManyByQuery($contact)
    {
        $user = new FoobarUser();
        $sql = 'SELECT * FROM user WHERE id >= ' . (int) $contact->user_id;
        $users = $user->fetchManyByQuery($sql);

        $found = false;
        $userForTesting = null;
        foreach ($users->getGenerator() as $userTmp) {
            if ($userTmp->getPrimaryKey() === $contact->user_id) {
                $found = true;
                $userForTesting = clone $userTmp;
            }
        }

        // name etc. will stored in user data array.
        static::assertTrue($found);
        static::assertSame($contact->user_id, $userForTesting->id);
        static::assertSame($contact->user_id, $userForTesting->getPrimaryKey());
        static::assertSame('demo1', $userForTesting->name);
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     */
    public function testFetchById($contact)
    {
        $user = new FoobarUser();
        /** @noinspection UnusedFunctionResultInspection */
        $user->fetchById($contact->user_id);

        // name etc. will stored in user data array.
        static::assertSame($contact->user_id, $user->id);
        static::assertSame($contact->user_id, $user->getPrimaryKey());
        static::assertSame('demo1', $user->name);
        static::assertSame('demo1', $user->get('name'));
    }

    /**
     * @depends testInsertUser
     *
     * @param FoobarUser $user
     */
    public function testCopy($user)
    {
        $userCopy = $user->copy(true);

        // name etc. will stored in user data array.
        static::assertNotSame($userCopy, $user);
        static::assertNotSame($user->id, $userCopy->id);
        static::assertNotSame($user->getPrimaryKey(), $userCopy->getPrimaryKey());
        static::assertSame($user->name, $userCopy->name);
    }

    public function testFetchByIdFail()
    {
        $this->expectException(\voku\db\exceptions\FetchingException::class);

        $userNon = new FoobarUser();
        /** @noinspection UnusedFunctionResultInspection */
        $userNon->fetchById(-1);
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     */
    public function testFetchByIds($contact)
    {
        $users = (new FoobarUser())->fetchByIds([$contact->user_id, $contact->user_id - 1]);

        $found = false;
        $userForTesting = null;
        foreach ($users as $userTmp) {
            if ($userTmp->getPrimaryKey() === $contact->user_id) {
                $found = true;
                $userForTesting = clone $userTmp;
            }
        }

        // name etc. will stored in user data array.
        static::assertTrue($found);
        static::assertSame($contact->user_id, $userForTesting->id);
        static::assertSame($contact->user_id, $userForTesting->getPrimaryKey());
        static::assertSame('demo1', $userForTesting->name);
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     */
    public function testFetchByIdsFail($contact)
    {
        $users = (new FoobarUser())->fetchByIds([-1, -2]);

        $found = false;
        $userForTesting = null;
        foreach ($users as $userTmp) {
            if ($userTmp->getPrimaryKey() === $contact->user_id) {
                $found = true;
            }
        }

        static::assertFalse($found);
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     */
    public function testFetchByIdsPrimaryKeyAsArrayIndex($contact)
    {
        $users = (new FoobarUser())->fetchByIdsPrimaryKeyAsArrayIndex([$contact->user_id, $contact->user_id - 1]);

        $found = false;
        $userForTesting = null;
        foreach ($users as $userId => $userTmp) {
            if (
                $userId === $contact->user_id
                &&
                $userTmp->getPrimaryKey() === $contact->user_id
            ) {
                $found = true;
                $userForTesting = clone $userTmp;
            }
        }

        // name etc. will stored in user data array.
        static::assertTrue($found);
        static::assertSame($contact->user_id, $userForTesting->id);
        static::assertSame($contact->user_id, $userForTesting->getPrimaryKey());
        static::assertSame('demo1', $userForTesting->name);

        // again, so we can test "Cannot traverse an already closed generator"

        $found = false;
        $userForTesting = null;
        foreach ($users as $userId => $userTmp) {
            if (
                $userId === $contact->user_id
                &&
                $userTmp->getPrimaryKey() === $contact->user_id
            ) {
                $found = true;
                $userForTesting = clone $userTmp;
            }
        }

        // name etc. will stored in user data array.
        static::assertTrue($found);
        static::assertSame($contact->user_id, $userForTesting->id);
        static::assertSame($contact->user_id, $userForTesting->getPrimaryKey());
        static::assertSame('demo1', $userForTesting->name);
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     */
    public function testFetchByIdIfExists($contact)
    {
        $user = new FoobarUser();
        $result = $user->fetchByIdIfExists($contact->user_id);

        // name etc. will stored in user data array.
        static::assertSame($user, $result);
        static::assertSame($contact->user_id, $user->id);
        static::assertSame($contact->user_id, $user->getPrimaryKey());
        static::assertSame('demo1', $user->name);
    }

    public function testFetchByIdIfExistsFail()
    {
        $userNon = new FoobarUser();
        $result = $userNon->fetchByIdIfExists(-1);

        // name etc. will not stored in user data array.
        static::assertNull($result);
        static::assertNull($userNon->id);
        static::assertNull($userNon->id);
        static::assertNull($userNon->getPrimaryKey());
        static::assertNull($userNon->name);
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     */
    public function testFetchByHashIdIfExists($contact)
    {
        $user = new FoobarUser();
        $result = $user->fetchByHashIdIfExists($contact->convertIdIntoHashId($contact->user_id));

        // name etc. will stored in user data array.
        static::assertSame($user, $result);
        static::assertSame($contact->user_id, $user->id);
        static::assertSame($contact->user_id, $user->getPrimaryKey());
        static::assertSame('demo1', $user->name);
    }

    public function testFetchByHashIdIfExistsFail()
    {
        $userNon = new FoobarUser();
        $result = $userNon->fetchByHashIdIfExists(-1);

        // name etc. will not stored in user data array.
        static::assertNull($result);
        static::assertNull($userNon->id);
        static::assertNull($userNon->id);
        static::assertNull($userNon->getPrimaryKey());
        static::assertNull($userNon->name);
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     */
    public function testOrder($contact)
    {
        $user = new FoobarUser();
        $user->where('id = ' . $contact->user_id)->orderBy('id DESC', 'name ASC')->limit(2, 1)->fetch();

        // email and address will stored in user data array.
        static::assertSame($contact->user_id, $user->id);
        static::assertSame($contact->user_id, $user->getPrimaryKey());
        static::assertSame('demo1', $user->name);
    }

    /**
     * @depends testInsertContact
     *
     * @param FoobarContact $contact
     */
    public function testGroup($contact)
    {
        $users = (new FoobarUser())->select('count(1) as count')->groupBy('name')->fetchAll();

        foreach ($users as $userTmp) {
            static::assertInstanceOf(FoobarUser::class, $userTmp);
        }
    }

    /**
     * @depends testInsertContact
     */
    public function testQuery()
    {
        $user = new FoobarUser();
        $user->isNotNull('id')->eq('id', 1)->lt('id', 2)->gt('id', 0)->fetch();
        static::assertGreaterThan(0, $user->id);
        static::assertSame([], $user->getDirty());
        $user->name = 'testname';
        static::assertSame(['name' => 'testname'], $user->getDirty());
        $name = $user->name;
        static::assertSame('testname', $name);
        static::assertSame(['name' => 'testname'], $user->getDirty());
        $user->reset()->isNotNull('id')->eq('id', 'aaa"')->wrap()->lt('id', 2)->gt('id', 0)->wrap('OR')->fetch();
        static::assertGreaterThan(0, $user->id);
        $user->reset()->isNotNull('id')->between('id', [0, 2])->fetch();
        static::assertGreaterThan(0, $user->id);
    }

    /**
     * @depends testRelations
     *
     * @param FoobarContact $contact
     */
    public function testDelete($contact)
    {
        $cid = $contact->id;
        $uid = $contact->user_id;
        $new_contact = new FoobarContact();
        $new_user = new FoobarUser();
        static::assertSame($cid, $new_contact->fetch($cid)->id);
        static::assertSame($uid, $new_user->eq('id', $uid)->fetch()->id);
        static::assertTrue($contact->user->delete());
        static::assertTrue($contact->delete());
        $new_contact = new FoobarContact();
        $new_user = new FoobarUser();
        static::assertFalse($new_contact->eq('id', $cid)->fetch());
        static::assertFalse($new_user->fetch($uid));

        /** @noinspection UnusedFunctionResultInspection */
        $this->db->query('DROP TABLE IF EXISTS user;');
        /** @noinspection UnusedFunctionResultInspection */
        $this->db->query('DROP TABLE IF EXISTS contact;');
    }
}
