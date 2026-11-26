<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/portal.php';
$user=requireUser();
if($user['role']!=='user'){header('Location: '.accountHome($user));exit;}
$showReports = ($_GET['tab'] ?? '') === 'reports';

function dashIcon(string $name): string {
    $paths=[
        'plus'=>'<path d="M12 5v14M5 12h14"/>',
        'message'=>'<path d="M4 5h16v11H9l-5 4V5z"/>',
        'download'=>'<path d="M12 4v11M7 11l5 5 5-5M4 20h16"/>',
        'arrow'=>'<path d="M4 12h15m-6-6 6 6-6 6"/>',
    ];
    return '<svg class="dash-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??'').'</svg>';
}

function dashStatus(string $status): string {
    $labels=['unassigned'=>'Awaiting reviewer','awaiting_owner'=>'Feedback received','completed'=>'Review completed'];
    $label=$labels[$status]??ucfirst(str_replace('_',' ',$status));
    return '<span class="badge '.($status==='unassigned'?'amber':'').'">'.esc($label).'</span>';
}

// The dashboard shows submitted snapshots. Working designs are available separately in the studio.
$rows=[];$total=0;$submissionsAvailable=true;
try{$list=$service->projects((int)$user['id'],'','',1,true);$rows=$list['rows'];$total=(int)$list['total'];}
catch(Throwable $e){$submissionsAvailable=false;}
$latest=$rows[0]??null;

$rooms=[];$roomCount=0;$area=0.0;$roomsJson='[]';$detailOk=false;
if($latest && !$showReports){
    try{
        $detail=$service->project((int)$user['id'],(int)$latest['id']);
        $data=json_decode((string)$detail['state_json'],true);
        $state=is_array($data)?($data['state']??[]):[];
        foreach(is_array($state['rooms']??null)?$state['rooms']:[] as $r){
            if(!is_array($r))continue;
            $rooms[]=['name'=>is_string($r['name']??null)?$r['name']:'Room','x'=>(float)($r['x']??0),'z'=>(float)($r['z']??0),'w'=>(float)($r['w']??0),'d'=>(float)($r['d']??0)];
        }
        $roomCount=count($rooms);
        $area=array_sum(array_map(fn($r)=>$r['w']*$r['d'],$rooms));
        $roomsJson=json_encode($rooms,JSON_THROW_ON_ERROR);
        $detailOk=true;
    }catch(Throwable $e){$detailOk=false;}
}

// Recent activity from the latest submissions and published reviewer feedback.
$events=[];
foreach(($showReports ? [] : array_slice($rows,0,5)) as $p){
    $events[]=['t'=>(int)$p['created_at'],'title'=>'Project submitted','text'=>(string)$p['name'].' · '.(string)$p['kind']];
    try{
        foreach($service->feedback((int)$user['id'],(int)$p['id']) as $f){
            if(($f['status']??'')!=='published')continue;
            $events[]=['t'=>(int)$f['updated_at'],'title'=>'Reviewer feedback received','text'=>(string)($f['author_name']??'Reviewer').': '.(string)($f['location']??'')];
        }
    }catch(Throwable $e){}
}
usort($events,fn($a,$b)=>$b['t']<=>$a['t']);
$events=array_slice($events,0,4);

$initials=mb_strtoupper(mb_substr((string)$user['name'],0,2));
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $showReports ? 'Reports and feedback' : 'Dashboard' ?> | Archispace</title><link rel="stylesheet" href="assets/fonts.css"><link rel="stylesheet" href="assets/theme.css"><link rel="stylesheet" href="assets/portal.css"><link rel="stylesheet" href="assets/dashboard.css"><script src="assets/portal.js" defer></script><script src="assets/dashboard.js" defer></script></head>
<body class="dashboard">
<header class="portal-nav">
    <a class="brand" href="dashboard.php"><span class="brand-mark"><?= icon('imgArchispaceMark','admin') ?></span>archispace</a>
    <nav aria-label="Workspace">
        <a href="dashboard.php"<?= $showReports ? '' : ' aria-current="page"' ?>>Dashboard</a>
        <a href="studio.php">Design studio</a>
        <a href="projects.php">My submissions</a>
        <a href="dashboard.php?tab=reports"<?= $showReports ? ' aria-current="page"' : '' ?>>Reports and feedback</a>
    </nav>
    <div class="nav-account">
        <span class="avatar" title="<?= esc((string)$user['name']) ?>"><?= esc($initials) ?></span>
        <form action="logout.php" method="post"><?php csrfField(); ?><button><?= icon('imgLogOut','admin') ?>Logout</button></form>
    </div>
