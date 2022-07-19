<?php

namespace Minifw\DB\TableComparer;

use Exception;
use Minifw\DB\SqlBuilder\BuilderMysql;

class MysqlTableComparer extends Comparer
{
    protected array $lastSql;
    protected string $fromCharset;
    protected string $fromCollate;
    protected string $toCharset;
    protected string $toCollate;
    protected array $fieldRemoved;
    protected array $fieldAdd;

    public function __construct(array $newCfg, ?array $oldCfg)
    {
        parent::__construct($newCfg, $oldCfg);
    }

    ///////////////////////////////
    protected function calcDiff() : void
    {
        $this->lastSql = [];
        $this->fieldRemoved = [];
        $this->fieldAdd = [];

        if (empty($this->oldCfg)) {
            $this->calcCreateDiff();
        } else {
            $this->calcStatusDiff();
            $this->calcFieldDiff();
            $this->calcIndexDiff();
        }

        $this->diffTrans = array_merge($this->diffTrans, $this->lastSql);
    }

    /////////////////////////
    protected function calcStatusDiff() : void
    {
        $from = $this->oldCfg['status'];
        $to = $this->newCfg['status'];

        $fields = [
            'comment' => '',
            'collate' => '',
            'checksum' => 0
        ];

        foreach ($fields as $v => $default) {
            if (!isset($from[$v])) {
                $from[$v] = $default;
            }
            if (!isset($to[$v])) {
                $to[$v] = $default;
            }
        }

        if ($from['engine'] != $to['engine']) {
            $this->diffDisplay[] = '- ENGINE=' . $from['engine'] . "\n" . '+ ENGINE=' . $to['engine'];
            $this->diffTrans[] = 'ALTER TABLE `' . $this->tbname . '` ENGINE=' . $to['engine'] . ';';
        }
        if ($from['comment'] != $to['comment']) {
            $this->diffDisplay[] = '- COMMENT=\'' . $from['comment'] . "'\n" . '+ COMMENT=\'' . $to['comment'] . '\'';
            $this->diffTrans[] = 'ALTER TABLE `' . $this->tbname . '` COMMENT=\'' . str_replace('\'', '\'\'', $to['comment']) . '\';';
        }
        if ($from['checksum'] != $to['checksum']) {
            $this->diffDisplay[] = '- CHECKSUM=\'' . $from['checksum'] . "'\n" . '+ CHECKSUM=\'' . $to['checksum'] . '\'';
            $this->diffTrans[] = 'ALTER TABLE `' . $this->tbname . '` CHECKSUM=' . str_replace('\'', '\'\'', $to['checksum']) . ';';
        }
        if ($from['charset'] != $to['charset'] || $from['collate'] != $to['collate']) {
            $from_charset = 'DEFAULT CHARSET=' . $from['charset'];
            $to_charset = 'DEFAULT CHARSET=' . $to['charset'];

            if (!empty($from['collate'])) {
                $from_charset .= ' COLLATE ' . $from['collate'];
            }

            if (!empty($to['collate'])) {
                $to_charset .= ' COLLATE ' . $to['collate'];
            }

            $this->diffDisplay[] = '- ' . $from_charset . "'\n" . '+ ' . $to_charset;
            $this->diffTrans[] = 'ALTER TABLE `' . $this->tbname . '` ' . $to_charset . ';';
        }

        $this->fromCharset = $from['charset'];
        $this->toCharset = $to['charset'];
        $this->fromCollate = isset($from['collate']) ? $from['collate'] : '';
        $this->toCollate = isset($to['collate']) ? $to['collate'] : '';
    }

    protected function calcFieldDiff() : void
    {
        $this->calcFieldDel();
        $this->calcFieldChange();
        $this->calcFieldAdd();

        return;
    }

    protected function calcFieldDel() : void
    {
        $from = $this->oldCfg['field'];
        $to = $this->newCfg['field'];

        $i = 0;
        foreach ($from as $k => $v) {
            $i++;
            if (isset($to[$k])) {
                continue;
            }

            $from_sql = BuilderMysql::fieldToSql($k, $v, $this->fromCharset, $this->fromCollate, false);
            $this->diffDisplay[] = '-[' . $i . '] ' . $from_sql['sql'];
            $this->diffTrans[] = 'ALTER TABLE `' . $this->tbname . '` DROP `' . $k . '`;';

            $this->fieldRemoved[$k] = 1;
        }
    }

    protected function calcFieldAdd() : void
    {
        $from = $this->oldCfg['field'];
        $to = $this->newCfg['field'];

        $i = 0;
        $tail = ' first';
        foreach ($to as $k => $v) {
            $i++;

            if (!isset($from[$k])) {
                $to_sql = BuilderMysql::fieldToSql($k, $v, $this->toCharset, $this->toCollate, false);

                $this->fieldAdd[$k] = 1;

                if (isset($to_sql['sql_first'])) {
                    $this->diffDisplay[] = '+[' . $i . '] ' . $to_sql['sql'];

                    $this->diffTrans[] = 'ALTER TABLE `' . $this->tbname . '` ADD ' . $to_sql['sql_first'] . $tail . ';';

                    $this->lastSql[] = 'ALTER TABLE `' . $this->tbname . '` CHANGE `' . $k . '` ' . $to_sql['sql'] . $tail . ';';
                } else {
                    $this->diffDisplay[] = '+[' . $i . '] ' . $to_sql['sql'];

                    $this->diffTrans[] = 'ALTER TABLE `' . $this->tbname . '` ADD ' . $to_sql['sql'] . $tail . ';';
                }
            }

            $tail = ' after `' . $k . '`';
        }
    }

