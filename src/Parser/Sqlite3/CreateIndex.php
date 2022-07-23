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
use Minifw\DB\TableInfo\Sqlite3\Index;
use Minifw\DB\TableInfo\Sqlite3\Table;

class CreateIndex
{
    protected Scanner $scaner;
    protected Table $obj;

    public function __construct(Scanner $scanner)
    {
        $this->scaner = $scanner;
    }

    public function parse(Table $obj) : void
    {
        $this->fields = [];
        $this->scaner->reset();

        $this->obj = $obj;

        try {
            $this->_parseIndex();
        } catch (Exception $ex) {
            throw new Exception('分析出错，位于: ' . $this->scaner->getPos() . "\n完整语句: " . $this->scaner->getSql() . "\n发生于: " . $ex->getFile() . '[' . $ex->getLine() . ']:' . $ex->getMessage());
        }
    }

    ///////////////////////////////////////////

    protected function _parseIndex()
    {
        $this->scaner->nextTokenIs(Token::TYPE_KEYWORD, 'CREATE');

        $index = new Index();

        $value = $this->scaner->nextTokenAs(Token::TYPE_KEYWORD);
        if ($value === 'UNIQUE') {
            $this->scaner->nextTokenIs(Token::TYPE_KEYWORD, 'INDEX');
            $index->set('unique', true);
        } elseif ($value !== 'INDEX') {
            throw new Exception('');
        }

        $name = $this->scaner->nextTokenAs(Token::TYPE_STRING);
        $index->set('name', $name);

        $this->scaner->nextTokenIs(Token::TYPE_KEYWORD, 'ON');

        $tbname = $this->scaner->nextTokenAs(Token::TYPE_STRING);

        $this->scaner->nextTokenIs(Token::TYPE_OPERATOR, '(');

        $fields = [];
        while (true) {
            $token = $this->scaner->nextToken();
            if ($token === null) {
                throw new Exception('');
            }

            if ($token->type === Token::TYPE_STRING) {
                $fields[] = $token->value;
            } elseif ($token->is(Token::TYPE_OPERATOR, ',')) {
                continue;
            } elseif ($token->is(Token::TYPE_OPERATOR, ')')) {
                break;
            } else {
                throw new Exception('');
            }
        }
        $index->set('fields', $fields);

        $comment = $this->scaner->nextTokenAs(Token::TYPE_COMMENT, false);
        if ($comment !== null) {
            $index->set('comment', $comment);
        }

        $this->obj->set('index', $index);
    }
}
