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

namespace Minifw\DB\TableInfo\Sqlite3;

use Minifw\Common\Exception;
use Minifw\DB\Parser\Scanner;

class Index
{
    protected array $fields;
    protected string $name;
    protected bool $unique = false;
    protected string $comment = '';

    public function __construct(?array $cfg = null)
    {
        if ($cfg === null) {
            return;
        }

        $required = ['name', 'fields', 'unique', 'comment'];
        foreach ($required as $name) {
            if (isset($cfg[$name])) {
                $this->set($name, $cfg[$name]);
            }
        }
    }

    public function set(string $name, $value) : void
    {
        if ($name == 'name') {
            if (!is_string($value) || $value === '') {
                throw new Exception('数据不合法');
            }
            if (strtoupper($value) == 'PRIMARY') {
                $this->name = 'PRIMARY';
            } else {
                $this->name = $value;
            }
        } elseif ($name == 'unique') {
            if (is_bool($value)) {
                $this->{$name} = $value;
            } else {
                $this->{$name} = false;
            }
        } elseif ($name == 'comment') {
            if (is_string($value)) {
                $this->comment = $value;
            } else {
                $this->comment = '';
            }
        } elseif ($name == 'fields') {
            if (empty($value) || !is_array($value)) {
                throw new Exception('数据不合法');
            }

            $fields = [];
            foreach ($value as $value) {
                $fields[] = strval($value);
            }
            $this->fields = $fields;
        } else {
            throw new Exception('数据不合法');
        }
    }

    public function validate() : void
    {
        $fields = ['name', 'fields'];
        foreach ($fields as $field) {
            if (!isset($this->{$field})) {
                throw new Exception('对象未初始化');
            }
        }

        if (empty($this->fields)) {
            throw new Exception('对象未初始化');
        }
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function toArray()
    {
        $this->validate();

        $ret = [
            'name' => $this->name,
            'fields' => $this->fields,
        ];

        if ($this->unique) {
            $ret['unique'] = $this->unique;
        }

        if ($this->comment !== '') {
            $ret['comment'] = $this->comment;
        }

        return $ret;
    }

    public function toSql(string $tbname = '', bool $comment = false)
    {
        $sql = '';
        $fields = array_map(function ($str) {
            return Scanner::escape($str, '`');
        }, $this->fields);

        if ($this->name == 'PRIMARY') {
            $sql = 'PRIMARY KEY (`' . implode('`,`', $fields) . '`)';
        } else {
            if ($tbname === '') {
                throw new Exception('数据不合法');
            }
            $sql = 'CREATE';
            if ($this->unique) {
                $sql .= ' UNIQUE';
            }

            $sql .= ' INDEX `' . Scanner::escape($this->name, '`') . '` on `' . Scanner::escape($tbname, '`') . '` (`' . implode('`,`', $fields) . '`)';
        }

        if ($comment && $this->comment !== '') {
            $sql .= ' /* ' . $this->comment . ' */';
        }

        return $sql;
    }

    public function isAllRemoved(array $removed)
    {
        foreach ($this->fields as $field) {
            if (!isset($removed[$field])) {
                return false;
            }
        }

        return true;
    }

    public function isPrimary() : bool
    {
        return ($this->name == 'PRIMARY');
    }

    public function isOnlyField(string $field) : bool
    {
        if (count($this->fields) > 1) {
            return false;
        }

        foreach ($this->fields as $name) {
            if ($name != $field) {
                return false;
            }
        }

        return true;
    }

    public function has(string $field) : bool
    {
        foreach ($this->fields as $name) {
            if ($name == $field) {
                return true;
            }
        }

        return false;
    }
}
