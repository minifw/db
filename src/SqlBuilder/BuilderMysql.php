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

namespace Minifw\DB\SqlBuilder;

use Minifw\Common\Exception;
use Minifw\DB;

class BuilderMysql
{
    public static function fieldToSql(string $name, array $attr, string $defaultCharset, string $defaultCollate, bool $inCreate) : array
    {
        $info = [
            'sql' => '',
        ];
        if (isset($attr['extra']) && $attr['extra'] === 'auto_increment') {
            $info['sql_first'] = '';
        }
        switch ($attr['type']) {
            case 'text':
                $info['sql'] = '`' . $name . '` text';

                self::parseCharset($info, $attr, $defaultCharset, $defaultCollate, $inCreate);

                if (!isset($attr['null']) || $attr['null'] === 'NO') {
                    $info['sql'] .= ' NOT NULL';
                }
                break;
            default:
                $info['sql'] = '`' . $name . '` ' . $attr['type'];

                if (strncmp($attr['type'], 'varchar', 7) == 0 || strncmp($attr['type'], 'char', 4) == 0) {
                    self::parseCharset($info, $attr, $defaultCharset, $defaultCollate, $inCreate);
                }

                if (!isset($attr['null']) || $attr['null'] === 'NO') {
                    $info['sql'] .= ' NOT NULL';
                }
                if (isset($info['sql_first'])) {
                    $info['sql_first'] = $info['sql'];
                }
                if (isset($attr['extra']) && $attr['extra'] !== null && $attr['extra'] !== '') {
                    $info['sql'] .= ' ' . $attr['extra'];
                }
                if (isset($attr['default']) && $attr['default'] !== null) {
                    if ($attr['default'] !== null) {
                        $tmp = ' DEFAULT \'' . str_replace('\'', '\'\'', $attr['default']) . '\'';
                    } else {
                        $tmp = ' DEFAULT NULL';
                    }
                    $info['sql'] .= $tmp;
                    if (isset($info['sql_first'])) {
                        $info['sql_first'] .= $tmp;
                    }
                }
                break;
        }
        if (isset($attr['comment']) && $attr['comment'] !== null) {
            $tmp = ' COMMENT \'' . str_replace('\'', '\'\'', $attr['comment']) . '\'';
            $info['sql'] .= $tmp;
            if (isset($info['sql_first'])) {
                $info['sql_first'] .= $tmp;
            }
        }

        return $info;
    }

    public static function parseCharset(array &$info, array $attr, string $defaultCharset, string $defaultCollate, bool $inCreate) : void
    {
        if (!isset($attr['charset'])) {
            $attr['charset'] = $defaultCharset;
        }

        if (!empty($attr['charset'])) {
            $info['sql'] .= ' CHARACTER SET ' . $attr['charset'];
        } else {
            $info['sql'] .= ' CHARACTER SET ' . $defaultCharset;
        }

        if (!empty($attr['collate'])) {
            $info['sql'] .= ' COLLATE ' . $attr['collate'];
        } elseif (!empty($defaultCollate)) {
            $info['sql'] .= ' COLLATE ' . $defaultCollate;
        }
    }

    public static function indexToSql(string $name, array $attr, bool $inCreate) : string
    {
        $sql = '';
        switch ($name) {
            case 'PRIMARY':
                $sql = 'PRIMARY KEY (`' . implode('`,`', $attr['fields']) . '`)';
                break;
            default:
                if ($inCreate) {
                    if (isset($attr['unique']) && $attr['unique'] === true) {
                        $sql = 'UNIQUE ';
                    } elseif (isset($attr['fulltext']) && $attr['fulltext'] === true) {
                        $sql = 'FULLTEXT ';
                    }
                    $sql .= 'KEY ';
                } else {
                    if (isset($attr['unique']) && $attr['unique'] === true) {
                        $sql = 'UNIQUE ';
                    } elseif (isset($attr['fulltext']) && $attr['fulltext'] === true) {
                        $sql = 'FULLTEXT ';
                    } else {
                        $sql = 'INDEX ';
                    }
                }
                $sql .= '`' . $name . '` (`' . implode('`,`', $attr['fields']) . '`)';
                break;
        }
        if (isset($attr['comment']) && $attr['comment'] != '') {
            $sql .= ' COMMENT \'' . str_replace('\'', '\'\'', $attr['comment']) . '\'';
        }

        return $sql;
    }

    public static function sqlCreate(string $tbname, array $status, array $field, array $index, string $dim = '') : string
    {
        $engine = isset($status['engine']) ? $status['engine'] : 'InnoDB';
        $charset = isset($status['charset']) ? $status['charset'] : 'utf8';
        $collate = isset($status['collate']) ? $status['collate'] : '';
        $comment = isset($status['comment']) ? $status['comment'] : '';

        if ($tbname === '' || $engine === '' || $charset == '') {
            throw new Exception('参数错误');
        }

        $sql = 'CREATE TABLE IF NOT EXISTS `' . $tbname . '` (' . $dim;
        $lines = [];
        foreach ($field as $k => $v) {
            $sql_info = self::fieldToSql($k, $v, $charset, $collate, true);
            $lines[] = $sql_info['sql'];
        }

        foreach ($index as $k => $v) {
            $lines[] = self::indexToSql($k, $v, true);
        }

        $sql .= implode(',' . $dim, $lines) . $dim;
        $sql .= ') ENGINE=' . $engine . ' DEFAULT CHARSET=' . $charset;

        if ($collate != '') {
            $sql .= ' COLLATE=' . $collate;
        }

        if ($comment != '') {
            $sql .= ' COMMENT=\'' . $comment . '\'';
        }

        return $sql;
    }

    public static function sqlDrop(string $tbname) : string
    {
        return 'DROP TABLE IF EXISTS `' . $tbname . '`';
    }

    public static function sqlCreateView(string $tbname, string $algorithm, string $definer, string $sqlSecurity, string $viewSql) : string
    {
        $sql = 'CREATE';

        if (!empty($algorithm)) {
            $sql .= ' ALGORITHM=' . $algorithm;
        }

        if (!empty($definer)) {
            $sql .= ' DEFINER=' . $definer;
        }

        if (!empty($sqlSecurity)) {
            $sql .= ' SQL SECURITY ' . $sqlSecurity;
        }

        $sql .= ' VIEW `' . $tbname . '` AS ' . $viewSql;

        return $sql;
    }
}
