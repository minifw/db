<?php

namespace Minifw\DB\TableComparer;

use Exception;
use Minifw\DB\TableInfo\Info;

abstract class Comparer
{
    protected string $tbname;
    protected array $diffDisplay;
    protected array $diffTrans;

    public function __construct(string $tbname)
    {
        $this->tbname = $tbname;
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
