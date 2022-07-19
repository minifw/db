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
use Minifw\DB\Driver\Driver;

abstract class Table
{
    public static string $tbname = '';
    public static array $status = [];
    public static array $field = [];
    public static array $index = [];
    protected Driver $driver;

    public static function get(?Driver $driver = null, ...$args) : Table
    {
        return new static($driver, ...$args);
    }

    public function getDriver() : Driver
    {
        return $this->driver;
    }

    public function query() : Query
    {
        return Query::get(static::$tbname);
    }

    public function add(array $post) : void
    {
        $data = $this->_prase($post, []);
        $this->query()->insert($data)->exec();
    }

    public function edit(array $post) : void
    {
        $id = isset($post['id']) ? intval($post['id']) : 0;
        $odata = $this->getById($id);
        if (empty($odata)) {
            throw new Exception('数据不存在');
        }
        $data = $this->_prase($post, $odata);

        $condition = [];
        $condition['id'] = $id;

        $this->query()->where($condition)->update($data)->exec();
    }

    /**
     * @param mixed $args
     */
    public function del($args) : void
    {
        $id = 0;
        if (is_array($args)) {
            $id = intval($args[0]);
        } else {
            $id = intval($args);
        }

        if ($id == 0) {
            return;
        }

        $condition = [
            'id' => $id
        ];

        $this->query()->where($condition)->delete()->exec();
    }

    /**
     * @param mixed $value
     */
    public function setField(int $id, string $field, $value)
    {
        $condition = [];
        $condition['id'] = intval($id);

        $data = [];
        $data[strval($field)] = $value;

        $this->query()->where($condition)->update($data)->exec();
    }

    public function getById(string $id, array $field = [], bool $lock = false) : ?array
    {
        $condition = [];
        $condition['id'] = intval($id);

        return $this->query()->where($condition)->select($field)->lock($lock)->first()->exec();
    }

    public function initTableSql() : string
    {
        return '';
    }

    ///////////////////////////////////////////////////

    protected function __construct(?Driver $driver = null)
    {
        $this->driver = $driver === null ? Query::getDefaultDriver() : $driver;
        if ($this->driver === null) {
            throw new Exception('runtime error');
        }
    }

    abstract protected function _prase(array $post, array $odata = []) : array;
}
