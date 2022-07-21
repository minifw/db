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

namespace Minifw\DB\SqlParser;

use Minifw\Common\Exception;
use Minifw\DB;
use Minifw\DB\TableInfo\Info;
use Minifw\DB\Driver\Driver;

abstract class Parser
{
    protected string $sql = '';
    protected int $index = 0;
    protected int $len;
    protected array $tokenCache = [];
    public static array $emptyChar = [
        ' ' => 1,
        "\n" => 1,
        "\r" => 1,
        "\t" => 1
    ];
    public static $oprator = [
        '(' => 1,
        ')' => 1,
        '=' => 1,
        ',' => 1,
        '@' => 1,
    ];
    public static string $escapeChar = '`';
    public static string $quoteChar = '\'';
    const TYPE_FIELD = 1;
    const TYPE_STRING = 2;
    const TYPE_KEYWORD = 3;
    const TYPE_OPERATOR = 4;
    public static array $type_hash = [
        self::TYPE_FIELD => 'fld',
        self::TYPE_STRING => 'str',
        self::TYPE_KEYWORD => 'kwd',
        self::TYPE_OPERATOR => 'opt'
    ];

    public function __construct(string $sql)
    {
        $this->sql = $sql;
        $this->len = strlen($sql);
    }

    public function pushToken(array $token) : void
    {
        array_push($this->tokenCache, $token);
    }

    public function nextToken() : ?array
    {
        if (!empty($this->tokenCache)) {
            return array_pop($this->tokenCache);
        }

        $char = $this->nextChar(true);
        if ($char === null) {
            return null;
        }
        if ($char == self::$escapeChar) { //一个mysql域
            $name = $this->nextString(self::$escapeChar);

            return [self::TYPE_FIELD, $name];
        } elseif ($char == self::$quoteChar) { //字符串
            $string = $this->nextString(self::$quoteChar);

            return [self::TYPE_STRING, $string];
        } elseif (isset(self::$oprator[$char])) {
            return [self::TYPE_OPERATOR, $char];
        } else {
            $this->index--;
            $word = $this->nextKeyword();

            return [self::TYPE_KEYWORD, $word];
        }
    }

    /**
     * @return mixed
     */
    public function nextKeyword() : string
    {
        $begin = $this->index;
        $end = $begin;
        while ($end < $this->len) {
            $char = $this->sql[$end];
            if (isset(self::$emptyChar[$char]) || isset(self::$oprator[$char])) {
                break;
            }
            $end++;
        }
        $word = substr($this->sql, $begin, $end - $begin);
        $this->index = $end;

        return $word;
    }

    public function nextString(string $dim) : string
    {
        $begin = $this->index;
        while ($begin < $this->len) {
            $end = strpos($this->sql, $dim, $begin);
            if ($end === null) {
                throw new Exception('');
            }
            if ($end + 1 < $this->len) {
                $nchar = $this->sql[$end + 1];
                if ($nchar == $dim) {
                    $begin = $end + 2;
                    continue;
                }
            }
            if ($end < $this->index) {
                throw new Exception('');
            }

            $name = substr($this->sql, $this->index, $end - $this->index);
            $name = str_replace($dim . $dim, $dim, $name);
            $this->index = $end + 1;

            return $name;
        }
        throw new Exception('');
    }

    public function nextChar(bool $skipEmpty = false) : ?string
    {
        while ($this->index < $this->len) {
            $char = $this->sql[$this->index];
            $this->index++;
            if ($skipEmpty && isset(self::$emptyChar[$char])) {
                continue;
            }

            return $char;
        }

        return null;
    }

    public function nextAll() : string
    {
        $sql = '';
        while (!empty($this->tokenCache)) {
            $token = array_pop($this->tokenCache);
            switch ($token[0]) {
                case self::TYPE_STRING:
                    $sql .= '\'' . str_replace('\'', '\'\'', $token[1]) . '\' ';
                    break;
                case self::TYPE_FIELD:
                    $sql .= '`' . str_replace('`', '``', $token[1]) . '` ';
                    break;
                case self::TYPE_OPERATOR:
                    $sql .= $token[1];
                    break;
                case self::TYPE_KEYWORD:
                    $sql .= $token[1] . ' ';
                    break;
            }
        }

        $sql .= substr($this->sql, $this->index);
        $this->index = strlen($this->sql);

        return $sql;
    }

    public function parse(Driver $driver) : Info
    {
        $this->tokenCache = [];
        $this->index = 0;
        try {
            return $this->_parse($driver);
        } catch (Exception $ex) {
            if ($ex->getCode() == 100) {
                throw $ex;
            }
            throw new Exception('语法错误，位于:' . substr($this->sql, $this->index, 80) . "\n\n" . '完整语句：' . $this->sql . "\n\n" . '发生于：' . $ex->getFile() . '[' . $ex->getLine() . ']:' . $ex->getMessage(), -1);
        }
    }

    abstract protected function _parse(Driver $driver) : Info;
}
