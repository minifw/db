<?php

namespace Minifw\DB\Test;

use Minifw\DB\Table;

class TableWithAll1 extends Table
{
    public static string $tbname = 'table_with_all';
    public static array $status = [
        'engine' => 'InnoDB',
        'charset' => 'utf8mb3',
        'comment' => 'Table To Create',
        'collate' => 'utf8_general_ci',
    ];
    public static array $field = [
        'id' => ['type' => 'int', 'attr' => '10', 'unsigned' => true, 'autoIncrement' => true, 'comment' => 'ID'],
        'intfield' => ['type' => 'int', 'attr' => '11', 'comment' => 'A int field'],
        'charfield' => ['type' => 'varchar', 'attr' => '200',  'comment' => 'A varchar field'],
        'textfield' => ['type' => 'text', 'charset' => 'utf8mb4', 'collate' => 'utf8mb4_general_ci', 'comment' => 'A new text field'],
        'intfield_def1' => ['type' => 'int', 'attr' => '11', 'default' => '0', 'comment' => 'A int field'],
        'charfield_def' => ['type' => 'varchar', 'attr' => '200', 'default' => '', 'comment' => 'A varchar field'],
        'decimal_f1' => ['type' => 'decimal', 'attr' => '20,2', 'default' => '0.00', 'comment' => 'A decimal field'],
        'enum_f1' => ['type' => 'enum', 'attr' => '\'Smail\',\'Medium\',\'Large\'',  'comment' => 'A enum field'],
    ];
    public static array $index = [
        'PRIMARY' => ['fields' => ['id'], 'comment' => '主键'],
        'uniqueindex' => ['fields' => ['intfield']],
        'intfield' => ['fields' => ['intfield', 'charfield']],
        'mixfield' => ['fields' => ['charfield', 'intfield']],
    ];

    protected function _prase(array $post, array $odata = []) : array
    {
        return [];
    }
}
