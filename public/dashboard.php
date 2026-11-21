<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/portal.php';
$user=requireUser();
if($user['role']!=='user'){header('Location: '.accountHome($user));exit;}

function dashIcon(string $name): string {
    $paths=[
        'upload'=>'<path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>',
        'layers'=>'<path d="M12 3l9 5-9 5-9-5 9-5zM3 13l9 5 9-5"/>',
        'sparkle'=>'<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3z"/>',
        'waves'=>'<path d="M3 8c3 0 3 2 6 2s3-2 6-2 3 2 6 2M3 14c3 0 3 2 6 2s3-2 6-2 3 2 6 2"/>',
        'clipboard'=>'<path d="M9 3h6v3H9zM7 5H5v16h14V5h-2M9 12h6M9 16h6"/>',
        'message'=>'<path d="M4 5h16v11H9l-5 4V5z"/>',
        'download'=>'<path d="M12 4v11M7 11l5 5 5-5M4 20h16"/>',
        'home'=>'<path d="M4 11l8-7 8 7M6 10v10h12V10"/>',
        'arrow'=>'<path d="M8 16L16 8M9 8h7v7"/>',
    ];
    return '<svg class="dash-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??'').'</svg>';
}

function dashStatus(string $status): string {
    $labels=['unassigned'=>'Awaiting reviewer','awaiting_owner'=>'Feedback received','completed'=>'Review completed'];
    $label=$labels[$status]??ucfirst(str_replace('_',' ',$status));
    return '<span class="badge '.($status==='unassigned'?'amber':'').'">'.esc($label).'</span>';
}

// Own submissions, newest first. Studio designs live in the browser, so the server only knows submissions.
$rows=[];$total=0;
try{$list=$service->projects((int)$user['id'],'','',1,true);$rows=$list['rows'];$total=(int)$list['total'];}
catch(Throwable $e){$rows=[];$total=0;}
usort($rows,fn($a,$b)=>(int)$b['created_at']<=>(int)$a['created_at']);
$latest=$rows[0]??null;

$roomCount=0;$area=0.0;$roomsJson='[]';$reviewer='Awaiting assignment';$detailOk=false;
if($latest){
    try{
        $detail=$service->project((int)$user['id'],(int)$latest['id']);
        $data=json_decode((string)$detail['state_json'],true);
        $state=is_array($data)?($data['state']??[]):[];
        $rooms=[];
        foreach(is_array($state['rooms']??null)?$state['rooms']:[] as $r){
            if(!is_array($r))continue;
            $rooms[]=['name'=>is_string($r['name']??null)?$r['name']:'Room','x'=>(float)($r['x']??0),'z'=>(float)($r['z']??0),'w'=>(float)($r['w']??0),'d'=>(float)($r['d']??0)];
        }
        $roomCount=count($rooms);
        $area=array_sum(array_map(fn($r)=>$r['w']*$r['d'],$rooms));
        $roomsJson=json_encode($rooms,JSON_THROW_ON_ERROR);
        if(!empty($detail['reviewer_name']))$reviewer=(string)$detail['reviewer_name'];
        $detailOk=true;
    }catch(Throwable $e){$detailOk=false;}
}

// Recent activity from the latest submissions and published reviewer feedback.
$events=[];$feedbackCount=0;
foreach(array_slice($rows,0,5) as $p){
    $events[]=['t'=>(int)$p['created_at'],'title'=>'Project submitted','text'=>(string)$p['name'].' · '.(string)$p['kind']];
    try{
        foreach($service->feedback((int)$user['id'],(int)$p['id']) as $f){
            if(($f['status']??'')!=='published')continue;
            $feedbackCount++;
            $events[]=['t'=>(int)$f['updated_at'],'title'=>'Reviewer feedback received','text'=>(string)($f['author_name']??'Reviewer').': '.(string)($f['location']??'')];
        }
    }catch(Throwable $e){}
}
usort($events,fn($a,$b)=>$b['t']<=>$a['t']);
$events=array_slice($events,0,4);

$first=$user['first_name'];
$initials=mb_strtoupper(mb_substr((string)$user['name'],0,2));
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard | Archispace</title><link rel="stylesheet" href="assets/fonts.css"><link rel="stylesheet" href="assets/portal.css"><link rel="stylesheet" href="assets/dashboard.css"><script src="assets/portal.js" defer></script><script src="assets/dashboard.js" defer></script></head>
<body>
<header class="portal-nav"><a class="brand" href="dashboard.php"><span class="brand-mark"><?= icon('imgArchispaceMark','admin') ?></span>archispace</a>
<nav aria-label="Workspace"><a href="dashboard.php" aria-current="page">Dashboard</a><a href="projects.php">Projects</a><a href="#services">Services</a><a href="index.php" title="Open the studio and use Quick guide">Help / tutorial</a></nav>
<div class="nav-account"><?= badge('user') ?><span class="muted-link" title="Account settings are coming soon">Account</span><form action="logout.php" method="post"><?php csrfField(); ?><button><?= icon('imgLogOut','admin') ?>Logout</button></form><span class="avatar" title="<?= esc((string)$user['name']) ?>"><?= esc($initials) ?></span></div></header>
<main class="portal-main">
<?php if(isset($_SESSION['portal_notice'])){echo '<div class="notice" role="status">'.esc((string)$_SESSION['portal_notice']).'</div>';unset($_SESSION['portal_notice']);} ?>
<div class="page-heading"><div><p class="eyebrow">YOUR WORKSPACE / USER</p><h1><span id="greet-word">Welcome</span>, <?= esc($first) ?>.</h1><p>A clearer view of your projects. Continue your design, or start with a new plan.</p></div><a class="button primary" href="index.php">+ Create project</a></div>

