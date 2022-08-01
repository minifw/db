--TEST--
Query Sqlite sql query test
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
require __DIR__ . '/../../bootstrap.php';

use Minifw\DB\Driver\Sqlite3;
use Minifw\DB\Query;
use Minifw\DB\TableInfo;
use Minifw\DB\TableUtils;

$driver = new Sqlite3($config['sqlite3']);
Query::setDefaultDriver($driver);

Query::get()->query('drop table if exists `test_table`');

$info = TableInfo::loadFromFile($driver, __DIR__ . '/test_table.json', TableInfo::FORMAT_JSON);

$diff = TableUtils::dbCmp($driver, $info);
$diff->apply($driver, true);
$diff = TableUtils::dbCmp($driver, $info);

echo $diff->display();

var_dump($diff->isEmpty());

echo "\n";

$data = [
    ['name' => '张三', 'no' => '0001', 'age' => 26, 'address' => '', 'code' => '123',  'money' => '100.00'],
    ['name' => '李四', 'no' => '0002', 'age' => '55', 'address' => '北京', 'code' => '10.25',  'money' => '200.00'],
    ['name' => '王五', 'no' => '0003', 'age' => '35', 'address' => '上海', 'code' => '代码1'],
    ['name' => '赵六', 'no' => '0004', 'age' => '34', 'address' => '广州', 'code' => ['expr', '\'address\''],  'money' => '53.01'],
    ['name' => '李华', 'no' => '0005', 'age' => '16', 'address' => ['rich', '<p>代码</p>']],
    ['name' => '韩梅梅', 'no' => '0006', 'age' => 29, 'address' => '', 'code' => ''],
];

foreach ($data as $v) {
    $id = Query::get('test_table')->insert($v)->exec();
    echo $id . "\n";
}

function print_result($result)
{
    foreach ($result as $line) {
        $val = array_values($line);
        echo implode(':', $val) . "\n";
    }
    echo "\n";
}

$query = Query::get('test_table')->count();
echo $query->dumpSql() . "\n";
$result = $query->exec();
var_dump($result);

$query = Query::get('test_table')->select([])->all();
echo $query->dumpSql() . "\n";
$result = $query->exec();
print_result($result);

$query = Query::get('test_table')->select(['name', 'age', 'address', 'no'])->all();
echo $query->dumpSql() . "\n";
$result = $query->exec();
print_result($result);

$query = Query::get('test_table')->select(['name', 'age', 'address', 'no'])->first();
echo $query->dumpSql() . "\n";
$result = $query->exec();
$val = array_values($result);
echo implode(':', $val) . "\n\n";

$query = Query::get('test_table')->select(['name', 'age', 'address', 'no'])->order('age', 'asc')->first();
echo $query->dumpSql() . "\n";
$result = $query->exec();
$val = array_values($result);
echo implode(':', $val) . "\n\n";

$query = Query::get('test_table')->select(['name', 'age', 'address', 'no'])->order('age', 'asc')->limit(3)->all();
echo $query->dumpSql() . "\n";
$result = $query->exec();
print_result($result);

$query = Query::get('test_table')->select(['id', 'name'])->order('id', 'asc')->limit(3)->hash();
echo $query->dumpSql() . "\n";
$result = $query->exec();
echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n\n";

$query = Query::get('test_table')->select(['id', 'name'])->where(['id' => ['>', 2]])->order('id', 'asc')->limit(3)->hash();
echo $query->dumpSql() . "\n";
$result = $query->exec();
echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n\n";

$query = Query::get('test_table')->select(['id', 'name'])->where(['id' => ['in', [1, 3, 5]]])->order('id', 'asc')->limit(3)->hash();
echo $query->dumpSql() . "\n";
$result = $query->exec();
echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n\n";

$query = Query::get('test_table')->select(['id', 'name'])->where(['id' => ['>', 2], 'age' => ['<', 35]])->order('id', 'asc')->limit(3)->hash();
echo $query->dumpSql() . "\n";
$result = $query->exec();
echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n\n";

$query = Query::get('test_table')->select(['id', 'name'])->where(['id' => ['in', [1, 3, 5]], 'age' => ['<', 60]], true)->order('id', 'asc')->hash();
echo $query->dumpSql() . "\n";
$result = $query->exec();
echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n\n";

$query = Query::get('test_table')->select(['id', 'name'])->where(['id' => [['in', [1, 3, 5]]], 'age' => ['<', 50]], true)->order('id', 'asc')->hash();
echo $query->dumpSql() . "\n";
$result = $query->exec();
echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n\n";

$query = Query::get('test_table')->select(['id', 'name'])->where(['id' => [['in', [1, 3, 5]], ['=', 2]], 'age' => ['<', 50]], true)->order('id', 'asc')->hash();
echo $query->dumpSql() . "\n";
$result = $query->exec();
echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n\n";

$result = Query::get('test_table')->all()->map(function ($data) {
    if ($data['id'] == 3) {
        return null;
    }

    return $data['no'] . '|' . $data['id'];
});
foreach ($result as $value) {
    echo $value . "\n";
}

?>
--EXPECT--
bool(true)

1
2
3
4
5
6
select count(*) from `test_table`;
int(6)
select * from `test_table`;
1:0001:26:张三::123.0:100.00
2:0002:55:李四:北京:10.25:200.00
3:0003:35:王五:上海:代码1:0
4:0004:34:赵六:广州:address:53.01
5:0005:16:李华:<p>代码</p>:0.0:0
6:0006:29:韩梅梅:::0

select `name`,`age`,`address`,`no` from `test_table`;
张三:26::0001
李四:55:北京:0002
王五:35:上海:0003
赵六:34:广州:0004
李华:16:<p>代码</p>:0005
韩梅梅:29::0006

select `name`,`age`,`address`,`no` from `test_table`;
张三:26::0001

select `name`,`age`,`address`,`no` from `test_table` order by age;
李华:16:<p>代码</p>:0005

select `name`,`age`,`address`,`no` from `test_table` order by age limit 3;
李华:16:<p>代码</p>:0005
张三:26::0001
韩梅梅:29::0006

select `id`,`name` from `test_table` order by id limit 3;
{"1":"张三","2":"李四","3":"王五"}

select `id`,`name` from `test_table` where `id`>:id order by id limit 3; [id:2]
{"3":"王五","4":"赵六","5":"李华"}

select `id`,`name` from `test_table` where `id` in ('1','3','5') order by id limit 3;
{"1":"张三","3":"王五","5":"李华"}

select `id`,`name` from `test_table` where `id`>:id and `age`<:age order by id limit 3; [id:2, age:35]
{"4":"赵六","5":"李华","6":"韩梅梅"}

select `id`,`name` from `test_table` where `id` in ('1','3','5') or `age`<:age order by id; [age:60]
{"1":"张三","2":"李四","3":"王五","4":"赵六","5":"李华","6":"韩梅梅"}

select `id`,`name` from `test_table` where `id` in ('1','3','5') or `age`<:age order by id; [age:50]
{"1":"张三","3":"王五","4":"赵六","5":"李华","6":"韩梅梅"}

select `id`,`name` from `test_table` where `id` in ('1','3','5') or `id`=:id or `age`<:age order by id; [id:2, age:50]
{"1":"张三","2":"李四","3":"王五","4":"赵六","5":"李华","6":"韩梅梅"}

0001|1
0002|2
0004|4
0005|5
0006|6