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

class TableUtils
{
    const FORMAT_ARRAY = 1;
    const FORMAT_SERIALIZE = 2;
    const FORMAT_JSON = 3;

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

        $dir = rtrim($dir, '\\/');

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        foreach ($tables as $table) {
            $info = $drvier->getTableInfo($table);

            switch ($format) {
                case self::FORMAT_ARRAY:
                    $string = "<?php\n return " . var_export($info, true) . ';';
                    $file = $dir . '/' . $table . '.php';
                    break;
                case self::FORMAT_SERIALIZE:
                    $string = serialize($info);
                    $file = $dir . '/' . $table . '.php';
                    break;
                case self::FORMAT_JSON:
                    $string = json_encode($info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    $file = $dir . '/' . $table . '.json';
                    break;
                default:
                    throw new Exception('格式错误');
            }

            file_put_contents($file, $string);
        }
    }

    public static function printAllDiff(array $diff) : string
    {
        $lines = [];
        $trans = [];
        foreach ($diff as $class => $info) {
            $lines[] = '++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++';
            $lines[] = $class . ' ' . $info['tbname'] . "\n";

            if (!empty($info['diff'])) {
                if (!empty($info['diff']['display'])) {
                    $lines[] = implode("\n", $info['diff']['display']);
                }
                if (!empty($info['diff']['trans'])) {
                    $trans[] = implode("\n", $info['diff']['trans']);
                }
            }
        }
        $lines[] = "\n\n================================================================\n";

        $lines[] = implode("\n", $trans);

        return implode("\n", $lines);
    }

    public static function dbCmp(Driver $drvier, array $info) : array
    {
        try {
            $old_info = $drvier->getTableInfo($info['tbname']);
        } catch (Exception $ex) {
            $old_info = null;
        }

        $comparer = $drvier->getComparer($info, $old_info);

        return $comparer->getDiff();
    }

    public static function dbApply(Driver $driver, array $table_diff) : void
    {
        if (empty($table_diff)) {
            return;
        }

        foreach ($table_diff['trans'] as $trans) {
            if (!empty($trans)) {
                $driver->exec($trans);
            }
        }

        return;
    }

    public static function obj2dbApplyAll(string $namespace = '', string $classPath = '') : void
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
                self::obj2dbApplyAll($namespace . '\\' . $file, $classPath . '/' . $file);
            } else {
                if (substr($file, -4, 4) !== '.php') {
                    continue;
                }
                $classname = $namespace . '\\' . substr($file, 0, strlen($file) - 4);
                require_once($classPath . '/' . $file);
                if (class_exists($classname) && is_callable($classname . '::get')) {
                    $obj = $classname::get();
                    if ($obj instanceof Table) {
                        $driver = $obj->getDriver();
                        $info = self::getInfoFromObj($obj);
                        $table_diff = self::dbCmp($driver, $info);

                        self::dbApply($driver, $table_diff);
                    }
                }
            }
        }
    }

    public static function obj2dbCmpAll(string $namespace = '', string $classPath = '') : ?array
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
            $ndiff = [];
            if (is_dir($classPath . '/' . $file)) {
                $ndiff = self::obj2dbCmpAll($namespace . '\\' . $file, $classPath . '/' . $file);
            } else {
                if (substr($file, -4, 4) !== '.php') {
                    continue;
                }
                $classname = $namespace . '\\' . substr($file, 0, strlen($file) - 4);
                require_once($classPath . '/' . $file);
                if (class_exists($classname) && is_callable($classname . '::get')) {
                    $obj = $classname::get();
                    if ($obj instanceof Table) {
                        $driver = $obj->getDriver();
                        $info = self::getInfoFromObj($obj);
                        $table_diff = self::dbCmp($driver, $info);

                        if (empty($table_diff)) {
                            continue;
                        }
                        $ndiff[$classname] = [
                            'tbname' => $classname::$tbname,
                            'diff' => $table_diff
                        ];
                    }
                }
            }
            if (empty($ndiff)) {
                continue;
            }
            $diff = array_merge($diff, $ndiff);
        }

        ksort($diff);

        return $diff;
    }

    public static function file2dbApplyAll(Driver $driver, string $dir, int $format = self::FORMAT_JSON) : void
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

            $info = self::getInfoFromFile($dir . '/' . $file, $format);
            $table_diff = self::dbCmp($driver, $info);

            self::dbApply($driver, $table_diff);
        }
    }

    public static function file2dbCmpAll(Driver $driver, string $dir, int $format = self::FORMAT_JSON) : ?array
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

            $info = self::getInfoFromFile($dir . '/' . $file, $format);
            $table_diff = self::dbCmp($driver, $info);
            if (empty($table_diff)) {
                continue;
            }

            $diff[$info['tbname']] = [
                'tbname' => $info['tbname'],
                'diff' => $table_diff
            ];
        }

        ksort($diff);

        return $diff;
    }

    ////////////////////////////

    public static function getInfoFromObj(Table $obj) : array
    {
        $info = [];
        $info['type'] = 'table';
        $info['status'] = isset($obj::$status) ? $obj::$status : [];
        $info['field'] = isset($obj::$field) ? $obj::$field : [];
        $info['index'] = isset($obj::$index) ? $obj::$index : [];
        $info['tbname'] = isset($obj::$tbname) ? $obj::$tbname : '';
        $info['init_table_sql'] = $obj->initTableSql();

        return $info;
    }

    public static function getInfoFromFile(string $full, int $format) : array
    {
        if (!file_exists($full)) {
            throw new Exception('file not found');
        }
        $info = [];

        switch ($format) {
            case self::FORMAT_ARRAY:
                $info = include $full;
                break;
            case self::FORMAT_SERIALIZE:
                $raw_data = file_get_contents($full);
                $info = unserialize($raw_data);
                break;
            case self::FORMAT_JSON:
                $raw_data = file_get_contents($full);
                $info = json_decode($raw_data, true);
                break;
            default:
                throw new Exception('格式错误');
        }

        if (empty($info['type'])) {
            $info['type'] = 'table';
        }

        return $info;
    }
}
