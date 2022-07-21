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

use Minifw\DB\Driver\Driver;
use Minifw\Common\Exception;

class Index
{
    protected string $name;
    protected bool $unique;
    protected bool $fulltext;
    protected array $fields;
    protected ?string $comment;

    public function __construct(array $cfg)
    {
        if (!isset($cfg['name']) || empty($cfg['name'])) {
            throw new Exception('数据不合法');
        }
        $this->name = strval($cfg['name']);

        if (isset($cfg['unique'])) {
            $this->unique = !!($cfg['unique']);
        } else {
            $this->unique = false;
        }

        if (isset($cfg['fulltext'])) {
            $this->fulltext = !!($cfg['fulltext']);
        } else {
            $this->fulltext = false;
        }

        if (isset($cfg['comment'])) {
            $this->comment = strval($cfg['comment']);
        } else {
            $this->comment = null;
        }

        if (empty($cfg['fields']) || !is_array($cfg['fields'])) {
            throw new Exception('数据不合法');
        }

        $fields = [];
        foreach ($cfg['fields'] as $value) {
            $fields[] = strval($value);
        }

        $this->fields = $fields;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function toArray()
    {
        $ret = [
            'name' => $this->name,
            'unique' => $this->unique,
            'fulltext' => $this->fulltext,
            'fields' => $this->fields,
        ];

        if ($this->comment !== null) {
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

        if ($this->comment !== null) {
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
