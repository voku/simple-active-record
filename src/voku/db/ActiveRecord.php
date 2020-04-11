<?php

declare(strict_types=1);

namespace voku\db;

use Arrayy\Arrayy;
use Arrayy\ArrayyIterator;
use Hashids\Hashids;
use Hashids\HashidsInterface;
use voku\db\exceptions\ActiveRecordException;
use voku\db\exceptions\FetchingException;
use voku\db\exceptions\FetchOneButFoundMultiple;
use voku\db\exceptions\FetchOneButFoundNone;

/**
 * A simple implement of active record via Arrayy.
 *
 * @template TKey of array-key
 * @template T
 * @extends  Arrayy<TKey,T>
 */
abstract class ActiveRecord extends Arrayy
{
    const BELONGS_TO = 'belongs_to';

    const HAS_MANY = 'has_many';

    const HAS_ONE = 'has_one';

    /**
     * @internal
     */
    const PREFIX = ':active_record';

    const SQL_SELECT = 'select';

    const SQL_INSERT = 'insert';

    const SQL_UPDATE = 'update';

    const SQL_SET = 'set';

    const SQL_DELETE = 'delete';

    const SQL_JOIN = 'join';

    const SQL_FROM = 'from';

    const SQL_VALUES = 'values';

    const SQL_WHERE = 'where';

    const SQL_HAVING = 'having';

    const SQL_LIMIT = 'limit';

    const SQL_ORDER = 'order';

    const SQL_GROUP = 'group';

    /**
     * Check "@property" types from class-phpdoc.
     *
     * @var bool
     */
    protected $checkPropertyTypes = true;

    /**
     * Check properties mismatch in the constructor.
     *
     * @var bool
     */
    protected $checkPropertiesMismatchInConstructor = false;

    /**
     * @var DB
     */
    protected $db;

    /**
     * The table name in database.
     *
     * @var string
     */
    protected $table;

    /**
     * The primary key of this ActiveRecord, just support single primary key.
     *
     * @var string
     */
    protected $primaryKeyName = 'id';

    /**
     * Stored the configure of the relation, or target of the relation.
     *
     * @var ActiveRecordExpressions[]|array<string, array<ActiveRecord|array|string|null>>|self[]|static[]
     */
    protected $relations = [];

    /**
     * The default sql expressions values.
     *
     * @var array
     */
    private $defaultSqlExpressions = [
        self::SQL_SELECT => null,
        self::SQL_INSERT => null,
        self::SQL_UPDATE => null,
        self::SQL_SET    => null,
        self::SQL_DELETE => null,
        self::SQL_JOIN   => null,
        self::SQL_FROM   => null,
        self::SQL_VALUES => null,
        self::SQL_WHERE  => null,
        self::SQL_HAVING => null,
        self::SQL_LIMIT  => null,
        self::SQL_ORDER  => null,
        self::SQL_GROUP  => null,
    ];

    /**
     * Stored the Expressions of the SQL.
     *
     * @var array
     */
    private $sqlExpressions = [];

    /**
     * Stored the dirty data of this object, when call "insert" or "update"
     * function, will write this data into database.
     *
     * @var array
     */
    private $dirty = [];

    /**
     * @var bool
     */
    private $new_data_are_dirty = true;

    /**
     * Stored the params will bind to SQL when call DB->query().
     *
     * @var array
     */
    private $params = [];

    /**
     * Mapping the function name and the operator,
     * to build Expressions in WHERE condition.
     *
     * @var array
     *
     * call the function like this:
     * <pre>
     *   $user->isNotNull()->eq('id', 1);
     * </pre>
     *
     * the result in SQL:
     * <pre>
     *   WHERE user.id IS NOT NULL AND user.id = :ph1
     * </pre>
     */
    private static $operators = [
        'equal'              => '=',
        'eq'                 => '=',
        'notequal'           => '<>',
        'ne'                 => '<>',
        'greaterthan'        => '>',
        'gt'                 => '>',
        'lessthan'           => '<',
        'lt'                 => '<',
        'greaterthanorequal' => '>=',
        'ge'                 => '>=',
        'gte'                => '>=',
        'lessthanorequal'    => '<=',
        'le'                 => '<=',
        'lte'                => '<=',
        'between'            => 'BETWEEN',
        'like'               => 'LIKE',
        'in'                 => 'IN',
        'notin'              => 'NOT IN',
        'isnull'             => 'IS NULL',
        'isnotnull'          => 'IS NOT NULL',
        'notnull'            => 'IS NOT NULL',
    ];

    /**
     * The count of bind params, using this count
     * and const "PREFIX" (:ph) to generate place holder in SQL.
     *
     * @var int
     */
    private static $count = 0;

    /**
     * Part of the SQL, mapping the function name and
     * the operator to build SQL Part.
     *
     * @var array
     *
     * <br />
     *
     * call the function like this:
     * <pre>
     *      $user->orderBy('id DESC', 'name ASC')->limit(2, 1);
     * </pre>
     *
     * the result in SQL:
     * <pre>
     *      ORDER BY id DESC, name ASC LIMIT 2,1
     * </pre>
     */
    private $sqlParts = [
        self::SQL_SELECT => 'SELECT',
        self::SQL_FROM   => 'FROM',
        self::SQL_SET    => 'SET',
        self::SQL_WHERE  => 'WHERE',
        self::SQL_GROUP  => 'GROUP BY',
        self::SQL_HAVING => 'HAVING',
        self::SQL_ORDER  => 'ORDER BY',
        self::SQL_LIMIT  => 'LIMIT',
    ];

    /**
     * @var array
     */
    private $expressions = [];

    /**
     * @var bool
     */
    private $wrap = false;

    /**
     * @var HashidsInterface
     */
    private $hashids;

