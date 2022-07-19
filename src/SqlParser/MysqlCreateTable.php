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

namespace Minifw\DB\SqlParser;

use Minifw\Common\Exception;
use Minifw\DB;
use Minifw\DB\TableInfo\Info;

class MysqlCreateTable extends Parser
{
    protected string $tbname;
    protected array $fields = [];
    protected array $indexs = [];
    protected array $status = [];
    protected $table_status;
    protected $table_fields;
    const DEFAULT_LEN = [
        'int' => 11,
        'int_unsigned' => 10,
        'bigint' => 20,
        'tinyint' => 4,
    ];

    public function init(string $sql, ?array $status = null, array $fields = []) : void
    {
        parent::init($sql, $status, $fields);
        $this->tbname = '';
        $this->fields = [];
        $this->indexs = [];
        $this->status = [];

        $this->table_fields = $fields;
        $this->table_status = $status;
    }

    protected function _parse() : Info
    {
        $this->_parseTableName();
        $this->_parseTableContent();
        $this->_parseTableStatus();
        $this->_mergeDefined();

        $info = [
            'type' => 'table',
            'driver' => 'mysqli',
            'tbname' => $this->tbname,
            'status' => $this->status,
            'field' => $this->fields,
            'index' => $this->indexs,
        ];

        return Info::load($info, Info::FORMAT_ARRAY, false);
    }

    ///////////////////////////////////////////

    protected function _parseTableName()
    {
        $prefix = [
            [self::TYPE_KEYWORD, 'CREATE'],
            [self::TYPE_KEYWORD, 'TABLE'],
        ];

        foreach ($prefix as $v) {
            $token = $this->nextToken();
            if ($token[0] !== $v[0] || $token[1] !== $v[1]) {
                throw new Exception('', 100);
            }
        }

        $token = $this->nextToken();
        if ($token[0] !== self::TYPE_FIELD || $token[1] === '') {
            throw new Exception('');
        }
        $this->tbname = $token[1];

        while (true) {
            $token = $this->nextToken();
            if ($token === null) {
                throw new Exception('');
            }
            if ($token[0] === self::TYPE_KEYWORD) {
                continue;
            } elseif ($token[0] === self::TYPE_OPERATOR && $token[1] === '(') {
                break;
            }
        }
    }

    protected function _parseTableContent()
    {
        while (true) {
            $info = $this->nextField();
            if ($info === null) {
                break;
            }
            $this->fields[$info[0]] = $info[1];
        }

        while (true) {
            $info = $this->nextIndex();
            if ($info === null) {
                break;
            }
            $this->indexs[$info[0]] = $info[1];
        }
    }

    protected function _parseTableStatus()
    {
        $token = $this->nextToken();
        if ($token[0] !== self::TYPE_OPERATOR || $token[1] !== ')') {
            throw new Exception('');
        }

        while (true) {
            $token = $this->nextToken();
            if ($token === null) {
                break;
            }
            if ($token[0] !== self::TYPE_KEYWORD) {
                throw new Exception($token);
            }
            if ($token[1] === 'ENGINE') {
                $token = $this->nextToken();
                if ($token[0] !== self::TYPE_OPERATOR || $token[1] !== '=') {
                    throw new Exception('');
                }
                $token = $this->nextToken();
                if ($token[0] !== self::TYPE_KEYWORD) {
                    throw new Exception('');
                }
                $this->status['engine'] = $token[1];
            } elseif ($token[1] === 'DEFAULT') {
                $tmp = [
                    [self::TYPE_KEYWORD, 'CHARSET'],
                    [self::TYPE_OPERATOR, '='],
                ];
                foreach ($tmp as $v) {
                    $token = $this->nextToken();
                    if ($token[0] !== $v[0] || $token[1] !== $v[1]) {
                        throw new Exception('');
                    }
                }
                $token = $this->nextToken();
                if ($token[0] !== self::TYPE_KEYWORD) {
                    throw new Exception('');
                }
                $this->status['charset'] = $token[1];
            } elseif ($token[1] === 'COLLATE') {
                $token = $this->nextToken();
                if ($token[0] !== self::TYPE_OPERATOR || $token[1] !== '=') {
                    throw new Exception('');
                }
                $token = $this->nextToken();
                if ($token[0] === self::TYPE_KEYWORD) {
                    $this->status['collate'] = $token[1];
                } else {
                    throw new Exception('');
                }
            } elseif ($token[1] === 'COMMENT') {
                $token = $this->nextToken();
                if ($token[0] !== self::TYPE_OPERATOR || $token[1] !== '=') {
                    throw new Exception('');
                }
                $token = $this->nextToken();
                if ($token[0] !== self::TYPE_STRING) {
                    throw new Exception('');
                }
                $this->status['comment'] = $token[1];
            } elseif ($token[1] === 'AUTO_INCREMENT') {
                $token = $this->nextToken();
                if ($token[0] !== self::TYPE_OPERATOR || $token[1] !== '=') {
                    throw new Exception('');
                }
                $token = $this->nextToken();
                if ($token[0] !== self::TYPE_KEYWORD) {
                    throw new Exception('');
                }
            } elseif ($token[1] === 'ROW_FORMAT') {
                $token = $this->nextToken();
                if ($token[0] !== self::TYPE_OPERATOR || $token[1] !== '=') {
                    throw new Exception('');
                }
                $token = $this->nextToken();
                if ($token[0] !== self::TYPE_KEYWORD) {
                    throw new Exception('');
                }
            } elseif ($token[1] === 'CHECKSUM') {
                $token = $this->nextToken();
                if ($token[0] !== self::TYPE_OPERATOR || $token[1] !== '=') {
                    throw new Exception('');
                }
                $token = $this->nextToken();
                if ($token[0] !== self::TYPE_KEYWORD) {
                    throw new Exception('');
                }
                $this->status['checksum'] = $token[1];
            } else {
                throw new Exception('');
            }
        }
    }

