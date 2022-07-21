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

namespace Minifw\DB;

use Minifw\Common\Exception;
use Minifw\DB\Driver\Driver;

class TableDiff
{
    protected string $tbname;
    protected array $display = [];
    protected array $sqlTrans = [];
    protected array $sqlLast = [];

    public function setTbname(string $tbname)
    {
        $this->tbname = $tbname;
    }

    public function addDisplay(string $display)
    {
        $this->display[] = $display;
    }

    public function addTrans(string $sql)
    {
        $this->sqlTrans[] = $sql;
    }

    public function addLast(string $sql)
    {
        $this->sqlLast[] = $sql;
    }

    public function getDisplay() : array
    {
        return $this->display;
    }

    public function getSql() : array
    {
        return array_merge($this->sqlTrans, $this->sqlLast);
    }

    public function isEmpty()
    {
        return empty($this->sqlTrans) && empty($this->sqlLast);
    }

    public function apply(Driver $driver) : void
    {
        $sqls = $this->getSql();
        foreach ($sqls as $sql) {
            if (!empty($sql)) {
                $driver->exec($sql);
            }
        }
    }

    public function display() : string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $lines = [];
        $lines[] = $this->tbname . "\n";

        if (!empty($this->display)) {
            $lines[] = implode("\n", $this->display);
        }

        $lines[] = "\n";

        $trans = $this->getSql();
        if (!empty($trans)) {
            $lines[] = implode(";\n", $trans) . ';';
        }

        return implode("\n", $lines);
    }

    public function __construct()
    {
    }
}
