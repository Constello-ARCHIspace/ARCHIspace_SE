<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/portal.php';
$user=requireStaff(true);
try{
    $search=textInput($_GET,'q');$status=textInput($_GET,'status');$list=$service->projects((int)$user['id'],$search,$status,integerInput($_GET,'page',1));
    $userSearch=textInput($_GET,'users');$users=$service->users((int)$user['id'],$userSearch,integerInput($_GET,'user_page',1));
    $selected=integerInput($_GET,'project',(int)($list['rows'][0]['id']??0));$project=$selected?$service->project((int)$user['id'],$selected,true):null;
    $feedback=$project?$service->feedback((int)$user['id'],$selected):[];$stats=$service->stats((int)$user['id']);$reviewers=$service->reviewers((int)$user['id']);
}catch(AccessDenied $e){http_response_code(403);exit('Project unavailable.');}catch(InvalidArgumentException $e){http_response_code(400);exit('Invalid filter.');}
// Same line icons as the uploaded admin console; inline SVG because the CSP allows only same-origin files.
function adminIcon(string $name): string {
    $paths=[
        'grid'=>'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'inbox'=>'<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.1 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.9A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.1Z"/>',
        'users'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
        'folder'=>'<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.7-.9l-.8-1.2A2 2 0 0 0 7.9 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/>',
        'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'check'=>'<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'reviewer'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 12 0v1"/><path d="m16 11 2 2 4-4"/>',
        'history'=>'<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>',
        'logout'=>'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'plus'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M16 11h6"/>',
    ];
    return '<svg class="a-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??'').'</svg>';
}
$initials=mb_strtoupper(mb_substr((string)$user['name'],0,2));
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin dashboard | Archispace</title><link rel="stylesheet" href="assets/fonts.css"><link rel="stylesheet" href="assets/theme.css"><link rel="stylesheet" href="assets/portal.css"><link rel="stylesheet" href="assets/admin.css"><script src="assets/portal.js" defer></script></head>
<body class="admin-console">
<div class="admin-app">
<aside class="admin-side" aria-label="Admin navigation">
    <a class="admin-brand" href="admin.php"><span class="brand-mark"><?= icon('imgArchispaceMark') ?></span><span><b>archispace</b><small>ADMIN CONSOLE</small></span></a>
    <nav class="admin-nav" aria-label="Workspace">
        <p class="admin-nav-label">Workspace</p>
        <a href="admin.php" aria-current="page"><?= adminIcon('grid') ?>Overview</a>
        <a href="#projects"><?= adminIcon('inbox') ?>Review queue<?php if((int)$stats['unassigned']): ?><span class="admin-count" title="Projects that need a reviewer"><?= (int)$stats['unassigned'] ?></span><?php endif; ?></a>
        <a href="#assignment"><?= adminIcon('reviewer') ?>Reviewers</a>
        <a href="studio.php"><?= adminIcon('folder') ?>Studio</a>
        <p class="admin-nav-label">Management</p>
        <a href="#users"><?= adminIcon('users') ?>Users &amp; roles</a>
        <a href="#activity"><?= adminIcon('history') ?>Audit log</a>
    </nav>
    <div class="admin-side-foot">
        <span class="avatar" title="<?= esc((string)$user['name']) ?>"><?= esc($initials) ?></span>
        <span class="admin-who"><b><?= esc((string)$user['name']) ?></b><small>ADMIN</small></span>
        <form action="logout.php" method="post"><?php csrfField(); ?><button aria-label="Log out" title="Log out"><?= adminIcon('logout') ?></button></form>
    </div>
</aside>
<div class="admin-main">
<main class="portal-main admin-content">
<?php if(isset($_SESSION['portal_notice'])){echo '<div class="notice" role="status">'.esc((string)$_SESSION['portal_notice']).'</div>';unset($_SESSION['portal_notice']);} ?>
<div class="page-heading">
    <div><p class="crumb">Administration / Overview</p><h1>Project overview</h1><p>Manage users and roles, assign reviewers, and keep architectural feedback moving.</p></div>
    <div class="admin-heading-actions">
        <a class="button primary" href="#projects"><?= adminIcon('inbox') ?>Open review queue</a>
        <button type="button" class="primary" data-open="inviteDialog"><?= adminIcon('plus') ?>Invite user</button>
    </div>
