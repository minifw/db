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

class Field
{
    protected string $name;
    protected string $type;
    protected string $comment = '';
    protected string $collate = 'binary';
    protected bool $nullable = false;
    protected bool $autoIncrement = false;

    /**
     * 可能会未初始化.
     * @var string|null|bool
     */
    protected $default = false;
    public static $collateHash = [
        'binary' => 'binary',
        'nocase' => 'nocase',
        'rtrim' => 'rtrim',
    ];
    public static $typeHash = [
        'integer' => 'integer',
        'text' => 'text',
        'real' => 'real',
        'blob' => 'blob',
    ];
    public static $typeAlias = [
        'int' => 'integer',
        'tinyint' => 'integer',
        'smallint' => 'integer',
        'mediumint' => 'integer',
        'bigint' => 'integer',
        'varchar' => 'text',
        'char' => 'text',
        'double' => 'real',
        'float' => 'real',
    ];

    public static function isCharType(string $type) : bool
    {
        if ($type == 'text') {
            return true;
        }

        return false;
    }

    public function __construct(?array $cfg = null)
    {
        if ($cfg === null) {
            return;
        }

        $fields = ['name', 'type', 'comment', 'nullable', 'autoIncrement', 'default'];
        foreach ($fields as $field) {
            if (isset($cfg[$field])) {
                $this->set($field, $cfg[$field]);
            }
        }

        if (self::isCharType($this->type)) {
            if (isset($cfg['collate'])) {
                $this->set('collate', $cfg['collate']);
            }
        }
    }

    public function set(string $name, $value) : void
    {
        $stringFields = [
            'name' => true,
            'type' => true,
            'comment' => false,
        ];

        if (isset($stringFields[$name])) {
            if (is_string($value) && $value !== '') {
                if ($name == 'type') {
                    $value = strtolower($value);
                    if (isset(self::$typeAlias[$value])) {
                        $value = self::$typeAlias[$value];
                    }
                    if (!isset(self::$typeHash[$value])) {
                        throw new Exception('列类型不合法:' . $value);
                    }
                }
                $this->{$name} = $value;
            } elseif ($stringFields[$name]) {
                throw new Exception('列数据不合法');
            } else {
                $this->{$name} = '';
            }
        } elseif ($name == 'collate') {
            if (!isset($this->type) || !is_string($value) || $value === '') {
                throw new Exception('初始化存在问题');
            }

            if (self::isCharType($this->type)) {
                $value = strtolower($value);
                if (!isset(self::$collateHash[$value])) {
                    throw new Exception('列数据不合法');
                }
                $this->{$name} = (string) $value;
            } else {
                $this->{$name} = '';
            }
        } elseif ($name == 'nullable' || $name == 'autoIncrement') {
            if (is_bool($value)) {
                $this->{$name} = $value;
            } else {
                $this->{$name} = false;
            }
        } elseif ($name == 'default') {
            if ($value === null || is_string($value)) {
                $this->default = $value;
            } else {
                throw new Exception('列数据不合法');
            }
        } else {
            throw new Exception('数据不合法');
        }
    }

    public function validate() : void
    {
        $require = [
            'name' => true,
            'type' => true,
        ];

        foreach ($require as $name => $notNull) {
            if (!isset($this->{$name})) {
                throw new Exception('对象未初始化');
            }
        }
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function toArray() : array
    {
        $this->validate();

        $ret = [
            'name' => $this->name,
            'type' => $this->type
        ];

        if ($this->autoIncrement) {
            $ret['autoIncrement'] = $this->autoIncrement;
        }

        if ($this->nullable) {
            $ret['nullable'] = $this->nullable;
        }

        if ($this->comment !== '') {
            $ret['comment'] = $this->comment;
        }

        if (self::isCharType($this->type)) {
            $ret['collate'] = $this->collate;
        }

        if ($this->default !== false) {
            $ret['default'] = $this->default;
        }

        return $ret;
    }

    public function toSql(bool $comment = false) : string
    {
        $this->validate();

        $sql = '`' . Scanner::escape($this->name, '`') . '` ' . $this->type;

        if (self::isCharType($this->type)) {
            $sql .= ' COLLATE ' . $this->collate;
        }

        if (!$this->nullable) {
            $sql .= ' NOT NULL';
        }

        if ($this->default !== false) {
            if ($this->default === null) {
                $sql .= ' DEFAULT NULL';
            } else {
                $sql .= ' DEFAULT \'' . Scanner::escape($this->default, '\'') . '\'';
            }
        }

        if ($this->autoIncrement) {
            $sql .= ' PRIMARY KEY AUTOINCREMENT';
        }

        if ($comment && $this->comment !== null && $this->comment !== '') {
            $sql .= ' /* ' . $this->comment . ' */';
        }

        return $sql;
    }

    public function isAutoIncrement() : bool
    {
        return $this->autoIncrement;
    }
}
