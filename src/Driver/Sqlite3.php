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

namespace Minifw\DB\Driver;

use Minifw\Common\Exception;
use Minifw\DB\Driver;
use Minifw\DB\TableInfo;

class Sqlite3 extends Driver
{
    public function escapeLike(string $str) : string
    {
        $str = str_replace(
            ['/', '\'', '"', '[', ']', '%', '&', '_', '(', ')'],
            ['//', '\'\'', '""', '/[', '/]', '/%', '/&', '/_', '/(', '/)'],
            $str
        );

        return trim($str);
    }

    /**
     * @param mixed $str
     * @return mixed
     */
    public function quoteField($str)
    {
        if (is_array($str)) {
            $ret = [];
            foreach ($str as $key => $v) {
                $ret[$key] = '`' . trim($v) . '`';
            }

            return $ret;
        }

        return '`' . trim($str) . '`';
    }

    /////////////////////////////////////////////////

    protected function getDsn() : string
    {
        if (empty($this->config)) {
            throw new Exception('数据库未配置');
        }

        $file = isset($this->config['file']) ? strval($this->config['file']) : '';
        if (empty($file)) {
            throw new Exception('数据库未配置');
        }

        return 'sqlite:' . $file;
    }

    public function getName() : string
    {
        return 'sqlite3';
    }

    //////////////////////////////////////////////////

    public function getTables() : array
    {
        $sql = 'SELECT `name` FROM `sqlite_master` WHERE `type` =\'table\' AND `name` NOT LIKE \'sqlite_%\';';
        $data = $this->query($sql, self::FETCH_NUM);
        $tables = [];
        foreach ($data as $v) {
            $tables[] = $v[0];
        }

        return $tables;
    }

    public function showCreate(string $table) : string
    {
        $sql = 'select `sql` from `sqlite_master` where `type`=\'table\' and `name`=\'' . $table . '\';';
        $data = $this->query($sql, self::FETCH_NUM);
        if (empty($data)) {
            throw new Exception('数据表不存在');
        }

        return $data[0];
    }

    public function getTableInfo(string $table) : TableInfo
    {
        $create_sql = $this->showCreate($table);

        $parser = new SqliteCreate();
        $parser->init($create_sql);
        $info = $parser->parse();

        return $info;
    }
}
