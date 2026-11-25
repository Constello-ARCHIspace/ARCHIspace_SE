<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Auth.php';require dirname(__DIR__).'/app/Admin.php';require dirname(__DIR__).'/app/Projects.php';require dirname(__DIR__).'/app/migrate.php';
$checks=0;function check(bool $v,string $label):void{global $checks;if(!$v)throw new RuntimeException('FAIL: '.$label);$checks++;echo "PASS: $label\n";}
function denies(Closure $f,string $class,string $label):void{try{$f();}catch(Throwable $e){if($e instanceof $class){check(true,$label);return;}throw $e;}throw new RuntimeException('FAIL: '.$label);}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
// Upgrade a populated pre-role schema, then repeat the migration.
$db->exec('CREATE TABLE users (
 id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT NOT NULL UNIQUE, name TEXT NOT NULL,
 email TEXT NOT NULL UNIQUE COLLATE NOCASE, password_hash TEXT NOT NULL, verified_at INTEGER, created_at INTEGER NOT NULL
)');
$db->prepare('INSERT INTO users(public_id,name,email,password_hash,verified_at,created_at) VALUES(?,?,?,?,?,?)')->execute(['legacy','Legacy user','legacy@example.com',password_hash('A long testing password!',PASSWORD_DEFAULT),time(),time()]);
$db->exec("INSERT INTO users(public_id,name,email,password_hash,created_at) VALUES
 ('multi','First Middle One Last','multi@example.com','x',0), ('single','Solo','solo@example.com','x',0)");
migrateAccounts($db);migrateAccounts($db);
check($db->query("SELECT phone,birthdate FROM users WHERE public_id='legacy'")->fetch(PDO::FETCH_NUM) === [null, null], 'legacy accounts keep unknown phone and birthdate after upgrade');
check($db->query("SELECT role FROM users WHERE public_id='legacy'")->fetchColumn()==='user','migration preserves legacy user with safe default role');
check($db->query("SELECT first_name,middle_name,last_name,name FROM users WHERE public_id='multi'")->fetch(PDO::FETCH_NUM) === ['First','Middle One','Last','First Middle One Last'],'migration splits multi-word names and preserves the display name');
check($db->query("SELECT first_name,middle_name,last_name,name FROM users WHERE public_id='single'")->fetch(PDO::FETCH_NUM) === ['Solo',null,'','Solo'],'migration preserves a legacy single-word name');
denies(fn()=>$db->exec("INSERT INTO users(public_id,first_name,email,password_hash,created_at) VALUES('missing','One','missing@example.com','x',0)"),PDOException::class,'upgraded database requires a surname on new accounts');
denies(fn()=>$db->exec("UPDATE users SET first_name=' ' WHERE public_id='legacy'"),PDOException::class,'upgraded database rejects blank first names');
function seed(string $name,string $role,bool $verified=true):int{global $db;$db->prepare('INSERT INTO users(public_id,first_name,last_name,email,password_hash,role,verified_at,created_at) VALUES(?,?,?,?,?,?,?,?)')->execute([$name,$name,'Tester',$name.'@example.com',password_hash('A long testing password!',PASSWORD_DEFAULT),$role,$verified?time():null,time()]);return (int)$db->lastInsertId();}
$admin=seed('Admin','admin');$sub=seed('Reviewer','sub_admin');$sub2=seed('OtherReviewer','sub_admin');$owner=seed('Owner','user');$other=seed('OtherOwner','user');$pending=seed('Pending','user',false);
$s=new AdminService($db);$ps=new ProjectSubmission($db,sys_get_temp_dir().'/archi-missing-blueprints');
$snapshot=json_encode(['state'=>['rooms'=>[['x'=>0,'z'=>0,'w'=>4,'d'=>5,'name'=>'Living']],'site'=>['width'=>20,'depth'=>20],'blueprints'=>[]]]);
$p=$ps->submit($owner,'A home','House','Please check layout',$snapshot);$p2=$ps->submit($other,'Other home','House','',$snapshot);
check($s->project($owner,$p)['owner_id']===$owner,'server binds project to authenticated owner');
denies(fn()=>$s->project($other,$p),AccessDenied::class,'cross-owner project denied');
denies(fn()=>$s->project($sub,$p,true),AccessDenied::class,'unassigned sub-admin denied');
denies(fn()=>$s->users($sub),AccessDenied::class,'sub-admin cannot list accounts');
denies(fn()=>$s->assign($sub,$p,$sub,1),AccessDenied::class,'sub-admin cannot assign projects');
denies(fn()=>$s->changeUser($owner,$owner,'admin','active',0),AccessDenied::class,'regular user cannot self-promote');
denies(fn()=>$s->changeUser($admin,$pending,'sub_admin','active',0),DomainException::class,'unverified account cannot become staff');
denies(fn()=>$s->changeUser($admin,$admin,'user','active',0),DomainException::class,'admin cannot remove own access');
$s->assign($admin,$p,$sub,1);check($s->projects($sub)['total']===1 && $s->stats($sub)['total']==1,'sub-admin list and metrics are assignment scoped');
check($s->projects($sub,'Other')['total']===0,'search cannot reveal other projects');
denies(fn()=>$s->assign($admin,$p,$sub2,1),StaleChange::class,'stale assignment rejected');
$s->saveFeedback($sub,$p,2,'Entry','needs_attention','Private draft observation.',false);
check(count($s->feedback($owner,$p))===0 && count($s->feedback($admin,$p))===0 && count($s->feedback($sub,$p))===1,'draft visible only to its author');
$s->saveFeedback($sub,$p,3,'Entry','needs_attention','Add a second exit for review.',true);
check(count($s->feedback($owner,$p))===1 && $s->project($owner,$p)['status']==='awaiting_owner','published feedback delivered to owner');
denies(fn()=>$s->saveFeedback($sub,$p,3,'Entry','urgent','Add another exit please.',true),StaleChange::class,'replayed publish cannot duplicate feedback');
$s->assign($admin,$p,$sub2,4);denies(fn()=>$s->project($sub,$p),AccessDenied::class,'reassignment removes previous reviewer access');
$s->changeUser($admin,$sub2,'sub_admin','suspended',0);check($s->project($admin,$p)['assigned_to']===null,'suspension unassigns reviews');
$auth=new Auth($db,fn()=>null);denies(fn()=>$auth->login('otherreviewer@example.com','A long testing password!'),DomainException::class,'suspended account cannot sign in');
check((int)$db->query('SELECT session_version FROM users WHERE id='.$sub2)->fetchColumn()===1,'access changes invalidate prior sessions');
denies(fn()=>$s->changeUser($admin,$sub2,'user','active',0),StaleChange::class,'stale account role update rejected');
$sent=[];$s->invite($admin,'Owner@example.com',function($email,$token)use(&$sent){$sent=[$email,$token];});
check($db->query('SELECT token_hash FROM invitations')->fetchColumn()!==$sent[1],'invitation stored as hash');
check(!$s->acceptInvite($other,$sent[1]),'invitation bound to verified email');check($s->acceptInvite($owner,$sent[1]),'matching verified user can accept invitation');check(!$s->acceptInvite($owner,$sent[1]),'invitation single use');
check($s->user($owner)['role']==='user','invitation never grants staff access');
$s->invite($admin,'Owner@example.com',function($email,$token)use(&$sent){$sent=[$email,$token];});$db->exec('UPDATE invitations SET expires_at=0');check(!$s->acceptInvite($owner,$sent[1]),'expired invitation rejected');
denies(fn()=>$s->invite($sub,'x@example.com',fn()=>null),AccessDenied::class,'sub-admin cannot invite users');
$before=(int)$db->query('SELECT count(*) FROM invitations')->fetchColumn();denies(fn()=>$s->invite($admin,'mail@example.com',function(){throw new RuntimeException('test SMTP failure');}),DomainException::class,'SMTP failure is reported safely');check((int)$db->query('SELECT count(*) FROM invitations')->fetchColumn()===$before,'failed invitation token removed');
$bad=json_decode($snapshot,true);$bad['state']['blueprints']=[['data'=>'https://example.com/a.png']];denies(fn()=>$ps->submit($owner,'Bad','House','',json_encode($bad)),InvalidArgumentException::class,'remote blueprint references rejected');
$bad['state']['blueprints']=[['data'=>'blueprint.php?id='.str_repeat('a',48)]];denies(fn()=>$ps->submit($owner,'Bad','House','',json_encode($bad)),InvalidArgumentException::class,'unowned or missing blueprint rejected');
$bad=json_decode($snapshot,true);$bad['state']['rooms'][0]['w']=-1;denies(fn()=>$ps->submit($owner,'Bad','House','',json_encode($bad)),InvalidArgumentException::class,'invalid room dimensions rejected');
denies(fn()=>$ps->submit($owner,'Bad','House','',str_repeat('a',4*1024*1024+1)),InvalidArgumentException::class,'oversize snapshot rejected');
check(!str_contains(json_encode($s->auditRows($admin)),'A long testing password!')&&!str_contains(json_encode($s->auditRows($admin)),'Private draft observation.'),'audit excludes passwords and feedback content');
echo "$checks admin checks passed\n";
