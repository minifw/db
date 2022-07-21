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
use Minifw\DB\Driver\Driver;
use Minifw\DB\TableInfo\Info;

class TableUtils
{
    public static function exportAllDb(Driver $drvier, string $dir, int $format = self::FORMAT_JSON, $table_list = '') : void
    {
        $tables = [];

        if (is_array($table_list)) {
            $tables = $table_list;
        } else {
            $list = $drvier->getTables();
            $len = strlen($table_list);
            if ($len > 0) {
                foreach ($list as $v) {
                    if (strncmp($v, $table_list, $len) === 0) {
                        $tables[] = $v;
                    }
                }
            } else {
                $tables = $list;
            }
        }

        foreach ($tables as $table) {
            $info = Info::loadFromDb($drvier, $table);
            $info->save($format, $dir, $table);
        }
    }

    /**
     * @param array<TableDiff> $diff
     */
    public static function printAllDiff(array $diff) : string
    {
        $lines = [];
        $trans = [];
        foreach ($diff as $info) {
            $lines[] = '++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++';
            $lines[] = $info->tbname . "\n";

            $display = $info->getDisplay();
            if (!empty($display)) {
                $lines[] = implode("\n", $display);
            }

            $trans = $info->getSql();
            if (!empty($trans)) {
                $trans[] = implode(";\n", $trans) . ';';
            }
        }
        $lines[] = "\n\n================================================================\n";

        $lines[] = implode("\n", $trans);

        return implode("\n", $lines);
    }

    public static function dbCmp(Driver $driver, Info $newCfg) : TableDiff
    {
        try {
            $oldCfg = Info::loadFromDb($driver, $newCfg->tbname);
        } catch (Exception $ex) {
            $oldCfg = null;
        }

        return $newCfg->cmp($oldCfg);
    }

    public static function obj2dbApplyAll(Driver $driver, string $namespace = '', string $classPath = '') : void
    {
        if ($classPath == '' || !is_dir($classPath)) {
            return;
        }

        $list = scandir($classPath);
        foreach ($list as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            if (is_dir($classPath . '/' . $file)) {
                self::obj2dbApplyAll($driver, $namespace . '\\' . $file, $classPath . '/' . $file);
            } else {
                if (substr($file, -4, 4) !== '.php') {
                    continue;
                }
                $classname = $namespace . '\\' . substr($file, 0, strlen($file) - 4);
                require_once($classPath . '/' . $file);

                $newCfg = Info::loadFromClass($classname);
                $diff = self::dbCmp($driver, $newCfg);
                $diff->apply($driver);
            }
        }
    }

    public static function obj2dbCmpAll(Driver $driver, string $namespace = '', string $classPath = '') : ?array
    {
        if ($classPath == '' || !is_dir($classPath)) {
            return null;
        }

        $diff = [];
        $list = scandir($classPath);
        foreach ($list as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            if (is_dir($classPath . '/' . $file)) {
                $diffList = self::obj2dbCmpAll($driver, $namespace . '\\' . $file, $classPath . '/' . $file);
                if (empty($diffList)) {
                    continue;
                }
                $diff = array_merge($diff, $diffList);
            } else {
                if (substr($file, -4, 4) !== '.php') {
                    continue;
                }
                $classname = $namespace . '\\' . substr($file, 0, strlen($file) - 4);
                require_once($classPath . '/' . $file);

                $newCfg = Info::loadFromClass($classname);
                $diffObj = self::dbCmp($driver, $newCfg);
                if (!$diffObj->isEmpty()) {
                    $diff[$newCfg->tbname] = $diffObj;
                }
            }
        }

        ksort($diff);

        return $diff;
    }

    public static function file2dbApplyAll(Driver $driver, string $dir, int $format = Info::FORMAT_JSON) : void
    {
        $dir = rtrim($dir, '/\\');
        if (empty($dir) || !file_exists($dir)) {
            return;
        }

        $file_list = scandir($dir);

        foreach ($file_list as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if (!is_file($path)) {
                continue;
            }

            $newCfg = Info::loadFromFile($driver, $path, $format);
            $diff = self::dbCmp($driver, $newCfg);
            $diff->apply($driver);
        }
    }

    public static function file2dbCmpAll(Driver $driver, string $dir, int $format = Info::FORMAT_JSON) : ?array
    {
        $dir = rtrim($dir, '/\\');
        if (empty($dir) || !file_exists($dir)) {
            return null;
        }

        $file_list = scandir($dir);
        $diff = [];

        foreach ($file_list as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if (!is_file($path)) {
                continue;
            }

            $newCfg = Info::loadFromFile($driver, $path, $format);
            $diffObj = self::dbCmp($driver, $newCfg);

            if ($diffObj->isEmpty()) {
                continue;
            }

            $diff[$newCfg->tbname] = $diffObj;
        }

        ksort($diff);

        return $diff;
    }
}
