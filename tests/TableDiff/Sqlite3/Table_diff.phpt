--TEST--
Table diff test
--SKIPIF--
<?php
require __DIR__ . '/../../bootstrap.php';
if (empty($config['sqlite3'])) {
    die('skip');
} ?>
--CAPTURE_STDIO--
STDOUT
--FILE--
<?php

namespace Minifw\DB\Test;

require __DIR__ . '/../../bootstrap.php';

use Minifw\Common\File;
use Minifw\DB\Driver\Sqlite3;
use Minifw\DB\Query;
use Minifw\DB\TableInfo;
use Minifw\DB\TableUtils;

$driver = new Sqlite3($config['sqlite3']);
Query::setDefaultDriver($driver);

Query::get()->query('drop table if exists `table1`');
Query::get()->query('drop table if exists `table2`');

if (file_exists(APP_ROOT . '/tmp/tests/db/sqlite3')) {
    (new File(APP_ROOT . '/tmp/tests/db/sqlite3'))->clearDir();
}

$dirList = [
    ['name' => 'json', 'format' => TableInfo::FORMAT_JSON],
];

foreach ($dirList as $dirInfo) {
    doFileTest($dirInfo, $driver);
}

function doFileTest($dirInfo, $driver)
{
    testCreate($dirInfo, $driver);
    testExport($dirInfo, $driver);
    testDiff($dirInfo, $driver);
}

function testCreate($dirInfo, $driver)
{
    echo '== create test of ' . $dirInfo['name'] . " begin ==\n";

    $dir = __DIR__ . '/old/' . $dirInfo['name'];
    $diff = TableUtils::file2dbCmpAll($driver, $dir, $dirInfo['format']);
    var_dump(count($diff));

    TableUtils::file2dbApplyAll($driver, $dir, $dirInfo['format'], true);
    $diff = TableUtils::file2dbCmpAll($driver, $dir, $dirInfo['format']);

    foreach ($diff as $one) {
        echo $one->display() . "\n";
    }
    var_dump(count($diff));

    echo '== create test of ' . $dirInfo['name'] . " end ==\n";
}
function testExport($dirInfo, $driver)
{
    echo '== export test of ' . $dirInfo['name'] . " begin ==\n";

    $dir = APP_ROOT . '/tmp/tests/db/sqlite3/' . $dirInfo['name'];
    TableUtils::exportAllDb($driver, $dir, $dirInfo['format']);

    $tables = ['table1', 'table2'];

    foreach ($tables as $table) {
        echo $table . "\n";
        $newInfo = TableInfo::loadFromFile($driver, $dir . '/' . $table . '.json', TableInfo::FORMAT_JSON);
        $oldInfo = TableInfo::loadFromFile($driver, __DIR__ . '/old/' . $dirInfo['name'] . '/' . $table . '.json', TableInfo::FORMAT_JSON);

        $diff = $newInfo->cmp($oldInfo);

        echo $diff->display();

        var_dump($diff->isEmpty());
    }

    echo '== export test of ' . $dirInfo['name'] . " end ==\n";
}

function testDiff($dirInfo, $driver)
{
    echo '== diff test of ' . $dirInfo['name'] . " begin ==\n";

    $dir = __DIR__ . '/new/' . $dirInfo['name'];
    $diff = TableUtils::file2dbCmpAll($driver, $dir, $dirInfo['format']);

    foreach ($diff as $one) {
        echo $one->display() . "\n";
    }

    var_dump(count($diff));

    TableUtils::file2dbApplyAll($driver, $dir, $dirInfo['format'], true);
    $diff = TableUtils::file2dbCmpAll($driver, $dir, $dirInfo['format']);
    foreach ($diff as $one) {
        echo $one->display();
    }
    var_dump(count($diff));

    echo '== diff test of ' . $dirInfo['name'] . " end ==\n";
}
?>
--EXPECT--
== create test of json begin ==
int(2)
int(0)
== create test of json end ==
== export test of json begin ==
table1
bool(true)
table2
bool(true)
== export test of json end ==
== diff test of json begin ==
--------table1--------
- CREATE UNIQUE INDEX `table1_no` on `table1` (`no`)
+ `phone` text COLLATE binary NOT NULL DEFAULT '' /* 电话 */
+ CREATE UNIQUE INDEX `table1_no` on `table1` (`phone`)
=============================
DROP INDEX IF EXISTS `table1_no`;
ALTER TABLE `table1` ADD `phone` text COLLATE binary NOT NULL DEFAULT '' /* 电话 */;
CREATE UNIQUE INDEX `table1_no` on `table1` (`phone`);
--------table2--------
- `age` integer NOT NULL /* 年龄 */
- `address` text COLLATE binary NOT NULL DEFAULT '' /* 地址 */
=============================
PRAGMA foreign_keys='0';
CREATE TABLE IF NOT EXISTS `tmp_table2_0` ( /* table2 */
`main_id` integer NOT NULL /* 顶级ID */,
`sub_id` integer NOT NULL /* 子ID */,
`no` text COLLATE binary NOT NULL /* 编号 */,
`name` text COLLATE binary NOT NULL /* 姓名 */,
PRIMARY KEY (`main_id`,`sub_id`)
) WITHOUT ROWID;
INSERT INTO `tmp_table2_0` SELECT `main_id`,`sub_id`,`no`,`name` FROM `table2`;
PRAGMA defer_foreign_keys = '1';
DROP TABLE `table2`;
ALTER TABLE `tmp_table2_0` RENAME TO `table2`;
PRAGMA defer_foreign_keys = '0';
CREATE UNIQUE INDEX `table2_no` on `table2` (`no`);
PRAGMA foreign_keys='1';
int(2)
int(0)
== diff test of json end ==