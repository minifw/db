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
use Minifw\DB\Driver;
use Minifw\DB\Parser\Scanner;
use Minifw\DB\TableDiff;
use Minifw\DB\TableInfo;

class Table extends TableInfo
{
    protected Status $status;

    /**
     * @var array<Field>
     */
    protected array $field = [];

    /**
     * @var array<Index>
     */
    protected array $index = [];
    protected string $initTableSql = '';

    public function __construct(Driver $driver, ?array $info = null)
    {
        parent::__construct($driver, $info);
        parent::set('type', 'table');

        if ($info === null) {
            return;
        }

        if (!isset($info['status'])) {
            throw new Exception('数据不合法');
        }
        $status = new Status($info['status']);
        $this->set('status', $status);

        if (empty($info['field'])) {
            throw new Exception('数据不合法');
        }

        foreach ($info['field'] as $key => $value) {
            if (!isset($value['name'])) {
                $value['name'] = $key;
            }
            $field = new Field($value, $this->status->charset, $this->status->collate);
            $this->set('field', $field);
        }

        if (!isset($info['index']) || !is_array($info['index'])) {
            throw new Exception('数据不合法');
        }

        foreach ($info['index'] as $key => $value) {
            if (!isset($value['name'])) {
                $value['name'] = $key;
            }
            $index = new Index($value);
            $this->set('index', $index);
        }

        if (!empty($info['initTableSql'])) {
            $this->set('initTableSql', (string) $info['initTableSql']);
        }
    }

    public function set(string $name, $value) : void
    {
        if ($name === 'status') {
            if (!($value instanceof Status)) {
                throw new Exception('数据不合法');
            }
            $value->validate();
            $this->status = $value;
        } elseif ($name === 'field') {
            if (!($value instanceof Field)) {
                throw new Exception('数据不合法');
            }
            $value->validate();
            $this->field[$value->getName()] = $value;
        } elseif ($name === 'index') {
            if (!($value instanceof Index)) {
                throw new Exception('数据不合法');
            }
            $value->validate();
            $this->index[$value->getName()] = $value;
        } elseif ($name === 'initTableSql') {
            if (!is_string($value)) {
                throw new Exception('数据不合法');
            }
            $this->initTableSql = (string) $value;
        } else {
            parent::set($name, $value);
        }
    }

    public function toArray() : array
    {
        $this->validate();

        $ret = parent::toArray();

        $ret['status'] = $this->status->toArray();

        $ret['field'] = [];
        foreach ($this->field as $field) {
            $ret['field'][] = $field->toArray();
        }

        $ret['index'] = [];
        foreach ($this->index as $index) {
            $ret['index'][] = $index->toArray();
        }

        if (!empty($this->initTableSql)) {
            $ret['initTableSql'] = $this->initTableSql;
        }

        return $ret;
    }

    public function toSql($dim, $tmpname = '') : string
    {
        $this->validate();

        $tbname = $this->tbname;
        if ($tmpname !== '') {
            $tbname = $tmpname;
        }

        $sql = 'CREATE TABLE IF NOT EXISTS `' . Scanner::escape($tbname, '`') . '`';

        if ($this->status->comment !== '') {
            $sql .= ' /* ' . $this->status->comment . ' */';
        }
        $sql .= ' (' . $dim;

        $innerSql = [];
        $autoIncrement = null;
        foreach ($this->field as $name => $field) {
            $innerSql[] = $field->toSql(true);
            if ($field->isAutoIncrement()) {
                $autoIncrement = $name;
            }
        }

        if ($autoIncrement === null) {
            foreach ($this->index as $index) {
                if ($index->isPrimary()) {
                    $innerSql[] = $index->toSql();
                }
            }
        }

        $sql .= implode(',' . $dim, $innerSql) . $dim;

        $sql .= ')';

        $status = $this->status->toSql();
        if ($status !== '') {
            $sql .= ' ' . $status;
        }

        return $sql;
    }

