<?php

namespace Minifw\DB\Test;

use Minifw\DB\Table;

class TableWithOne1 extends Table
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
        'charfield' => ['type' => 'varchar', 'attr' => '200',  'comment' => 'A varchar field'],
        'textfield' => ['type' => 'text', 'charset' => 'utf8mb3', 'collate' => 'utf8_general_ci', 'comment' => 'A new text field'],
    ];
    public static array $index = [
        'intfield' => ['fields' => ['intfield', 'charfield']],
    ];

    protected function _prase(array $post, array $odata = []) : array
    {
        return [];
    }
}
