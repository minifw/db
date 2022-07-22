<?php

namespace Minifw\DB\Parser\Mysqli;

use Minifw\Common\Exception;
use Minifw\DB\TableInfo\Mysqli\View;

class CreateView
{
    protected Scanner $scaner;
    protected View $obj;

    public function __construct(Scanner $scanner)
    {
        $this->scaner = $scanner;
    }

    public function parse($driver) : View
    {
        $this->scaner->reset();
        $this->obj = new View($driver);

        $this->_parseSql();

        return $this->obj;
    }

    protected function _parseSql()
    {
        $this->scaner->nextTokenIs(Token::TYPE_KEYWORD, 'CREATE');

        while (true) {
            $value = $this->scaner->nextTokenAs(Token::TYPE_KEYWORD);

            switch ($value) {
                case 'ALGORITHM':
                    $this->scaner->nextTokenIs(Token::TYPE_OPERATOR, '=');
                    $value = $this->scaner->nextTokenAs(Token::TYPE_KEYWORD);

                    $this->obj->set('algorithm', strtolower($value));
                    break;
                case 'DEFINER':
                    $this->scaner->nextTokenIs(Token::TYPE_OPERATOR, '=');

                    $value = $this->scaner->nextTokenAs(Token::TYPE_FIELD);
                    //$definer = '`' . $value . '`';

                    $this->scaner->nextTokenIs(Token::TYPE_OPERATOR, '@');

                    $value = $this->scaner->nextTokenAs(Token::TYPE_FIELD);
                    //$definer .= '@`' . $value . '`';
                    break;
                case 'SQL':
                    $this->scaner->nextTokenIs(Token::TYPE_KEYWORD, 'SECURITY');
                    $value = $this->scaner->nextTokenAs(Token::TYPE_KEYWORD);

                    $this->obj->set('security', strtolower($value));
                    break;
                case 'VIEW':
                    $tbname = $this->scaner->nextTokenAs(Token::TYPE_FIELD);
                    $this->obj->set('tbname', $tbname);

                    $this->scaner->nextTokenIs(Token::TYPE_KEYWORD, 'AS');
                    break 2;
                default:
                    throw new Exception('');
            }
        }

        $sql = trim($this->scaner->nextAll());
        $this->obj->set('sql', $sql);
    }
}
