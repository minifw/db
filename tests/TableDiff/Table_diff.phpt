--TEST--
Table diff test
--CAPTURE_STDIO--
STDOUT
--FILE--
<?php

namespace Minifw\DB\Test;

require __DIR__ . '/../bootstrap.php';

use Minifw\Common\File;
use Minifw\DB\Driver\Mysqli;
use Minifw\DB\Query;
use Minifw\DB\TableInfo;
use Minifw\DB\TableUtils;

$driver = new Mysqli($config['mysql']);
Query::setDefaultDriver($driver);

Query::get()->query('drop table if exists `table_with_all`');
Query::get()->query('drop table if exists `table_with_one`');
Query::get()->query('drop view if exists `test_view`');

if (file_exists(APP_ROOT . '/tmp/tests/db')) {
    (new File(APP_ROOT . '/tmp/tests/db'))->clearDir();
}

$dirList = [
    ['name' => 'json', 'format' => TableInfo::FORMAT_JSON],
];

foreach ($dirList as $dirInfo) {
    doFileTest($dirInfo, $driver);
}

Query::get()->query('drop table if exists `table_with_all`');
Query::get()->query('drop table if exists `table_with_one`');
Query::get()->query('drop view if exists `test_view`');

doObjTest($driver);

function doFileTest($dirInfo, $driver)
{
    testCreate($dirInfo, $driver);
    testExport($dirInfo, $driver);
    testDiff($dirInfo, $driver);
}

function jsoncmp($left, $right)
{
    $left = json_encode(json_decode($left, true), JSON_UNESCAPED_UNICODE);
    $right = json_encode(json_decode($right, true), JSON_UNESCAPED_UNICODE);

    return strcmp($left, $right);
}

function testCreate($dirInfo, $driver)
{
    echo '== create test of ' . $dirInfo['name'] . " begin ==\n";

    $dir = __DIR__ . '/old/' . $dirInfo['name'];
    $diff = TableUtils::file2dbCmpAll($driver, $dir, $dirInfo['format']);
    var_dump(count($diff));

    TableUtils::file2dbApplyAll($driver, $dir, $dirInfo['format']);
    $diff = TableUtils::file2dbCmpAll($driver, $dir, $dirInfo['format']);

    foreach ($diff as $one) {
        echo $one->display();
    }
    var_dump(count($diff));

    echo '== create test of ' . $dirInfo['name'] . " end ==\n";
}

function testExport($dirInfo, $driver)
{
    echo '== export test of ' . $dirInfo['name'] . " begin ==\n";

    $dir = APP_ROOT . '/tmp/tests/db/' . $dirInfo['name'];
    TableUtils::exportAllDb($driver, $dir, $dirInfo['format']);

    $tables = ['table_with_all', 'table_with_one', 'test_view'];

    foreach ($tables as $table) {
        echo $table . "\n";
        $newInfo = TableInfo::loadFromFile($driver, $dir . '/' . $table . '.json', TableInfo::FORMAT_JSON);
        $oldInfo = TableInfo::loadFromFile($driver, __DIR__ . '/old/' . $dirInfo['name'] . '/' . $table . '.json', TableInfo::FORMAT_JSON);

        $diff = $newInfo->cmp($oldInfo);
        var_dump($diff->isEmpty());
    }

    echo '== export test of ' . $dirInfo['name'] . " end ==\n";
}

function testDiff($dirInfo, $driver)
{
    echo '== diff test of ' . $dirInfo['name'] . " begin ==\n";

    $dir = __DIR__ . '/new/' . $dirInfo['name'];
    $diff = TableUtils::file2dbCmpAll($driver, $dir, $dirInfo['format']);

    var_dump(count($diff));

    TableUtils::file2dbApplyAll($driver, $dir, $dirInfo['format']);
    $diff = TableUtils::file2dbCmpAll($driver, $dir, $dirInfo['format']);
    foreach ($diff as $one) {
        echo $one->display();
    }
    var_dump(count($diff));

    echo '== diff test of ' . $dirInfo['name'] . " end ==\n";
}

function doObjTest($driver)
{
    testObjCreate($driver);
    testObjDiff($driver);
}

function testObjCreate($driver)
{
    echo '== create test of ' . " obj begin ==\n";

    $dir = __DIR__ . '/old/obj';
    $diff = TableUtils::obj2dbCmpAll($driver, __NAMESPACE__, $dir);
    var_dump(count($diff));

    TableUtils::obj2dbApplyAll($driver, __NAMESPACE__, $dir);
    $diff = TableUtils::obj2dbCmpAll($driver, __NAMESPACE__, $dir);
    foreach ($diff as $one) {
        echo $one->display();
    }
    var_dump(count($diff));

    echo '== create test of ' . " obj end ==\n";
}

function testObjDiff($driver)
{
    echo '== diff test of ' . " obj begin ==\n";

    $dir = __DIR__ . '/new/obj';
    $diff = TableUtils::obj2dbCmpAll($driver, __NAMESPACE__, $dir);
    var_dump(count($diff));

    TableUtils::obj2dbApplyAll($driver, __NAMESPACE__, $dir);
    $diff = TableUtils::obj2dbCmpAll($driver, __NAMESPACE__, $dir);
    foreach ($diff as $one) {
        echo $one->display();
    }
    var_dump(count($diff));

    echo '== diff test of ' . " obj end ==\n";
}

?>
--EXPECT--
== create test of json begin ==
int(3)
int(0)
== create test of json end ==
== export test of json begin ==
table_with_all
bool(true)
table_with_one
bool(true)
test_view
bool(true)
== export test of json end ==
== diff test of json begin ==
int(3)
int(0)
== diff test of json end ==
== create test of  obj begin ==
int(2)
int(0)
== create test of  obj end ==
== diff test of  obj begin ==
int(2)
int(0)
== diff test of  obj end ==