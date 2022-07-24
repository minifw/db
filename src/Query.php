<?php

/*
 * Copyright (C) 2022 Yang Ming <yangming0116@163.com>.
 *
 * This library is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this library.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Minifw\DB;

use Minifw\Common\Exception;

class Query
{
    public static function get(?string $tbname = null, ?Driver $driver = null) : self
    {
        $obj = new self($driver);
        if (!empty($tbname)) {
            $obj->table($tbname);
        }

        return $obj;
    }

    public static function setDefaultDriver(Driver $driver) : void
    {
        self::$defaultDriver = $driver;
    }

    public static function getDefaultDriver() : Driver
    {
        return self::$defaultDriver;
    }

    public function __construct(?Driver $driver = null)
    {
        if (!empty($driver)) {
            $this->driver = $driver;
        } elseif (!empty(static::$defaultDriver)) {
            $this->driver = static::$defaultDriver;
        } else {
            throw new Exception('数据库未配置');
        }
    }

    public function buildSql(?self $parent = null) : string
    {
        if ($parent === null) {
            $this->bind = [];
            $this->parent = null;
        } else {
            $this->parent = $parent;
        }
        switch ($this->type) {
            case self::TYPE_SELECT_FIRST:
            case self::TYPE_SELECT_ALL:
            case self::TYPE_SELECT_COUNT:
            case self::TYPE_SELECT_HASH:
                $fieldList = $this->buildField();
                $from = $this->buildFrom();
                $whereList = $this->buildWhere();
                $groupList = $this->buildGroup();
                $orderList = $this->buildOrder();
                $limit = $this->buildLimit();
                $isLock = $this->buildLock();

                $sql = 'select ' . $fieldList . ' from ' . $from . $whereList . $groupList . $orderList . $limit . $isLock;

                return $sql;
            case self::TYPE_INSERT:
                $valueList = $this->buildValue();

                $sql = 'insert into ' . $this->tbname . ' ' . $valueList;

                return $sql;
            case self::TYPE_REPLACE:
                $valueList = $this->buildValue();

                $sql = 'replace into ' . $this->tbname . ' ' . $valueList;

                return $sql;
            case self::TYPE_UPDATE:
                $valueList = $this->buildUpdate();
                $whereList = $this->buildWhere();
                $orderList = $this->buildOrder();
                $limit = $this->buildLimit();

                $sql = 'update ' . $this->tbname . ' set ' . $valueList . $whereList . $orderList . $limit;

                return $sql;
            case self::TYPE_DELETE:
                $whereList = $this->buildWhere();
                $orderList = $this->buildOrder();
                $limit = $this->buildLimit();

                $sql = 'delete from ' . $this->tbname . $whereList . $orderList . $limit;

                return $sql;
            default:
                throw new Exception('参数不合法');
        }
    }

    public function buildBind() : array
    {
        return $this->bind;
    }

    public function dumpSql() : string
    {
        $sql = $this->buildSql() . ';';
        $bind = $this->buildBind();

        if (empty($bind)) {
            return $sql;
        }

        $strs = [];
        foreach ($bind as $k => $v) {
            $strs[] = $k . ':' . $v;
        }

        return $sql . ' [' . implode(', ', $strs) . ']';
    }

    /**
     * @throws Exception
     * @return mixed
     */
    public function exec()
    {
        switch ($this->type) {
            case self::TYPE_SELECT_ALL:
                $result = $this->driver->query($this);

                return $result;
            case self::TYPE_SELECT_COUNT:
                $result = $this->driver->queryOne($this);
                if (empty($result)) {
                    return 0;
                }

                return $result[0];
            case self::TYPE_SELECT_FIRST:
                $result = $this->driver->queryOne($this);

                return $result;
            case self::TYPE_SELECT_HASH:
                $result = $this->driver->queryHash($this);

                return $result;
            case self::TYPE_DELETE:
                $this->driver->exec($this);

                return null;
            case self::TYPE_INSERT:
                $this->driver->exec($this);

                return null;
            case self::TYPE_REPLACE:
                $this->driver->exec($this);

                return null;
            case self::TYPE_UPDATE:
                $this->driver->exec($this);

                return null;
            default:
                throw new Exception('参数不合法');
        }
    }

    public function query(string $sql, ?array $param = null)
    {
        switch ($this->type) {
            case self::TYPE_SELECT_ALL:
                $result = $this->driver->query($sql, Driver::FETCH_ASSOC, $param);

                return $result;
            case self::TYPE_SELECT_COUNT:
                $result = $this->driver->queryOne($sql, Driver::FETCH_ASSOC, $param);
                if (empty($result)) {
                    return 0;
                }

                return $result[0];
            case self::TYPE_SELECT_FIRST:
                $result = $this->driver->queryOne($sql, Driver::FETCH_ASSOC, $param);

                return $result;
            case self::TYPE_SELECT_HASH:
                $result = $this->driver->queryHash($sql, Driver::FETCH_ASSOC, $param);

                return $result;
            default:
                $this->driver->exec($sql, $param);

                return null;
        }
    }

    public function getDriver() : Driver
    {
        return $this->driver;
    }

    //////////////////////////////////////////////

    public function table(string $tbname, ?string $alis = null) : self
    {
        $this->tbname = $this->driver->quoteField($tbname);
        if (empty($alis)) {
            $this->alis = $this->tbname;
        } else {
            $this->alis = $this->driver->quoteField($alis);
        }

        return $this;
    }

    public function join($joinStr) : self
    {
        $this->joinStr = $joinStr;

        return $this;
    }

    public function where($whereList, bool $isOr = false) : self
    {
        $this->whereList = $whereList;
        if (is_string($this->whereList)) {
            $this->isOr = false;
        } else {
            $this->isOr = $isOr;
        }

        return $this;
    }

    public function group($groupList) : self
    {
        $this->groupList = $groupList;

        return $this;
    }

    public function order($orderList) : self
    {
        $this->orderList = $orderList;

        return $this;
    }

    public function lock(bool $isLock = true) : self
    {
        $this->isLock = $isLock;

        return $this;
    }

    public function limit(int $count, int $begin = 0) : self
    {
        $this->begin = $begin;
        $this->count = $count;

        return $this;
    }

    public function select($fieldList) : self
    {
        $this->fieldList = $fieldList;

        return $this;
    }

    //////////////////////////////////////

    public function count() : self
    {
        $this->type = self::TYPE_SELECT_COUNT;
        $this->fieldList = [
            ['expr', 'count(*)']
        ];

        return $this;
    }

    public function first() : self
    {
        $this->type = self::TYPE_SELECT_FIRST;

        return $this;
    }

    public function all() : self
    {
        $this->type = self::TYPE_SELECT_ALL;

        return $this;
    }

    public function hash() : self
    {
        $this->type = self::TYPE_SELECT_HASH;

        return $this;
    }

    public function insert(array $valueList) : self
    {
        $this->valueList = $valueList;
        $this->type = self::TYPE_INSERT;

        return $this;
    }

    public function replace(array $valueList) : self
    {
        $this->valueList = $valueList;
        $this->type = self::TYPE_REPLACE;

        return $this;
    }

    public function update(array $valueList) : self
    {
        $this->valueList = $valueList;
        $this->type = self::TYPE_UPDATE;

        return $this;
    }

    public function delete() : self
    {
        $this->type = self::TYPE_DELETE;

        return $this;
    }

    ///////////////////////////////////////////////////////////////

    protected function buildFrom() : string
    {
        $from = $this->tbname;
        if ($this->alis !== $this->tbname) {
            $from .= ' ' . $this->alis;
        }

        if (!empty($this->joinStr)) {
            $from .= ' ' . $this->joinStr;
        }

        return $from;
    }

    protected function buildGroup() : string
    {
        if (empty($this->groupList)) {
            return '';
        }
        if (is_array($this->groupList)) {
            $list = $this->driver->quoteField($this->groupList, true);
            $groupList = implode(',', $list);
        } else {
            $groupList = strval($this->groupList);
        }

        return ' group by ' . $groupList;
    }

    protected function buildOrder() : string
    {
        if (empty($this->orderList)) {
            return '';
        }
        if (is_array($this->orderList)) {
            $order_array = [];
            foreach ($this->orderList as $k => $v) {
                $order_array[] = $this->driver->quoteField($k) . ' ' . $v;
            }
            $orderList = implode(',', $order_array);
        } else {
            $orderList = strval($this->orderList);
        }

        return ' order by ' . $orderList;
    }

    protected function buildValue() : string
    {
        if (empty($this->valueList)) {
            return '';
        }

        $farr = [];
        $varr = [];

        foreach ($this->valueList as $k => $v) {
            $farr[] = $this->driver->quoteField($k);
            if (is_array($v)) {
                if ($v[0] == 'expr') {
                    $varr[] = strval($v[1]);
                } elseif ($v[0] == 'rich') {
                    $bind_key = $this->doBind($k, strval($v[1]));
                    $varr[] = ':' . $bind_key;
                } else {
                    throw new Exception('参数错误');
                }
            } else {
                $v = htmlspecialchars(strval($v));
                $bind_key = $this->doBind($k, $v);
                $varr[] = ':' . $bind_key;
            }
        }

        return '(' . implode(',', $farr) . ') values (' . implode(',', $varr) . ')';
    }

    protected function buildField() : string
    {
        if (empty($this->fieldList)) {
            return '*';
        }

        if (is_string($this->fieldList)) {
            return $this->fieldList;
        }

        if (!empty($this->joinStr)) {
            if ($this->alis) {
                $prefix = $this->driver->quoteField($this->alis) . '.';
            } else {
                $prefix = $this->driver->quoteField($this->tbname) . '.';
            }
        } else {
            $prefix = '';
        }

        $arr = [];
        foreach ($this->fieldList as $k => $v) {
            if (is_array($v)) {
                $val = $v;
                if ($v[0] == 'expr') {
                    $val = $v[1];
                }
                if (!is_int($k)) {
                    $arr[] = $val . ' as ' . $this->driver->quoteField($k);
                } else {
                    $arr[] = $val;
                }
            } elseif (is_int($k)) {
                $arr[] = $prefix . $this->driver->quoteField($v);
            } else {
                $arr[] = $v . ' as ' . $this->driver->quoteField($k);
            }
        }

        return implode(',', $arr);
    }

    protected function buildUpdate() : string
    {
        $arr = [];

        foreach ($this->valueList as $k => $v) {
            if (is_array($v)) {
                if ($v[0] == 'expr') {
                    $arr[] = $this->driver->quoteField($k) . '=' . strval($v[1]);
                } elseif ($v[0] == 'rich') {
                    $bind_key = $this->doBind($k, strval($v[1]));
                    $arr[] = $this->driver->quoteField($k) . '=:' . $bind_key;
                } else {
                    throw new Exception('参数错误');
                }
            } else {
                $v = htmlspecialchars(strval($v));
                $bind_key = $this->doBind($k, $v);
                $arr[] = $this->driver->quoteField($k) . '=:' . $bind_key;
            }
        }

        return implode(',', $arr);
    }

    protected function buildLimit() : string
    {
        $limit = '';
        if ($this->count > 0) {
            if ($this->begin > 0) {
                $limit .= $this->begin . ',';
            }
            $limit = ' limit ' . $limit . $this->count;
        }

        return $limit;
    }

    protected function buildWhere() : string
    {
        if (empty($this->whereList)) {
            return '';
        } elseif (is_string($this->whereList)) {
            return ' where ' . $this->whereList;
        }

        $condition = [];
        foreach ($this->whereList as $key => $where) {//循环处理条件数组,key为字段名，where为限制条件
            if (!empty($this->joinStr)) {//存在连表查询时在字段名前添加表名
                $fkey = $this->tbname . '.' . $this->driver->quoteField($key);
            } else {
                $fkey = $this->driver->quoteField($key);
            }

            if (is_array($where)) {//限制条件是数组
                if (is_array($where[0])) {//如果数字的第一个元素也是数组，则说明where的每一个元素都是一个限制条件
                    foreach ($where as $one) {
                        $condition[] = $this->_parseOpt($key, $one, $fkey);
                    }
                } else {//否则where自身是一个限制条件
                    $condition[] = $this->_parseOpt($key, $where, $fkey);
                }
            } else {//如果限制条件不是数组，按照`=`处理
                $bind_key = $this->doBind($key, $where);
                $condition[] = $fkey . '=:' . $bind_key;
            }
        }

        if (empty($condition)) {
            return '';
        }

        if ($this->isOr) {
            return ' where ' . implode(' or ', $condition);
        } else {
            return ' where ' . implode(' and ', $condition);
        }
    }

    protected function buildLock() : string
    {
        if ($this->isLock) {
            return ' for update';
        }

        return '';
    }

    //////////////////////////////////////////////

    protected function _parseOpt(string $key, array $value, string $fkey) : string
    {
        $value[0] = strval($value[0]);
        switch ($value[0]) {
            case '>':
            case '<':
            case '=':
            case '>=':
            case '<=':
            case '<>':
                $bind_key = $this->doBind($key, strval($value[1]));

                return $fkey . $value[0] . ':' . $bind_key;
            case 'between':
                $bind_key_min = $this->doBind($key . '_min', strval($value[1]));
                $bind_key_max = $this->doBind($key . '_max', strval($value[2]));

                return $fkey . ' between :' . $bind_key_min . ' and :' . $bind_key_max;
            case 'have':
                $bind_key = $this->doBind($key, '%' . $this->driver->escapeLike($value[1]) . '%');

                return $fkey . ' like :' . $bind_key;
            case 'end':
                $bind_key = $this->doBind($key, '%' . $this->driver->escapeLike($value[1]));

                return $fkey . ' like :' . $bind_key;
            case 'begin':
                $bind_key = $this->doBind($key, $this->driver->escapeLike($value[1]) . '%');

                return $fkey . ' like :' . $bind_key;
            case 'nohave':
                $bind_key = $this->doBind($key, '%' . $this->driver->escapeLike($value[1]) . '%');

                return $fkey . ' not like :' . $bind_key;
            case 'in':
                if (is_array($value[1])) {
                    return $fkey . ' in (' . implode(',', $this->driver->quoteValue($value[1], true)) . ')';
                } elseif ($value[1] instanceof Query) {
                    $sub = $value[1];

                    if ($this->parent !== null) {
                        $sql = $sub->buildSql($this->parent);
                    } else {
                        $sql = $sub->buildSql($this);
                    }

                    return $fkey . ' in (' . $sql . ')';
                } else {
                    throw new Exception('查询条件错误');
                }
                // no break
            default:
                throw new Exception('查询条件错误');
        }
    }

    protected function doBind(string $key, string $value) : string
    {
        $bind_key = $key;
        $value = strval($value);
        $index = 0;

        $obj = $this;
        if ($this->parent !== null) {
            $obj = $this->parent;
        }
        while (true) {
            if (isset($obj->bind[$bind_key]) && $obj->bind[$bind_key] !== $value) {
                $bind_key = $key . '_' . ++$index;
                continue;
            }

            $obj->bind[$bind_key] = $value;

            return $bind_key;
        }
    }
    protected Driver $driver;
    protected static ?Driver $defaultDriver = null;
    protected string $tbname;
    protected string $alis;
    ///////////////////

    protected $fieldList = [];
    protected string $joinStr = '';
    protected $whereList = [];
    protected bool $isOr = false;
    protected $groupList = [];
    protected $orderList = [];
    protected array $valueList = [];
    protected bool $isLock = false;
    protected int $begin = 0;
    protected int $count = 0;
    protected int $type = 0;
    ///////////////////////

    protected array $bind = [];
    protected ?self $parent = null;
    public const TYPE_NONE = 0;
    public const TYPE_SELECT_COUNT = 1;
    public const TYPE_SELECT_FIRST = 2;
    public const TYPE_SELECT_ALL = 3;
    public const TYPE_SELECT_HASH = 4;
    public const TYPE_INSERT = 5;
    public const TYPE_REPLACE = 6;
    public const TYPE_UPDATE = 7;
    public const TYPE_DELETE = 8;
}
