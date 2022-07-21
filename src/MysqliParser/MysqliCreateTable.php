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

namespace Minifw\DB\MysqliParser;

use Minifw\Common\Exception;
use Minifw\DB\TableInfo\Info;
use Minifw\DB\TableInfo\MysqliTableInfo;
use Minifw\DB\Driver\Driver;
use Minifw\DB\TableInfo\MysqlTableInfo\Status;
use Minifw\DB\TableInfo\MysqlTableInfo\Field;
use Minifw\DB\TableInfo\MysqlTableInfo\Index;

class MysqliCreateTable
{
    protected MysqliScanner $scaner;
    protected MysqliTableInfo $obj;
    protected string $tableCollate;
    protected string $tableCharset;
    protected array $tableStatus;
    protected array $tableColumns;
    const DEFAULT_LEN = [
        'int' => 11,
        'int_unsigned' => 10,
        'bigint' => 20,
        'tinyint' => 4,
    ];

    /**
     * @var array<Field>
     */
    protected array $fields = [];

    public function __construct(MysqliScanner $scanner, array $status, array $columns)
    {
        $this->scaner = $scanner;

        $this->tableCollate = $status['Collation'];
        $this->tableStatus = $status;
        $this->tableColumns = $columns;
    }

    public function parse(Driver $driver) : MysqliTableInfo
    {
        $this->fields = [];
        $this->scaner->reset();

        $this->obj = new MysqliTableInfo($driver);

        try {
            $this->_parseTableName();
            $this->_parseTableContent();
            $this->_parseTableStatus();
            $this->_parseFieldCharset();
        } catch (Exception $ex) {
            throw new Exception('分析出错，位于: ' . $this->scaner->getPos() . "\n完整语句: " . $this->scaner->getSql() . "\n发生于: " . $ex->getFile() . '[' . $ex->getLine() . ']:' . $ex->getMessage());
        }

        return $this->obj;
    }

    ///////////////////////////////////////////

    protected function _parseTableName()
    {
        $this->scaner->nextTokenIs(MysqliToken::TYPE_KEYWORD, 'CREATE');
        $this->scaner->nextTokenIs(MysqliToken::TYPE_KEYWORD, 'TABLE');

        $tbname = $this->scaner->nextTokenAs(MysqliToken::TYPE_FIELD);
        $this->obj->set('tbname', $tbname);

        while (true) {
            $token = $this->scaner->nextToken();
            if ($token === null) {
                throw new Exception('');
            }

            if ($token->type === MysqliToken::TYPE_KEYWORD) {
                continue;
            } elseif ($token->is(MysqliToken::TYPE_OPERATOR, '(')) {
                break;
            } else {
                throw new Exception('');
            }
        }
    }

    protected function _parseTableContent()
    {
        while (true) {
            $field = $this->nextField();
            if ($field === null) {
                break;
            }
            $this->fields[$field->getName()] = $field;
        }

        while (true) {
            $index = $this->nextIndex();
            if ($index === null) {
                break;
            }
            $this->obj->set('index', $index);
        }
    }

    protected function _parseTableStatus()
    {
        $this->scaner->nextTokenIs(MysqliToken::TYPE_OPERATOR, ')');
        $status = new Status();

        while (true) {
            $token = $this->scaner->nextToken();
            if ($token === null) {
                break;
            }
            switch ($token->value) {
                case 'ENGINE':
                    $this->scaner->nextTokenIs(MysqliToken::TYPE_OPERATOR, '=');

                    $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_KEYWORD);
                    $status->set('engine', strtolower($value));
                    break;
                case 'DEFAULT':
                    $this->scaner->nextTokenIs(MysqliToken::TYPE_KEYWORD, 'CHARSET');
                    $this->scaner->nextTokenIs(MysqliToken::TYPE_OPERATOR, '=');

                    $value = strtolower($this->scaner->nextTokenAs(MysqliToken::TYPE_KEYWORD));
                    $status->set('charset', $value);
                    $this->tableCharset = $value;
                    break;
                case 'COLLATE':
                    $this->scaner->nextTokenIs(MysqliToken::TYPE_OPERATOR, '=');

                    $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_KEYWORD);
                    $status->set('collate', strtolower($value));
                    $this->tableCollate = $value;
                    break;
                case 'COMMENT':
                    $this->scaner->nextTokenIs(MysqliToken::TYPE_OPERATOR, '=');

                    $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_STRING);
                    $status->set('comment', $value);
                    break;
                case 'AUTO_INCREMENT':
                    $this->scaner->nextTokenIs(MysqliToken::TYPE_OPERATOR, '=');

