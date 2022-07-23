--TEST--
Query Mysqli sql build test
--SKIPIF--
<?php
require __DIR__ . '/../../bootstrap.php';
if (empty($config['mysqli'])) {
    die('skip');
} ?>
--CAPTURE_STDIO--
STDOUT
--FILE--
<?php
require __DIR__ . '/../../bootstrap.php';

use Minifw\DB\Driver\Mysqli;
use Minifw\DB\Query;

$driver = new Mysqli($config['mysqli']);
Query::setDefaultDriver($driver);

echo Query::get()
    ->table('test1', 'T1')
    ->select(['t1', 't2'])
    ->limit(1, 10)
    ->first()
    ->dumpSql() . "\n";

echo Query::get()
    ->table('test1', 'T1')
    ->select(['t1', 't2'])
    ->limit(20)
    ->all()
    ->dumpSql() . "\n";

echo Query::get()
    ->table('test1', 'T1')
    ->select(['t1', 't2'])
    ->limit(20, 0)
    ->all()
    ->dumpSql() . "\n";

echo Query::get()
    ->table('test1', 'T1')
    ->select(['t1', 't2'])
    ->hash()
    ->dumpSql() . "\n";

echo Query::get()
    ->table('test1', 'T1')
    ->select(['t1', 't2'])
    ->where(['name' => ['have', 'fff']])
    ->all()
    ->dumpSql() . "\n";

echo "\n";

echo Query::get()
    ->table('test1', 'T1')
    ->count()
    ->dumpSql() . "\n";

echo "\n";

echo Query::get()
    ->table('test1', 'T1')
    ->select(['t1', 't2', ['expr', '1+2'], 'ggg' => ['expr', '1+2'], 'aaa' => '`bbb`'])
    ->hash()
    ->dumpSql() . "\n";

echo Query::get()
    ->table('test1', 'T1')
    ->select(['t1', 't2', 't3' => '`T2`.`a1`'])
    ->join('left join `test2` `T2` on `T1`.`id` = `T2`.`tid`')
    ->all()
    ->dumpSql() . "\n";

echo Query::get()
    ->table('test1', 'T1')
    ->select(['t1', 't2', 't3' => '`T2`.`a1`'])
    ->join('left join `test2` `T2` on `T1`.`id` = `T2`.`tid`')
    ->where(['id' => ['<', 300]])
    ->all()
    ->dumpSql() . "\n";

echo Query::get()
    ->table('test1', 'T1')
    ->select(['t1', 't2', 't3' => '`T2`.`a1`'])
    ->join('left join `test2` `T2` on `T1`.`id` = `T2`.`tid`')
    ->where('`T1`.`id` > 400 and `T2`.`id` <= 9')
    ->all()
    ->dumpSql() . "\n";

echo "\n";

echo Query::get('test1')
    ->where(['id' => 4])
    ->update(['name' => '44444'])
    ->dumpSql() . "\n";

echo Query::get('test1')
    ->update(['name' => '44444'])
    ->where(['id' => 4, 'name' => '555'])
    ->dumpSql() . "\n";

echo Query::get('test1')
    ->where(['id' => 4, 'name' => '555'], true)
    ->update(['name' => '44444'])
    ->dumpSql() . "\n";

echo "\n";

echo Query::get('test1')
    ->where(['id' => 4, 'name' => '555'])
    ->delete()
    ->dumpSql() . "\n";

echo Query::get('test1')
    ->where(['id' => 4, 'name' => '555', 'tid' => [
        'in', Query::get('test2')
            ->select(['id'])
            ->where(['id' => 666])
            ->all()
    ]])
    ->delete()
    ->dumpSql() . "\n";

echo Query::get('test1')
    ->where(['id' => 4, 'name' => '555', 'tid' => [
        'in', Query::get('test2')
            ->select(['id'])
            ->where(['id' => 4])
            ->all()
    ]])
    ->delete()
    ->dumpSql() . "\n";

echo Query::get('test1')
    ->where(['id' => 4, 'name' => '555', 'tid' => [
        'in', [1, 2, 3]
    ]])
    ->delete()
    ->dumpSql() . "\n";

