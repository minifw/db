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

    protected function __construct(Driver $driver, array $info)
    {
        parent::__construct($driver, $info);

        $fields = ['algorithm', 'security', 'sql'];

        foreach ($fields as $fname) {
            if (empty($info[$fname]) || !is_string($info[$fname])) {
                throw new Exception('数据不合法');
            }
        }

        foreach ($fields as $fname) {
            $this->{$fname} = $info[$fname];
        }
    }

    protected function toArray() : array
    {
        $ret = parent::toArray();

        $fields = ['algorithm', 'security', 'sql'];

        foreach ($fields as $fname) {
            $ret[$fname] = $this->{$fname};
        }

        return $ret;
    }

    public function toSql() : string
    {
        $sql = 'CREATE';

        if (!empty($this->algorithm)) {
            $sql .= ' ALGORITHM=' . $this->algorithm;
        }

        $user = $this->driver->getUser();

        if (!empty($user)) {
            $sql .= ' DEFINER=' . $user;
        }

        if (!empty($this->security)) {
            $sql .= ' SQL SECURITY ' . $this->security;
        }

        $sql .= ' VIEW `' . $this->tbname . '` AS ' . $this->sql;

        return $sql;
    }

    /////////////////////////////

    public function cmp(?Info $oldInfo) : TableDiff
    {
        if ($oldInfo !== null && !($oldInfo instanceof self)) {
            throw new Exception('数据不合法');
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
