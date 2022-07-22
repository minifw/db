--TEST--
Query Mysqli sql build test
--CAPTURE_STDIO--
STDOUT
--FILE--
<?php
require __DIR__ . '/../bootstrap.php';

use Minifw\DB\Driver\Mysqli;
use Minifw\DB\Query;
use Minifw\DB\TableInfo;
use Minifw\DB\TableUtils;

$driver = new Mysqli($config['mysql']);
Query::setDefaultDriver($driver);

Query::get()->query('drop table if exists `test_table`');

$info = TableInfo::loadFromFile($driver, __DIR__ . '/test_table.json', TableInfo::FORMAT_JSON);
$diff = TableUtils::dbCmp($driver, $info);
$diff->apply($driver);

$diff = TableUtils::dbCmp($driver, $info);

echo $diff->display();

var_dump($diff->isEmpty());

echo "\n";

$data = [
    ['name' => '张三', 'age' => 26, 'address' => '', 'code' => '123', 'sex' => '男', 'money' => '100.00'],
    ['name' => '李四', 'age' => '55', 'address' => '北京', 'code' => '', 'sex' => '女', 'money' => '200.00'],
    ['name' => '王五', 'age' => '35', 'address' => '上海', 'code' => '代码1', 'sex' => '未知'],
    ['name' => '赵六', 'age' => '34', 'address' => '广州', 'code' => ['expr', '`address`'], 'sex' => '男', 'money' => '53.01'],
    ['name' => '李华', 'age' => '16', 'address' => '<p>代码</p>', 'code' => ['rich', '<p>代码</p>'], 'sex' => '女'],
    ['name' => '韩梅梅', 'age' => 29, 'address' => '', 'code' => '', 'sex' => '女'],
];

foreach ($data as $v) {
    Query::get('test_table')->insert($v)->exec();
}

function print_result($result)
{
    foreach ($result as $line) {
        $val = array_values($line);
        echo implode(':', $val) . "\n";
    }
    echo "\n";
}

$query = Query::get('test_table')->select([])->all();
echo $query->dumpSql() . "\n";
$result = $query->exec();
print_result($result);

$query = Query::get('test_table')->select(['name', 'age', 'address', 'code'])->all();
echo $query->dumpSql() . "\n";
$result = $query->exec();
print_result($result);

$query = Query::get('test_table')->select(['name', 'age', 'address', 'code'])->first();
echo $query->dumpSql() . "\n";
$result = $query->exec();
$val = array_values($result);
echo implode(':', $val) . "\n\n";

$query = Query::get('test_table')->select(['name', 'age', 'address', 'code'])->order('age', 'asc')->first();
echo $query->dumpSql() . "\n";
$result = $query->exec();
$val = array_values($result);
echo implode(':', $val) . "\n\n";

$query = Query::get('test_table')->select(['name', 'age', 'address', 'code'])->order('age', 'asc')->limit(3)->all();
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

?>
--EXPECT--
bool(true)

select * from `test_table`;
1:26:张三::123:100.00:男
2:55:李四:北京::200.00:女
3:35:王五:上海:代码1:0.00:未知
4:34:赵六:广州:广州:53.01:男
5:16:李华:&lt;p&gt;代码&lt;/p&gt;:<p>代码</p>:0.00:女
6:29:韩梅梅:::0.00:女

select `name`,`age`,`address`,`code` from `test_table`;
张三:26::123
李四:55:北京:
王五:35:上海:代码1
赵六:34:广州:广州
李华:16:&lt;p&gt;代码&lt;/p&gt;:<p>代码</p>
韩梅梅:29::

select `name`,`age`,`address`,`code` from `test_table`;
张三:26::123

select `name`,`age`,`address`,`code` from `test_table` order by age;
李华:16:&lt;p&gt;代码&lt;/p&gt;:<p>代码</p>

select `name`,`age`,`address`,`code` from `test_table` order by age limit 3;
李华:16:&lt;p&gt;代码&lt;/p&gt;:<p>代码</p>
张三:26::123
韩梅梅:29::

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