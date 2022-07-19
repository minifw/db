<?php

namespace Minifw\DB\TableComparer;

use Exception;
use Minifw\DB\SqlBuilder\BuilderMysql;
use Minifw\DB\TableInfo\MysqliViewInfo;

class MysqlViewComparer extends Comparer
{
    protected MysqliViewInfo $newCfg;
    protected ?MysqliViewInfo $oldCfg;
    protected string $user = '';

    public function __construct(MysqliViewInfo $newCfg, ?MysqliViewInfo $oldCfg, string $user)
    {
        parent::__construct($newCfg->tbname);
        $this->newCfg = $newCfg;
        $this->oldCfg = $oldCfg;
        $this->user = $user;
    }

    ///////////////////////////////
    protected function calcDiff() : void
    {
        if (empty($this->oldCfg)) {
            $sql = BuilderMysql::sqlCreateView(
                $this->tbname,
                $this->newCfg->algorithm,
                $this->user,
                $this->newCfg->security,
                $this->newCfg->sql
            );
            $this->diffDisplay[] = '+ ' . $sql;
            $this->diffTrans[] = $sql;

            return;
        }

        $changed = false;
        if ($this->newCfg->algorithm != $this->oldCfg->algorithm) {
            $changed = true;
            $this->diffDisplay[] = '- ALGORITHM=' . $this->oldCfg->algorithm . " \n+ ALGORITHM=" . $this->newCfg->algorithm;
        }

        if ($this->newCfg->security != $this->oldCfg->security) {
            $changed = true;
            $this->diffDisplay[] = '- SQL SECURITY=' . $this->oldCfg->security . " \n+ SQL SECURITY=" . $this->newCfg->security;
        }

        if ($this->newCfg->sql != $this->oldCfg->sql) {
            $changed = true;
            $this->diffDisplay[] = '- SQL=' . $this->oldCfg->sql . " \n+ SQL=" . $this->newCfg->sql;
        }

        if ($changed) {
            $this->diffTrans[] = 'DROP VIEW IF EXISTS `' . $this->tbname . '`';
            $this->diffTrans[] = BuilderMysql::sqlCreateView($this->tbname, $this->newCfg->algorithm, $this->user, $this->newCfg->security, $this->newCfg->sql);
        }
    }
}
