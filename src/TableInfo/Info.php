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

namespace Minifw\DB\TableInfo;

use Minifw\Common\Exception;
use Minifw\DB\Table;
use Minifw\DB\Driver\Mysqli;
use Minifw\DB\Driver\Sqlite3;
use Minifw\Common\File;
use Minifw\Common\FileUtils;

abstract class Info
{
    const FORMAT_ARRAY = 1;
    const FORMAT_SERIALIZE = 2;
    const FORMAT_JSON = 3;
    const FORMAT_OBJECT = 4;
    protected string $tbname;
    protected string $driverName;
    protected string $type;
    public static array $driverList = [
        'mysqli' => 'Mysqli',
        'sqlite3' => 'Sqlite3',
    ];

    public function __get(string $name)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }

        return null;
    }

    public static function load($data, int $format, bool $isFile) : ?self
    {
        if ($format < 1 || $format > 4) {
            return null;
        }

        $info = null;
        switch ($format) {
            case self::FORMAT_ARRAY:
                if ($isFile) {
                    if (file_exists($data)) {
                        $info = include($data);
                    } else {
                        return null;
                    }
                } else {
                    $info = $data;
                }
                break;
            case self::FORMAT_JSON:
                if ($isFile) {
                    $data = file_get_contents($data);
                }
                $info = json_decode($data, true);
                break;
            case self::FORMAT_SERIALIZE:
                if ($isFile) {
                    $data = file_get_contents($data);
                }
                $info = unserialize($data);
                break;
            case self::FORMAT_OBJECT:
                if ($isFile) {
                    $classname = $data;
                    if (!class_exists($classname) || !is_callable($classname . '::get')) {
                        return null;
                    }
                    $obj = $classname::get();
                    if (!($obj instanceof Table)) {
                        return null;
                    }
                } else {
                    $obj = $data;
                }

                $info = [];
                $info['type'] = 'table';

                $driver = $obj->getDriver();
                if ($driver instanceof Mysqli) {
                    $info['driver'] = 'mysqli';
                } elseif ($driver instanceof Sqlite3) {
                    $info['driver'] = 'sqlite3';
                } else {
                    return null;
                }

                $info['status'] = isset($obj::$status) ? $obj::$status : [];
                $info['field'] = isset($obj::$field) ? $obj::$field : [];
                $info['index'] = isset($obj::$index) ? $obj::$index : [];
                $info['tbname'] = isset($obj::$tbname) ? $obj::$tbname : '';
                $info['initTableSql'] = $obj->initTableSql();
                break;
            default:
                return null;
        }

        if ($info['type'] !== 'table' && $info['type'] !== 'view') {
            return null;
        }

        if (!array_key_exists($info['driver'], self::$driverList)) {
            return null;
        }

        $infoClass = __NAMESPACE__ . '\\' . ucfirst($info['driver']) . ucfirst($info['type']) . 'Info';
        $ret = new $infoClass();

        if (!$ret->init($info)) {
            return null;
        }

        return $ret;
    }

    public function save(int $format, string $dir, string $name) : bool
    {
        $string = $this->toString($format);
        if ($string === null) {
            return false;
        }

        $dir = rtrim($dir, '/\\');

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $full = '';
        switch ($format) {
            case self::FORMAT_ARRAY:
            case self::FORMAT_SERIALIZE:
                $full = $dir . '/' . $name . '.php';
                break;
            case self::FORMAT_JSON:
                $full = $dir . '/' . $name . '.json';
                break;
            default:
                return false;
        }

        file_put_contents($full, $string);

        return true;
    }

    public function toString(int $format) : ?string
    {
        $data = $this->toArray();

        switch ($format) {
            case self::FORMAT_ARRAY:
                return "<?php\n return " . var_export($data, true) . ';';
            case self::FORMAT_JSON:
                return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            case self::FORMAT_SERIALIZE:
                return serialize($data);
            default:
                return null;
        }
    }

    protected function toArray() : array
    {
        return [
            'type' => $this->type,
            'driver' => $this->driverName,
            'tbname' => $this->tbname,
        ];
    }

    protected function init(array $info) : bool
    {
        if (empty($info['tbname']) || !is_string($info['tbname'])) {
            return false;
        }

        if (empty($info['driver']) || !is_string($info['driver'])) {
            return false;
        }

        if (empty($info['type']) || !is_string($info['type']) || $info['type'] !== 'table' && $info['type'] !== 'view') {
            return false;
        }

        $this->tbname = $info['tbname'];
        $this->driverName = $info['driver'];
        $this->type = $info['type'];

        return true;
    }

    protected function __construct()
    {
    }
}
