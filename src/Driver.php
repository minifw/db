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
use PDO;

abstract class Driver
{
    private int $_transactionLv = 0;
    protected PDO $pdo;
    protected array $config = [];
    protected array $option = [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ];
    const FETCH_ASSOC = PDO::FETCH_ASSOC;
    const FETCH_NUM = PDO::FETCH_NUM;
    const FETCH_BOTH = PDO::FETCH_BOTH;

    public function begin() : void
    {
        ++$this->_transactionLv;
        if ($this->_transactionLv == 1) {
            $this->pdo->beginTransaction();
        }
    }

    public function commit() : void
    {
        --$this->_transactionLv;
        if ($this->_transactionLv <= 0) {
            $this->_transactionLv = 0;
            $this->pdo->commit();
        }
    }

    public function rollback() : void
    {
        --$this->_transactionLv;
        if ($this->_transactionLv <= 0) {
            $this->_transactionLv = 0;
            $this->pdo->rollBack();
        }
    }

    public function __construct(?array $config = null)
    {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
            $this->connect();
        }
    }

    public function connect(?array $config = null) : void
    {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }
        try {
            if (empty($this->config['dsn'])) {
                $this->config['dsn'] = $this->getDsn();
                if (empty($this->config['dsn'])) {
                    throw new Exception('数据库未配置');
                }
            }
            $this->pdo = new PDO($this->config['dsn'], $this->config['username'], $this->config['password'], $this->option);
        } catch (\Exception $ex) {
            throw new Exception('数据库连接失败：' . $ex->getMessage());
        }
    }

    /**
     * @param mixed $query
     */
    public function queryOne($query, int $fetch = self::FETCH_ASSOC) : array
    {
        $stm = $this->execute($query);
        $data = $stm->fetch($fetch);
        $stm->closeCursor();

        return $data;
    }

    /**
     * @param mixed $query
     */
    public function query($query, int $fetch = self::FETCH_ASSOC) : array
    {
        $stm = $this->execute($query);
        $data = $stm->fetchAll($fetch);
        $stm->closeCursor();

        return $data;
    }

    /**
     * @param mixed $query
     */
    public function queryHash($query, int $fetch = self::FETCH_ASSOC) : array
    {
        $stm = $this->execute($query);
        $data = $stm->fetchAll($fetch);
        $stm->closeCursor();

        $hash = [];
        $count = 0;
        $key = '';
        $value = '';

        foreach ($data as $v) {
            if ($count == 0) {
                $count = count($v);
                if ($count <= 1) {
                    return [];
                }

                $key = key($v);

                if ($count == 2) {
                    next($v);
                    $value = key($v);
                }
            }

            if ($count == 2) {
                $hash[$v[$key]] = $v[$value];
            } else {
                $hash[$v[$key]] = $v;
            }
        }

        return $hash;
    }

    /**
     * @param mixed $query
     */
    public function exec($query) : bool
    {
        $this->execute($query);

        return true;
    }

    /**
     * @param mixed $query
     * @throws Exception
     */
    public function execute($query) : \PDOStatement
    {
        $sql = '';
        try {
            if (is_string($query)) {
                $sql = $query;
                $bind = null;
            } else {
                $sql = $query->buildSql();
                $bind = $query->buildBind();
            }

            $stm = $this->pdo->prepare($sql);
            if ($stm === false) {
                throw new Exception('');
            }

            $result = $stm->execute($bind);
            if ($result === false) {
                throw new Exception('');
            }

            return $stm;
        } catch (\Exception $ex) {
            throw new Exception($ex->getMessage() . "\n" . $sql);
        }
    }

    /**
     * @return string|false
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    //////////////////////////////////////////////////////////////

    abstract protected function getDsn() : string;

    abstract public function getName() : string;

    /**
     * @param mixed $field
     * @return mixed
     */
    abstract public function quoteField($field);

    /**
     * @param mixed $value
     * @return mixed
     */
    public function quoteValue($value)
    {
        if (is_array($value)) {
            $ret = [];
            foreach ($value as $k => $v) {
                $ret[$k] = $this->pdo->quote($v, PDO::PARAM_STR);
            }

            return $ret;
        }

        return $this->pdo->quote($value, PDO::PARAM_STR);
    }

    abstract public function escapeLike(string $str) : string;

    /////////////////////////////////////////////////////////////

    abstract public function getTables() : array;

    abstract public function showCreate(string $table) : ?string;

    abstract public function getTableInfo(string $table) : ?TableInfo;
}
