<?php

namespace Minifw\DB\TableComparer;

use Exception;

abstract class Comparer
{
    protected array $newCfg;
    protected ?array $oldCfg;
    protected string $tbname;
    protected array $diffDisplay;
    protected array $diffTrans;

    public function __construct(array $newCfg, ?array $oldCfg)
    {
        $this->newCfg = $newCfg;
        $this->oldCfg = $oldCfg;

        $this->tbname = $newCfg['tbname'];
    }

    public function getDiff() : array
    {
        $this->diffDisplay = [];
        $this->diffTrans = [];

        $this->calcDiff();

        if (empty($this->diffDisplay) && empty($this->diffTrans)) {
            return [];
        }

        return [
            'display' => $this->diffDisplay,
            'trans' => $this->diffTrans,
        ];
    }

    ///////////////////////////////
    abstract protected function calcDiff() : void;
}
