import {PHP,PHPRequestHandler} from '@php-wasm/universal';
import {loadNodeRuntime,createNodeFsMountHandler} from '@php-wasm/node';
import fs from 'node:fs';import path from 'node:path';import os from 'node:os';import {fileURLToPath} from 'node:url';
const source=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const temp=fs.mkdtempSync(path.join(os.tmpdir(),'archispace-admin-'));
for(const item of ['app','public'])fs.cpSync(path.join(source,item),path.join(temp,item),{recursive:true});
fs.copyFileSync(path.join(source,'config.example.php'),path.join(temp,'config.php'));fs.mkdirSync(path.join(temp,'storage'));
const php=new PHP(await loadNodeRuntime('8.5',{emscriptenOptions:{processId:124}}));php.mkdir('/app');await php.mount('/app',createNodeFsMountHandler(temp));
const run=async code=>{const r=await php.run({code:'<?php '+code});if(r.errors||r.exitCode)throw Error(r.errors||r.text);return r.text};
const db="$c=require '/app/config.php';$d=new PDO('sqlite:'.$c['database']);$d->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);";
await run(db+`require '/app/app/migrate.php';migrateAccounts($d);$q=$d->prepare('INSERT INTO users(public_id,name,email,password_hash,role,verified_at,created_at) VALUES(?,?,?,?,?,?,?)');foreach(['admin','sub_admin','user','user'] as $i=>$role)$q->execute(['http-user-'.($i+1),'Test user '.($i+1),'user'.($i+1).'@example.com',password_hash('A long testing password!',PASSWORD_DEFAULT),$role,time(),time()]);`);
let n=0;const check=(v,label)=>{if(!v)throw Error('FAIL: '+label);n++;console.log('PASS: '+label)};
// Separate cookie stores for each browser/account; request handling is sequential.
const clients=Array.from({length:5},()=>new PHPRequestHandler({php,documentRoot:'/app/public',absoluteUrl:'http://localhost:8000'}));
const csrf=r=>{const m=r.text.match(/name="csrf" value="([a-f0-9]+)"/);if(!m)throw Error('Missing CSRF: '+r.text.slice(0,300));return m[1]};
let r=await clients[4].request({url:'/admin.php'});check(r.httpStatusCode===302,'anonymous admin access redirects to login');
for(let i=0;i<4;i++){r=await clients[i].request({url:'/auth.php'});r=await clients[i].request({url:'/auth.php',method:'POST',body:{csrf:csrf(r),email:`user${i+1}@example.com`,password:'A long testing password!'}});check(r.httpStatusCode===303&&r.headers.location[0]===['admin.php','subadmin.php','index.php','index.php'][i],'login routes role '+(i+1));}
r=await clients[2].request({url:'/admin.php'});check(r.httpStatusCode===403,'user cannot open admin dashboard');
r=await clients[1].request({url:'/admin.php'});check(r.httpStatusCode===403,'sub-admin cannot open admin dashboard');
r=await clients[0].request({url:'/admin.php'});check(r.httpStatusCode===200&&r.text.includes('No projects in this queue'),'admin empty state renders real counts');let adminCsrf=csrf(r);
r=await clients[1].request({url:'/subadmin.php'});let subCsrf=csrf(r);check(r.httpStatusCode===200&&r.text.includes('No assigned projects'),'sub-admin empty state renders');
r=await clients[1].request({url:'/admin-action.php',method:'POST',body:{csrf:subCsrf,action:'user_access',user_id:'2',version:'0',role:'admin',status:'active',password:'A long testing password!'}});check(r.httpStatusCode===403,'forged sub-admin role promotion rejected');
r=await clients[0].request({url:'/admin-action.php'});check(r.httpStatusCode===405,'staff mutations reject GET');
r=await clients[0].request({url:'/admin-action.php',method:'POST',body:{action:'assign'}});check(r.httpStatusCode===403,'staff mutations reject missing CSRF');
r=await clients[2].request({url:'/projects.php'});const ownerCsrf=csrf(r);
// Fake PNG belongs only to owner 3; image content is a fixture, never emailed.
const asset='a'.repeat(48);fs.mkdirSync(path.join(temp,'storage/blueprints/http-user-3'),{recursive:true});fs.writeFileSync(path.join(temp,'storage/blueprints/http-user-3',asset+'.png'),Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4T8AAAAASUVORK5CYII=','base64'));
const snapshot=JSON.stringify({state:{rooms:[{name:'Living room',x:0,z:0,w:6,d:5},{name:'Kitchen',x:4.5,z:0,w:3,d:5}],site:{width:20,depth:20},blueprints:[{name:'Owner blueprint',data:'blueprint.php?id='+asset}]}});
r=await clients[2].request({url:'/submit-project.php',method:'POST',body:{csrf:ownerCsrf,name:'Courtyard residence',kind:'House',request:'Please review entry circulation.',snapshot,owner_id:'4',assigned_to:'2',role:'admin'}});check(r.httpStatusCode===303,'owner submits snapshot');
const id=Number(r.headers.location[0].match(/id=(\d+)/)[1]);
check(await run(db+`echo $d->query('SELECT owner_id FROM projects WHERE id=${id}')->fetchColumn();`)==='3','forged owner and role fields ignored');
for(const endpoint of ['project.php','project-download.php','project-asset.php']){r=await clients[3].request({url:`/${endpoint}?id=${id}&asset=0`});check(r.httpStatusCode===403,endpoint+' denies cross-owner access');r=await clients[1].request({url:`/${endpoint}?id=${id}&asset=0`});check(r.httpStatusCode===403,endpoint+' denies unassigned reviewer');}
r=await clients[0].request({url:'/admin-action.php',method:'POST',body:{csrf:adminCsrf,action:'assign',project_id:String(id),version:'1',reviewer_id:'2',password:'wrong password'}});check(r.httpStatusCode===422,'assignment requires password reauthentication');
r=await clients[0].request({url:'/admin-action.php',method:'POST',body:{csrf:adminCsrf,action:'assign',project_id:String(id),version:'1',reviewer_id:'2',password:'A long testing password!'}});check(r.httpStatusCode===303,'admin assigns project');
r=await clients[1].request({url:'/subadmin.php?project='+id});check(r.httpStatusCode===200&&r.text.includes('Courtyard residence'),'assigned project appears in reviewer dashboard');
if(process.env.ARCHI_RENDER_DIR){fs.mkdirSync(process.env.ARCHI_RENDER_DIR,{recursive:true});fs.writeFileSync(path.join(process.env.ARCHI_RENDER_DIR,'subadmin.html'),r.text);const a=await clients[0].request({url:'/admin.php?project='+id});fs.writeFileSync(path.join(process.env.ARCHI_RENDER_DIR,'admin.html'),a.text);fs.cpSync(path.join(temp,'public/assets'),path.join(process.env.ARCHI_RENDER_DIR,'assets'),{recursive:true});}
r=await clients[1].request({url:`/project-asset.php?id=${id}&asset=0`});check(r.httpStatusCode===200&&r.headers['content-type'][0]==='image/png','assigned reviewer can view submitted blueprint');
r=await clients[1].request({url:`/blueprint.php?id=${asset}`});check(r.httpStatusCode===404,'reviewer cannot bypass submission image scope');
r=await clients[1].request({url:`/project-download.php?id=${id}`});check(r.httpStatusCode===200&&r.headers['content-disposition'][0].startsWith('attachment'),'authorized plan download uses attachment');
const body={csrf:subCsrf,action:'feedback',project_id:String(id),version:'2',location:'Garden entry',priority:'needs_attention',body:'<script>alert(1)</script> Please widen the circulation path.',intent:'draft'};
r=await clients[1].request({url:'/admin-action.php',method:'POST',body});check(r.httpStatusCode===303,'reviewer saves private draft');
r=await clients[2].request({url:`/project.php?id=${id}`});check(!r.text.includes('Please widen'),'owner cannot see draft');
r=await clients[1].request({url:'/admin-action.php',method:'POST',body:{...body,version:'3',intent:'publish'}});check(r.httpStatusCode===303,'reviewer publishes feedback');
r=await clients[2].request({url:`/project.php?id=${id}`});check(r.text.includes('&lt;script&gt;alert(1)&lt;/script&gt;')&&!r.text.includes('<script>alert'),'published feedback is escaped');
r=await clients[1].request({url:'/admin-action.php',method:'POST',body:{...body,version:'3',intent:'publish'}});check(r.httpStatusCode===409,'duplicate publish rejected by version check');
r=await clients[0].request({url:'/admin-action.php',method:'POST',body:{csrf:adminCsrf,action:'user_access',user_id:'2',version:'0',role:'sub_admin',status:'suspended',password:'A long testing password!'}});check(r.httpStatusCode===303,'admin suspends reviewer');
r=await clients[1].request({url:'/subadmin.php'});check(r.httpStatusCode===302,'suspension revokes existing session immediately');
r=await clients[0].request({url:'/admin.php?project='+id});check(r.text.includes('Unassigned'),'suspended reviewer assignment removed');
// Invite token stays out of the address bar and is bound to the recipient email.
const token='b'.repeat(64);await run(db+`$d->prepare('INSERT INTO invitations(email,token_hash,created_by,expires_at,created_at) VALUES(?,?,?,?,?)')->execute(['user3@example.com',hash('sha256','${token}'),1,time()+1000,time()]);`);
r=await clients[2].request({url:'/invite.php?token='+token});check(r.httpStatusCode===303&&r.headers.location[0]==='invite.php','invitation secret removed from URL without redemption');
r=await clients[2].request({url:'/invite.php'});check(r.httpStatusCode===200&&r.text.includes('Accept invitation'),'invitation requires explicit confirmation');
r=await clients[2].request({url:'/invite.php',method:'POST',body:{csrf:csrf(r)}});check(r.httpStatusCode===303,'verified owner accepts invitation with CSRF');
// Public registration ignores privilege fields even when SMTP is unavailable in this test environment.
r=await clients[4].request({url:'/auth.php?view=register'});r=await clients[4].request({url:'/auth.php?view=register',method:'POST',body:{csrf:csrf(r),name:'Public applicant',email:'applicant@example.com',password:'A long testing password!',confirmation:'A long testing password!',role:'admin',verified_at:'1',status:'active'}});
check(await run(db+"echo $d->query(\"SELECT role || ':' || (verified_at IS NULL) FROM users WHERE email='applicant@example.com'\")->fetchColumn();")==='user:1','public registration cannot grant staff or verified status');
console.log(n+' admin HTTP checks passed');php.exit();fs.rmSync(temp,{recursive:true,force:true});
