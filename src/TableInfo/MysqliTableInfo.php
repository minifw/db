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

use Minifw\DB\Driver\Driver;
use Minifw\Common\Exception;
use Minifw\DB\TableInfo\MysqlTableInfo\Status;
use Minifw\DB\TableInfo\MysqlTableInfo\Field;
use Minifw\DB\TableInfo\MysqlTableInfo\Index;
use Minifw\DB\TableDiff;

class MysqliTableInfo extends Info
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

    protected function toArray() : array
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

    public function toSql($dim) : string
    {
        $this->validate();

        $sql = 'CREATE TABLE IF NOT EXISTS `' . $this->tbname . '` (' . $dim;
        $lines = [];

        foreach ($this->field as $field) {
            $lines[] = $field->toSql();
        }

        foreach ($this->index as $index) {
            $lines[] = $index->toSql(true);
        }

        $sql .= implode(',' . $dim, $lines) . $dim . ') ';
        $sql .= $this->status->toSql();

        return $sql;
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
            $this->status->cmp($this->tbname, $oldInfo->status, $diff);
            $removed = $this->calcFieldDiff($oldInfo, $diff);
            $this->calcIndexDiff($oldInfo, $diff, $removed);
        }

        return $diff;
    }

    protected function calcFieldDiff(self $oldInfo, TableDiff $diff) : array
    {
        $removed = $this->calcFieldDel($oldInfo, $diff);
        $this->calcFieldChange($oldInfo, $diff);
        $this->calcFieldAdd($oldInfo, $diff);

        return $removed;
    }

    protected function calcFieldDel(self $oldInfo, TableDiff $diff) : array
    {
        $i = 0;
        $removed = [];
        foreach ($oldInfo->field as $name => $field) {
            $i++;
            if (isset($this->field[$name])) {
                continue;
            }

            $sql = $field->toSql();
            $diff->addDisplay('-[' . $i . '] ' . $sql);
            $diff->addTrans('ALTER TABLE `' . $this->tbname . '` DROP `' . $name . '`');

            $removed[$name] = 1;
        }

        return $removed;
    }

    protected function calcFieldAdd(self $oldInfo, TableDiff $diff) : void
    {
        $i = 0;
        $tail = ' first';
        foreach ($this->field as $name => $field) {
            $i++;

            if (!isset($oldInfo->field[$name])) {
                $to_sql = $field->toSql();
                $diff->addDisplay('+[' . $i . '] ' . $to_sql);

                if ($field->isAutoIncrement()) {//对于新增了auto_increment属性的列，需要先添加一个不包含该属性的列，然后建立索引，最后再增加该属性
                    $diff->addTrans('ALTER TABLE `' . $this->tbname . '` ADD ' . $field->toSql(true) . $tail);
                    $diff->addLast('ALTER TABLE `' . $this->tbname . '` CHANGE `' . $name . '` ' . $to_sql . $tail);
                } else {
                    $diff->addTrans('ALTER TABLE `' . $this->tbname . '` ADD ' . $to_sql . $tail);
                }
            }

            $tail = ' after `' . $name . '`';
        }
    }

    protected function calcFieldChange(self $oldInfo, TableDiff $diff) : void
    {
        $i = 1;
        $left = 1;

        $fromNoOri = [];
        $fromNoCur = [];
        $toNo = [];

        //先确定剩余列的在旧结构中的编号以及在删除完之后剩余列中的编号
        foreach ($oldInfo->field as $name => $field) {
            $fromNoOri[$name] = $i++;
            if (!isset($this->fieldRemoved[$name])) {
                $fromNoCur[$name] = $left++;
            }
        }

        $i = 1;
        //列在新结构中的编号
        foreach ($this->field as $name => $field) {
            $toNo[$name] = $i++;
        }

        $curIndex = 0;
        $tail = ' first';
        foreach ($this->field as $name => $field) {
            $i++;

            if (isset($oldInfo->field[$name])) {
                $curIndex++;

                $from_sql = $oldInfo->field[$name]->toSql();
                $to_sql = $field->toSql();

                if ($from_sql !== $to_sql || $curIndex != $fromNoCur[$name]) {//列有变化或者位置不同
                    $diff->addDisplay('-[' . $fromNoOri[$name] . '] ' . $from_sql);
                    $diff->addDisplay('+[' . $toNo[$name] . '] ' . $to_sql);

                    if (!$oldInfo->field[$name]->isAutoIncrement() && $field->isAutoIncrement()) {//对于新增了auto_increment属性的列，需要先添加一个不包含该属性的列，然后建立索引，最后再增加该属性
                        $diff->addTrans('ALTER TABLE `' . $this->tbname . '` CHANGE `' . $name . '` ' . $field->toSql(true) . $tail);
                        $diff->addLast('ALTER TABLE `' . $this->tbname . '` CHANGE `' . $name . '` ' . $to_sql . $tail);
                    } else {
                        $diff->addTrans('ALTER TABLE `' . $this->tbname . '` CHANGE `' . $name . '` ' . $to_sql . $tail);
                    }

                    foreach ($oldInfo->field as $k1 => $v1) {//调整位置之后，所有在该列之前的列统一后移一位
                        if (isset($fromNoCur[$k1]) && $fromNoCur[$k1] < $fromNoCur[$name]) {
                            $fromNoCur[$k1]++;
                        }
                    }
                }

                $tail = ' after `' . $name . '`';
            }
        }
    }

    protected function calcIndexDiff(self $oldInfo, TableDiff $diff, array $removed) : void
    {
        foreach ($this->index as $name => $index) {
            $to_sql = $index->toSql(false);

            if (!isset($oldInfo->index[$name])) {
                $diff->addDisplay('+ ' . $to_sql);
                $diff->addTrans('ALTER TABLE `' . $this->tbname . '` ADD ' . $to_sql);
                continue;
            }

            $from_sql = $oldInfo->index[$name]->toSql(false);

            if ($to_sql != $from_sql) {
                $trans = 'ALTER TABLE `' . $this->tbname . '` DROP';
                if ($name == 'PRIMARY') {
                    $trans .= ' PRIMARY KEY';
                } else {
                    $trans .= ' INDEX `' . $name . '`';
                }
                $trans .= ', ADD ' . $to_sql;

                $diff->addDisplay('- ' . $from_sql);
                $diff->addDisplay('+ ' . $to_sql);
                $diff->addTrans($trans);
            }
        }

        foreach ($oldInfo->index as $name => $index) {
            if (array_key_exists($name, $this->index)) {
                continue;
            }

            $from_sql = $index->toSql(false);
            $diff->addDisplay('- ' . $from_sql);

            if ($index->isAllRemoved($removed)) {
                continue;
            }

            if ($name == 'PRIMARY') {
                $trans = 'ALTER TABLE `' . $this->tbname . '` DROP PRIMARY KEY';
            } else {
                $trans = 'ALTER TABLE `' . $this->tbname . '` DROP INDEX `' . $name . '`';
            }

            $diff->addTrans($trans);
        }
    }

    protected function calcCreateDiff(TableDiff $diff) : void
    {
        $diff->addDisplay('+' . $this->toSql("\n+ "));
        $diff->addTrans($this->toSql(''));

        if (!empty($this->initTableSql)) {
            $diff->addDisplay('+' . $this->initTableSql);
            $diff->addTrans($this->initTableSql);
        }
    }
}