</div>
<section class="metrics" aria-label="Overview">
<div class="panel metric"><div class="metric-top"><p>All projects</p><span class="metric-icon"><?= adminIcon('folder') ?></span></div><strong><?= (int)$stats['total'] ?></strong><small>Submitted project snapshots</small></div>
<div class="panel metric"><div class="metric-top"><p>Active users</p><span class="metric-icon"><?= adminIcon('users') ?></span></div><strong><?= (int)$stats['active_users'] ?></strong><small>Verified accounts with access</small></div>
<div class="panel metric"><div class="metric-top"><p>Needs a reviewer</p><span class="metric-icon"><?= adminIcon('clock') ?></span></div><strong><?= (int)$stats['unassigned'] ?></strong><?php $newUnassigned=(int)$stats['unassigned_new'];$oldUnassigned=(int)$stats['unassigned']-$newUnassigned; ?><small><?= (int)$stats['unassigned'] ? '<b class="metric-new">'.$newUnassigned.' new</b> this week · <b>'.$oldUnassigned.' older</b> than 7 days' : 'Nothing waiting' ?></small></div>
<div class="panel metric"><div class="metric-top"><p>Assigned reviews</p><span class="metric-icon"><?= adminIcon('check') ?></span></div><strong><?= (int)$stats['assigned'] ?></strong><small>Assigned to Admin or Sub-admin reviewers</small></div>
</section>
<section class="panel queue-panel" id="projects"><div class="section-heading queue-head"><div><h2>All-project review queue</h2><p class="hint"><?= $search!=='' ? 'Results for “'.esc($search).'”' : 'Newest submissions first' ?></p></div><form method="get" class="queue-filters"><?php if($search!==''){hidden('q',$search);} ?><label class="status-filter"><span class="sr-only">Status</span><select name="status"><option value="">All statuses</option><?php foreach(['unassigned','needs_feedback','in_review','awaiting_owner','completed'] as $value): ?><option value="<?= $value ?>" <?= $status===$value?'selected':'' ?>><?= esc(ucwords(str_replace('_',' ',$value))) ?></option><?php endforeach; ?></select></label><button><?= icon('imgListFilter') ?>Apply</button><?php if($search!==''||$status!==''): ?><a class="button" href="admin.php#projects">Clear</a><?php endif; ?></form></div>
<?php if(!$list['rows']): emptyState('No projects in this queue','Owners can submit a saved design from the studio or upload an exported project on My submissions.'); else: ?>
<div class="table-scroll"><table><thead><tr><th>Project / owner</th><th>Review status</th><th>Assigned reviewer</th><th>Received</th><th>Action</th></tr></thead><tbody>
<?php foreach($list['rows'] as $p): ?><tr class="<?= $selected===(int)$p['id']?'selected':'' ?>"><td><strong><?= esc($p['name']) ?></strong><small><?= esc($p['owner_name'].' · '.$p['kind']) ?></small></td><td><?= badge($p['status']) ?></td><td><?= esc($p['reviewer_name']??'Unassigned') ?></td><td><?= dateLabel($p['created_at']) ?></td><td><a class="open-review" href="admin.php?<?= esc(http_build_query(['project'=>$p['id'],'q'=>$search,'status'=>$status,'page'=>$list['page']])) ?>#review">Open review <?= icon('imgExternalLink') ?></a></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<div class="pagination"><span><?= $list['total'] ?> projects · Page <?= $list['page'] ?></span><?php if($list['page']>1): ?><a href="?<?= esc(http_build_query(['q'=>$search,'status'=>$status,'page'=>$list['page']-1])) ?>#projects">Previous</a><?php endif; ?><?php if($list['total']>$list['page']*20): ?><a href="?<?= esc(http_build_query(['q'=>$search,'status'=>$status,'page'=>$list['page']+1])) ?>#projects">Next</a><?php endif; ?></div></section>
<div class="management-grid"><section class="panel" id="users"><h2>Users &amp; role management</h2><p class="hint">Only an Admin can change account roles. Access changes revoke existing sessions.</p><form method="get" class="search-row"><label>Find an account<input name="users" maxlength="100" value="<?= esc($userSearch) ?>" placeholder="Search name or email"></label><button>Search users</button></form>
<?php foreach($users['rows'] as $u): ?><div class="user-row"><div><strong><?= esc($u['name']) ?></strong><small><?= esc($u['email']) ?></small></div><?= badge($u['role']) ?><span class="hint"><?= !$u['verified_at']?'Unverified':esc(ucfirst($u['status'])) ?></span><details><summary>Edit</summary><form method="post" action="admin-action.php" class="access-form stack"><?php csrfField();hidden('action','user_access');hidden('user_id',(int)$u['id']);hidden('version',(int)$u['session_version']); ?><h3><?= esc($u['name']) ?></h3><label>Role<select name="role"><?php foreach(['user'=>'User','sub_admin'=>'Sub admin','admin'=>'Admin'] as $role=>$label): ?><option value="<?= $role ?>" <?= $u['role']===$role?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><label>Account status<select name="status"><option value="active" <?= $u['status']==='active'?'selected':'' ?>>Active</option><option value="suspended" <?= $u['status']==='suspended'?'selected':'' ?>>Suspended</option></select></label><label>Your current password<input type="password" name="password" autocomplete="current-password" required></label><p class="hint">Suspension or removal of staff access also unassigns their reviews. Your own Admin access is protected.</p><button class="primary">Save access changes</button></form></details></div><?php endforeach; ?>
<?php if(!$users['rows'])emptyState('No matching accounts','Try another name or email.'); ?>
<div class="pagination"><span><?= $users['total'] ?> accounts · Page <?= $users['page'] ?></span><?php if($users['page']>1): ?><a href="?<?= esc(http_build_query(['users'=>$userSearch,'user_page'=>$users['page']-1])) ?>#users">Previous</a><?php endif; ?><?php if($users['total']>$users['page']*20): ?><a href="?<?= esc(http_build_query(['users'=>$userSearch,'user_page'=>$users['page']+1])) ?>#users">Next</a><?php endif; ?></div><p class="panel-note">Public registration creates User accounts only. Sub admins cannot grant or change roles.</p></section>
<section class="panel" id="assignment"><h2>Assigned reviewer management</h2><p class="hint">Assign or reassign the selected project to an active Admin or Sub admin.</p><?php if($project): ?><form method="post" action="admin-action.php" class="stack"><?php csrfField();hidden('action','assign');hidden('project_id',(int)$project['id']);hidden('version',(int)$project['version']); ?><label>Project<input value="<?= esc($project['name']) ?>" readonly></label><label>Reviewer<select name="reviewer_id"><option value="0">Unassigned</option><?php foreach($reviewers as $r): ?><option value="<?= (int)$r['id'] ?>" <?= (int)$project['assigned_to']===(int)$r['id']?'selected':'' ?>><?= esc($r['name'].' · '.str_replace('_',' ',$r['role'])) ?></option><?php endforeach; ?></select></label><label>Your current password<input type="password" name="password" autocomplete="current-password" required></label><p class="hint">Reassignment immediately removes the previous Sub-admin’s access to this project.</p><button class="primary">Assign project</button></form><?php else: emptyState('Select a project first','New submissions will appear in the review queue above.'); endif; ?></section></div>
<section id="review"><?php if($project): ?><div class="review-grid"><aside class="panel selected-project"><h2>Selected for review</h2><?= badge($project['name']) ?><p>Owner: <?= esc($project['owner_name']) ?><br>Type: <?= esc($project['kind']) ?><br>Received: <?= dateLabel($project['created_at']) ?></p><hr><h3>Owner’s request</h3><p class="preserve"><?= esc($project['owner_request']?:'No specific request.') ?></p><a class="button" href="project.php?id=<?= (int)$project['id'] ?>"><?= icon('imgExternalLink') ?>Open uploaded plans</a></aside><?php feedbackComposer($project,$user,$feedback); ?></div><?php feedbackHistory($feedback); else: ?><section class="panel"><?php emptyState('Ready for your first review','Select an owner’s submitted project to write architectural feedback.'); ?></section><?php endif; ?></section>
<section class="panel" id="activity"><h2>Security &amp; activity log</h2><p class="hint">Latest 100 events. Passwords, invitation tokens and feedback bodies are never recorded here.</p><div class="table-scroll"><table><thead><tr><th>When (UTC)</th><th>Actor</th><th>Event</th><th>Target</th></tr></thead><tbody><?php foreach($service->auditRows((int)$user['id']) as $event): ?><tr><td><?= esc(gmdate('Y-m-d H:i',(int)$event['created_at'])) ?></td><td><?= esc($event['actor_name']??'Server console') ?></td><td><?= esc(str_replace('_',' ',$event['action'])) ?></td><td><?= esc($event['target_type']).' #'.(int)$event['target_id'] ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<dialog id="inviteDialog"><div class="section-heading"><h2>Invite a user</h2><button type="button" data-close="inviteDialog" aria-label="Close invitation">×</button></div><p class="hint">Send a 48-hour invitation using your configured PHPMailer SMTP account. New users must register and verify their email. Invitations never grant staff privileges.</p><form method="post" action="admin-action.php" class="stack"><?php csrfField();hidden('action','invite'); ?><label>Email address<input type="email" name="email" maxlength="254" required></label><label>Your current password<input type="password" name="password" autocomplete="current-password" required></label><button class="primary"><?= icon('imgSend') ?>Send invitation</button></form></dialog>
</main>
<footer class="portal-footer"><span>ARCHISPACE / ADMIN CONSOLE</span><span>Human feedback does not replace professional assessment or certification.</span><span>ARCHISPACE / V.02</span></footer>
</div></div>
</body></html>
