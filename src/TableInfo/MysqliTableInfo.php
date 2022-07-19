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

class MysqliTableInfo extends Info
{
    protected array $status;
    protected array $field;
    protected array $index;
    protected string $initTableSql;

    protected function init(array $info) : bool
    {
        $fields = ['status', 'field'];

        foreach ($fields as $fname) {
            if (empty($info[$fname]) || !is_array($info[$fname])) {
                return false;
            }
        }

        if (!isset($info['index']) || !is_array($info['index'])) {
            return false;
        }

        if (!parent::init($info)) {
            return false;
        }

        $fields = ['status', 'field', 'index'];
        foreach ($fields as $fname) {
            $this->{$fname} = $info[$fname];
        }

        if (!empty($info['initTableSql'])) {
            $this->initTableSql = strval($info['initTableSql']);
        } else {
            $this->initTableSql = '';
        }

        return true;
    }

    protected function toArray() : array
    {
        $ret = parent::toArray();

        $fields = ['status', 'field', 'index'];

        foreach ($fields as $fname) {
            $ret[$fname] = $this->{$fname};
        }

        if (!empty($this->initTableSql)) {
            $ret['initTableSql'] = $this->initTableSql;
        }

        return $ret;
    }
}