    protected function _mergeDefined()
    {
        if (!empty($this->table_status['Collation'])) {
            $this->status['collate'] = $this->table_status['Collation'];
        }

        foreach ($this->table_fields as $field) {
            if (!empty($field['Collation'])) {
                $this->fields[$field['Field']]['collate'] = $field['Collation'];
            }
        }

        foreach ($this->fields as $name => $cfg) {
            if (isset($cfg['charset']) && $cfg['charset'] == $this->status['charset']) {
                unset($cfg['charset']);
            }
            if (isset($cfg['collate']) && $cfg['collate'] == $this->status['collate']) {
                unset($cfg['collate']);
            }
            $this->fields[$name] = $cfg;
        }
    }

    /////////////////////////////////////////////

    protected function nextIndex()
    {
        $token = $this->nextToken();
        if ($token[0] !== self::TYPE_KEYWORD) {
            $this->pushToken($token);

            return null;
        }

        $index_name = null;
        $index_type = null;
        $info = [];

        $two = ['PRIMARY', 'UNIQUE', 'FULLTEXT'];
        if (in_array($token[1], $two)) {
            $index_type = $token[1];
            $token = $this->nextToken();
            if ($token[0] !== self::TYPE_KEYWORD || $token[1] !== 'KEY') {
                throw new Exception('');
            }
            if ($index_type === 'UNIQUE') {
                $info['unique'] = true;
            } elseif ($index_type === 'FULLTEXT') {
                $info['fulltext'] = true;
            }
        } elseif ($token[1] !== 'KEY') {
            throw new Exception('');
        }

        if ($index_type !== 'PRIMARY') {
            $token = $this->nextToken();
            if ($token[0] !== self::TYPE_FIELD || $token[1] === '') {
                throw new Exception('');
            }
            $index_name = $token[1];
        } else {
            $index_name = 'PRIMARY';
        }

        $token = $this->nextToken();
        if ($token[0] !== self::TYPE_OPERATOR || $token[1] !== '(') {
            throw new Exception('');
        }

        $info['fields'] = [];
        while (true) {
            $token = $this->nextToken();
            if ($token === null) {
                throw new Exception('');
            }
            if ($token[0] === self::TYPE_OPERATOR) {
                if ($token[1] === ')') {
                    if (empty($info['fields'])) {
                        throw new Exception('');
                    }
                    break;
                } elseif ($token[1] === ',') {
                    continue;
                }
            } elseif ($token[0] === self::TYPE_FIELD) {
                $field = $token[1];

                $token = $this->nextToken();
                if ($token !== null && $token[0] === self::TYPE_OPERATOR && $token[1] === '(') {
                    $token = $this->nextToken();
                    if ($token === null || $token[0] !== self::TYPE_KEYWORD) {
                        throw new Exception($token[1]);
                    }

                    $field .= '(' . $token[1] . ')';

                    $token = $this->nextToken();
                    if ($token === null || $token[0] !== self::TYPE_OPERATOR || $token[1] !== ')') {
                        throw new Exception('');
                    }
                } else {
                    $this->pushToken($token);
                }

                $info['fields'][] = $field;
            } else {
                throw new Exception('');
            }
        }

        while (true) {
            $token = $this->nextToken();
            if ($token[0] === self::TYPE_KEYWORD) {
                if ($token[1] === 'COMMENT') {
                    $token = $this->nextToken();
                    if ($token[0] !== self::TYPE_STRING) {
                        throw new Exception('');
                    }
                    $info['comment'] = $token[1];
                } elseif ($token[1] === 'USING') {
                    $token = $this->nextToken();
                    if ($token[0] !== self::TYPE_KEYWORD) {
                        throw new Exception('');
                    }
                } else {
                    throw new Exception('');
                }
            } else {
                break;
            }
        }

        if ($token[0] !== self::TYPE_OPERATOR || $token[1] !== ',') {
            $this->pushToken($token);
        }

        return [$index_name, $info];
    }

