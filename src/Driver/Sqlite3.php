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
use Minifw\DB\Parser\Sqlite3\CreateIndex;
use Minifw\DB\Parser\Sqlite3\CreateTable;
use Minifw\DB\Parser\Sqlite3\Scanner;
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

        if (!defined('APP_ROOT')) {
            throw new Exception('APP_ROOT未配置');
        }

        $file = APP_ROOT . $file;

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

    public function showCreate(string $table) : ?string
    {
        try {
            $sql = 'select `sql` from `sqlite_master` where `type`=\'table\' and `name`=\'' . $table . '\';';
            $data = $this->queryOne($sql, self::FETCH_NUM);
            if (empty($data)) {
                return null;
            }

            return $data[0];
        } catch (Exception $ex) {
            return null;
        }
    }

    public function getIndex(string $table) : array
    {
        try {
            $sql = 'select `sql` from `sqlite_master` where `type`=\'index\' and `tbl_name`=\'' . $table . '\';';
            $data = $this->query($sql, self::FETCH_NUM);
            if (empty($data)) {
                return [];
            }

            return $data;
        } catch (Exception $ex) {
            return [];
        }
    }

    public function getTableInfo(string $table) : ?TableInfo
    {
        $create_sql = $this->showCreate($table);
        if ($create_sql === null) {
            return null;
        }

        $scaner = new Scanner($create_sql);
        $parser = new CreateTable($scaner);
        $obj = $parser->parse($this);

        $indexList = $this->getIndex($table);
        foreach ($indexList as $index) {
            $scaner = new Scanner($index[0]);
            $parser = new CreateIndex($scaner);
            $parser->parse($obj);
        }

        return $obj;
    }
}
