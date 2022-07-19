<?php

namespace Minifw\DB\TableComparer;

use Exception;
use Minifw\DB\SqlBuilder\BuilderMysql;

class MysqlViewComparer extends Comparer
{
    protected string $user = '';

    public function __construct(array $newCfg, ?array $oldCfg, string $user)
    {
        parent::__construct($newCfg, $oldCfg);
        $this->user = $user;
    }

    ///////////////////////////////
    protected function calcDiff() : void
    {
        if (empty($this->oldCfg)) {
            $sql = BuilderMysql::sqlCreateView($this->tbname, $this->newCfg['algorithm'], $this->user, $this->newCfg['sql_security'], $this->newCfg['view_sql']);
            $this->diffDisplay[] = '+ ' . $sql;
            $this->diffTrans[] = $sql;

            return;
        }

        $changed = false;
        if ($this->newCfg['algorithm'] != $this->oldCfg['algorithm']) {
            $changed = true;
            $this->diffDisplay[] = '- ALGORITHM=' . $this->oldCfg['algorithm'] . " \n+ ALGORITHM=" . $this->newCfg['algorithm'];
        }

        if ($this->newCfg['sql_security'] != $this->oldCfg['sql_security']) {
            $changed = true;
            $this->diffDisplay[] = '- SQL SECURITY=' . $this->oldCfg['sql_security'] . " \n+ SQL SECURITY=" . $this->newCfg['sql_security'];
        }

        if ($this->newCfg['view_sql'] != $this->oldCfg['view_sql']) {
            $changed = true;
            $this->diffDisplay[] = '- SQL=' . $this->oldCfg['view_sql'] . " \n+ SQL=" . $this->newCfg['view_sql'];
        }

        if ($changed) {
            $this->diffTrans[] = 'DROP VIEW IF EXISTS `' . $this->tbname . '`';
            $this->diffTrans[] = BuilderMysql::sqlCreateView($this->tbname, $this->newCfg['algorithm'], $this->user, $this->newCfg['sql_security'], $this->newCfg['view_sql']);
        }
    }
}