    /**
     * @param mixed  $data                                   <p>
     *                                                       Should be an array or a generator, otherwise it will try
     *                                                       to convert it into an array.
     *                                                       </p>
     * @param string $iteratorClass                          optional <p>
     *                                                       You can overwrite the ArrayyIterator, but mostly you don't
     *                                                       need this option.
     *                                                       </p>
     * @param bool   $checkForMissingPropertiesInConstructor optional <p>
     *                                                       You need to extend the "Arrayy"-class and you need to set
     *                                                       the $checkPropertiesMismatchInConstructor class property
     *                                                       to
     *                                                       true, otherwise this option didn't not work anyway.
     *                                                       </p>
     *
     * @psalm-param class-string<\Arrayy\ArrayyIterator> $iteratorClass
     */
    public function __construct(
        $data = [],
        string $iteratorClass = ArrayyIterator::class,
        bool $checkForMissingPropertiesInConstructor = true
    ) {
        parent::__construct(
            $data,
            $iteratorClass,
            $checkForMissingPropertiesInConstructor
        );

        /** @noinspection UnusedFunctionResultInspection */
        $this->prepareHashids();

        $this->reset();
        $this->resetDirty();

        if ($this->table === null) {
            $this->table = \strtolower(static::class);
        }

        $this->init();
    }

    /**
     * Magic function to UNSET values of the current object.
     *
     * @param mixed $var
     */
    public function __unset($var)
    {
        if (\array_key_exists($var, $this->sqlExpressions)) {
            unset($this->sqlExpressions[$var]);
        }

        if (isset($this->array[$var])) {
            unset($this->array[$var]);
        }

        if (isset($this->dirty[$var])) {
            unset($this->dirty[$var]);
        }
    }

    /**
     * Magic function to SET values of the current object.
     *
     * @param string $var
     * @param mixed  $val
     *
     * @return void
     */
    public function __set($var, $val)
    {
        if (!$this->db instanceof DB) {
            $this->db = DB::getInstance();
        }

        if (
            \array_key_exists($var, $this->sqlExpressions)
            ||
            \array_key_exists($var, $this->defaultSqlExpressions)
        ) {
            $this->sqlExpressions[$var] = $val;
        } elseif (
            \array_key_exists($var, $this->relations)
            &&
            $val instanceof self
        ) {
            $this->relations[$var] = $val;
        } else {
            /** @noinspection UnusedFunctionResultInspection */
            $this->set($var, $val);

            if ($this->new_data_are_dirty === true) {
                $this->dirty[$var] = $this->db->escape($val);
            }
        }
    }

    /**
     * Magic function to make calls witch in function mapping stored in $operators and $sqlPart.
     * also can call function of DB object.
     *
     * @param string $name
     *                     <p>The name of the function.</p>
     * @param array  $args
     *                     <p>The arguments of the function.</p>
     *
     * @throws ActiveRecordException
     *
     * @return mixed|static
     *                      <p>Return the result of callback or the current object to make chain method calls.</p>
     */
    public function __call(string $name, array $args = [])
    {
        if (!$this->db instanceof DB) {
            $this->db = DB::getInstance();
        }

        $nameTmp = \strtolower($name);

        if (\array_key_exists($nameTmp, self::$operators)) {
            $this->addCondition(
                $args[0],
                self::$operators[$nameTmp],
                isset($args[1]) ? $this->db->escape($args[1]) : null,
                \is_string(\end($args)) && \strtolower(\end($args)) === 'or' ? 'OR' : 'AND'
            );
        } elseif (\array_key_exists($nameTmp, $this->sqlParts)) {
            $this->{$name} = new ActiveRecordExpressions(
                [
                    ActiveRecordExpressions::OPERATOR => $this->sqlParts[$nameTmp],
                    ActiveRecordExpressions::TARGET   => \rtrim(\implode(',', $args), ','),
                ]
            );
        } elseif (\is_callable([$this->db, $name])) {
            return \call_user_func_array([$this->db, $name], $args);
        } else {
            throw new ActiveRecordException("Method ${name} not exist.");
        }

        return $this;
    }

    /**
     * @return static
     */
    public static function fetchEmpty(): self
    {
        $class = static::class;

        return new $class();
    }

    /**
     * Magic function to GET the values of current object.
     *
     * @param mixed $var
     *
     * @return mixed
     */
    public function &__get($var)
    {
        if (\array_key_exists($var, $this->sqlExpressions)) {
            return $this->sqlExpressions[$var];
        }

        if (\array_key_exists($var, $this->relations)) {
            return $this->getRelation($var);
        }

        /** @noinspection NullCoalescingOperatorCanBeUsedInspection */
        if (isset($this->dirty[$var])) {
            return $this->dirty[$var];
        }

        return parent::__get($var);
    }

    /**
     * Function to find one record and assign in to current object.
     *
     * @param mixed $id
     *                  <p>
     *                  If call this function using this param, we will find the record by using this id.
     *                  If not set, just find the first record in database.
     *                  </p>
     *
     * @return false|static
     *                      <p>
     *                      If we could find the record, assign in to current object and return it,
     *                      otherwise return "false".
     *                      </p>
     */
    public function fetch($id = null)
    {
        if ($id) {
            $this->reset()->eq($this->primaryKeyName, $id);
        }

        $sqlQuery = $this->limit(1)->_buildSql(
            [
                self::SQL_SELECT,
                self::SQL_FROM,
                self::SQL_JOIN,
                self::SQL_WHERE,
                self::SQL_GROUP,
                self::SQL_HAVING,
                self::SQL_ORDER,
                self::SQL_LIMIT,
            ]
        );

        return $this->query(
            $sqlQuery,
            $this->params,
            $this->reset(),
            true
        );
    }

    /**
     * Function to reset the $params and $sqlExpressions.
     *
     * @return $this
     */
    public function reset(): self
    {
        $this->params = [];
        $this->sqlExpressions = [];

        return $this;
    }

