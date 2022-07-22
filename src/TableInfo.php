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

abstract class TableInfo
{
    const FORMAT_ARRAY = 1;
    const FORMAT_SERIALIZE = 2;
    const FORMAT_JSON = 3;
    protected string $tbname;
    protected Driver $driver;
    protected string $type;

    public function __get(string $name)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }

        return null;
    }

    public static function loadFromArray(Driver $driver, array $data) : self
    {
        if ($data['type'] !== 'table' && $data['type'] !== 'view') {
            throw new Exception('数据不合法');
        }
        $infoClass = __NAMESPACE__ . '\\TableInfo\\' . ucfirst($driver->getName()) . '\\' . ucfirst($data['type']);

        return new $infoClass($driver, $data);
    }

    public static function loadFromFile(Driver $driver, string $path, int $format) : self
    {
        $info = null;
        switch ($format) {
            case self::FORMAT_ARRAY:
                if (file_exists($path)) {
                    $info = include($path);
                } else {
                    throw new Exception('文件不存在');
                }

                break;
            case self::FORMAT_JSON:
                if (file_exists($path)) {
                    $path = file_get_contents($path);
                    $info = json_decode($path, true);
                } else {
                    throw new Exception('文件不存在');
                }
                break;
            case self::FORMAT_SERIALIZE:
                if (file_exists($path)) {
                    $path = file_get_contents($path);
                    $info = unserialize($path);
                } else {
                    throw new Exception('文件不存在');
                }
                break;
            default:
                throw new Exception('format不合法');
        }

        return self::loadFromArray($driver, $info);
    }

    public static function loadFromDb(Driver $driver, string $tbname) : ?self
    {
        return $driver->getTableInfo($tbname);
    }

    public static function loadFromObject(Table $object) : self
    {
        if (!($object instanceof Table)) {
            throw new Exception('类不合法');
        }

        $info = [];
        $info['type'] = 'table';
        $info['status'] = isset($object::$status) ? $object::$status : [];
        $info['field'] = isset($object::$field) ? $object::$field : [];
        $info['index'] = isset($object::$index) ? $object::$index : [];
        $info['tbname'] = isset($object::$tbname) ? $object::$tbname : '';
        $info['initTableSql'] = $object->initTableSql();

        return self::loadFromArray($object->getDriver(), $info);
    }

    public static function loadFromClass(string $classname) : self
    {
        if (!class_exists($classname) || !is_callable($classname . '::get')) {
            throw new Exception('类不存在');
        }

        $obj = $classname::get();

        return self::loadFromObject($obj);
    }

    public function save(int $format, string $dir, string $name) : void
    {
        $string = $this->toString($format);

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
                throw new Exception('数据不合法');
        }

        $ret = file_put_contents($full, $string);
        if ($ret === false) {
            throw new Exception('数据写入失败');
        }
    }

    public function toString(int $format) : string
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
                throw new Exception('数据不合法');
        }
    }

    protected function toArray() : array
    {
        $this->validate();

        return [
            'type' => $this->type,
            'tbname' => $this->tbname,
        ];
    }

    public function set(string $name, $value) : void
    {
        if ($name === 'type') {
            if (empty($value) || !is_string($value) || $value !== 'table' && $value !== 'view') {
                throw new Exception('数据不合法');
            }

            $this->type = $value;
        } elseif ($name === 'tbname') {
            if (empty($value) || !is_string($value)) {
                throw new Exception('数据不合法');
            }
            $this->tbname = $value;
        } else {
            throw new Exception('非法操作' . $name . ' => ' . $value);
        }
    }

    public function __construct(Driver $driver, ?array $info = null)
    {
        if ($info !== null) {
            if (!isset($info['tbname'])) {
                throw new Exception('数据不合法');
            }
            $this->set('tbname', $info['tbname']);

            if (!isset($info['type'])) {
                throw new Exception('数据不合法');
            }
            $this->set('type', $info['type']);
        }

        $this->driver = $driver;
    }

    public function validate() : void
    {
        if (!isset($this->tbname) || !isset($this->type)) {
            throw new Exception('对象未初始化');
        }
    }
    /////////////////////////////

    abstract public function cmp(?self $oldInfo) : TableDiff;
}
