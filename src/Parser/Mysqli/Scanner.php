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

namespace Minifw\DB\Parser\Mysqli;

use Minifw\Common\Exception;
use Minifw\DB\Parser;

class Scanner extends Parser\Scanner
{
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
        parent::__construct($sql);
    }

    public function nextToken() : ?Token
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

            return new Token(Token::TYPE_FIELD, $name);
        } elseif ($char == self::$quoteChar) { //字符串
            $string = $this->nextString(self::$quoteChar);

            return new Token(Token::TYPE_STRING, $string);
        } elseif (isset(self::$oprator[$char])) {
            return new Token(Token::TYPE_OPERATOR, $char);
        } else {
            $this->index--;

            $word = $this->nextKeyword();
            $word = strtoupper($word);

            return new Token(Token::TYPE_KEYWORD, $word);
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
            if ($end === false) {
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
                case Token::TYPE_STRING:
                    $sql .= '\'' . self::escape($token->value, '\'') . '\' ';
                    break;
                case Token::TYPE_FIELD:
                    $sql .= '`' . self::escape($token->value, '`') . '` ';
                    break;
                case Token::TYPE_OPERATOR:
                    $sql .= $token->value;
                    break;
                case Token::TYPE_KEYWORD:
                    $sql .= $token->value . ' ';
                    break;
            }
        }

        $sql .= substr($this->sql, $this->index);
        $this->index = strlen($this->sql);

        return $sql;
    }
}