    public function getIndexSql($tmpname = '') : array
    {
        $list = [];

        $tbname = $this->tbname;
        if ($tmpname !== '') {
            $tbname = $tmpname;
        }

        foreach ($this->index as $index) {
            if ($index->isPrimary()) {
                continue;
            }
            $list[] = $index->toSql($tbname, true);
        }

        return $list;
    }

    public function validate() : void
    {
        parent::validate();

        if (!isset($this->status)) {
            throw new Exception('对象未初始化');
        }

        if (empty($this->field)) {
            throw new Exception('对象未初始化');
        }

        $autoIncrement = null;
        foreach ($this->field as $name => $field) {
            if ($field->isAutoIncrement()) {
                if ($autoIncrement !== null) {
                    throw new Exception('存在多个自增字段');
                }
                $autoIncrement = $name;
            }
        }

        $primary = null;
        foreach ($this->index as $name => $index) {
            if ($index->isPrimary()) {
                if ($primary !== null) {
                    throw new Exception('存在多个主键');
                }
                $primary = $name;
                if ($autoIncrement !== null && !$index->isOnlyField($autoIncrement)) {
                    throw new Exception('数据不合法');
                }
            }
        }
    }

    /////////////////////////////

    public function cmp(?TableInfo $oldInfo) : TableDiff
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
            $this->status->cmp($this->tbname, $oldInfo->status, $diff);
            $this->calcIndexDel($oldInfo, $diff);
            $this->calcFieldDiff($oldInfo, $diff);
            $this->calcIndexDiff($oldInfo, $diff);