                    $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_KEYWORD);
                    break;
                case 'ROW_FORMAT':
                    $this->scaner->nextTokenIs(MysqliToken::TYPE_OPERATOR, '=');

                    $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_KEYWORD);
                    $status->set('rowFormat', strtolower($value));
                    break;
                case 'CHECKSUM':
                    $this->scaner->nextTokenIs(MysqliToken::TYPE_OPERATOR, '=');

                    $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_KEYWORD);
                    $status->set('checksum', strtolower($value));
                    break;
                default:
                    throw new Exception('');
            }
        }

        $hash = [
            'collate' => 'Collation',
            'rowFormat' => 'Row_format',
            'engine' => 'Engine',
            'checksum' => 'Checksum'
        ];

        foreach ($hash as $name => $key) {
            if (!empty($this->tableStatus[$key])) {
                $status->set($name, $this->tableStatus[$key]);
            }
        }
        $this->obj->set('status', $status);
    }

    protected function _parseFieldCharset()
    {
        foreach ($this->tableColumns as $field) {
            if (!empty($field['Collation'])) {
                $this->fields[$field['Field']]->set('collate', $field['Collation']);
            }
        }

        foreach ($this->fields as $field) {
            $this->obj->set('field', $field);
        }
    }

    /////////////////////////////////////////////

    protected function nextIndex() : ?Index
    {
        $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_KEYWORD, false);
        if ($value === null) {
            return null;
        }

        $index_type = null;
        $index = new Index();

        $two = ['PRIMARY', 'UNIQUE', 'FULLTEXT'];
        if (in_array($value, $two)) {
            $index_type = $value;

            $this->scaner->nextTokenIs(MysqliToken::TYPE_KEYWORD, 'KEY');

            if ($index_type === 'UNIQUE') {
                $index->set('unique', true);
            } elseif ($index_type === 'FULLTEXT') {
                $index->set('fulltext', true);
            }
        } elseif ($value !== 'KEY') {
            throw new Exception('');
        }

        if ($index_type !== 'PRIMARY') {
            $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_FIELD);
            $index->set('name', $value);
        } else {
            $index->set('name', 'PRIMARY');
        }

        $this->scaner->nextTokenIs(MysqliToken::TYPE_OPERATOR, '(');

        $fields = [];
        while (true) {
            $token = $this->scaner->nextToken();
            if ($token === null) {
                throw new Exception('');
            }

            if ($token->type === MysqliToken::TYPE_OPERATOR) {
                if ($token->value === ')') {
                    if (empty($fields)) {
                        throw new Exception('');
                    }
                    break;
                } elseif ($token->value === ',') {
                    continue;
                }
            } elseif ($token->type === MysqliToken::TYPE_FIELD) {
                $field = $token->value;

                $token = $this->scaner->nextToken();
                if ($token !== null && $token->type === MysqliToken::TYPE_OPERATOR && $token->value === '(') {
                    $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_KEYWORD);
                    $field .= '(' . $value . ')';
                    $this->scaner->nextTokenIs(MysqliToken::TYPE_OPERATOR, ')');
                } else {
                    $this->scaner->pushToken($token);
                }

                $fields[] = $field;
            } else {
                throw new Exception('');
            }
        }

        $index->set('fields', $fields);

        while (true) {
            $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_KEYWORD, false);
            if ($value === null) {
                break;
            }

            if ($value === 'COMMENT') {
                $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_STRING);
                $index->set('comment', $value);
            } elseif ($value === 'USING') {
                $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_KEYWORD);
            } else {
                throw new Exception('');
            }
        }

        $this->scaner->nextTokenIs(MysqliToken::TYPE_OPERATOR, ',', false);

        return $index;
    }

    protected function nextField() : ?Field
    {
        $field = new Field();

        $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_FIELD, false);
        if ($value === null) {
            return null;
        }

        $field->set('name', $value);
        $type = $this->nextFieldType();
        $field->set('type', $type);

        $nullable = true;

        while (true) {
            $token = $this->scaner->nextToken();
            if ($token === null) {
                throw new Exception('');
            }

            if ($token->type == MysqliToken::TYPE_OPERATOR) {
                if ($token->value === ')') {
                    $this->scaner->pushToken($token);
                } elseif ($token->value !== ',') {
                    throw new Exception('');
                }
                break;
            } elseif ($token->type !== MysqliToken::TYPE_KEYWORD) {
                throw new Exception('');
            }

            switch ($token->value) {
                case 'NOT':
                    $this->scaner->nextTokenIs(MysqliToken::TYPE_KEYWORD, 'NULL');
                    $nullable = false;
                    break;
                case 'AUTO_INCREMENT':
                    $field->set('autoIncrement', true);
                    break;
                case 'COMMENT':
                    $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_STRING);
                    $field->set('comment', $value);
                    break;
                case 'DEFAULT':
                    $token = $this->scaner->nextToken();
                    if ($token->type === MysqliToken::TYPE_STRING) {
                        $field->set('default', $token->value);
                    } elseif ($token->is(MysqliToken::TYPE_KEYWORD, 'NULL')) {
                        $field->set('default', null);
                    } else {
                        throw new Exception('');
                    }
                    break;
                case 'CHARACTER':
                    $this->scaner->nextTokenIs(MysqliToken::TYPE_KEYWORD, 'SET');
                    $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_KEYWORD);
                    $field->set('charset', strtolower($value));
                    break;
                case 'COLLATE':
                    $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_KEYWORD);
                    $field->set('collate', strtolower($value));
                    break;
                default:
                    throw new Exception($token->value);
            }
        }

        $field->set('nullable', $nullable);

        return $field;
    }

    protected function nextFieldType()
    {
        $type = $this->scaner->nextTokenAs(MysqliToken::TYPE_KEYWORD);
        $type = strtolower($type);

        $token = $this->scaner->nextToken();

        if ($type == 'enum') {
            if (!$token->is(MysqliToken::TYPE_OPERATOR, '(')) {
                throw new Exception('');
            }
            $type .= '(';
            while (true) {
                $token = $this->scaner->nextToken();
                if ($token === null) {
                    throw new Exception('');
                }
                if ($token->type === MysqliToken::TYPE_OPERATOR) {
                    if ($token->value === ')') {
                        $type .= ')';
                        $token = $this->scaner->nextToken();
                        break;
                    } elseif ($token->value === ',') {
                        $type .= ',';
                    }
                } elseif ($token->type === MysqliToken::TYPE_KEYWORD) {
                    $type .= $token->value;
                } elseif ($token->type === MysqliToken::TYPE_STRING) {
                    $type .= '\'' . $token->value . '\'';
                } else {
                    throw new Exception('');
                }
            }
        } else {
            if ($token->is(MysqliToken::TYPE_OPERATOR, '(')) {
                $type .= '(';
                while (true) {
                    $token = $this->scaner->nextToken();
                    if ($token === null) {
                        throw new Exception('');
                    }
                    if ($token->type === MysqliToken::TYPE_OPERATOR) {
                        if ($token->value === ')') {
                            $type .= ')';
                            $token = $this->scaner->nextToken();
                            break;
                        } elseif ($token->value === ',') {
                            $type .= ',';
                        } else {
                            throw new Exception('');
                        }
                    } elseif ($token->type === MysqliToken::TYPE_KEYWORD) {
                        $type .= $token->value;
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
            if ($token->is(MysqliToken::TYPE_KEYWORD, 'UNSIGNED')) {
                $tail .= ' unsigned';
                if ($type === 'int') {
                    $len = '(' . self::DEFAULT_LEN['int_unsigned'] . ')';
                }
            } elseif ($token->is(MysqliToken::TYPE_KEYWORD, 'ZEROFILL')) {
                $tail .= ' zerofill';
            } else {
                $this->scaner->pushToken($token);
                break;
            }
            $token = $this->scaner->nextToken();
        }

        return $type . $len . $tail;
    }
}
