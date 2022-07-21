<?php

namespace Minifw\DB\MysqliParser;

use Minifw\Common\Exception;
use Minifw\DB\TableInfo\Info;
use Minifw\DB\TableInfo\MysqliViewInfo;

class MysqliCreateView
{
    protected MysqliScanner $scaner;
    protected MysqliViewInfo $obj;

    public function __construct(MysqliScanner $scanner)
    {
        $this->scaner = $scanner;
    }

    public function parse($driver) : MysqliViewInfo
    {
        $this->scaner->reset();
        $this->obj = new MysqliViewInfo($driver);

        $this->_parseSql();

        return $this->obj;
    }

    protected function _parseSql()
    {
        $this->scaner->nextTokenIs(MysqliToken::TYPE_KEYWORD, 'CREATE');

        while (true) {
            $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_KEYWORD);

            switch ($value) {
                case 'ALGORITHM':
                    $this->scaner->nextTokenIs(MysqliToken::TYPE_OPERATOR, '=');
                    $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_KEYWORD);

                    $this->obj->set('algorithm', strtolower($value));
                    break;
                case 'DEFINER':
                    $this->scaner->nextTokenIs(MysqliToken::TYPE_OPERATOR, '=');

                    $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_FIELD);
                    //$definer = '`' . $value . '`';

                    $this->scaner->nextTokenIs(MysqliToken::TYPE_OPERATOR, '@');

                    $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_FIELD);
                    //$definer .= '@`' . $value . '`';
                    break;
                case 'SQL':
                    $this->scaner->nextTokenIs(MysqliToken::TYPE_KEYWORD, 'SECURITY');
                    $value = $this->scaner->nextTokenAs(MysqliToken::TYPE_KEYWORD);

                    $this->obj->set('security', strtolower($value));
                    break;
                case 'VIEW':
                    $tbname = $this->scaner->nextTokenAs(MysqliToken::TYPE_FIELD);
                    $this->obj->set('tbname', $tbname);

                    $this->scaner->nextTokenIs(MysqliToken::TYPE_KEYWORD, 'AS');
                    break 2;
                default:
                    throw new Exception('');
            }
        }

        $sql = trim($this->scaner->nextAll());
        $this->obj->set('sql', $sql);
    }
}