    protected function nextField()
    {
        $field = [];

        $token = $this->nextToken();

        if ($token[0] !== self::TYPE_FIELD || $token[1] === '') {
            $this->pushToken($token);

            return null;
        }

        $field_name = $token[1];
        $field['type'] = $this->nextFieldType();
        $field['null'] = true;

        while (true) {
            $token = $this->nextToken();
            if ($token === null) {
                throw new Exception('');
            }

            if ($token[0] === self::TYPE_KEYWORD) {
                if ($token[1] === 'NOT') {
                    $token = $this->nextToken();
                    if ($token[0] === self::TYPE_KEYWORD && $token[1] === 'NULL') {
                        unset($field['null']);
                    } else {
                        throw new Exception('');
                    }
                } elseif ($token[1] === 'AUTO_INCREMENT') {
                    $field['extra'] = 'auto_increment';
                } elseif ($token[1] === 'COMMENT') {
                    $token = $this->nextToken();
                    if ($token[0] === self::TYPE_STRING) {
                        $field['comment'] = $token[1];
                    } else {
                        throw new Exception('');
                    }
                } elseif ($token[1] === 'DEFAULT') {
                    $token = $this->nextToken();
                    if ($token[0] === self::TYPE_STRING) {
                        $field['default'] = $token[1];
                    } elseif ($token[0] === self::TYPE_KEYWORD && $token[1] === 'NULL') {
                        $field['default'] = null;
                    } else {
                        throw new Exception('');
                    }
                } elseif ($token[1] === 'CHARACTER') {
                    $token = $this->nextToken();
                    if ($token[0] !== self::TYPE_KEYWORD || $token[1] !== 'SET') {
                        throw new Exception('');
                    }
                    $token = $this->nextToken();
                    if ($token[0] === self::TYPE_KEYWORD) {
                        $field['charset'] = $token[1];
                    } else {
                        throw new Exception('');
                    }
                } elseif ($token[1] === 'COLLATE') {
                    $token = $this->nextToken();
                    if ($token[0] === self::TYPE_KEYWORD) {
                        $field['collate'] = $token[1];
                    } else {
                        throw new Exception('');
                    }
                } else {
                    throw new Exception($token[1]);
                }
            } else {
                if ($token[0] === self::TYPE_OPERATOR) {
                    if ($token[1] === ')') {
                        $this->pushToken($token);
                    } elseif ($token[1] !== ',') {
                        throw new Exception('');
                    }
                    break;
                }
            }
        }

        return [$field_name, $field];
    }

    protected function nextFieldType()
    {
        $token = $this->nextToken();
        if ($token[0] !== self::TYPE_KEYWORD) {
            throw new Exception('');
        }

        $type = $token[1];
        $token = $this->nextToken();

        if ($type == 'enum') {
            if ($token[0] === self::TYPE_OPERATOR && $token[1] === '(') {
                $type .= '(';
                while (true) {
                    $token = $this->nextToken();
                    if ($token === null) {
                        throw new Exception('');
                    }
                    if ($token[0] === self::TYPE_OPERATOR) {
                        if ($token[1] === ')') {
                            $type .= ')';
                            $token = $this->nextToken();
                            break;
                        } elseif ($token[1] === ',') {
                            $type .= ',';
                        }
                    } elseif ($token[0] === self::TYPE_KEYWORD) {
                        $type .= $token[1];
                    } elseif ($token[0] === self::TYPE_STRING) {
                        $type .= '\'' . $token[1] . '\'';
                    } else {
                        throw new Exception('');
                    }
                }
            }
        } else {
            if ($token[0] === self::TYPE_OPERATOR && $token[1] === '(') {
                $type .= '(';
                while (true) {
                    $token = $this->nextToken();
                    if ($token === null) {
                        throw new Exception('');
                    }
                    if ($token[0] === self::TYPE_OPERATOR) {
                        if ($token[1] === ')') {
                            $type .= ')';
                            $token = $this->nextToken();
                            break;
                        } elseif ($token[1] === ',') {
                            $type .= ',';
                        }
                    } elseif ($token[0] === self::TYPE_KEYWORD) {
                        $type .= $token[1];
                    } else {
                        throw new Exception('');
                    }
                }
            }
        }

        $len = '';
        if (isset(self::DEFAULT_LEN[$type])) {
            $len = '(' . self::DEFAULT_LEN[$type] . ')';
        }

        $tail = '';

        while (true) {
            if ($token[0] === self::TYPE_KEYWORD && $token[1] === 'unsigned') {
                $tail .= ' unsigned';
                if ($type === 'int') {
                    $len = '(' . self::DEFAULT_LEN['int_unsigned'] . ')';
                }
            } elseif ($token[0] === self::TYPE_KEYWORD && $token[1] === 'zerofill') {
                $tail .= ' zerofill';
            } else {
                $this->pushToken($token);
                break;
            }
            $token = $this->nextToken();
        }

        return $type . $len . $tail;
    }
}
