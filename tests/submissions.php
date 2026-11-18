<?php
// Owner changes to submissions: update and delete only before review starts.
declare(strict_types=1);
require dirname(__DIR__).'/app/Auth.php';require dirname(__DIR__).'/app/Admin.php';require dirname(__DIR__).'/app/Projects.php';require dirname(__DIR__).'/app/migrate.php';
$checks=0;function check(bool $v,string $label):void{global $checks;if(!$v)throw new RuntimeException('FAIL: '.$label);$checks++;echo "PASS: $label\n";}
function denies(Closure $f,string $class,string $label):void{try{$f();}catch(Throwable $e){if($e instanceof $class){check(true,$label);return;}throw $e;}throw new RuntimeException('FAIL: '.$label);}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec(file_get_contents(dirname(__DIR__).'/app/schema.sql'));migrateAccounts($db);
function seed(string $name,string $role):int{global $db;$db->prepare('INSERT INTO users(public_id,name,email,password_hash,role,verified_at,created_at) VALUES(?,?,?,?,?,?,?)')->execute([$name,$name,$name.'@example.com','x',$role,time(),time()]);return (int)$db->lastInsertId();}
$admin=seed('Admin','admin');$sub=seed('Reviewer','sub_admin');$owner=seed('Owner','user');$other=seed('Other','user');
$s=new AdminService($db);$ps=new ProjectSubmission($db,sys_get_temp_dir().'/archi-missing-blueprints');
$snap=fn(float $w)=>json_encode(['state'=>['rooms'=>[['x'=>0,'z'=>0,'w'=>$w,'d'=>5,'name'=>'Living']],'site'=>['width'=>20,'depth'=>20],'blueprints'=>[]]]);
$version=fn(int $id)=>(int)$db->query('SELECT version FROM projects WHERE id='.$id)->fetchColumn();
$p=$ps->submit($owner,'First','House','Check it',$snap(4));
check(ProjectSubmission::editableStatus($db,$s->project($owner,$p)),'new submission is editable');
$ps->update($owner,$p,1,'First v2','Commercial','Check again',$snap(6));
$row=$s->project($owner,$p);$state=json_decode($row['state_json'],true)['state'];
check($row['name']==='First v2'&&$row['kind']==='Commercial'&&$row['owner_request']==='Check again'&&$state['rooms'][0]['w']==6&&(int)$row['version']===2,'owner updates snapshot, name, type and request; version increases');
denies(fn()=>$ps->update($owner,$p,1,'Old','House','',$snap(5)),StaleChange::class,'stale update rejected');
denies(fn()=>$ps->update($other,$p,2,'Mine','House','',$snap(5)),AccessDenied::class,'another user cannot update it');
denies(fn()=>$ps->withdraw($other,$p,2),AccessDenied::class,'another user cannot delete it');
denies(fn()=>$ps->withdraw($admin,$p,2),AccessDenied::class,'staff cannot delete an owner submission through the owner action');
denies(fn()=>$ps->update($owner,$p,2,'','House','',$snap(5)),InvalidArgumentException::class,'update validates the name');
check($version($p)===2,'failed updates change nothing');
// Assigned: locked.
$s->assign($admin,$p,$sub,2);
check(!ProjectSubmission::editableStatus($db,$s->project($owner,$p)),'assigned submission is not editable');
denies(fn()=>$ps->update($owner,$p,3,'Late','House','',$snap(5)),DomainException::class,'update after assignment rejected');
denies(fn()=>$ps->withdraw($owner,$p,3),DomainException::class,'delete after assignment rejected');
// Unassigned again but with a feedback draft: still locked.
$s->saveFeedback($admin,$p,3,'Entry','information','Admin draft note here.',false);$s->assign($admin,$p,null,$version($p));
check(!ProjectSubmission::editableStatus($db,$s->project($owner,$p)),'submission with feedback (even a draft) is not editable');
denies(fn()=>$ps->withdraw($owner,$p,$version($p)),DomainException::class,'delete with feedback rejected');
// Delete before review.
$q=$ps->submit($owner,'Second','House','',$snap(4));
$ps->withdraw($owner,$q,1);
check(!$db->query('SELECT count(*) FROM projects WHERE id='.$q)->fetchColumn(),'owner deletes an unreviewed submission');
check($db->query("SELECT count(*) FROM audit_log WHERE action IN ('project_updated','project_withdrawn')")->fetchColumn()==2,'update and delete are audited');
echo "$checks submission checks passed\n";
