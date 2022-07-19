<?php

namespace Minifw\DB\SqlParser;

use Minifw\Common\Exception;

class MysqlCreateView extends Parser
{
    protected $tbname = null;
    protected $algorithm = '';
    protected $definer = '';
    protected $sqlSecurity = '';
    protected $viewSql = '';

    public function init(string $sql, ?array $status = null, array $fields = []) : void
    {
        parent::init($sql);
        $this->tbname = null;
        $this->algorithm = '';
        $this->definer = '';
        $this->sqlSecurity = '';
        $this->viewSql = '';
    }

    protected function _parse() : array
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

        return [
            'type' => 'view',
            'tbname' => $this->tbname,
            'algorithm' => $this->algorithm,
            'sql_security' => $this->sqlSecurity,
            'view_sql' => $this->viewSql,
        ];
    }
}
