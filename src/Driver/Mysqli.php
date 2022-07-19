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
use Minifw\DB;
use Minifw\DB\TableInfo\Info;
use Minifw\DB\TableInfo\MysqliTableInfo;
use Minifw\DB\TableInfo\MysqliViewInfo;

class Mysqli extends DB\Driver\Driver
{
    public function escapeLike(string $str) : string
    {
        $str = str_replace('_', '\\_', $str);
        $str = str_replace('%', '\\%', $str);

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

    /////////////////////////////////////////

    protected function getDsn() : string
    {
        if (empty($this->config)) {
            throw new Exception('数据库未配置');
        }

        $host = isset($this->config['host']) ? strval($this->config['host']) : '127.0.0.1';
        $port = isset($this->config['port']) ? strval($this->config['port']) : '3306';
        $dbname = isset($this->config['dbname']) ? strval($this->config['dbname']) : '';
        $charset = isset($this->config['charset']) ? strval($this->config['charset']) : 'utf8mb4';
        if (empty($dbname)) {
            throw new Exception('数据库未配置');
        }

        return 'mysql:dbname=' . $dbname . ';host=' . $host . ';port=' . $port . ';charset=' . $charset;
    }

    /////////////////////////////////////////

    public function getTables() : array
    {
        $sql = 'show tables';
        $data = $this->query($sql, self::FETCH_NUM);
        $tables = [];
        foreach ($data as $v) {
            $tables[] = $v[0];
        }

        return $tables;
    }

    public function showCreate(string $table) : string
    {
        $sql = 'show create table `' . $table . '`';
        $data = $this->queryOne($sql, self::FETCH_NUM);
        if (empty($data)) {
            throw new Exception('数据表不存在');
        }

        return $data[1];
    }

    public function getColumns(string $table) : array
    {
        $sql = 'show full columns from `' . $table . '`';
        $data = $this->query($sql, self::FETCH_ASSOC);

        return $data;
    }

    public function getTableStatus(string $table) : array
    {
        $sql = 'show table status where `Name` = \'' . $table . '\'';
        $data = $this->queryOne($sql, self::FETCH_ASSOC);

        return $data;
    }

    public function getUser() : string
    {
        $sql = 'select user()';
        $data = $this->queryOne($sql, self::FETCH_NUM);
        if (empty($data)) {
            throw new Exception('操作失败');
        }

        return $data[0];
    }

    public function getTableInfo(string $table) : Info
    {
        $create_sql = $this->showCreate($table);

        try {
            $status = $this->getTableStatus($table);
            $fields = $this->getColumns($table);

            $parser = new DB\SqlParser\MysqlCreateTable();
            $parser->init($create_sql, $status, $fields);
            $info = $parser->parse();
        } catch (Exception $ex) {
            if ($ex->getCode() == 100) {
                $parser = new DB\SqlParser\MysqlCreateView();
                $parser->init($create_sql);
                $info = $parser->parse();
            } else {
                throw $ex;
            }
        }

        return $info;
    }

    public function getComparer(Info $new_cfg, ?Info $old_cfg) : DB\TableComparer\Comparer
    {
        if ($old_cfg !== null && !($new_cfg instanceof $old_cfg)) {
            throw new Exception('不支持的操做');
        }

        if ($new_cfg->driverName !== 'mysqli' || ($old_cfg !== null && $old_cfg->driverName !== 'mysqli')) {
            throw new Exception('不支持的操做');
        }

        if ($new_cfg instanceof MysqliTableInfo) {
            return new DB\TableComparer\MysqlTableComparer($new_cfg, $old_cfg);
        } elseif ($new_cfg instanceof MysqliViewInfo) {
            $user = $this->getUser();

            return new DB\TableComparer\MysqlViewComparer($new_cfg, $old_cfg, $user);
        } else {
            throw new Exception('不支持的操做');
        }
    }
}
