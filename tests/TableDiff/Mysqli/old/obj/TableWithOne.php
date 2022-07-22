<?php

namespace Minifw\DB\Test;

use Minifw\DB\Table;

class TableWithOne extends Table
{
    public static string $tbname = 'table_with_one';
    public static array $status = [
        'engine' => 'InnoDB',
        'charset' => 'utf8mb4',
        'comment' => 'Table To Create',
        'collate' => 'utf8mb4_general_ci',
    ];
    public static array $field = [
        'intfield' => ['type' => 'int', 'attr' => '11', 'comment' => 'A int field'],
    ];
    public static array $index = [
    ];

    protected function _prase(array $post, array $odata = []) : array
    {
        return [];
    }
}
