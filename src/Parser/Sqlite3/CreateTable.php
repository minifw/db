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

namespace Minifw\DB\Parser\Sqlite3;

use Minifw\Common\Exception;
use Minifw\DB\Driver;
use Minifw\DB\TableInfo\Sqlite3\Field;
use Minifw\DB\TableInfo\Sqlite3\Index;
use Minifw\DB\TableInfo\Sqlite3\Status;
use Minifw\DB\TableInfo\Sqlite3\Table;

class CreateTable
{
    protected Scanner $scaner;
    protected Table $obj;

    public function __construct(Scanner $scanner)
    {
        $this->scaner = $scanner;
    }

    public function parse(Driver $driver) : Table
    {
        $this->fields = [];
        $this->scaner->reset();

        $this->obj = new Table($driver);

        try {
            $status = $this->_parseTableName();
            $this->_parseTableContent();
            $this->_parseTableStatus($status);
        } catch (Exception $ex) {
            throw new Exception('分析出错，位于: ' . $this->scaner->getPos() . "\n完整语句: " . $this->scaner->getSql() . "\n发生于: " . $ex->getFile() . '[' . $ex->getLine() . ']:' . $ex->getMessage());
        }

        return $this->obj;
    }

    ///////////////////////////////////////////

    protected function _parseTableName() : Status
    {
        $this->scaner->nextTokenIs(Token::TYPE_KEYWORD, 'CREATE');
        $this->scaner->nextTokenIs(Token::TYPE_KEYWORD, 'TABLE');

        $tbname = $this->scaner->nextTokenAs(Token::TYPE_STRING);
        $this->obj->set('tbname', $tbname);

        $status = new Status();

        while (true) {
            $token = $this->scaner->nextToken();
            if ($token === null) {
                throw new Exception('');
            }

            if ($token->type === Token::TYPE_KEYWORD) {
                continue;
            } elseif ($token->is(Token::TYPE_OPERATOR, '(')) {
                break;
            } elseif ($token->type === Token::TYPE_COMMENT) {
                $status->set('comment', $token->value);
            } else {
                throw new Exception('');
            }
        }

        return $status;
    }

    protected function _parseTableContent()
    {
        while (true) {
            $field = $this->nextField();
            if ($field === null) {
                break;
            }
            $this->obj->set('field', $field);
        }

        $index = $this->nextPrimary();
        if ($index !== null) {
            $this->obj->set('index', $index);
        }
    }

    protected function _parseTableStatus(Status $status)
    {
        $this->scaner->nextTokenIs(Token::TYPE_OPERATOR, ')');

        while (true) {
            $token = $this->scaner->nextToken();
            if ($token === null) {
                break;
            }

            if ($token->is(Token::TYPE_KEYWORD, 'WITHOUT')) {
                $this->scaner->nextTokenIs(Token::TYPE_KEYWORD, 'ROWID');
                $status->set('rowid', false);
            } else {
                throw new Exception('');
            }
        }

        $this->obj->set('status', $status);
    }

    /////////////////////////////////////////////

    protected function nextPrimary() : ?Index
    {
        if (!$this->scaner->nextTokenIS(Token::TYPE_KEYWORD, 'PRIMARY', false)) {
            return null;
        }

        $this->scaner->nextTokenIS(Token::TYPE_KEYWORD, 'KEY');
        $this->scaner->nextTokenIS(Token::TYPE_OPERATOR, '(');

        $index = new Index();
        $index->set('name', 'PRIMARY');

        $fields = [];
        while (true) {
            $token = $this->scaner->nextToken();
            if ($token === null) {
                throw new Exception('');
            }

            if ($token->type === Token::TYPE_OPERATOR) {
                if ($token->value === ')') {
                    if (empty($fields)) {
                        throw new Exception('');
                    }
                    break;
                } elseif ($token->value === ',') {
                    continue;
                }
            } elseif ($token->type === Token::TYPE_STRING) {
                $field = $token->value;
                $fields[] = $field;
            } else {
                throw new Exception('');
            }
        }

        $index->set('fields', $fields);

        $value = $this->scaner->nextTokenAs(Token::TYPE_COMMENT, false);
        if ($value !== null) {
            $index->set('comment', $value);
        }

        return $index;
    }

    protected function nextField() : ?Field
    {
        $field = new Field();

        $value = $this->scaner->nextTokenAs(Token::TYPE_STRING, false);
        if ($value === null) {
            return null;
        }

        $field->set('name', $value);
        $this->nextFieldType($field);

        $nullable = true;

        while (true) {
            $token = $this->scaner->nextToken();
            if ($token === null) {
                throw new Exception('');
            }

            if ($token->type == Token::TYPE_OPERATOR) {
                if ($token->value === ')') {
                    $this->scaner->pushToken($token);
                } elseif ($token->value !== ',') {
                    throw new Exception('');
                }
                break;
            } elseif ($token->type == Token::TYPE_COMMENT) {
                $field->set('comment', $token->value);
                continue;
            } elseif ($token->type !== Token::TYPE_KEYWORD) {
                throw new Exception('');
            }

            switch ($token->value) {
                case 'NOT':
                    $this->scaner->nextTokenIs(Token::TYPE_KEYWORD, 'NULL');
                    $nullable = false;
                    break;
                case 'AUTOINCREMENT':
                    $field->set('autoIncrement', true);
                    break;
                case 'DEFAULT':
                    $token = $this->scaner->nextToken();
                    if ($token->type === Token::TYPE_STRING) {
                        $field->set('default', $token->value);
                    } elseif ($token->is(Token::TYPE_KEYWORD, 'NULL')) {
                        $field->set('default', null);
                    } else {
                        throw new Exception('');
                    }
                    break;
                case 'COLLATE':
                    $value = $this->scaner->nextTokenAs(Token::TYPE_KEYWORD);
                    $field->set('collate', strtolower($value));
                    break;
                case 'PRIMARY':
                    $this->scaner->nextTokenIs(Token::TYPE_KEYWORD, 'KEY');
                    if ($field->getName() === '') {
                        throw new Exception('数据不合法');
                    }
                    $index = new Index();
                    $index->set('name', 'PRIMARY');
                    $index->set('fields', [$field->getName()]);
                    $this->obj->set('index', $index);
                    break;
                default:
                    throw new Exception($token->value);
            }
        }

        $field->set('nullable', $nullable);

        return $field;
    }

    protected function nextFieldType(Field $field) : void
    {
        $type = $this->scaner->nextTokenAs(Token::TYPE_KEYWORD);

        $type = strtolower($type);
        $field->set('type', $type);

        $token = $this->scaner->nextToken();

        if ($token->is(Token::TYPE_OPERATOR, '(')) {
            $attr = '';
            while (true) {
                $token = $this->scaner->nextToken();
                if ($token === null) {
                    throw new Exception('');
                }
                if ($token->type === Token::TYPE_OPERATOR) {
                    if ($token->value === ')') {
                        break;
                    } elseif ($token->value === ',') {
                        $attr .= ',';
                    } else {
                        throw new Exception('');
                    }
                } elseif ($token->type === Token::TYPE_KEYWORD) {
                    $attr .= $token->value;
                } else {
                    throw new Exception('');
                }
            }
        } else {
            $this->scaner->pushToken($token);
        }
    }
}
