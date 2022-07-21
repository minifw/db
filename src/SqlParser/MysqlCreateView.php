<?php

namespace Minifw\DB\SqlParser;

use Minifw\Common\Exception;
use Minifw\DB\TableInfo\Info;
use Minifw\DB\TableInfo\MysqliViewInfo;

class MysqlCreateView extends Parser
{
    protected string $tbname;
    protected ?string $algorithm;
    protected ?string $definer;
    protected ?string $sqlSecurity;
    protected string $viewSql;

    public function __construct(string $sql)
    {
        parent::__construct($sql);
    }

    protected function _parse($driver) : MysqliViewInfo
    {
        $this->tbname = '';
        $this->viewSql = '';
        $this->algorithm = null;
        $this->definer = null;
        $this->sqlSecurity = null;

        $this->_parseSql();

        $info = [
            'type' => 'view',
            'tbname' => $this->tbname,
            'algorithm' => $this->algorithm,
            'security' => $this->sqlSecurity,
            'sql' => $this->viewSql,
        ];

        return new MysqliViewInfo($driver, $info);
    }

    protected function _parseSql()
    {
        $token = $this->nextToken();
        if ($token[0] !== self::TYPE_KEYWORD || $token[1] !== 'CREATE') {
            throw new Exception('');
        }
        while (true) {
            $token = $this->nextToken();
            if ($token === null) {
                throw new Exception('');
            }

            if ($token[0] !== self::TYPE_KEYWORD) {
                throw new Exception('');
            }

            switch ($token[1]) {
                case 'ALGORITHM':
                    $token = $this->nextToken();
                    if ($token === null || $token[0] !== self::TYPE_OPERATOR || $token[1] !== '=') {
                        throw new Exception('');
                    }

                    $token = $this->nextToken();
                    if ($token === null || $token[0] !== self::TYPE_KEYWORD) {
                        throw new Exception('');
                    }

                    $this->algorithm = $token[1];
                    break;
                case 'DEFINER':
                    $token = $this->nextToken();
                    if ($token === null || $token[0] !== self::TYPE_OPERATOR || $token[1] !== '=') {
                        throw new Exception('');
                    }

                    $token = $this->nextToken();
                    if ($token === null || $token[0] !== self::TYPE_FIELD) {
                        throw new Exception('');
                    }

                    $this->definer = '`' . $token[1] . '`';

                    $token = $this->nextToken();
                    if ($token === null || $token[0] !== self::TYPE_OPERATOR || $token[1] !== '@') {
                        throw new Exception(json_encode($token));
                    }

                    $token = $this->nextToken();
                    if ($token === null || $token[0] !== self::TYPE_FIELD) {
                        throw new Exception('');
                    }

                    $this->definer .= '@`' . $token[1] . '`';
                    break;
                case 'SQL':
                    $token = $this->nextToken();
                    if ($token === null || $token[0] !== self::TYPE_KEYWORD || $token[1] !== 'SECURITY') {
                        throw new Exception('');
                    }

                    $token = $this->nextToken();
                    if ($token === null || $token[0] !== self::TYPE_KEYWORD) {
                        throw new Exception('');
                    }

                    $this->sqlSecurity = $token[1];
                    break;
                case 'VIEW':
                    $token = $this->nextToken();
                    if ($token === null || $token[0] !== self::TYPE_FIELD) {
                        throw new Exception('');
                    }

                    $this->tbname = $token[1];

                    $token = $this->nextToken();
                    if ($token === null || $token[0] !== self::TYPE_KEYWORD || $token[1] !== 'AS') {
                        throw new Exception('');
                    }

                    break 2;
                default:
                    throw new Exception('');
            }
        }

        $this->viewSql = trim($this->nextAll());
    }
}