    /**
     * Helper function to exec sql.
     *
     * @param string $sql
     *                      <p>The SQL need to be execute.</p>
     * @param array  $param
     *                      <p>The param will be bind to the sql statement.</p>
     *
     * @return bool|int|Result|string
     *                                <p>
     *                                "Result" by "<b>SELECT</b>"-queries<br />
     *                                "int|string" (insert_id) by "<b>INSERT / REPLACE</b>"-queries<br />
     *                                "int" (affected_rows) by "<b>UPDATE / DELETE</b>"-queries<br />
     *                                "true" by e.g. "DROP"-queries<br />
     *                                "false" on error
     *                                </p>
     */
    public function execute(string $sql, array $param = [])
    {
        if (!$this->db instanceof DB) {
            $this->db = DB::getInstance();
        }

        return $this->db->query($sql, $param);
    }

    /**
     * Function to find all records in database.
     *
     * @param array<int, float|int|string|null>|null $ids
     *                                                    <p>
     *                                                    If call this function using this param, we will find the record by using this id's.
     *                                                    If not set, just find all records in database.
     *                                                    </p>
     *
     * @return CollectionActiveRecord|\Generator|static[]
     * @psalm-return CollectionActiveRecord<TKey,T>
     */
    public function yieldAll(array $ids = null)
    {
        if ($ids) {
            $this->reset()->in($this->primaryKeyName, $ids);
        }

        return $this->query(
            $this->_buildSql(
                [
                    self::SQL_SELECT,
                    self::SQL_FROM,
                    self::SQL_JOIN,
                    self::SQL_WHERE,
                    self::SQL_GROUP,
                    self::SQL_HAVING,
                    self::SQL_ORDER,
                    self::SQL_LIMIT,
                ]
            ),
            $this->params,
            $this->reset(),
            false,
            true
        );
    }

    /**
     * Function to find all records in database.
     *
     * @param array<int, int|string>|null $ids
     *                                         <p>
     *                                         If call this function using this param, we will find the record by using this id's.
     *                                         If not set, just find all records in database.
     *                                         </p>
     *
     * @return CollectionActiveRecord|\Generator|static[]
     * @psalm-return CollectionActiveRecord<TKey,T>
     */
    public function fetchAll(array $ids = null)
    {
        if ($ids) {
            $this->reset()->in($this->primaryKeyName, $ids);
        }

        return $this->query(
            $this->_buildSql(
                [
                    self::SQL_SELECT,
                    self::SQL_FROM,
                    self::SQL_JOIN,
                    self::SQL_WHERE,
                    self::SQL_GROUP,
                    self::SQL_HAVING,
                    self::SQL_ORDER,
                    self::SQL_LIMIT,
                ]
            ),
            $this->params,
            $this->reset(),
            false,
            true
        );
    }

    /**
     * Helper function to copy an existing active record (and insert it into the database).
     *
     * @param bool $insert
     *
     * @return static
     */
    public function copy(bool $insert = true): self
    {
        $new = clone $this;

        if ($insert) {
            /** @noinspection UnusedFunctionResultInspection */
            $new->setPrimaryKey(null);
            $id = $new->insert();
            /** @noinspection UnusedFunctionResultInspection */
            $new->setPrimaryKey($id);
        }

        return $new;
    }

    /**
     * @param mixed $primaryKey
     * @param bool  $dirty
     *
     * @return $this
     */
    public function setPrimaryKey($primaryKey, bool $dirty = true): self
    {
        if (!$this->db instanceof DB) {
            $this->db = DB::getInstance();
        }

        if (\property_exists($this, $this->primaryKeyName)) {
            $this->{$this->primaryKeyName} = $primaryKey;
        }

        if ($dirty === true) {
            $this->dirty[$this->primaryKeyName] = $this->db->escape($primaryKey);
        } else {
            $this->array[$this->primaryKeyName] = $primaryKey;
        }

        return $this;
    }

    /**
     * Function to build insert SQL, and insert current record into database.
     *
     * @return bool|int
     *                  <p>
     *                  If insert was successful, it will return the new id,
     *                  otherwise it will return false or true (if there are no dirty data).
     *                  </p>
     */
    public function insert()
    {
        if (!$this->db instanceof DB) {
            $this->db = DB::getInstance();
        }

        if (\count($this->dirty) === 0) {
            return true;
        }

        $value = $this->_filterParam($this->dirty);

        $fields = [];
        foreach ($this->dirty as $fieldTmp => $valueTmp) {
            $fields['`' . $fieldTmp . '`'] = $valueTmp;
        }

        /** @noinspection SqlNoDataSourceInspection */
        $this->setSqlExpressionHelper(
            self::SQL_INSERT,
            new ActiveRecordExpressions(
                [
                    ActiveRecordExpressions::OPERATOR => 'INSERT INTO ' . $this->table,
                    ActiveRecordExpressions::TARGET   => new ActiveRecordExpressionsWrap([ActiveRecordExpressions::TARGET => \array_keys($fields)]),
                ]
            )
        );

        $this->setSqlExpressionHelper(
            self::SQL_VALUES,
            new ActiveRecordExpressions(
                [
                    ActiveRecordExpressions::OPERATOR => 'VALUES',
                    ActiveRecordExpressions::TARGET   => new ActiveRecordExpressionsWrap([ActiveRecordExpressions::TARGET => $value]),
                ]
            )
        );

        $result = $this->execute($this->_buildSql([self::SQL_INSERT, self::SQL_VALUES]), $this->params);
        if (\is_int($result)) {
            $this->{$this->primaryKeyName} = $result;

            /** @noinspection UnusedFunctionResultInspection */
            $this->resetDirty();
            /** @noinspection UnusedFunctionResultInspection */
            $this->reset();

            return $result;
        }

        return false;
    }

