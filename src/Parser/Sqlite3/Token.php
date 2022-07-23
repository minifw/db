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

namespace Minifw\DB\Parser\Sqlite3;

use Minifw\Common\Exception;
use Minifw\DB\Parser;

class Token extends Parser\Token
{
    const TYPE_STRING = 1;
    const TYPE_KEYWORD = 2;
    const TYPE_OPERATOR = 3;
    const TYPE_COMMENT = 4;
    public static array $typeHash = [
        self::TYPE_STRING => 'str',
        self::TYPE_KEYWORD => 'kwd',
        self::TYPE_OPERATOR => 'opt',
        self::TYPE_COMMENT => 'cmt',
    ];

    public function __get(string $name)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }

        return null;
    }

    public function __construct(int $type, string $value)
    {
        if ($type === '') {
            throw new Exception('数据不合法');
        }
        if (!isset(self::$typeHash[$type])) {
            throw new Exception('数据不合法');
        }
        parent::__construct($type, $value);
    }
}