            if (!$diff->isPossible()) {
                $diff->setPossible(false);
                $this->calcRecreate($oldInfo, $diff);
            }
        }

        return $diff;
    }

    protected function calcFieldDiff(self $oldInfo, TableDiff $diff) : void
    {
        $this->calcFieldDel($oldInfo, $diff);
        $this->calcFieldChange($oldInfo, $diff);
        $this->calcFieldAdd($oldInfo, $diff);
    }

    protected function calcFieldDel(self $oldInfo, TableDiff $diff) : void
    {
        foreach ($oldInfo->field as $name => $field) {
            if (isset($this->field[$name])) {
                continue;
            }

            $sql = $field->toSql(true);
            $diff->addDisplay('- ' . $sql);
            $diff->setPossible(false);
        }
    }

    protected function calcFieldAdd(self $oldInfo, TableDiff $diff) : void
    {
        foreach ($this->field as $name => $field) {
            if (!isset($oldInfo->field[$name])) {
                $to_sql = $field->toSql(true);
                $diff->addDisplay('+ ' . $to_sql);

                if ($field->isAutoIncrement()) {
                    $diff->setPossible(false);
                } else {
                    $diff->addTrans('ALTER TABLE `' . Scanner::escape($this->tbname, '`') . '` ADD ' . $to_sql);
                }
            }
        }
    }

    protected function calcFieldChange(self $oldInfo, TableDiff $diff) : void
    {
        foreach ($this->field as $name => $field) {
            if (isset($oldInfo->field[$name])) {
                $from_sql = $oldInfo->field[$name]->toSql();
                $to_sql = $field->toSql();

                if ($from_sql !== $to_sql) {
                    $diff->addDisplay('- ' . $oldInfo->field[$name]->toSql(true));
                    $diff->addDisplay('+ ' . $field->toSql(true));

                    $diff->setPossible(false);
                }
            }
        }
    }

    protected function calcIndexDel(self $oldInfo, TableDiff $diff) : void
    {
        foreach ($oldInfo->index as $name => $index) {
            if (array_key_exists($name, $this->index)) {
                continue;
            }

            $from_sql = $index->toSql($this->tbname, true);
            $diff->addDisplay('- ' . $from_sql);

            $sql = 'DROP INDEX IF EXISTS `' . Scanner::escape($name, '`') . '`';
            $diff->addTrans($sql);
        }

        foreach ($this->index as $name => $index) {
            $to_sql = $index->toSql($this->tbname);

            if (!isset($oldInfo->index[$name])) {
                continue;
            }

            $from_sql = $oldInfo->index[$name]->toSql($this->tbname);

            if ($to_sql != $from_sql) {
                $from_sql = $oldInfo->index[$name]->toSql($this->tbname, true);

                $diff->addDisplay('- ' . $from_sql);

                if ($index->isPrimary() || $oldInfo->index[$name]->isPrimary()) {
                    $diff->setPossible(false);
                } else {
                    $sql = 'DROP INDEX IF EXISTS `' . Scanner::escape($name, '`') . '`';
                    $diff->addTrans($sql);
                }
            }
        }
    }

    protected function calcIndexDiff(self $oldInfo, TableDiff $diff) : void
    {
        foreach ($this->index as $name => $index) {
            $to_sql = $index->toSql($this->tbname);

            if (!isset($oldInfo->index[$name])) {
                $sql = $index->toSql($this->tbname, true);
                $diff->addDisplay('+ ' . $sql);
                if ($index->isPrimary()) {
                    $diff->setPossible(false);
                } else {
                    $diff->addTrans($sql);
                }
                continue;
            }

            $from_sql = $oldInfo->index[$name]->toSql($this->tbname);

            if ($to_sql != $from_sql) {
                $to_sql = $index->toSql($this->tbname, true);

                $diff->addDisplay('+ ' . $to_sql);

                if ($index->isPrimary() || $oldInfo->index[$name]->isPrimary()) {
                    $diff->setPossible(false);
                } else {
                    $diff->addTrans($to_sql);
                }
            }
        }
    }

    protected function calcCreateDiff(TableDiff $diff) : void
    {
        $display = $this->toSql("\n+ ");
        $createSql = $this->toSql("\n");

        $diff->addDisplay($display);
        $diff->addTrans($createSql);

        $indexList = $this->getIndexSql();

        foreach ($indexList as $index) {
            $diff->addDisplay('+ ' . $index);
            $diff->addTrans($index);
        }

        if (!empty($this->initTableSql)) {
            $diff->addDisplay('+' . $this->initTableSql);
            $diff->addTrans($this->initTableSql);
        }
    }

    protected function calcRecreate(self $oldInfo, TableDiff $diff) : void
    {
        $tables = $this->driver->getTables();

        $tableHash = [];
        foreach ($tables as $name) {
            $tableHash[$name] = 1;
        }

        $i = 0;
        $tmpName = 'tmp_' . $this->tbname . '_' . $i;
        while (true) {
            if (!isset($tableHash[$tmpName])) {
                break;
            }
            $i++;
            $tmpName = 'tmp_' . $this->tbname . '_' . $i;
        }

        $diff->addTrans('PRAGMA foreign_keys=\'0\'');

        $createSql = $this->toSql("\n", $tmpName);
        $diff->addTrans($createSql);

        $commonField = [];
        foreach ($this->field as $name => $field) {
            if (isset($oldInfo->field[$name])) {
                $commonField[] = Scanner::escape($name);
            }
        }

        $diff->addTrans('INSERT INTO `' . Scanner::escape($tmpName) . '` SELECT `' . implode('`,`', $commonField) . '` FROM `' . $this->tbname . '`');
        $diff->addTrans('PRAGMA defer_foreign_keys = \'1\'');
        $diff->addTrans('DROP TABLE `' . Scanner::escape($this->tbname) . '`');
        $diff->addTrans('ALTER TABLE `' . Scanner::escape($tmpName) . '` RENAME TO `' . Scanner::escape($this->tbname) . '`');
        $diff->addTrans('PRAGMA defer_foreign_keys = \'0\'');

        $indexList = $this->getIndexSql();

        foreach ($indexList as $sql) {
            $diff->addTrans($sql);
        }

        $diff->addTrans('PRAGMA foreign_keys=\'1\'');
    }
}