    /**
     * Reset the dirty data.
     *
     * @return $this
     */
    public function resetDirty(): self
    {
        $this->dirty = [];

        return $this;
    }

    /**
     * Function to delete current record in database.
     *
     * @param string|null $keyOrNull
     *
     * @return bool
     */
    public function delete($keyOrNull = null): bool
    {
        if ($keyOrNull === null) {
            $keyOrNull = $this->{$this->primaryKeyName};
        }

        $return = $this->execute(
            $this->eq($this->primaryKeyName, $keyOrNull)->_buildSql(
                [
                    self::SQL_DELETE,
                    self::SQL_FROM,
                    self::SQL_WHERE,
                    self::SQL_LIMIT,
                ]
            ),
            $this->params
        );

        return $return !== false;
    }

    /**
     * @param mixed $id
     *
     * @throws FetchingException
     *                           <p>Will be thrown, if we can not find the id.</p>
     *
     * @return static
     */
    public function fetchById($id): self
    {
        $obj = $this->fetchByIdIfExists($id);
        if ($obj === null) {
            throw new FetchingException("No row with primary key '${id}' in table '{$this->table}'.");
        }

        return $obj;
    }

    /**
     * @param mixed $id
     *
     * @return static|null
     */
    public function fetchByIdIfExists($id)
    {
        if ($id === null) {
            return null;
        }

        $list = $this->fetch($id);

        if (!$list) {
            return null;
        }

        return $list;
    }

    /**
     * @return string|null
     */
    public function getHashId()
    {
        $key = $this->getPrimaryKey();

        if ($key) {
            $hashIds = $this->hashids->encode($key);

            return $hashIds[0];
        }

        return null;
    }

    /**
     * @param mixed $id
     *
     * @return string|null
     */
    public function convertIdIntoHashId($id)
    {
        if ($id) {
            return $this->hashids->encode($id);
        }

        return null;
    }

    /**
     * @param string $hashId
     *
     * @throws FetchingException
     *                           <p>Will be thrown, if we can not find the id.</p>
     *
     * @return static
     */
    public function fetchByHashId(string $hashId): self
    {
        $obj = $this->fetchByHashIdIfExists($hashId);
        if ($obj === null) {
            $ids = $this->hashids->decode($hashId);
            $id = $ids[0];

            throw new FetchingException("No row with primary key '${id}' in table '{$this->table}'.");
        }

        return $obj;
    }

    /**
     * @param string $hashId
     *
     * @return static|null
     */
    public function fetchByHashIdIfExists($hashId)
    {
        $ids = $this->hashids->decode($hashId);
        $id = $ids[0] ?? null;

        if ($id === null) {
            return null;
        }

        $list = $this->fetch($id);

        if (!$list) {
            return null;
        }

        return $list;
    }

    /**
     * @param array<int, int|string> $ids
     *
     * @return CollectionActiveRecord|static[]
     * @psalm-return CollectionActiveRecord<TKey,T>
     */
    public function fetchByIds(array $ids)
    {
        if (empty($ids)) {
            return new CollectionActiveRecord();
        }

        return $this->fetchAll($ids);
    }

    /**
     * @param array $ids
     *
     * @return CollectionActiveRecord|\Generator|static[]
     * @psalm-return CollectionActiveRecord<TKey,T>
     *
     * @noinspection PhpInconsistentReturnPointsInspection
     */
    public function fetchByIdsPrimaryKeyAsArrayIndex(array $ids)
    {
        $result = $this->fetchAll($ids);

        $resultNew = CollectionActiveRecord::createFromGeneratorFunction(
            static function () use ($result) {
                foreach ($result->getGenerator() as $item) {
                    yield $item->getPrimaryKey() => $item;
                }
            }
        );

        return $resultNew;
    }

    /**
     * @return mixed|null
     */
    public function getPrimaryKey()
    {
        $id = $this->{$this->primaryKeyName};
        if ($id) {
            return $id;
        }

        return null;
    }

    /**
     * @param string $query
     *
     * @return CollectionActiveRecord|\Generator|static[]
     * @psalm-return CollectionActiveRecord<TKey,T>
     */
    public function fetchManyByQuery(string $query)
    {
        return $this->query(
            $query,
            $this->params,
            $this->reset(),
            false,
            true
        );
    }

    /**
     * @param string $query
     *
     * @return CollectionActiveRecord|\Generator|static|static[]
     * @psalm-return CollectionActiveRecord<TKey,T>|static|static[]
     */
    public function fetchByQuery(string $query)
    {
        return $this->query(
            $query,
            $this->params,
            $this->reset(),
            false,
            true
        );
    }

    /**
     * @param string $query
     *
     * @return static|null
     */
    public function fetchOneByQuery(string $query)
    {
        $list = $this->fetchByQuery($query);

        $count = $list->count();

        if ($count === 0) {
            return null;
        }

        if ($count > 1) {
            throw new FetchOneButFoundMultiple('Found this results: ' . \print_r($list, true) . ' | Query: ' . $query);
        }

        if (isset($list[0])) {
            $this->array = $list[0]->getArray();
        } elseif ($list instanceof Arrayy) {
            $this->array = $list->getArray();
        }

        return $this;
    }

