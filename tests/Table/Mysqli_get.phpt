--TEST--
Table get test
--SKIPIF--
<?php
require __DIR__ . '/../bootstrap.php';
if (empty($config['mysqli'])) {
    die('skip');
} ?>
--CAPTURE_STDIO--
STDOUT
--FILE--
<?php
require __DIR__ . '/../bootstrap.php';

use Minifw\DB\Driver\Mysqli;
use Minifw\DB\Query;
use Minifw\DB\Table;

$driver = new Mysqli($config['mysqli']);
Query::setDefaultDriver($driver);

class Table1 extends Table
{
    protected function _prase(array $post, array $odata = []) : array
    {
        return [];
    }
}

class Table2 extends Table
{
    protected function _prase(array $post, array $odata = []) : array
    {
        return [];
    }
}

$t1 = Table1::get();
$t2 = Table2::get();
$t3 = Table2::get($driver);

echo get_class($t1) . "\n";
echo get_class($t2) . "\n";
echo get_class($t3) . "\n";

var_dump($t2 === $t3);

?>
--EXPECT--
Table1
Table2
Table2
bool(false)