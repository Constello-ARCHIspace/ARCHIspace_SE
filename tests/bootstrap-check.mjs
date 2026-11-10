import fs from 'node:fs';import path from 'node:path';import os from 'node:os';import {spawnSync} from 'node:child_process';import {fileURLToPath} from 'node:url';
const source=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const dir=fs.mkdtempSync(path.join(os.tmpdir(),'archi-bootstrap-'));
const php=process.env.PHP_EXECUTABLE||'php';let n=0;
function run(file,args=[]){const r=spawnSync(php,[path.join(dir,file),...args],{encoding:'utf8'});if(r.error)throw r.error;return (r.stdout||'')+(r.stderr||'')}
function check(v,label){if(!v)throw Error('FAIL: '+label);n++;console.log('PASS: '+label)}
try{
for(const folder of ['bin','app'])fs.cpSync(path.join(source,folder),path.join(dir,folder),{recursive:true});fs.copyFileSync(path.join(source,'config.example.php'),path.join(dir,'config.php'));
check(run('bin/init.php').includes('initialized'),'CLI initializes private database');
fs.writeFileSync(path.join(dir,'seed.php'),`<?php $c=require __DIR__.'/config.php';$d=new PDO('sqlite:'.$c['database']);$d->prepare('INSERT INTO users(public_id,name,email,password_hash,verified_at,created_at) VALUES(?,?,?,?,?,?)')->execute(['cli-fixture','CLI Test','cli@example.com',password_hash('A long test passphrase!',PASSWORD_DEFAULT),NULL,time()]);`);
run('seed.php');
check(run('bin/promote-admin.php',['cli@example.com']).includes('Register and verify'),'CLI refuses unverified account');
fs.writeFileSync(path.join(dir,'verify-fixture.php'),`<?php $c=require __DIR__.'/config.php';$d=new PDO('sqlite:'.$c['database']);$d->exec('UPDATE users SET verified_at=1');`);run('verify-fixture.php');
check(run('bin/promote-admin.php',['cli@example.com']).includes('First Admin promoted'),'CLI promotes first verified Admin');
check(run('bin/promote-admin.php',['cli@example.com']).includes('An Admin already exists'),'CLI refuses subsequent bootstrap');
fs.writeFileSync(path.join(dir,'inspect-fixture.php'),`<?php $c=require __DIR__.'/config.php';$d=new PDO('sqlite:'.$c['database']);echo $d->query('SELECT role || session_version FROM users')->fetchColumn();echo ':';echo $d->query("SELECT count(*) FROM audit_log WHERE action='bootstrap_admin'")->fetchColumn();`);
check(run('inspect-fixture.php').trim()==='admin1:1','bootstrap rotates session version and records one audit event');
console.log(n+' bootstrap checks passed');
}finally{fs.rmSync(dir,{recursive:true,force:true})}