    /**
     * @param string $query
     *
     * @return static
     */
    public function fetchOneByQueryOrThrowException(string $query): self
    {
        $result = $this->fetchOneByQuery($query);

        if ($result === null) {
            throw new FetchOneButFoundNone('Query: ' . $query);
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getDirty(): array
    {
        return $this->dirty;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return string
     */
    public function getPrimaryKeyName(): string
    {
        return $this->primaryKeyName;
    }

    /**
     * @param string $primaryKeyName
     *
     * @return $this
     */
    public function setPrimaryKeyName(string $primaryKeyName): self
    {
        $this->primaryKeyName = $primaryKeyName;

        return $this;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @param string $table
     *
     * @return $this
     */
    public function setTable(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Helper function for "GROUP BY".
     *
     * @param mixed ...$args
     *
     * @return $this
     */
    public function groupBy(...$args): self
    {
        $this->__call(self::SQL_GROUP, $args);

        return $this;
    }

    /**
     * Helper function to add condition into WHERE.
     *
     * @param string $field
     *                                <p>The field name, the source of Expressions</p>
     * @param string $operator
     *                                <p>The operator for this condition.</p>
     * @param mixed  $value
     *                                <p>The target of the Expressions.</p>
     * @param string $operator_concat
     *                                <p>The operator to concat this Expressions into WHERE or SET statement.</p>
     * @param string $name
     *                                <p>The Expression will contact to.</p>
     *
     * @return $this
     */
    public function addCondition(
        string $field,
        string $operator,
        $value,
        string $operator_concat = 'AND',
        string $name = self::SQL_WHERE
    ): self {
        $field = '`' . $field . '`';
        $value = $this->_filterParam($value);

        if (\is_array($value)) {
            if (\strtolower($operator) === 'between') {
                $expression = new ActiveRecordExpressions(
                    [
                        ActiveRecordExpressions::SOURCE   => (\strtolower($name) === self::SQL_WHERE ? $this->table . '.' : '') . $field,
                        ActiveRecordExpressions::OPERATOR => $operator,
                        ActiveRecordExpressions::TARGET   => new ActiveRecordExpressionsWrap(
                            [
                                ActiveRecordExpressions::TARGET        => $value,
                                ActiveRecordExpressionsWrap::START     => ' ',
                                ActiveRecordExpressionsWrap::END       => ' ',
                                ActiveRecordExpressionsWrap::DELIMITER => ' AND ',
                            ]
                        ),
                    ]
                );
            } else {
                $expression = new ActiveRecordExpressions(
                    [
                        ActiveRecordExpressions::SOURCE   => (\strtolower($name) === self::SQL_WHERE ? $this->table . '.' : '') . $field,
                        ActiveRecordExpressions::OPERATOR => $operator,
                        ActiveRecordExpressions::TARGET   => new ActiveRecordExpressionsWrap(
                            [
                                ActiveRecordExpressions::TARGET => $value,
                            ]
                        ),
                    ]
                );
            }
        } else {
            $expression = new ActiveRecordExpressions(
                [
                    ActiveRecordExpressions::SOURCE   => (\strtolower($name) === self::SQL_WHERE ? $this->table . '.' : '') . $field,
                    ActiveRecordExpressions::OPERATOR => $operator,
                    ActiveRecordExpressions::TARGET   => $value,
                ]
            );
        }

        if ($this->wrap) {
            $this->_addExpression($expression, $operator_concat);
        } else {
            $this->_addCondition($expression, $operator_concat, $name);
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isNewDataAreDirty(): bool
    {
        return $this->new_data_are_dirty;
    }

    /**
     * @param bool $bool
     *
     * @return void
     *
     * @internal use it only if you know what you are doing
     */
    public function setNewDataAreDirty(bool $bool)
    {
        $this->new_data_are_dirty = $bool;
    }

    /**
     * Helper function to add condition into JOIN.
     *
     * @param string $table
     *                      <p>The join table name.</p>
     * @param string $on
     *                      <p>The condition of ON.</p>
     * @param string $type
     *                      <p>The join type, like "LEFT", "INNER", "OUTER".</p>
     *
     * @return $this
     */
    public function join(string $table, string $on, string $type = 'LEFT'): self
    {
        $this->setSqlExpressionHelper(
            self::SQL_JOIN,
            new ActiveRecordExpressions(
                [
                    ActiveRecordExpressions::SOURCE   => $this->getSqlExpressionHelper(self::SQL_JOIN) ?: '',
                    ActiveRecordExpressions::OPERATOR => $type . ' JOIN',
                    ActiveRecordExpressions::TARGET   => new ActiveRecordExpressions(
                        [
                            ActiveRecordExpressions::SOURCE   => $table,
                            ActiveRecordExpressions::OPERATOR => 'ON',
                            ActiveRecordExpressions::TARGET   => $on,
                        ]
                    ),
                ]
            )
        );

        return $this;
    }

    /**
     * Helper function for "ORDER BY".
     *
     * @param mixed ...$args
     *
     * @return $this
     */
    public function orderBy(...$args): self
    {
        $this->__call(self::SQL_ORDER, $args);

        return $this;
    }

    /**
     * set the DB connection.
     *
     * @param DB $db
     *
     * @return $this;
     */
    public function setDb(DB $db): self
    {
        $this->db = $db;

        return $this;
    }

    /**
     * Function to build update SQL, and update current record in database, just write the dirty data into database.
     *
     * @return bool|int
     *                  <p>
     *                  If update was successful, it will return the affected rows as int,
     *                  otherwise it will return false or true (if there are no dirty data).
     *                  </p>
     */
    public function update()
    {
        if (\count($this->dirty) === 0) {
            return true;
        }

        /** @noinspection AlterInForeachInspection */
        foreach ($this->dirty as $field => &$value) {
            $this->addCondition((string) $field, '=', $value, ',', self::SQL_SET);
        }

        $result = $this->execute(
            $this->eq($this->primaryKeyName, $this->{$this->primaryKeyName})->_buildSql(
                [
                    self::SQL_UPDATE,
                    self::SQL_SET,
                    self::SQL_WHERE,
                    self::SQL_LIMIT,
                ]
            ),
            $this->params
        );

        if (\is_int($result)) {
            /** @noinspection UnusedFunctionResultInspection */
            $this->resetDirty();
            /** @noinspection UnusedFunctionResultInspection */
            $this->reset();

            return $result;
        }

        return false;
    }

    /**
     * Make wrap when build the SQL expressions of WHERE.
     *
     * @param string|null $op
     *                        <p>If given, this param will build one "ActiveRecordExpressionsWrap" and include the
     *                        stored expressions add into WHERE, otherwise it will stored the expressions into an
     *                        array.</p>
     *
     * @return $this
     */
    public function wrap($op = null): self
    {
        if (\func_num_args() === 1) {
            $this->wrap = false;
            if (
                \is_array($this->expressions)
                &&
                \count($this->expressions) > 0
            ) {
                $this->_addCondition(
                    new ActiveRecordExpressionsWrap(
                        [
                            ActiveRecordExpressionsWrap::DELIMITER => ' ',
                            ActiveRecordExpressions::TARGET        => $this->expressions,
                        ]
                    ),
                    $op && \strtolower($op) === 'or' ? 'OR' : 'AND'
                );
            }
            $this->expressions = [];
        } else {
            $this->wrap = true;
        }

        return $this;
    }

    /**
     * @param string $dbProperty
     *
     * @return $this
     */
    public function select(string $dbProperty): self
    {
        $this->__call('select', [$dbProperty]);

        return $this;
    }

    /**
     * @param string                $dbProperty
     * @param float|int|string|null $value
     *
     * @return $this
     */
    public function eq(string $dbProperty, $value = null): self
    {
        $this->__call('eq', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string $table
     *
     * @return $this
     */
    public function from(string $table): self
    {
        $this->__call('from', [$table]);

        return $this;
    }

    /**
     * @param array ...$where
     *
     * @psalm-param array<string, int|float|null|string> ...$where
     *
     * @return $this
     *
     * @noinspection PhpDocSignatureInspection
     */
    public function whereEscape(array ...$where): Arrayy
    {
        if (!$this->db instanceof DB) {
            $this->db = DB::getInstance();
        }

        // init
        $whereStr = '';
        foreach ($where as $whereTmp) {
            $whereStr .= $this->db->_parseArrayPair($whereTmp, 'AND');
        }

        $this->__call('where', [$whereStr]);

        return $this;
    }

    /**
     * @param string $where
     * @param null   $dummy
     *
     * @return $this
     *
     * @deprecated please use "whereEscape()"
     * @noinspection OverridingDeprecatedMethodInspection
     */
    public function where(string $where, $dummy = null): Arrayy
    {
        $this->__call('where', [$where]);

        return $this;
    }

    /**
     * @param string $having
     *
     * @return $this
     */
    public function having(string $having): self
    {
        $this->__call('having', [$having]);

        return $this;
    }

    /**
     * @param int      $start
     * @param int|null $end
     *
     * @return $this
     */
    public function limit(int $start, $end = null): self
    {
        $this->__call('limit', [$start, $end !== null ? (int) $end : $end]);

        return $this;
    }

    /**
     * @param string                $dbProperty
     * @param float|int|string|null $value
     *
     * @return $this
     */
    public function equal(string $dbProperty, $value): self
    {
        $this->__call('equal', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string                $dbProperty
     * @param float|int|string|null $value
     *
     * @return $this
     */
    public function notEqual(string $dbProperty, $value): self
    {
        $this->__call('notEqual', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string                $dbProperty
     * @param float|int|string|null $value
     *
     * @return $this
     */
    public function ne(string $dbProperty, $value): self
    {
        $this->__call('ne', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string    $dbProperty
     * @param float|int $value
     *
     * @return $this
     */
    public function greaterThan(string $dbProperty, $value): self
    {
        $this->__call('greaterThan', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string    $dbProperty
     * @param float|int $value
     *
     * @return $this
     */
    public function gt(string $dbProperty, $value): self
    {
        $this->__call('gt', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string    $dbProperty
     * @param float|int $value
     *
     * @return $this
     */
    public function lessThan(string $dbProperty, $value): self
    {
        $this->__call('lessThan', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string    $dbProperty
     * @param float|int $value
     *
     * @return $this
     */
    public function lt(string $dbProperty, $value): self
    {
        $this->__call('lt', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string    $dbProperty
     * @param float|int $value
     *
     * @return $this
     */
    public function greaterThanOrEqual(string $dbProperty, $value): self
    {
        $this->__call('greaterThanOrEqual', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string    $dbProperty
     * @param float|int $value
     *
     * @return $this
     */
    public function ge(string $dbProperty, $value): self
    {
        $this->__call('ge', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string    $dbProperty
     * @param float|int $value
     *
     * @return $this
     */
    public function gte(string $dbProperty, $value): self
    {
        $this->__call('gte', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string    $dbProperty
     * @param float|int $value
     *
     * @return $this
     */
    public function lessThanOrEqual(string $dbProperty, $value): self
    {
        $this->__call('lessThanOrEqual', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string    $dbProperty
     * @param float|int $value
     *
     * @return $this
     */
    public function le(string $dbProperty, $value): self
    {
        $this->__call('le', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string    $dbProperty
     * @param float|int $value
     *
     * @return $this
     */
    public function lte(string $dbProperty, $value): self
    {
        $this->__call('lte', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string                            $dbProperty
     * @param array<int, float|int|string|null> $value
     *
     * @return $this
     */
    public function between(string $dbProperty, array $value): self
    {
        $this->__call('between', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string $dbProperty
     * @param string $value
     *
     * @return $this
     */
    public function like(string $dbProperty, string $value): self
    {
        $this->__call('like', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string                            $dbProperty
     * @param array<int, float|int|string|null> $value
     *
     * @return $this
     */
    public function in(string $dbProperty, array $value): self
    {
        $this->__call('in', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string                            $dbProperty
     * @param array<int, float|int|string|null> $value
     *
     * @return $this
     */
    public function notIn(string $dbProperty, array $value): self
    {
        $this->__call('notIn', [$dbProperty, $value]);

        return $this;
    }

    /**
     * @param string $dbProperty
     *
     * @return $this
     */
    public function isNull(string $dbProperty): self
    {
        $this->__call('isNull', [$dbProperty]);

        return $this;
    }

    /**
     * @param string $dbProperty
     *
     * @return $this
     */
    public function isNotNull(string $dbProperty): self
    {
        $this->__call('isNotNull', [$dbProperty]);

        return $this;
    }

    /**
     * @param string $dbProperty
     *
     * @return $this
     */
    public function notNull(string $dbProperty): self
    {
        $this->__call('notNull', [$dbProperty]);

        return $this;
    }

    /**
     * @return void
     */
    protected function init()
    {
        // can be overwritten in sub-classes
    }

    /**
     * This method can / should be overwritten, so that you can generate your own unique hash.
     *
     * @return HashidsInterface
     */
    protected function prepareHashids(): HashidsInterface
    {
        $this->hashids = new Hashids(self::class, 10);

        return $this->hashids;
    }

    /**
     * @param string            $relation_name
     * @param string            $relation_type               <p>self::HAS_MANY || self::HAS_ONE || self::BELONGS_TO</p>
     * @param string            $child_namespaced_classname
     * @param string            $foreign_key_of_child
     * @param array|null        $array_of_sql_part_functions
     * @param ActiveRecord|null $backref
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function addRelation(
        string $relation_name,
        string $relation_type,
        string $child_namespaced_classname,
        string $foreign_key_of_child,
        $array_of_sql_part_functions = null,
        $backref = null
    ) {
        if (
            $relation_type !== self::BELONGS_TO
            &&
            $relation_type !== self::HAS_ONE
            &&
            $relation_type !== self::HAS_MANY
        ) {
            throw new \InvalidArgumentException('$relation_type: must be "self::HAS_ONE" or "self::HAS_MANY"');
        }

        if (!\class_exists($child_namespaced_classname)) {
            throw new \InvalidArgumentException('$child_namespaced_classname: class ["' . $child_namespaced_classname . '"] does not exists, add a valid class name as parameter');
        }

        $this->relations[$relation_name] = [
            $relation_type,
            $child_namespaced_classname,
            $foreign_key_of_child,
            $array_of_sql_part_functions,
            $backref,
        ];
    }

    /**
     * Helper function to get relation of this object.
     *
     * There was three types of relations: {BELONGS_TO, HAS_ONE, HAS_MANY}
     *
     * @param string $name
     *                     <p>The name of the relation (the array key from the definition).</p>
     *
     * @throws ActiveRecordException
     *                               <p>If the relation can't be found .</p>
     *
     * @return mixed
     */
    protected function &getRelation(string $name)
    {
        $relation = $this->relations[$name];
        if (
            $relation instanceof self
            ||
            (
                isset($relation[0])
                &&
                $relation[0] instanceof self
            )
        ) {
            return $relation;
        }

        /* @var $obj self */
        $obj = new $relation[1]();

        $this->relations[$name] = $obj;
        if (isset($relation[3]) && \is_array($relation[3])) {
            foreach ((array) $relation[3] as $func => $args) {
                \call_user_func_array([$obj, $func], (array) $args);
            }
        }

        $backref = $relation[4] ?? '';
        $relationInstanceOfStatic = ($relation[1] instanceof static);
        if (
            $relationInstanceOfStatic === false
            &&
            $relation[0] === self::HAS_ONE
        ) {
            $this->relations[$name] = $obj->eq((string) $relation[2], $this->{$this->primaryKeyName})->fetch();

            if ($backref) {
                $obj->{$backref} = $this;
            }
        } elseif (
            $relation[0] === self::HAS_MANY
        ) {
            $this->relations[$name] = $obj->eq((string) $relation[2], $this->{$this->primaryKeyName})->fetchAll();
            if ($backref) {
                foreach ($this->relations[$name] as $o) {
                    $o->{$backref} = $this;
                }
            }
        } elseif (
            $relationInstanceOfStatic === false
            &&
            $relation[0] === self::BELONGS_TO
        ) {
            $this->relations[$name] = $obj->eq($obj->primaryKeyName, $this->{$relation[2]})->fetch();

            if ($backref) {
                $obj->{$backref} = $this;
            }
        } else {
            throw new ActiveRecordException("Relation ${name} not found.");
        }

        return $this->relations[$name];
    }

    /**
     * Helper function to build SQL with sql parts.
     *
     * @param string[] $sql_array
     *                            <p>The SQL part will be build.</p>
     *
     * @return string
     */
    protected function _buildSql(array $sql_array = []): string
    {
        \array_walk($sql_array, [$this, '_buildSqlCallback'], $this);

        // DEBUG
        //echo 'SQL: ', implode(' ', $sql_array), "\n", 'PARAMS: ', implode(', ', $this->params), "\n";

        return \implode(' ', $sql_array);
    }

    /**
     * Helper function to build place holder when make SQL expressions.
     *
     * @param mixed $value
     *                     <p>The value will be bind to SQL, just store it in $this->params.</p>
     *
     * @return mixed $value
     */
    protected function _filterParam($value)
    {
        if (\is_array($value)) {
            foreach ($value as $key => $val) {
                $this->params[$value[$key] = self::PREFIX . ++self::$count] = $val;
            }
        } elseif (\is_string($value)) {
            $this->params[$ph = self::PREFIX . ++self::$count] = $value;
            $value = $ph;
        }

        return $value;
    }

    /**
     * helper function to make wrapper. Stored the expression in to array.
     *
     * @param ActiveRecordExpressions $exp
     *                                          <p>The expression will be stored.</p>
     * @param string                  $operator
     *                                          <p>The operator to concat this Expressions into WHERE statement.</p>
     *
     * @return void
     */
    protected function _addExpression(ActiveRecordExpressions $exp, string $operator)
    {
        if (
            !\is_array($this->expressions)
            ||
            \count($this->expressions) === 0
        ) {
            $this->expressions = [$exp];
        } else {
            $this->expressions[] = new ActiveRecordExpressions(
                [
                    ActiveRecordExpressions::OPERATOR => $operator,
                    ActiveRecordExpressions::TARGET   => $exp,
                ]
            );
        }
    }

    /**
     * Helper function to add condition into WHERE.
     *
     * @param ActiveRecordExpressions $expression
     *                                            <p>The expression will be concat into WHERE or SET statement.</p>
     * @param string                  $operator
     *                                            <p>The operator to concat this Expressions into WHERE or SET
     *                                            statement.</p>
     * @param string                  $name
     *                                            <p>The Expression will contact to.</p>
     *
     * @return void
     */
    protected function _addCondition(
        ActiveRecordExpressions $expression,
        string $operator,
        string $name = self::SQL_WHERE
    ) {
        if ($this->{$name}) {
            $this->{$name}->target = new ActiveRecordExpressions(
                [
                    ActiveRecordExpressions::SOURCE   => $this->{$name}->target,
                    ActiveRecordExpressions::OPERATOR => $operator,
                    ActiveRecordExpressions::TARGET   => $expression,
                ]
            );
        } else {
            $this->{$name} = new ActiveRecordExpressions(
                [
                    ActiveRecordExpressions::OPERATOR => \strtoupper($name),
                    ActiveRecordExpressions::TARGET   => $expression,
                ]
            );
        }
    }

    /**
     * Helper function to query one record by sql and params.
     *
     * @param string    $sql
     *                          <p>
     *                          The SQL query to find the record.
     *                          </p>
     * @param array     $param
     *                          <p>
     *                          The param will be bind to the $sql query.
     *                          </p>
     * @param self|null $obj
     *                          <p>
     *                          The object, if find record in database, we will assign the attributes into
     *                          this object.
     *                          </p>
     * @param bool      $single
     *                          <p>
     *                          If set to true, we will find record and fetch in current object, otherwise
     *                          will find all records.
     *                          </p>
     * @param bool      $yield
     *                          <p>
     *                          If set to true, we will return a generator via yield.
     *                          </p>
     *
     * @return CollectionActiveRecord|false|\Generator|static|static[]
     * @psalm-return CollectionActiveRecord<TKey,T>|false|static|static[]
     *
     * @noinspection PhpInconsistentReturnPointsInspection
     */
    private function query(
        string $sql,
        array $param = [],
        self $obj = null,
        bool $single = false,
        bool $yield = false
    ) {
        $result = $this->execute($sql, $param);

        if (!$result instanceof Result) {
            return false;
        }

        if ($obj && \is_object($obj) === true) {
            $called_class = $obj;
        } else {
            $called_class = static::class;
        }

        $this->setNewDataAreDirty(false);

        if ($single) {
            if ($yield) {
                $return = static function () use ($result, $called_class) {
                    return $result->fetchYield($called_class, null, true);
                };
            } else {
                $return = $result->fetchObject($called_class, null, true);
            }
        } else {
            /** @noinspection NestedPositiveIfStatementsInspection */
            if ($yield) {
                $return = static function () use ($result, $called_class) {
                    return $result->fetchAllYield($called_class, null);
                };
            } else {
                $return = $result->fetchAllObject($called_class, null);
            }
        }

        $tmpCollection = null;
        if (!$single) {
            if (\is_callable($return)) {
                $tmpCollection = CollectionActiveRecord::createFromGeneratorFunction(
                    $return
                );
            } else {
                $tmpCollection = new CollectionActiveRecord();
                foreach ($return as $key => $value) {
                    $tmpCollection[$key] = $value;
                }
            }
        }

        $this->setNewDataAreDirty(true);

        return $tmpCollection ?? $return;
    }

    /**
     * @param string                           $sqlPart
     * @param \voku\db\ActiveRecordExpressions $expressions
     *
     * @return void
     */
    private function setSqlExpressionHelper(string $sqlPart, ActiveRecordExpressions $expressions)
    {
        $this->{$sqlPart} = $expressions;
    }

    /**
     * @param string $sqlPart
     *
     * @return ActiveRecordExpressions|null
     */
    private function getSqlExpressionHelper(string $sqlPart)
    {
        return $this->{$sqlPart};
    }

    /**
     * Helper function to build SQL with sql parts.
     *
     * @param string $sql_string_part
     *                                <p>The SQL part will be build.</p>
     * @param int    $index
     *                                <p>The index of $n in $sql array.</p>
     * @param self   $active_record
     *                                <p>The reference to $this.</p>
     *
     * @return void
     *
     * @noinspection PhpUnusedParameterInspection
     */
    private function _buildSqlCallback(string &$sql_string_part, int $index, self $active_record)
    {
        if (
            $sql_string_part === self::SQL_SELECT
            &&
            $active_record->{$sql_string_part} === null
        ) {
            $sql_string_part = \strtoupper($sql_string_part) . ' ' . $active_record->table . '.*';

            return;
        }

        if (
            (
                $sql_string_part === self::SQL_UPDATE
                ||
                $sql_string_part === self::SQL_FROM
            )
            &&
            $active_record->{$sql_string_part} === null
        ) {
            $sql_string_part = \strtoupper($sql_string_part) . ' ' . $active_record->table;

            return;
        }

        if ($sql_string_part === self::SQL_DELETE) {
            $sql_string_part = \strtoupper($sql_string_part) . ' ';

            return;
        }

        $sql_string_part = $active_record->{$sql_string_part} !== null ? $active_record->{$sql_string_part} . ' ' : '';
    }
}
