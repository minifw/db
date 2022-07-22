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

namespace Minifw\DB\TableInfo\MysqlTableInfo;

use Minifw\Common\Exception;

class Index
{
    protected string $name;
    protected bool $unique = false;
    protected bool $fulltext = false;
    protected array $fields;
    protected string $comment = '';

    public function __construct(?array $cfg = null)
    {
        if ($cfg === null) {
            return;
        }

        $required = ['name', 'fields'];
        foreach ($required as $name) {
            if (!isset($cfg[$name])) {
                throw new Exception('数据不合法');
            }
            $this->set($name, $cfg[$name]);
        }

        $optional = ['unique', 'fulltext', 'comment'];
        foreach ($optional as $name) {
            if (!isset($cfg[$name])) {
                $this->set($name, null);
            } else {
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
            $this->name = $value;
        } elseif ($name == 'unique' || $name == 'fulltext') {
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
        $fields = ['name', 'unique', 'fulltext', 'fields', 'comment'];
        foreach ($fields as $field) {
            if (!isset($this->{$field})) {
                throw new Exception('对象未初始化');
            }
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

        if ($this->fulltext) {
            $ret['fulltext'] = $this->fulltext;
        }

        if ($this->comment !== '') {
            $ret['comment'] = $this->comment;
        }

        return $ret;
    }

    public function toSql(bool $inCreate)
    {
        $sql = '';
        if ($this->name == 'PRIMARY') {
            $sql = 'PRIMARY KEY (`' . implode('`,`', $this->fields) . '`)';
        } else {
            if ($inCreate) {
                if ($this->unique) {
                    $sql = 'UNIQUE ';
                } elseif ($this->fulltext) {
                    $sql = 'FULLTEXT ';
                }
                $sql .= 'KEY ';
            } else {
                if ($this->unique) {
                    $sql = 'UNIQUE ';
                } elseif ($this->fulltext) {
                    $sql = 'FULLTEXT ';
                } else {
                    $sql = 'INDEX ';
                }
            }
            $sql .= '`' . $this->name . '` (`' . implode('`,`', $this->fields) . '`)';
        }

        if ($this->comment !== '') {
            $sql .= ' COMMENT \'' . str_replace('\'', '\'\'', $this->comment) . '\'';
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
}
