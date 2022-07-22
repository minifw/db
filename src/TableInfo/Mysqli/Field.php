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

namespace Minifw\DB\TableInfo\Mysqli;

use Minifw\Common\Exception;

class Field
{
    protected string $name;
    protected string $type;
    protected string $attr = '';
    protected string $comment = '';
    protected string $charset = '';
    protected string $collate = '';
    protected bool $nullable = false;
    protected bool $autoIncrement = false;
    protected bool $unsigned = false;
    protected bool $zerofill = false;

    /**
     * 可能会未初始化.
     * @var string|null|bool
     */
    protected $default = false;

    public static function isCharType(string $type) : bool
    {
        if ($type == 'text' || strncmp($type, 'varchar', 7) == 0 || strncmp($type, 'char', 4) == 0) {
            return true;
        }

        return false;
    }

    public function __construct(?array $cfg = null, ?string $tableCharset = null, ?string $tableCollate = null)
    {
        if ($cfg === null) {
            return;
        }

        if ($tableCharset === null || $tableCollate === null) {
            throw new Exception('参数不合法');
        }

        $fields = ['name', 'type', 'attr', 'comment', 'nullable', 'autoIncrement', 'unsigned', 'zerofill'];
        foreach ($fields as $field) {
            if (isset($cfg[$field])) {
                $this->set($field, $cfg[$field]);
            } else {
                $this->set($field, null);
            }
        }

        if (self::isCharType($this->type)) {
            if (!isset($cfg['charset'])) {
                $this->set('charset', $tableCharset);
            } else {
                $this->set('charset', $cfg['charset']);
            }
            if (!isset($cfg['collate'])) {
                $this->set('collate', $tableCollate);
            } else {
                $this->set('collate', $cfg['collate']);
            }
        }

        if (isset($cfg['default'])) {
            $this->set('default', $cfg['default']);
        }
    }

    public function set(string $name, $value) : void
    {
        $stringFields = [
            'name' => true,
            'type' => true,
            'comment' => false,
            'attr' => false,
        ];

        if (isset($stringFields[$name])) {
            if (is_string($value) && $value !== '') {
                if ($name == 'type') {
                    $value = strtolower($value);
                }
                $this->{$name} = $value;
            } elseif ($stringFields[$name]) {
                throw new Exception('列数据不合法');
            } else {
                $this->{$name} = '';
            }
        } elseif ($name == 'charset' || $name == 'collate') {
            if (!isset($this->type) || !is_string($value) || $value === '') {
                throw new Exception('初始化存在问题');
            }

            if (self::isCharType($this->type)) {
                $value = strtolower($value);
                $this->{$name} = (string) $value;
            } else {
                $this->{$name} = '';
            }
        } elseif ($name == 'nullable' || $name == 'autoIncrement' || $name == 'unsigned' || $name == 'zerofill') {
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
            'comment' => false,
            'charset' => false,
            'collate' => false,
            'nullable' => true,
            'autoIncrement' => true,
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

        if ($this->attr !== '') {
            $ret['attr'] = $this->attr;
        }

        if ($this->unsigned) {
            $ret['unsigned'] = $this->unsigned;
        }

        if ($this->autoIncrement) {
            $ret['autoIncrement'] = $this->autoIncrement;
        }

        if ($this->nullable) {
            $ret['nullable'] = $this->nullable;
        }

        if ($this->zerofill) {
            $ret['zerofill'] = $this->zerofill;
        }

        if ($this->comment !== '') {
            $ret['comment'] = $this->comment;
        }

        if (self::isCharType($this->type)) {
            $ret['charset'] = $this->charset;
            $ret['collate'] = $this->collate;
        }

        if ($this->default !== false) {
            $ret['default'] = $this->default;
        }

        return $ret;
    }

    public function toSql(bool $trimAutoIncreament = false) : string
    {
        $this->validate();

        $sql = '';
        if ($this->type == 'text') {
            $sql = '`' . $this->name . '` text CHARACTER SET ' . $this->charset . ' COLLATE ' . $this->collate;
            if (!$this->nullable) {
                $sql .= ' NOT NULL';
            }
        } else {
            $sql = '`' . $this->name . '` ' . $this->type;

            if ($this->attr !== '') {
                $sql .= '(' . $this->attr . ')';
            }

            if ($this->unsigned) {
                $sql .= ' unsigned';
            }

            if ($this->zerofill) {
                $sql .= ' zerofill';
            }

            if (self::isCharType($this->type)) {
                $sql .= ' CHARACTER SET ' . $this->charset . ' COLLATE ' . $this->collate;
            }

            if (!$this->nullable) {
                $sql .= ' NOT NULL';
            }

            if ($this->autoIncrement && !$trimAutoIncreament) {
                $sql .= ' auto_increment';
            }

            if ($this->default !== false) {
                if ($this->default === null) {
                    $sql .= ' DEFAULT NULL';
                } else {
                    $sql .= ' DEFAULT \'' . str_replace('\'', '\'\'', $this->default) . '\'';
                }
            }
        }

        if ($this->comment !== null && $this->comment !== '') {
            $sql .= ' COMMENT \'' . str_replace('\'', '\'\'', $this->comment) . '\'';
        }

        return $sql;
    }

    public function isAutoIncrement() : bool
    {
        return $this->autoIncrement;
    }
}
