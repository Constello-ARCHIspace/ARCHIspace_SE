<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
$service=new AdminService($db);
function textInput(array $source,string $key,string $default=''): string {
    $value=$source[$key]??$default;if(!is_string($value))throw new InvalidArgumentException('Invalid input.');return $value;
}
function integerInput(array $source,string $key,int $default=0): int {
    $v=$source[$key]??(string)$default;
    if(!is_string($v)&&!is_int($v))throw new InvalidArgumentException('Invalid number.');
    if(!preg_match('/^[0-9]{1,10}$/D',(string)$v))throw new InvalidArgumentException('Invalid number.');return (int)$v;
}
function icon(string $name,string $prefix='admin'): string {return '<img class="icon" alt="" src="assets/figma/'.esc($prefix.'-'.$name).'.svg">';}
function csrfField(): void {echo '<input type="hidden" name="csrf" value="'.esc($_SESSION['csrf']).'">';}
function hidden(string $name,int|string $value): void {echo '<input type="hidden" name="'.esc($name).'" value="'.esc((string)$value).'">';}
function badge(string $value): string {return '<span class="badge '.($value==='unassigned'?'amber':'').'">'.esc(str_replace('_',' ',$value)).'</span>';}
function dateLabel(int|string|null $date): string {return $date?gmdate('M j, Y',(int)$date):'—';}
function portalHeader(string $title,array $user): void {
    $role=$user['role'];$prefix=$role==='sub_admin'?'subadmin':'admin';$home=accountHome($user);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc($title).' | Archispace</title><link rel="stylesheet" href="assets/fonts.css"><link rel="stylesheet" href="assets/theme.css"><link rel="stylesheet" href="assets/portal.css"><script src="assets/portal.js" defer></script></head><body><header class="portal-nav"><a class="brand" href="'.esc($home).'"><span class="brand-mark">'.icon('imgArchispaceMark',$prefix).'</span>archispace</a><nav aria-label="Workspace">';
    if($role==='admin')echo '<a href="admin.php">Overview</a><a href="admin.php#projects">All projects</a><a href="admin.php#users">Users &amp; roles</a><a href="admin.php#assignment">Reviewers</a><a href="admin.php#activity">Audit log</a>';
    elseif($role==='sub_admin')echo '<a href="subadmin.php">Assigned projects</a><a href="subadmin.php?published=1">My reviews</a><a href="#review-guide">Review guide</a>';
    else echo '<a href="studio.php">Design studio</a><a href="projects.php">My submissions</a><a href="dashboard.php?tab=reports">Reports and feedback</a>';
    echo '</nav><div class="nav-account">'.badge($role).'<a href="studio.php">Studio</a><form action="logout.php" method="post">';csrfField();echo '<button>'.icon('imgLogOut',$prefix).'Logout</button></form><span class="avatar" title="'.esc($user['name']).'">'.esc(mb_strtoupper(mb_substr($user['name'],0,2))).'</span></div></header><main class="portal-main">';
    if(isset($_SESSION['portal_notice'])){echo '<div class="notice" role="status">'.esc($_SESSION['portal_notice']).'</div>';unset($_SESSION['portal_notice']);}
}
function portalFooter(): void {echo '</main><footer class="portal-footer"><span>ARCHISPACE / CONCEPT PLANNING</span><span>Human feedback does not replace professional assessment or certification.</span><span>ARCHISPACE / V.02</span></footer></body></html>';}
function emptyState(string $heading,string $text): void {echo '<div class="empty-state"><h3>'.esc($heading).'</h3><p>'.esc($text).'</p></div>';}
function feedbackComposer(array $project,array $user,array $feedback): void {
    $draft=null;foreach($feedback as $f)if($f['status']==='draft'&&(int)$f['author_id']===(int)$user['id']){$draft=$f;break;}
    echo '<section class="panel" id="composer"><h2>Architectural feedback composer</h2><p class="review-by">'.badge($user['role'].' · human review').' <span>'.esc($project['name']).' / Submission '.(int)$project['id'].'</span></p><form method="post" action="admin-action.php" class="stack">';csrfField();hidden('action','feedback');hidden('project_id',(int)$project['id']);hidden('version',(int)$project['version']);
    echo '<div class="two-fields"><label>Location<input name="location" maxlength="160" placeholder="e.g. Living / dining · garden entry" value="'.esc($draft['location']??'').'" required></label><label>Priority<select name="priority">';
    foreach(['information'=>'Information','needs_attention'=>'Needs attention','urgent'=>'Urgent'] as $v=>$label)echo '<option value="'.$v.'" '.(($draft['priority']??'needs_attention')===$v?'selected':'').'>'.$label.'</option>';
    echo '</select></label></div><label>Observation &amp; recommended action<textarea name="body" rows="5" minlength="10" maxlength="5000" placeholder="Describe the observation and a specific action the owner can take." required>'.esc($draft['body']??'').'</textarea></label><p class="hint">Identify what needs site-specific validation. Feedback is delivered to the owner’s My submissions page.</p><label>After publishing<select name="next_status"><option value="awaiting_owner">Awaiting owner</option><option value="completed">Review completed</option></select></label><div class="form-actions"><button name="intent" value="draft">Save draft</button><button class="primary" name="intent" value="publish">'.icon('imgSend',$user['role']==='sub_admin'?'subadmin':'admin').'Send feedback to owner</button></div></form></section>';
}
function feedbackHistory(array $rows,bool $publishedOnly=false): void {
    echo '<section class="panel" id="feedback"><h2>Feedback history</h2>';
    $visible=array_filter($rows,fn($f)=>!$publishedOnly||$f['status']==='published');
    if(!$visible)emptyState('No feedback yet','Published feedback will appear here.');
    foreach($visible as $f)echo '<article class="feedback-item"><div class="section-heading"><strong>'.esc($f['location']).'</strong>'.badge($f['status']).'</div><p class="hint">'.esc($f['author_name']).' · '.esc(str_replace('_',' ',$f['priority'])).' · '.dateLabel($f['updated_at']).'</p><p class="preserve">'.esc($f['body']).'</p></article>';
    echo '</section>';
}
function planPreview(array $project): void {
    $data=json_decode($project['state_json'],true);$state=$data['state'];
    $rooms=array_map(fn($r)=>['name'=>$r['name']??'Room','x'=>(float)$r['x'],'z'=>(float)$r['z'],'w'=>(float)$r['w'],'d'=>(float)$r['d']],$state['rooms']);
    $area=array_sum(array_map(fn($r)=>$r['w']*$r['d'],$rooms));
    echo '<section class="panel plan-panel"><div class="section-heading"><div><h2>'.esc($project['name']).'</h2><p class="hint">'.esc($project['kind']).' · Submission '.(int)$project['id'].' · '.dateLabel($project['created_at']).'</p></div><a class="button" href="project-download.php?id='.(int)$project['id'].'">'.icon('imgMaximize','subadmin').'Download plan</a></div><div class="plan-body"><canvas class="review-plan" width="700" height="360" data-rooms="'.esc(json_encode($rooms,JSON_THROW_ON_ERROR)).'" aria-label="Submitted room layout"></canvas><div class="plan-facts">'.badge('submitted snapshot').'<p>Room floor area</p><strong>'.number_format($area,1).' m²</strong><p>Units · Metric<br>Area may include overlapping rooms.<br>Dimensions are owner-provided.</p></div></div><div class="plan-request">'.icon('imgMessageSquare1','subadmin').'<p>'.esc($project['owner_name']).': '.esc($project['owner_request']?:'No specific review request.').'</p></div>';
    $bps=$state['blueprints']??[];
    if($bps){echo '<div class="attachments"><h3>Submitted blueprint images</h3>';foreach($bps as $i=>$bp)echo '<a class="button" href="project-asset.php?id='.(int)$project['id'].'&amp;asset='.(int)$i.'" target="_blank" rel="noopener">'.esc(is_string($bp['name']??null)?$bp['name']:'Blueprint '.($i+1)).'</a>';echo '</div>';}
    echo '</section>';
}
