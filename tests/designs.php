<?php
declare(strict_types=1);
// My designs: run with  php tests/designs.php
require dirname(__DIR__).'/app/Auth.php';require dirname(__DIR__).'/app/Admin.php';require dirname(__DIR__).'/app/Projects.php';require dirname(__DIR__).'/app/Designs.php';require dirname(__DIR__).'/app/migrate.php';
$checks=0;function check(bool $v,string $label):void{global $checks;if(!$v)throw new RuntimeException('FAIL: '.$label);$checks++;echo "PASS: $label\n";}
function denies(Closure $f,string $class,string $label):void{try{$f();}catch(Throwable $e){if($e instanceof $class){check(true,$label);return;}throw $e;}throw new RuntimeException('FAIL: '.$label);}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
migrateAccounts($db);migrateAccounts($db);
check((bool)$db->query('SELECT version FROM app_migrations WHERE version=3')->fetchColumn(),'migration 3 adds the designs tables and can run twice');
function seed(string $name,bool $verified=true,string $status='active'):int{global $db;$db->prepare('INSERT INTO users(public_id,name,email,password_hash,role,status,verified_at,created_at) VALUES(?,?,?,?,?,?,?,?)')->execute([$name,$name,$name.'@example.com','x','user',$status,$verified?time():null,time()]);return (int)$db->lastInsertId();}
$owner=seed('owner');$other=seed('other');$pending=seed('pending',false);$suspended=seed('suspended',true,'suspended');
$lib=new DesignLibrary($db,new ProjectSubmission($db,sys_get_temp_dir().'/archi-missing-blueprints'));
$snap=fn(string $room,int $n=1)=>json_encode(['application'=>'ARCHIspace','version'=>'3.0','state'=>['rooms'=>array_map(fn($i)=>['x'=>$i,'z'=>0,'w'=>3,'d'=>4,'name'=>"$room $i"],range(1,$n)),'site'=>['width'=>20,'depth'=>20],'blueprints'=>[]]]);

$a=$lib->save($owner,null,'  My   first house ','House',$snap('Living'));
check($a['version']===1 && preg_match('/^[a-f0-9]{32}$/',$a['id'])===1 && $a['name']==='My first house','new design starts at version 1 with a clean name');
$b=$lib->save($owner,$a['id'],'My first house','House',$snap('Kitchen'),1);
check($b['version']===2 && $b['id']===$a['id'],'saving again adds version 2 to the same design');
$g=$lib->get($owner,$a['id']);check($g['version']===2 && $g['snapshot']['state']['rooms'][0]['name']==='Kitchen 1','open returns the latest version');
$g1=$lib->get($owner,$a['id'],1);check($g1['version']===1 && $g1['latest']===2 && $g1['snapshot']['state']['rooms'][0]['name']==='Living 1','an older version can be opened');
check(count($lib->versions($owner,$a['id']))===2 && $lib->versions($owner,$a['id'])[0]['version']===2,'versions are listed newest first');
denies(fn()=>$lib->save($owner,$a['id'],'My first house','House',$snap('Old tab'),1),StaleChange::class,'save from an old tab (stale version) is refused');
check($lib->get($owner,$a['id'])['version']===2,'stale save changed nothing');
check(count($lib->list($owner))===1 && $lib->list($owner)[0]['versions']===2 && count($lib->list($other))===0,'list shows only the owner\'s designs');
denies(fn()=>$lib->get($other,$a['id']),AccessDenied::class,'another user cannot open the design');
denies(fn()=>$lib->save($other,$a['id'],'Hijack','House',$snap('X')),AccessDenied::class,'another user cannot save over the design');
denies(fn()=>$lib->rename($other,$a['id'],'Hijack'),AccessDenied::class,'another user cannot rename the design');
denies(fn()=>$lib->delete($other,$a['id']),AccessDenied::class,'another user cannot delete the design');
denies(fn()=>$lib->list($pending),AccessDenied::class,'unverified account cannot use My designs');
denies(fn()=>$lib->save($suspended,null,'S','House',$snap('S')),AccessDenied::class,'suspended account cannot save');
denies(fn()=>$lib->get($owner,'not-an-id'),InvalidArgumentException::class,'malformed design ID rejected');
denies(fn()=>$lib->save($owner,null,'','House',$snap('X')),InvalidArgumentException::class,'empty name rejected');
denies(fn()=>$lib->save($owner,null,str_repeat('n',101),'House',$snap('X')),InvalidArgumentException::class,'name over 100 characters rejected');
denies(fn()=>$lib->save($owner,null,'Bad kind','Castle',$snap('X')),InvalidArgumentException::class,'unknown building type rejected');
denies(fn()=>$lib->save($owner,null,'Bad json','House','{not json'),InvalidArgumentException::class,'invalid snapshot rejected');
denies(fn()=>$lib->save($owner,null,'Remote image','House',json_encode(['state'=>['rooms'=>[],'site'=>['width'=>1],'blueprints'=>[['data'=>'https://evil.example/x.png']]]])),InvalidArgumentException::class,'remote blueprint images rejected (same rules as submissions)');
$lib->rename($owner,$a['id'],'Renamed house');check($lib->get($owner,$a['id'])['name']==='Renamed house','rename works');
for($i=3;$i<=DesignLibrary::KEEP_VERSIONS+5;$i++)$lib->save($owner,$a['id'],'Renamed house','House',$snap('V'.$i),$i-1);
$vs=$lib->versions($owner,$a['id']);
check(count($vs)===DesignLibrary::KEEP_VERSIONS && $vs[0]['version']===DesignLibrary::KEEP_VERSIONS+5,'only the newest '.DesignLibrary::KEEP_VERSIONS.' versions are kept');
denies(fn()=>$lib->get($owner,$a['id'],1),DomainException::class,'pruned version reports that it is no longer kept');
for($i=count($lib->list($owner));$i<DesignLibrary::MAX_DESIGNS;$i++)$lib->save($owner,null,'D'.$i,'Commercial',$snap('R'));
denies(fn()=>$lib->save($owner,null,'One too many','House',$snap('R')),DomainException::class,'design count limit enforced');
$lib->delete($owner,$a['id']);
check(count($lib->list($owner))===DesignLibrary::MAX_DESIGNS-1 && (int)$db->query('SELECT count(*) FROM design_versions v LEFT JOIN designs d ON d.id=v.design_id WHERE d.id IS NULL')->fetchColumn()===0,'delete removes the design and all its versions');
$big=json_encode(['state'=>['rooms'=>array_map(fn($i)=>['x'=>0,'z'=>0,'w'=>3,'d'=>4,'name'=>str_repeat('x',99)],range(1,500)),'site'=>['width'=>20,'depth'=>20,'pad'=>str_repeat('p',3*1024*1024)],'blueprints'=>[]]]);
$q=$lib->save($other,null,'Big','House',$big);$n=1;
denies(function()use($lib,$other,$big,$q,&$n){for($i=0;$i<40;$i++){$lib->save($other,$q['id'],'Big','House',$big,$n);$n++;}},DomainException::class,'64 MB storage quota enforced');
echo "\n$checks checks passed\n";
