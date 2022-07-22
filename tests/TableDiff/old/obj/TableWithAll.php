<?php

namespace Minifw\DB\Test;

use Minifw\DB\Table;

class TableWithAll extends Table
{
    public static string $tbname = 'table_with_all';
    public static array $status = [
        'engine' => 'InnoDB',
        'charset' => 'utf8mb4',
        'comment' => 'Table To Create',
        'collate' => 'utf8mb4_general_ci',
    ];
    public static array $field = [
        'id' => ['type' => 'int', 'attr' => '10', 'unsigned' => true, 'autoIncrement' => true, 'comment' => 'ID'],
        'intfield' => ['type' => 'int', 'attr' => '11', 'comment' => 'A int field'],
        'charfield' => ['type' => 'varchar', 'attr' => '200', 'charset' => 'utf8mb3', 'collate' => 'utf8_general_ci', 'comment' => 'A varchar field'],
        'textfield' => ['type' => 'text',  'comment' => 'A new text field'],
        'intfield_def' => ['type' => 'int', 'attr' => '11', 'default' => '0', 'comment' => 'A int field'],
        'charfield_def' => ['type' => 'varchar', 'attr' => '200', 'default' => '', 'comment' => 'A varchar field'],
        'decimal_f' => ['type' => 'decimal', 'attr' => '20,2', 'default' => '0.00', 'comment' => 'A decimal field'],
        'enum_f' => ['type' => 'enum', 'attr' => '\'Smail\',\'Medium\',\'Large\'',  'comment' => 'A enum field'],
    ];
    public static array $index = [
        'PRIMARY' => ['fields' => ['id'], 'comment' => '主键'],
        'uniqueindex' => ['unique' => true, 'fields' => ['intfield']],
        'intfield' => ['fields' => ['intfield']],
        'mixfield' => ['fields' => ['charfield', 'intfield']],
    ];

    protected function _prase(array $post, array $odata = []) : array
    {
        return [];
    }
}