<section class="dash-banner" aria-label="About the platform"><?= dashIcon('home') ?><div><h2>Your plans, brought into perspective.</h2><p>Archispace connects blueprint uploads, spatial design, optional AI and human feedback, and illustrative disaster readiness. Explore ideas before you discuss them with qualified professionals.</p></div><button type="button" data-dash-open="about-dialog">About the platform</button></section>

<section class="dash-hero" aria-label="Latest project">
<?php if($latest): ?>
<article class="dash-project"><div class="dash-project-body">
<div class="dash-plan-wrap"><?php if($detailOk && $roomCount>0): ?><canvas class="review-plan" width="640" height="300" data-rooms="<?= esc($roomsJson) ?>" aria-label="Layout of your latest submission"></canvas><?php else: ?><div class="dash-plan-empty">Plan preview unavailable</div><?php endif; ?></div>
<div class="dash-details"><div class="dash-tags"><span class="badge"><?= esc(mb_strtoupper((string)$latest['kind'])) ?></span><?= dashStatus((string)$latest['status']) ?></div>
<h2><?= esc((string)$latest['name']) ?></h2>
<p class="dash-meta"><?php if($detailOk): ?><?= $roomCount ?> rooms · room floor area <?= number_format($area,1) ?> m²<br><?php endif; ?>Submitted <?= dateLabel($latest['created_at']) ?><br>Reviewer: <?= esc($reviewer) ?></p>
<p class="dash-meta">Studio designs are saved in your browser. Submit one for review to see it here.</p>
<a class="button primary" href="project.php?id=<?= (int)$latest['id'] ?>">View <?= esc((string)$latest['name']) ?> →</a></div></div>
<div class="dash-strip"><span><?= $total ?> <?= $total===1?'submission':'submissions' ?></span><span><?= $feedbackCount ?> published feedback</span><span>Concept plan · submitted snapshot</span></div></article>
<?php else: ?>
<article class="dash-project dash-project-empty"><?php emptyState('No projects yet','Create a project in the studio, then submit it for review and it will appear here.'); ?></article>
<?php endif; ?>
<aside class="panel dash-activity"><h2>Recent activity</h2>
<?php if(!$events): ?><p class="hint">Nothing yet. Submitting a project and receiving feedback will show up here.</p><?php endif;foreach($events as $e): ?>
<div class="dash-event"><time class="when mono" datetime="<?= esc(gmdate('c',$e['t'])) ?>"><?= esc(gmdate('M j · H:i',$e['t'])) ?> UTC</time><strong><?= esc($e['title']) ?></strong><span><?= esc($e['text']) ?></span></div>
<?php endforeach; ?></aside>
</section>

<div class="dash-section-head"><h2>Main modules</h2><span class="hint">Your complete planning workflow</span></div>
<section class="dash-modules" aria-label="Main modules">
<a class="dash-card" href="index.php"><div class="dash-card-top"><?= dashIcon('upload') ?><?= dashIcon('arrow') ?></div><h3>Uploads</h3><p>Site and floor plans, organized and calibrated.</p><span class="dash-tag">Open studio</span></a>
<a class="dash-card" href="index.php"><div class="dash-card-top"><?= dashIcon('layers') ?><?= dashIcon('arrow') ?></div><h3>Blueprint studio</h3><p>2D / 3D editing, site tools and materials.</p><span class="dash-tag">Continue design</span></a>
<div class="dash-card is-soon" aria-disabled="true"><div class="dash-card-top"><?= dashIcon('sparkle') ?></div><h3>AI design review</h3><p>Explore suggestions and compare alternatives.</p><span class="dash-tag">Coming soon</span></div>
<a class="dash-card" href="index.php"><div class="dash-card-top"><?= dashIcon('waves') ?><?= dashIcon('arrow') ?></div><h3>Disaster simulation</h3><p>Flood, storm and earthquake scenarios.</p><span class="dash-tag">Open studio</span></a>
<a class="dash-card" href="index.php"><div class="dash-card-top"><?= dashIcon('clipboard') ?><?= dashIcon('arrow') ?></div><h3>Readiness reports</h3><p>Review concept estimates and next steps.</p><span class="dash-tag">Open studio</span></a>
</section>

<div class="dash-section-head" id="services"><h2>Services for your next decision</h2></div>
<section class="dash-services" aria-label="Services">
<a class="dash-card" href="projects.php"><div class="dash-card-top"><span class="dash-title"><?= dashIcon('message') ?>Administrator review</span></div><p>Request human architectural feedback on your own projects.</p><span class="dash-tag">Request a review →</span></a>
<a class="dash-card" href="index.php"><div class="dash-card-top"><span class="dash-title"><?= dashIcon('download') ?>Export report</span></div><p>Share your concept estimates, assumptions and action plan.</p><span class="dash-tag">Open studio to export →</span></a>
</section>

<p class="hint dash-note">You manage your own projects and can request feedback. Readiness scores are illustrative concept estimates, not structural certification or safety guarantees.</p>
</main>
<dialog id="about-dialog"><div class="section-heading"><h2>About Archispace</h2><button type="button" data-dash-close aria-label="Close">×</button></div><p>Archispace is a concept-planning workspace. Bring in blueprints, shape a design in 2D and 3D, and review illustrative flood, storm and earthquake readiness.</p><p class="hint">Concept design tool. Not construction-ready CAD. Verify dimensions, structure, drainage and local codes with qualified professionals.</p></dialog>
<footer class="portal-footer"><span>ARCHISPACE / CONCEPT PLANNING</span><span>Logout returns to Log in · Help can reopen the tutorial</span><span>WORKSPACE / V.01</span></footer>
</body></html>
