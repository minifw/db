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

class MysqliViewInfo extends Info
{
    protected string $algorithm;
    protected string $security;
    protected string $sql;

    protected function init(array $info) : bool
    {
        $fields = ['algorithm', 'security', 'sql'];

        foreach ($fields as $fname) {
            if (empty($info[$fname]) || !is_string($info[$fname])) {
                return false;
            }
        }

        if (!parent::init($info)) {
            return false;
        }

        foreach ($fields as $fname) {
            $this->{$fname} = $info[$fname];
        }

        return true;
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
}