    protected function calcFieldChange() : void
    {
        $from = $this->oldCfg['field'];
        $to = $this->newCfg['field'];

        $i = 1;
        $left = 1;
        foreach ($from as $k => $v) {
            $from[$k]['no_ori'] = $i++;
            if (!isset($this->fieldRemoved[$k])) {
                $from[$k]['no_cur'] = $left++;
            }
        }

        $i = 1;
        foreach ($to as $k => $v) {
            $to[$k]['no'] = $i++;
        }

        $cur_index = 0;
        $tail = ' first';
        foreach ($to as $k => $v) {
            $i++;

            if (isset($from[$k])) {
                $cur_index++;

                $to_sql = BuilderMysql::fieldToSql($k, $v, $this->toCharset, $this->toCollate, false);
                $from_sql = BuilderMysql::fieldToSql($k, $from[$k], $this->fromCharset, $this->fromCollate, false);

                if ($from_sql['sql'] != $to_sql['sql'] || $cur_index != $from[$k]['no_cur']) {
                    $this->diffDisplay[] = '-[' . $from[$k]['no_ori'] . '] ' . $from_sql['sql'];
                    $this->diffDisplay[] = '+[' . $to[$k]['no'] . '] ' . $to_sql['sql'];

                    if (isset($to_sql['sql_first']) && !isset($from_sql['sql_first'])) {
                        $this->diffTrans[] = 'ALTER TABLE `' . $this->tbname . '` CHANGE `' . $k . '` ' . $to_sql['sql_first'] . $tail . ';';
                        $this->lastSql[] = 'ALTER TABLE `' . $this->tbname . '` CHANGE `' . $k . '` ' . $to_sql['sql'] . $tail . ';';
                    } else {
                        $this->diffTrans[] = 'ALTER TABLE `' . $this->tbname . '` CHANGE `' . $k . '` ' . $to_sql['sql'] . $tail . ';';
                    }

                    foreach ($from as $k1 => $v1) {
                        if (isset($from[$k1]['no_cur']) && $from[$k1]['no_cur'] < $from[$k]['no_cur']) {
                            $from[$k1]['no_cur']++;
                        }
                    }
                }
                $tail = ' after `' . $k . '`';
            }
        }
    }

    protected function calcIndexDiff() : void
    {
        $from = $this->oldCfg['index'];
        $to = $this->newCfg['index'];

        foreach ($to as $k => $v) {
            $to_sql = BuilderMysql::indexToSql($k, $v, false);
            if (!isset($from[$k])) {
                $this->diffDisplay[] = '+ ' . $to_sql;
                $this->diffTrans[] = 'ALTER TABLE `' . $this->tbname . '` ADD ' . $to_sql . ';';
                continue;
            }
            $from_sql = BuilderMysql::indexToSql($k, $from[$k], false);
            if ($from_sql != $to_sql) {
                $trans = 'ALTER TABLE `' . $this->tbname . '` DROP';
                if ($k == 'PRIMARY') {
                    $trans .= ' PRIMARY KEY';
                } else {
                    $trans .= ' INDEX `' . $k . '`';
                }
                $trans .= ', ADD ' . $to_sql . ';';

                $this->diffDisplay[] = '- ' . $from_sql;
                $this->diffDisplay[] = '+ ' . $to_sql;
                $this->diffTrans[] = $trans;

                continue;
            }
        }

        foreach ($from as $k => $v) {
            if (array_key_exists($k, $to)) {
                continue;
            }
            $has_removed = true;
            foreach ($v['fields'] as $field) {
                if (!isset($this->fieldRemoved[$field])) {
                    $has_removed = false;
                    break;
                }
            }
            $from_sql = BuilderMysql::indexToSql($k, $v, false);

            $this->diffDisplay[] = '- ' . $from_sql;

            if (!$has_removed) {
                if ($k == 'PRIMARY') {
                    $trans = 'ALTER TABLE `' . $this->tbname . '` DROP PRIMARY KEY;';
                } else {
                    $trans = 'ALTER TABLE `' . $this->tbname . '` DROP INDEX `' . $k . '`;';
                }

                $this->diffTrans[] = $trans;
            }
        }
    }

    protected function calcCreateDiff() : void
    {
        $table_diff = [];

        $cfg = $this->newCfg;

        $sql_display = BuilderMysql::sqlCreate(
            $cfg['tbname'],
            $cfg['status'],
            $cfg['field'],
            $cfg['index'],
            "\n+ "
        );
        $sql_exec = BuilderMysql::sqlCreate(
            $cfg['tbname'],
            $cfg['status'],
            $cfg['field'],
            $cfg['index'],
            "\n"
        );

        $this->diffDisplay[] = '+' . $sql_display;
        $this->diffTrans[] = $sql_exec . ';';

        if (!empty($cfg['init_table_sql'])) {
            $this->diffDisplay[] = '+' . $cfg['init_table_sql'];
            $this->diffTrans[] = $cfg['init_table_sql'] . ';';
        }
    }
}
