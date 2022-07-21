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

namespace Minifw\DB\TableInfo;

use Minifw\Common\Exception;
use Minifw\DB\Driver\Driver;
use Minifw\DB\TableDiff;

class MysqliViewInfo extends Info
{
    protected string $algorithm;
    protected string $security;
    protected string $sql;

    public function __construct(Driver $driver, ?array $info = null)
    {
        parent::__construct($driver, $info);
        parent::set('type', 'view');

        if ($info === null) {
            return;
        }

        if (!isset($info['algorithm'])) {
            throw new Exception('数据不合法');
        }

        if (!isset($info['security'])) {
            throw new Exception('数据不合法');
        }

        if (!isset($info['sql'])) {
            throw new Exception('数据不合法');
        }

        $this->set('algorithm', $info['algorithm']);
        $this->set('security', $info['security']);
        $this->set('sql', $info['sql']);
    }

    public function set(string $name, $value) : void
    {
        if ($name === 'algorithm' || $name === 'security' || $name === 'sql') {
            if (empty($value) || !is_string($value)) {
                throw new Exception('数据不合法');
            }
            if ($name != 'sql') {
                $value = strtolower($value);
            }
            $this->{$name} = $value;
        } else {
            parent::set($name, $value);
        }
    }

    protected function toArray() : array
    {
        $ret = parent::toArray();

        $this->validate();

        $fields = ['algorithm', 'security', 'sql'];
        foreach ($fields as $fname) {
            $ret[$fname] = $this->{$fname};
        }

        return $ret;
    }

    public function toSql() : string
    {
        $this->validate();
        $user = $this->driver->getUser();
        if (empty($user) || !is_string($user)) {
            throw new Exception('对象未初始化');
        }

        $sql = 'CREATE ALGORITHM=' . $this->algorithm .
        ' DEFINER=' . $user .
        ' SQL SECURITY ' . $this->security .
        ' VIEW `' . $this->tbname . '` AS ' . $this->sql;

        return $sql;
    }

    public function validate() : void
    {
        parent::validate();

        $fields = ['algorithm', 'security', 'sql'];
        foreach ($fields as $fname) {
            if (!isset($this->{$fname})) {
                throw new Exception('对象未初始化');
            }
        }
    }

    /////////////////////////////

    public function cmp(?Info $oldInfo) : TableDiff
    {
        if ($oldInfo !== null && !($oldInfo instanceof self)) {
            throw new Exception('数据不合法');
        }

        $this->validate();
        if ($oldInfo !== null) {
            $oldInfo->validate();
        }

        $diff = new TableDiff();
        $diff->setTbname($this->tbname);

        if ($oldInfo === null) {
            $this->calcCreateDiff($diff);
        } else {
            $this->calcChangeDiff($oldInfo, $diff);
        }

        return $diff;
    }

    protected function calcCreateDiff(TableDiff $diff) : void
    {
        $sql = $this->toSql();

        $diff->addDisplay('+ ' . $sql);
        $diff->addTrans($sql);
    }

    protected function calcChangeDiff(self $oldInfo, TableDiff $diff) : void
    {
        $changed = false;
        if ($this->algorithm != $oldInfo->algorithm) {
            $changed = true;
            $diff->addDisplay('- ALGORITHM=' . $oldInfo->algorithm . " \n+ ALGORITHM=" . $this->algorithm);
        }

        if ($this->security != $oldInfo->security) {
            $changed = true;
            $diff->addDisplay('- SQL SECURITY=' . $oldInfo->security . " \n+ SQL SECURITY=" . $this->security);
        }

        if ($this->sql != $oldInfo->sql) {
            $changed = true;
            $diff->addDisplay('- SQL=' . $oldInfo->sql . " \n+ SQL=" . $this->sql);
        }

        if ($changed) {
            $diff->addTrans('DROP VIEW IF EXISTS `' . $this->tbname . '`');
            $diff->addTrans($this->toSql());
        }
    }
}
