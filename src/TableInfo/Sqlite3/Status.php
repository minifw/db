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
use Minifw\DB\TableDiff;

class Status
{
    protected bool $rowid = true;
    protected string $comment = '';

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

        $fields = ['rowid',  'comment'];
        foreach ($fields as $name) {
            if (isset($cfg[$name])) {
                $this->set($name, $cfg[$name]);
            }
        }
    }

    public function set(string $name, $value) : void
    {
        if ($name == 'rowid') {
            if (is_bool($value)) {
                $this->rowid = $value;
            } else {
                $this->rowid = true;
            }
        } elseif ($name == 'comment') {
            if (is_string($value)) {
                $this->comment = $value;
            } else {
                $this->comment = '';
            }
        } else {
            throw new Exception('数据不合法');
        }
    }

    public function validate() : void
    {
        return;
    }

    public function toSql() : string
    {
        $this->validate();

        $sql = '';
        if (!$this->rowid) {
            $sql .= 'WITHOUT ROWID';
        }

        return $sql;
    }

    public function toArray() : array
    {
        $this->validate();

        $ret = [
            'rowid' => $this->rowid,
        ];

        if ($this->comment !== '') {
            $ret['comment'] = $this->comment;
        }

        return $ret;
    }

    public function cmp(string $tbname, self $old, TableDiff $diff) : void
    {
        $this->validate();
        $old->validate();

        if ($this->rowid != $old->rowid) {
            $diff->setPossible(false);
            if ($this->rowid) {
                $diff->addDisplay('+ WITHOUT ROWID');
            } else {
                $diff->addDisplay('- WITHOUT ROWID');
            }
        }
    }
}