</header>
<main class="portal-main dash-main">
    <?php if(isset($_SESSION['portal_notice'])){echo '<div class="notice" role="status">'.esc((string)$_SESSION['portal_notice']).'</div>';unset($_SESSION['portal_notice']);} ?>

    <?php if(!$showReports): ?>
    <section class="dash-intro <?= $latest ? 'has-submission' : '' ?>" aria-labelledby="intro-heading">
        <div class="dash-intro-copy">
            <h1 id="intro-heading">Your design workspace</h1>
            <p>Start a new project, reopen a saved design or check the feedback on your latest submission.</p>
            <div class="dash-actions">
                <a class="button primary" href="studio.php?tool=new"><?= dashIcon('plus') ?>Create new project</a>
                <a class="dash-link" href="studio.php?tool=designs">Open saved designs <?= dashIcon('arrow') ?></a>
            </div>
        </div>
        <?php if(!$latest): ?>
        <figure class="dash-example">
            <img src="assets/coastal-retreat-plan.png" width="1072" height="592" alt="Ground-floor plan with a living room and kitchen above two bedrooms and a bathroom.">
            <figcaption><strong>Coastal Retreat</strong><span>Example floor plan</span></figcaption>
        </figure>
        <?php endif; ?>
    </section>

    <?php if($latest): ?>
    <section class="dash-review" aria-labelledby="latest-title">
        <div class="dash-section-head"><h2 id="latest-title">Latest submission</h2><a class="dash-link" href="projects.php">My submissions (<?= $total ?>) <?= dashIcon('arrow') ?></a></div>
        <div class="dash-review-grid">
            <article class="dash-project">
                <div class="dash-details">
                    <div class="dash-tags"><span><?= esc((string)$latest['kind']) ?></span><?= dashStatus((string)$latest['status']) ?></div>
                    <h3><?= esc((string)$latest['name']) ?></h3>
                    <p>Submitted <?= dateLabel($latest['created_at']) ?><br>Reviewer: <?= esc((string)($latest['reviewer_name'] ?: 'Awaiting assignment')) ?></p>
                    <a class="dash-link" href="project.php?id=<?= (int)$latest['id'] ?>">View plan and feedback <?= dashIcon('arrow') ?></a>
                </div>
                <div class="dash-plan">
                    <?php if($detailOk && $roomCount): ?>
                    <canvas class="review-plan" width="640" height="300" data-rooms="<?= esc($roomsJson) ?>" role="img" aria-label="<?= esc(implode(', ',array_map(fn($r)=>$r['name'].' '.$r['w'].' by '.$r['d'].' metres',$rooms))) ?>"></canvas>
                    <p><?= $roomCount ?> <?= $roomCount===1?'room':'rooms' ?> · <?= number_format($area,1) ?> m²</p>
                    <?php else: ?><p>Plan preview unavailable</p><?php endif; ?>
                </div>
            </article>
            <?php if($events): ?>
            <aside class="dash-activity"><h3>Recent activity</h3>
                <?php foreach($events as $e): ?>
                <div class="dash-event"><strong><?= esc($e['title']) ?></strong><span><?= esc($e['text']) ?></span><time class="when" datetime="<?= esc(gmdate('c',$e['t'])) ?>"><?= esc(gmdate('M j · H:i',$e['t'])) ?> UTC</time></div>
                <?php endforeach; ?>
            </aside>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php else: ?>
    <section class="dash-outcomes" aria-labelledby="review-title">
        <div>
            <h1 id="review-title">Reports and feedback</h1>
            <p>Use the report to discuss your concept with a qualified professional.</p>
        </div>
        <div class="dash-outcome-list">
            <a href="studio.php?tool=report"><?= dashIcon('download') ?><div><h3>Export a readiness report</h3><p>Print or save a PDF of your floor plans and concept estimates.</p></div><?= dashIcon('arrow') ?></a>
            <a href="studio.php?tool=review"><?= dashIcon('message') ?><div><h3>Ask a reviewer for feedback</h3><p>Share a snapshot of your design and tell the reviewer what to look at.</p></div><?= dashIcon('arrow') ?></a>
            <?php if(!$latest && $submissionsAvailable): ?>
            <div class="dash-submission-note">
                <strong>No submissions yet.</strong> After you submit, you can track feedback in <a href="projects.php">My submissions</a>.
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>
    <?php if(!$submissionsAvailable): ?>
    <p class="notice" role="status">We couldn’t load your submissions. <a href="<?= $showReports ? 'dashboard.php?tab=reports' : 'dashboard.php' ?>">Try again</a>.</p>
    <?php endif; ?>
</main>
<footer class="portal-footer dash-footer"><span>Archispace · Concept planning</span><a href="studio.php?tool=guide">Quick guide</a><button type="button" data-open="about-dialog">About Archispace</button></footer>
<dialog id="about-dialog" aria-labelledby="about-title"><div class="section-heading"><h2 id="about-title">About Archispace</h2><button type="button" data-close="about-dialog" aria-label="Close">×</button></div><p>Design a floor plan, explore site conditions and compare flood, storm and earthquake scenarios. Save working designs in My designs, and submit a copy when you want human feedback.</p><p>Readiness reports give illustrative estimates. Ask qualified professionals to verify dimensions, structure, drainage and local code requirements before construction.</p></dialog>
</body></html>
