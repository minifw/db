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
use Minifw\DB\TableDiff;

class Field
{
    protected string $name;
    protected string $type;
    protected bool $nullable;
    protected bool $autoIncrement;
    protected ?string $charset;
    protected ?string $collate;
    protected ?string $comment;

    /**
     * 可能会未初始化.
     */
    protected ?string $default;

    public static function isCharType(string $type) : bool
    {
        if ($type == 'text' || strncmp($type, 'varchar', 7) == 0 || strncmp($type, 'char', 4) == 0) {
            return true;
        }

        return false;
    }

    public function __construct(array $cfg, string $tableCharset, string $tableCollate)
    {
        $fields = [
            'name' => true,
            'type' => true,
            'comment' => false,
        ];

        foreach ($fields as $name => $require) {
            if (!isset($cfg[$name]) || !is_string($cfg[$name])) {
                if ($require) {
                    throw new Exception('列数据不合法[' . $name . ']');
                } else {
                    $this->comment = null;
                }
            } else {
                $this->{$name} = (string) $cfg[$name];
            }
        }

        if (self::isCharType($this->type)) {
            if (isset($cfg['charset'])) {
                $this->charset = (string) $cfg['charset'];
            } else {
                $this->charset = $tableCharset;
            }

            if (isset($cfg['collate'])) {
                $this->collate = (string) $cfg['collate'];
            } else {
                $this->collate = $tableCollate;
            }
        } else {
            $this->charset = null;
            $this->collate = null;
        }

        if (!isset($cfg['nullable'])) {
            $this->nullable = false;
        } else {
            $this->nullable = !!($cfg['nullable']);
        }

        if (isset($cfg['autoIncrement'])) {
            $this->autoIncrement = !!($cfg['autoIncrement']);
        } else {
            $this->autoIncrement = false;
        }

        if (isset($cfg['default'])) {
            if ($cfg['default'] === null || is_string($cfg['default'])) {
                $this->default = $cfg['default'];
            } else {
                throw new Exception('列数据不合法[default]');
            }
        }
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function toArray() : array
    {
        $required = [
            'name' => true,
            'type' => true,
            'nullable' => true,
            'comment' => false,
            'autoIncrement' => false,
        ];

        $ret = [];
        foreach ($required as $name => $require) {
            if (isset($this->{$name})) {
                $ret[$name] = $this->{$name};
            } elseif ($require) {
                $ret[$name] = '';
            }
        }

        if (self::isCharType($this->type)) {
            $ret['charset'] = $this->charset;
            $ret['collate'] = $this->collate;
        }

        if (isset($this->default)) {
            $ret['default'] = $this->default;
        }

        return $ret;
    }

    public function toSql(bool $trimAutoIncreament = false) : string
    {
        $sql = '';
        if ($this->type == 'text') {
            $sql = '`' . $this->name . '` text CHARACTER SET ' . $this->charset . ' COLLATE ' . $this->collate;
            if (!$this->nullable) {
                $sql .= ' NOT NULL';
            }
        } else {
            $sql = '`' . $this->name . '` ' . $this->type;

            if (self::isCharType($this->type)) {
                $sql .= ' CHARACTER SET ' . $this->charset . ' COLLATE ' . $this->collate;
            }

            if (!$this->nullable) {
                $sql .= ' NOT NULL';
            }

            if ($this->autoIncrement && !$trimAutoIncreament) {
                $sql .= ' auto_increment';
            }

            if (isset($this->default)) {
                if ($this->default === null) {
                    $sql .= ' DEFAULT NULL';
                } else {
                    $sql .= ' DEFAULT \'' . str_replace('\'', '\'\'', $this->default) . '\'';
                }
            }
        }

        if (isset($this->comment) && $this->comment !== null) {
            $sql .= ' COMMENT \'' . str_replace('\'', '\'\'', $this->comment) . '\'';
        }

        return $sql;
    }

    public function isAutoIncrement() : bool
    {
        return $this->autoIncrement;
    }
}
