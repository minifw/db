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

namespace Minifw\DB\MysqliParser;

use Minifw\Common\Exception;

class MysqliScanner
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

    public function __construct(string $sql)
    {
        $this->sql = $sql;
        $this->len = strlen($sql);
        $this->tokenCache = [];
        $this->index = 0;
    }

    public function nextTokenIs(int $type, string $value, bool $throw = true) : bool
    {
        $token = $this->nextToken();
        if ($token === null) {
            if ($throw) {
                throw new Exception('token提取出错');
            } else {
                return false;
            }
        }

        if ($token->type !== $type || $token->value !== $value) {
            $this->pushToken($token);
            if ($throw) {
                $msg = [
                    $type => $token->type,
                    $value => $token->value
                ];
                throw new Exception('token提取出错:' . json_encode($msg, JSON_UNESCAPED_UNICODE));
            } else {
                return false;
            }
        }

        return true;
    }

    public function nextTokenAs(int $type, bool $throw = true) : ?string
    {
        $token = $this->nextToken();
        if ($token === null) {
            if ($throw) {
                throw new Exception('token提取出错');
            } else {
                return null;
            }
        }

        if ($token->type != $type) {
            $this->pushToken($token);

            if ($throw) {
                $msg = [
                    $type => $token->type,
                    'value' => $token->value
                ];
                throw new Exception('token提取出错:' . json_encode($msg, JSON_UNESCAPED_UNICODE));
            } else {
                return null;
            }
        }

        return $token->value;
    }

    public function pushToken(MysqliToken $token) : void
    {
        array_push($this->tokenCache, $token);
    }

    public function nextToken() : ?MysqliToken
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

            return new MysqliToken(MysqliToken::TYPE_FIELD, $name);
        } elseif ($char == self::$quoteChar) { //字符串
            $string = $this->nextString(self::$quoteChar);

            return new MysqliToken(MysqliToken::TYPE_STRING, $string);
        } elseif (isset(self::$oprator[$char])) {
            return new MysqliToken(MysqliToken::TYPE_OPERATOR, $char);
        } else {
            $this->index--;

            $word = $this->nextKeyword();
            $word = strtoupper($word);

            return new MysqliToken(MysqliToken::TYPE_KEYWORD, $word);
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
            switch ($token->type) {
                case MysqliToken::TYPE_STRING:
                    $sql .= '\'' . str_replace('\'', '\'\'', $token->value) . '\' ';
                    break;
                case MysqliToken::TYPE_FIELD:
                    $sql .= '`' . str_replace('`', '``', $token->value) . '` ';
                    break;
                case MysqliToken::TYPE_OPERATOR:
                    $sql .= $token->value;
                    break;
                case MysqliToken::TYPE_KEYWORD:
                    $sql .= $token->value . ' ';
                    break;
            }
        }

        $sql .= substr($this->sql, $this->index);
        $this->index = strlen($this->sql);

        return $sql;
    }

    public function reset() : void
    {
        $this->tokenCache = [];
        $this->index = 0;
    }

    public function getPos() : string
    {
        return substr($this->sql, $this->index, 80);
    }

    public function getSql() : string
    {
        return $this->sql;
    }
}