echo Query::get('test1')
    ->where(['id' => 4, 'name' => '555', 'tid' => [
        '>', 333
    ]])
    ->delete()
    ->dumpSql() . "\n";

echo Query::get('test1')
    ->where(['tid' => [
        'between', 333, 555
    ]])
    ->delete()
    ->dumpSql() . "\n";

echo "\n";

echo Query::get('test1')
    ->insert(['id' => '123', 'name' => '456'])
    ->dumpSql() . "\n";

echo "\n";

echo Query::get('test1')
    ->replace(['id' => '123', 'name' => '456'])
    ->dumpSql() . "\n";

echo "\n";

echo Query::get()
    ->table('test1', 'T1')
    ->select('`id`,`name`')
    ->where('`T1`.`id` > 400 and `T2`.`id` <= 9')
    ->group('`id`')
    ->order('`name` desc')
    ->all()
    ->dumpSql() . "\n";

echo Query::get()
    ->table('test1', 'T1')
    ->select('`id`,`name`')
    ->where('`T1`.`id` > 400 and `T2`.`id` <= 9')
    ->group(['id', 'tid'])
    ->order(['name' => 'desc', 'id' => 'asc'])
    ->all()
    ->dumpSql() . "\n";

echo Query::get()
    ->table('test1', 'T1')
    ->select('`id`,`name`')
    ->where(['id' => 9])
    ->first()
    ->lock()
    ->dumpSql() . "\n";
?>
--EXPECT--
select `t1`,`t2` from `test1` `T1` limit 10,1;
select `t1`,`t2` from `test1` `T1` limit 20;
select `t1`,`t2` from `test1` `T1` limit 20;
select `t1`,`t2` from `test1` `T1`;
select `t1`,`t2` from `test1` `T1` where `name` like :name; [name:%fff%]

select count(*) from `test1` `T1`;

select `t1`,`t2`,1+2,1+2 as `ggg`,`bbb` as `aaa` from `test1` `T1`;
select ``T1``.`t1`,``T1``.`t2`,`T2`.`a1` as `t3` from `test1` `T1` left join `test2` `T2` on `T1`.`id` = `T2`.`tid`;
select ``T1``.`t1`,``T1``.`t2`,`T2`.`a1` as `t3` from `test1` `T1` left join `test2` `T2` on `T1`.`id` = `T2`.`tid` where `test1`.`id`<:id; [id:300]
select ``T1``.`t1`,``T1``.`t2`,`T2`.`a1` as `t3` from `test1` `T1` left join `test2` `T2` on `T1`.`id` = `T2`.`tid` where `T1`.`id` > 400 and `T2`.`id` <= 9;

update `test1` set `name`=:name where `id`=:id; [name:44444, id:4]
update `test1` set `name`=:name where `id`=:id and `name`=:name_1; [name:44444, id:4, name_1:555]
update `test1` set `name`=:name where `id`=:id or `name`=:name_1; [name:44444, id:4, name_1:555]

delete from `test1` where `id`=:id and `name`=:name; [id:4, name:555]
delete from `test1` where `id`=:id and `name`=:name and `tid` in (select `id` from `test2` where `id`=:id_1); [id:4, name:555, id_1:666]
delete from `test1` where `id`=:id and `name`=:name and `tid` in (select `id` from `test2` where `id`=:id); [id:4, name:555]
delete from `test1` where `id`=:id and `name`=:name and `tid` in ('1','2','3'); [id:4, name:555]
delete from `test1` where `id`=:id and `name`=:name and `tid`>:tid; [id:4, name:555, tid:333]
delete from `test1` where `tid` between :tid_min and :tid_max; [tid_min:333, tid_max:555]

insert into `test1` (`id`,`name`) values (:id,:name); [id:123, name:456]

replace into `test1` (`id`,`name`) values (:id,:name); [id:123, name:456]

select `id`,`name` from `test1` `T1` where `T1`.`id` > 400 and `T2`.`id` <= 9 group by `id` order by `name` desc;
select `id`,`name` from `test1` `T1` where `T1`.`id` > 400 and `T2`.`id` <= 9 group by `id`,`tid` order by `name` desc,`id` asc;
select `id`,`name` from `test1` `T1` where `id`=:id for update; [id:9]