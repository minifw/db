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
use Minifw\DB\TableDiff;

class Status
{
    protected string $engine;
    protected string $charset;
    protected string $collate;
    protected string $comment = '';
    protected string $checksum = '';
    protected string $rowFormat = '';

    public function __get($name)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }

        return null;
    }

    public function __construct(?array $cfg = null)
    {
        if ($cfg === null) {
            return;
        }

        $fields = ['engine', 'charset', 'collate', 'comment', 'checksum', 'rowFormat'];
        foreach ($fields as $name) {
            if (isset($cfg[$name])) {
                $this->set($name, $cfg[$name]);
            } else {
                $this->set($name, '');
            }
        }
    }

    public function set(string $name, $value) : void
    {
        $required = [
            'engine' => true,
            'charset' => true,
            'collate' => true,
            'checksum' => false,
            'comment' => false,
            'rowFormat' => false,
        ];

        if (!is_string($value) || $value === '') {
            if ($required[$name]) {
                throw new Exception('数据不合法');
            } else {
                $this->{$name} = '';
            }
        } else {
            $this->{$name} = strtolower($value);
        }
    }

    public function validate() : void
    {
        $required = [
            'engine' => true,
            'charset' => true,
            'collate' => true,
            'comment' => false,
            'checksum' => false,
            'rowFormat' => false,
        ];

        foreach ($required as $name => $require) {
            if (!isset($this->{$name})) {
                throw new Exception('对象未初始化');
            }
        }
    }

    public function toSql() : string
    {
        $this->validate();

        $sql = 'ENGINE=' . $this->engine . ' DEFAULT CHARSET=' . $this->charset . ' COLLATE=' . $this->collate;

        if (!empty($this->checksum)) {
            $sql .= ' CHECKSUM=' . $this->checksum;
        }

        if (!empty($this->rowFormat)) {
            $sql .= ' ROW_FORMAT=' . $this->rowFormat;
        }

        if (!empty($this->comment)) {
            $sql .= ' COMMENT=\'' . $this->comment . '\'';
        }

        return $sql;
    }

    public function toArray() : array
    {
        $this->validate();

        $fields = [
            'engine' => true,
            'charset' => true,
            'collate' => true,
            'comment' => false,
            'checksum' => false,
            'rowFormat' => false,
        ];

        $ret = [];
        foreach ($fields as $name => $require) {
            if (!empty($this->{$name})) {
                $ret[$name] = $this->{$name};
            }
        }

        return $ret;
    }

    public function cmp(string $tbname, self $old, TableDiff $diff) : void
    {
        $this->validate();
        $old->validate();

        if ($old->engine != $this->engine) {
            $diff->addDisplay('- ENGINE=' . $old->engine . "\n" . '+ ENGINE=' . $this->engine);
            $diff->addTrans('ALTER TABLE `' . $tbname . '` ENGINE=' . $this->engine);
        }

        if ($old->charset != $this->charset || $old->collate != $this->collate) {
            $from_charset = 'DEFAULT CHARSET=' . $old->charset . ' COLLATE ' . $old->collate;
            $to_charset = 'DEFAULT CHARSET=' . $this->charset . ' COLLATE ' . $this->collate;

            $diff->addDisplay('- ' . $from_charset . "'\n" . '+ ' . $to_charset);
            $diff->addTrans('ALTER TABLE `' . $tbname . '` ' . $to_charset);
        }

        if ($old->comment != $this->comment) {
            if ($old->comment !== '') {
                $diff->addDisplay('- COMMENT=\'' . strval($old->comment) . '\'');
            }
            if ($this->comment !== '') {
                $diff->addDisplay('+ COMMENT=\'' . strval($this->comment) . '\'');
            }
            $diff->addTrans('ALTER TABLE `' . $tbname . '` COMMENT=\'' . str_replace('\'', '\'\'', strval($this->comment)) . '\'');
        }

        if ($this->checksum !== '' && $old->checksum != $this->checksum) {
            if ($old->checksum !== '') {
                $diff->addDisplay('- CHECKSUM=' . $old->checksum);
            }
            $diff->addDisplay('+ CHECKSUM=' . $this->checksum);
            $diff->addTrans('ALTER TABLE `' . $tbname . '` CHECKSUM=' . $this->checksum);
        }

        if ($this->rowFormat !== '' && $old->rowFormat != $this->rowFormat) {
            if ($old->rowFormat !== '') {
                $diff->addDisplay('- ROW_FORMAT=' . $old->rowFormat);
            }
            $diff->addDisplay('+ ROW_FORMAT=' . $this->rowFormat);
            $diff->addTrans('ALTER TABLE `' . $tbname . '` ROW_FORMAT=' . $this->rowFormat);
        }
    }
}
