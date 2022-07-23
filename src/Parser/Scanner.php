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

namespace Minifw\DB\Parser;

use Minifw\Common\Exception;

abstract class Scanner
{
    protected string $sql = '';
    protected int $index = 0;
    protected int $len;
    protected array $tokenCache = [];

    public static function escape(string $value, string $dim = '\'')
    {
        return str_replace($dim, $dim . $dim, $value);
    }

    public static function unescape(string $value, string $dim = '\'')
    {
        return str_replace($dim . $dim, $dim, $value);
    }

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

        if (!$token->is($type, $value)) {
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

    public function pushToken(Token $token) : void
    {
        array_push($this->tokenCache, $token);
    }

    abstract public function nextToken() : ?Token;

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
