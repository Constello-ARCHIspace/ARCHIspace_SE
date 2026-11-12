<?php
/**
 * ARCHIspace — PHP entry point.
 * Run with: php -S localhost:8000
 * Then open: http://localhost:8000/index.php
 *
 * PHP renders the page; browser-side JavaScript and Three.js power the
 * interactive designer. Existing project storage and JSON export are retained.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
$currentUser = requireUser();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
$appTitle = 'ARCHIspace — Concept by aqua lucero';
$defaultProjectName = 'Untitled house';
function htmlEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title><?= htmlEscape($appTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="assets/fonts.css" rel="stylesheet">
<style>
:root{--bg:#090d12;--panel:#10151c;--panel2:#161d27;--panel3:#1d2633;--line:#222c3a;--line2:#2f3b4e;--text:#e9eef5;--muted:#8592a6;--accent:#f2b84b;--accent2:#4fd1c5;--danger:#ff6b7a;--r:10px;--mono:'IBM Plex Mono',ui-monospace,Menlo,monospace}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;background:var(--bg);color:var(--text);font:13px/1.45 Inter,system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;overflow:hidden;-webkit-font-smoothing:antialiased}
button,input,select{font:inherit;color:inherit}
button{cursor:pointer;border:1px solid var(--line2);background:var(--panel3);border-radius:8px;padding:7px 11px;transition:.15s;white-space:nowrap}
button:hover{border-color:#52627b;background:#243041}
button:active{transform:translateY(1px)}
button.on,button.primary{background:var(--accent);border-color:var(--accent);color:#17120a;font-weight:650}
button.primary:hover,button.on:hover{background:#ffc965}
button.ghost{background:transparent;border-color:transparent;color:var(--muted)}
button.ghost:hover{color:var(--text);background:var(--panel3)}
button.danger{color:#ffb3bb}
button:disabled{opacity:.35;cursor:default}
.shell{display:grid;grid-template-rows:56px minmax(0,1fr);height:100vh;height:100dvh}
header{display:flex;align-items:center;gap:10px;padding:0 14px;background:var(--panel);border-bottom:1px solid var(--line);z-index:30}
.brand{display:flex;align-items:center;gap:10px;font-weight:700;letter-spacing:.2px;font-size:15px}
.logo{width:32px;height:32px;border-radius:9px;display:grid;place-items:center;background:linear-gradient(135deg,var(--accent),#ff8f5a);color:#1a1206}
.brand small{display:block;font-size:8.5px;letter-spacing:2.2px;color:var(--muted);font-weight:500;margin-top:-2px}
.sep{width:1px;height:24px;background:var(--line2);margin:0 4px}
#projName{background:transparent;border:1px solid transparent;border-radius:8px;padding:6px 9px;width:210px;font-weight:600;color:var(--text)}
#projName:hover,#projName:focus{border-color:var(--line2);background:var(--panel2);outline:none}
.sp{flex:1}
.hgroup{display:flex;gap:6px;align-items:center}
details.menu{position:relative}
details.menu>summary{list-style:none;cursor:pointer;border:1px solid var(--line2);background:var(--panel3);border-radius:8px;padding:7px 11px}
details.menu>summary::-webkit-details-marker{display:none}
details.menu[open]>summary{border-color:var(--accent)}
.menu-list{position:absolute;right:0;top:40px;background:var(--panel2);border:1px solid var(--line2);border-radius:10px;padding:6px;min-width:170px;box-shadow:0 18px 50px #000a;z-index:50;display:flex;flex-direction:column;gap:2px}
.menu-list button{text-align:left;border:0;background:transparent}
.menu-list button:hover{background:var(--panel3)}
.only-small{display:none}
.app{display:grid;grid-template-columns:292px minmax(0,1fr) 292px;min-height:0}
.side{background:var(--panel);overflow:auto;min-height:0;scrollbar-width:thin;scrollbar-color:#2c3a4e transparent}
.left{border-right:1px solid var(--line)}
.right{border-left:1px solid var(--line);padding:12px}
.tabs{display:flex;gap:4px;padding:10px 10px 0;position:sticky;top:0;background:var(--panel);z-index:3}
.tab{flex:1;border:0;border-bottom:2px solid var(--line);background:transparent;border-radius:0;padding:9px 4px;color:var(--muted);font-weight:600;font-size:12px}
.tab.on{background:transparent;color:var(--accent);border-bottom-color:var(--accent)}
.pane{display:none;padding:12px}.pane.on{display:block}
.sect{margin-bottom:18px}
.stitle{font-size:10px;letter-spacing:1.6px;text-transform:uppercase;font-weight:700;color:var(--muted);margin:0 0 9px;display:flex;align-items:center;gap:8px}
.stitle::after{content:"";flex:1;height:1px;background:var(--line)}
.tiles{display:grid;grid-template-columns:repeat(3,1fr);gap:7px}
.tile{display:flex;flex-direction:column;align-items:center;gap:4px;padding:8px 4px 7px;background:var(--panel2);border:1px solid var(--line);border-radius:10px;font-size:10.5px;color:#c9d4e3}
.tile:hover{border-color:var(--accent);background:var(--panel3)}
.tile canvas{width:44px;height:44px}
.tile small{color:var(--muted);font-size:9.5px;font-family:var(--mono)}
.subhead{font-size:11px;color:#b5c2d6;margin:11px 0 6px;font-weight:600}
.field{margin:11px 0}
.field label{display:flex;justify-content:space-between;color:var(--muted);font-size:11.5px;margin-bottom:5px}
.field output{color:var(--text);font-family:var(--mono);font-size:11px}
input[type=range]{width:100%;accent-color:var(--accent);height:18px}
select,input[type=text],input[type=number]{width:100%;background:#0b1118;border:1px solid var(--line2);border-radius:8px;padding:7px 9px}
select:focus,input:focus{outline:none;border-color:var(--accent)}
input[type=color]{width:100%;height:32px;padding:2px;background:#0b1118;border:1px solid var(--line2);border-radius:8px;cursor:pointer}
.row{display:flex;gap:6px}.row>*{flex:1}
.chips{display:flex;gap:6px;flex-wrap:wrap}.chips button{padding:5px 9px;font-size:11.5px}
.help{color:var(--muted);font-size:11px;line-height:1.55}
.workspace{display:flex;flex-direction:column;min-width:0;min-height:0}
.toolbar{display:flex;gap:8px;align-items:center;padding:8px 10px;background:var(--panel);border-bottom:1px solid var(--line);flex-wrap:wrap}
.seg{display:flex;background:#0b1118;border:1px solid var(--line);border-radius:10px;padding:3px;gap:2px}
.seg button{border:0;background:transparent;padding:5px 11px;font-size:12px;color:var(--muted);border-radius:7px}
.seg button:hover{color:var(--text);background:transparent}
.seg button.on{background:var(--accent);color:#17120a}
.canvas-wrap{position:relative;flex:1;min-height:0;overflow:hidden;background:#0a1018}
canvas#view3d,canvas#plan2d{position:absolute;inset:0;width:100%;height:100%;display:block;touch-action:none}
canvas#plan2d{display:none;cursor:default}
.hud{position:absolute;left:12px;top:12px;pointer-events:none;background:#0c121bd9;backdrop-filter:blur(8px);border:1px solid var(--line2);border-radius:10px;padding:8px 12px;max-width:min(430px,70%)}
.hud b{display:block;font-size:12px}.hud span{color:var(--muted);font-size:11px}
.pills{position:absolute;left:12px;bottom:12px;display:flex;gap:6px;flex-wrap:wrap;pointer-events:none}
.pill{background:#0c121bd9;backdrop-filter:blur(8px);border:1px solid var(--line2);border-radius:8px;padding:5px 9px;font-size:10.5px;font-family:var(--mono);color:#cfdbea}
.coords{position:absolute;right:12px;bottom:12px;pointer-events:none}
.simbar{position:absolute;right:12px;top:12px;display:none;background:#0d2a37e6;border:1px solid #2c7d93;border-radius:10px;padding:7px 11px;font-size:11.5px;color:#bff0ff}
.toast{position:absolute;left:50%;top:14px;transform:translateX(-50%) translateY(-20px);opacity:0;transition:.25s;background:#12261fee;border:1px solid #2f8a6e;border-radius:10px;padding:8px 14px;pointer-events:none;z-index:6;max-width:80%}
.toast.show{opacity:1;transform:translateX(-50%)}
.foot{padding:6px 12px;border-top:1px solid var(--line);background:var(--panel);color:#6f7d92;font-size:10px}
.card{background:var(--panel2);border:1px solid var(--line);border-radius:12px;padding:12px;margin-bottom:11px}
.card h3{margin:0 0 10px;font-size:11px;letter-spacing:1.4px;text-transform:uppercase;color:#b5c2d6}
.stat{display:flex;justify-content:space-between;color:var(--muted);margin:6px 0;font-size:12px}.stat b{color:var(--text);font-family:var(--mono);font-weight:500}
.empty{color:var(--muted);font-size:12px;padding:6px 0;line-height:1.6}
.chip{display:inline-block;font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;padding:3px 8px;border-radius:6px;background:#2a3547;color:var(--accent)}
.insp-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:10px 0}
.if span{display:block;color:var(--muted);font-size:10.5px;margin-bottom:3px}
.if.full{grid-column:1/-1}
.btnrow{display:flex;gap:6px;margin-top:6px}.btnrow button{flex:1;padding:7px 4px;font-size:12px}
.list{display:flex;flex-direction:column;gap:5px;max-height:260px;overflow:auto}
.li{display:flex;align-items:center;gap:8px;padding:6px 8px;background:#0d131b;border:1px solid var(--line);border-radius:8px;cursor:pointer;font-size:12px}
.li:hover{border-color:var(--line2)}.li.sel{border-color:var(--accent);background:#211b0d}
.li i{width:8px;height:22px;border-radius:4px;flex-shrink:0}.li div{flex:1;min-width:0}.li small{display:block;color:var(--muted);font-size:10.5px}
.li button{padding:2px 7px;font-size:11px}
.padgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:5px;width:132px;margin:8px auto 0}
.padgrid button{padding:7px 0}.padgrid .u{grid-column:2}.padgrid .l{grid-column:1;grid-row:2}.padgrid .d{grid-column:2;grid-row:2}.padgrid .r{grid-column:3;grid-row:2}
.sim{margin-top:10px;font-size:11.5px;line-height:1.6;color:#c4d0e0}
.sim b{color:var(--accent)}.sim .bad{color:#ffb3bb}.sim .ok{color:#8fe3b8}
.modal{display:none;position:fixed;inset:0;z-index:100;background:#03070dd9;backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:18px}
.modal.open{display:flex}
.mbox{width:min(980px,100%);max-height:92vh;overflow:auto;background:var(--panel);border:1px solid var(--line2);border-radius:18px;padding:22px;box-shadow:0 30px 120px #000c}
.mhead{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:16px}
.mhead h2{margin:0 0 3px;font-size:20px}
.gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px}
.gcard{background:var(--panel2);border:1px solid var(--line);border-radius:14px;overflow:hidden;transition:.2s}
.gcard:hover{border-color:var(--accent);transform:translateY(-2px)}
.gcard canvas{width:100%;height:150px;display:block;background:#0b1118}
.ginfo{padding:12px}.ginfo h3{margin:0 0 3px;font-size:14px}.ginfo p{margin:0 0 10px;color:var(--muted);font-size:11.5px;min-height:32px}
.tags{display:flex;gap:5px;margin-bottom:10px;flex-wrap:wrap}.tags span{font-size:10px;font-family:var(--mono);background:#243041;color:#b9cbe0;border-radius:5px;padding:2px 6px}
/* multi-storey */
.floor-row{display:flex;gap:6px;align-items:center;margin-bottom:8px}.floor-row select{flex:1}
.floor-actions{display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin:8px 0}.floor-actions button{padding:6px 4px;font-size:11.5px}
#floorSelect{width:auto;min-width:118px;padding:5px 8px}
/* blueprint upload styles */
.bp-dropzone{border:2px dashed var(--line2);border-radius:14px;padding:28px 16px;text-align:center;transition:.2s;cursor:pointer;background:transparent}
.bp-dropzone:hover,.bp-dropzone.dragover{border-color:var(--accent);background:#f2b84b0a}
.bp-dropzone svg{display:block;margin:0 auto 10px;opacity:.45}
.bp-dropzone .bp-title{font-weight:600;font-size:13px;margin-bottom:4px}
.bp-dropzone .bp-sub{color:var(--muted);font-size:11px}
.bp-item{display:flex;align-items:center;gap:10px;padding:9px 10px;background:var(--panel2);border:1px solid var(--line);border-radius:10px;margin-bottom:8px;transition:.15s}
.bp-item:hover{border-color:var(--line2)}
.bp-item .bp-thumb{width:48px;height:48px;border-radius:8px;object-fit:cover;background:#0b1118;flex-shrink:0}
.bp-item .bp-info{flex:1;min-width:0}
.bp-item .bp-info .bp-name{font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bp-item .bp-info .bp-meta{font-size:10.5px;color:var(--muted);margin-top:2px}
.bp-item .bp-toggle{padding:4px 8px;font-size:10px;border-radius:6px}
.bp-item .bp-toggle.on{background:var(--accent);border-color:var(--accent);color:#17120a}
.bp-item .bp-remove{padding:4px 8px;font-size:11px;color:#ffb3bb;background:transparent;border-color:transparent}
.bp-item .bp-remove:hover{background:#ff6b7a22}
.bp-item .bp-suggest{padding:4px 7px;font-size:12px;color:var(--accent);background:transparent;border-color:transparent}
.bp-item .bp-suggest:hover{border-color:var(--accent)}
.bp-controls{margin-top:6px}
.bp-badge{display:inline-flex;align-items:center;gap:5px;font-size:9.5px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:3px 8px;border-radius:5px;margin-bottom:6px}
.bp-badge.house{background:#1a3a5e;color:#6dbbf5}
.bp-badge.lot{background:#2a4e3a;color:#7fd4a1}
/* Blueprint-assisted concept suggestion: compare before applying */
.suggest-intro{color:var(--muted);font-size:12px;line-height:1.6;margin:0 0 14px}
.suggest-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:15px}
.suggest-card{background:var(--panel2);border:1px solid var(--line2);border-radius:12px;overflow:hidden}
.suggest-card.proposed{border-color:#5c946f}
.suggest-card-title{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;font-size:12px;font-weight:700}
.suggest-card canvas{display:block;width:100%;height:auto;aspect-ratio:13/8;background:#101821}
.suggest-card .suggest-meta{padding:10px 12px;font-size:11px;color:var(--muted);line-height:1.55}
.suggest-actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin-top:14px}
.suggest-actions button{min-width:165px}
.suggest-note{padding:10px 12px;border:1px solid var(--line);border-radius:9px;background:#111b26;font-size:11px;color:#bac8d8;line-height:1.6}
@media(max-width:640px){.suggest-grid{grid-template-columns:1fr}.suggest-actions button{flex:1;min-width:130px}}
@media(max-width:1180px){
 .app{grid-template-columns:276px minmax(0,1fr)}
 .right{position:fixed;right:0;top:56px;bottom:0;width:310px;transform:translateX(105%);transition:.25s;z-index:40;box-shadow:-20px 0 60px #000a}
 .right.open{transform:none}
 .only-small{display:inline-block}
}
@media(max-width:820px){
 .app{grid-template-columns:1fr}
 .left{position:fixed;left:0;top:56px;bottom:0;width:310px;transform:translateX(-105%);transition:.25s;z-index:40;box-shadow:20px 0 60px #000a}
 .left.open{transform:none}
 #projName{width:120px}.brand small,.hide-s{display:none}
 .toolbar{gap:6px}.seg button{padding:5px 8px}
}
</style>
<link rel="stylesheet" href="assets/studio.css"><script src="assets/studio-ui.js" defer></script>
</head>
<body>
<div class="shell">
<header>
  <a class="brand" href="index.php" aria-label="Archispace workspace"><span class="studio-symbol"><img src="assets/figma/studio-imgArchispaceMark.svg" alt=""></span><span>archispace</span></a>
  <div class="sep"></div>
  <input id="projName" type="text" maxlength="48" aria-label="Project name" value="<?= htmlEscape($defaultProjectName) ?>">
  <div class="hgroup"><button id="undoBtn" class="ghost icon-button" title="Undo (Ctrl+Z)" aria-label="Undo"><img src="assets/figma/studio-imgUndo2.svg" alt=""></button><button id="redoBtn" class="ghost icon-button" title="Redo (Ctrl+Shift+Z)" aria-label="Redo"><img src="assets/figma/studio-imgRedo2.svg" alt=""></button></div>
  <div class="sp"></div>
  <button id="toolsToggle" class="only-small">☰ Tools</button>
  <button id="inspToggle" class="only-small">◧ Inspector</button>
  <button id="galleryBtn">▦ <span class="hide-s">Sample designs</span></button>
  <button id="loadBtn">Open</button>
  <button id="saveBtn">Save</button>
  <details class="menu" id="reviewMenu"><summary>Review ▾</summary><div class="menu-list"><button id="submitReviewBtn">Submit for review</button><a href="projects.php">My submissions</a><?php if ($currentUser['role'] !== 'user'): ?><a href="<?= esc(accountHome($currentUser)) ?>">Staff dashboard</a><?php endif; ?></div></details>
  <form action="logout.php" method="post"><input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>"><button type="submit" title="<?= esc($currentUser['email']) ?>">Sign out</button></form>
  <details class="menu" id="exportMenu"><summary class="primary" style="background:var(--accent);color:white;font-weight:500;border-color:var(--accent)">Export ▾</summary><div class="menu-list"><button id="exportJson">Project file (.json)</button><button id="exportPng">Current view (.png)</button></div></details>
  <input type="file" id="loadFile" accept=".json,application/json" hidden>
</header>
<nav class="workflow" aria-label="Planning steps"><span class="workflow-label">RESIDENTIAL / CONCEPT PLAN</span><button data-step="blueprint"><span>01</span>Import blueprint</button><button data-step="design" class="active" aria-current="step"><span>02</span>Design site</button><button data-step="simulate"><span>03</span>Simulate</button><button data-step="readiness"><span>04</span>Review readiness</button><button class="quick-guide" id="quickGuide"><img src="assets/figma/studio-imgCircleHelp.svg" alt="">Quick guide</button></nav>
<div class="app">
<aside class="side left" id="left">
<div class="environment-tools"><p>BUILD YOUR ENVIRONMENT</p><div class="environment-grid"><button data-focus="terrain"><img src="assets/figma/studio-imgMountain.svg" alt=""><span>Terrain<small>Shape &amp; elevation</small></span></button><button data-focus="river"><img src="assets/figma/studio-imgWaves.svg" alt=""><span>River<small>Draw a watercourse</small></span></button><button data-focus="landscape"><img src="assets/figma/studio-imgTrees.svg" alt=""><span>Landscape<small>Trees &amp; planting</small></span></button><button data-focus="furniture"><img src="assets/figma/studio-imgArmchair.svg" alt=""><span>Furniture<small>Indoor &amp; outdoor</small></span></button></div></div>
  <div class="tabs"><button class="tab on" data-tab="build">Build</button><button class="tab" data-tab="site">Site</button><button class="tab" data-tab="env">Environment</button></div>

  <div class="pane on" data-pane="build">
    <div class="sect"><div class="stitle">Storeys</div>
      <div class="field"><label for="buildingKind">Building type</label><select id="buildingKind"><option>House</option><option>Commercial</option><option>Mixed use</option></select></div>
      <div class="floor-row"><select id="floorPanelSelect" aria-label="Floor being edited"></select></div>
      <div class="floor-actions"><button id="addFloorBtn" title="Add an empty floor on top">+ Add floor</button><button id="copyFloorBtn" title="Copy this floor's rooms and fixtures to a new top floor">⧉ Copy floor</button><button id="removeFloorBtn" class="danger" title="Remove this floor and everything on it">Remove</button></div>
      <div class="field"><label for="floorHeight">Floor height (m) <output id="floorHeightOut"></output></label><input id="floorHeight" type="number" min="2.2" max="6" step="0.1" placeholder="Uses wall height"></div>
      <p class="help" id="floorHelp">Each floor sits one floor height plus a slab above the floor below. Leave the height empty to use the wall height in Site. Rooms and fixtures are added to the floor shown here.</p>
    </div>
    <div class="sect"><div class="stitle">Design versions</div><select id="versionList" aria-label="Design versions"></select><button id="restoreVersion">Restore as new version</button></div><div class="sect"><div class="stitle">Rooms</div><div class="tiles" id="roomPalette"></div></div>
    <div class="sect"><div class="stitle">Furniture &amp; fixtures</div><div id="objPalette"></div>
      <p class="help" style="margin-top:10px">Doors and windows snap to the nearest wall and cut a real opening. Moving a room carries its furniture and openings with it.</p></div>
  </div>

  <div class="pane" data-pane="site">
    <div class="sect"><div class="stitle">Blueprints</div>
      <div id="bpList"></div>
      <div class="bp-dropzone" id="bpDropzone">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <div class="bp-title">Upload Blueprint</div>
        <div class="bp-sub">Drag &amp; drop or click · JPG, PNG, PDF, SVG</div>
      </div>
      <input type="file" id="bpFileInput" accept="image/*,.pdf,.svg" multiple hidden>
      <p class="help" style="margin-top:8px">Upload house or lot blueprints to overlay on the floor plan. Adjust opacity and scale to trace your design.</p>
    </div>
    <div class="sect"><div class="stitle">Hazard Data · Location</div>
      <div class="row"><div class="field"><label for="siteLat">Latitude</label><input id="siteLat" type="number" min="-90" max="90" step="any"></div><div class="field"><label for="siteLng">Longitude</label><input id="siteLng" type="number" min="-180" max="180" step="any"></div></div>
      <div class="field"><label for="hazardPeriod">Flood return period</label><select id="hazardPeriod"><option>5</option><option>25</option><option selected>100</option></select></div>
      <div class="field"><label for="surgeScenario">Storm surge scenario</label><select id="surgeScenario"><option>1</option><option>2</option><option>3</option><option selected>4</option></select></div>
      <button id="fetchHazard">Check location</button><div id="hazardStatus" class="help">No location checked.</div>
      <p class="help">Project NOAH and contributors. Beta, single-point screening; coverage varies (including gaps in 100-year flood data for Tawi-Tawi). NOAH does not assess earthquakes; obtain PHIVOLCS active-fault and liquefaction information separately. <a href="https://noah.up.edu.ph/know-your-hazards" target="_blank" rel="noopener">NOAH</a> · <a href="https://hazardhunter.georisk.gov.ph/" target="_blank" rel="noopener">HazardHunterPH</a></p>
    </div><div class="sect"><div class="stitle">Plot</div>
      <div class="field"><label>Width <output></output></label><input type="range" data-s="site.width" data-a="r" data-u=" m" min="12" max="60" step="1"></div>
      <div class="field"><label>Depth <output></output></label><input type="range" data-s="site.depth" data-a="r" data-u=" m" min="12" max="60" step="1"></div>
    </div>
    <div class="sect"><div class="stitle">Terrain</div>
      <div class="field"><label>Hill height <output></output></label><input type="range" data-s="site.hills" data-a="r" data-u=" m" min="0" max="6" step="0.1"></div>
      <div class="field"><label>Hill frequency <output></output></label><input type="range" data-s="site.scale" data-a="r" data-u="×" min="0.4" max="2.4" step="0.1"></div>
      <div class="field"><label>Ground elevation <output></output></label><input type="range" data-s="site.ground" data-a="r" data-u=" m" min="-2" max="4" step="0.1"></div>
      <div class="field"><label>Ground material</label><select data-s="site.material" data-a="r"><option value="grass">Grass</option><option value="sand">Sand</option><option value="stone">Stone</option><option value="soil">Soil</option><option value="snow">Snow</option></select></div>
      <button id="shuffleBtn" style="width:100%">⟳ Reshuffle terrain</button>
    </div>
    <div class="sect"><div class="stitle">River</div>
      <button id="riverBtn" style="width:100%;margin-bottom:8px">〰 Draw river on plan</button>
      <div class="field"><label>River width <output></output></label><input type="range" data-s="river.width" data-a="r" data-u=" m" min="0.8" max="10" step="0.2"></div>
      <div class="field"><label>Channel depth <output></output></label><input type="range" data-s="river.depth" data-a="r" data-u=" m" min="0.2" max="1.5" step="0.05"></div>
      <button id="clearRiverBtn" style="width:100%">Remove river</button>
    </div>
    <div class="sect"><div class="stitle">Building</div>
      <div class="field"><label>Raised floor <output></output></label><input type="range" data-s="site.floorRaise" data-a="r" data-u=" m" min="0" max="5" step="0.1"></div><div class="field"><label>Wall height <output></output></label><input type="range" data-s="site.wall" data-a="r" data-u=" m" min="2.2" max="4.5" step="0.1"></div>
      <div class="field"><label>Roof</label><select data-s="site.roof" data-a="r"><option value="off">Open (interior view)</option><option value="flat">Flat roof</option><option value="gable">Gable roof</option></select></div>
      <div class="row"><div class="field"><label>Walls</label><input type="color" data-s="site.wallColor" data-a="r"></div><div class="field"><label>Roof</label><input type="color" data-s="site.roofColor" data-a="r"></div></div>
    </div>
    <button id="resetBtn" class="danger" style="width:100%">Reset project</button>
  </div>

  <div class="pane" data-pane="env">
    <div class="sect"><div class="stitle">Light &amp; sky</div>
      <div class="field"><label>Time of day <output></output></label><input type="range" data-s="env.hour" data-a="e" data-f="hour" min="0" max="24" step="0.25"></div>
      <div class="chips"><button data-hour="8">Morning</button><button data-hour="12.5">Noon</button><button data-hour="17.75">Sunset</button><button data-hour="22">Night</button></div>
      <div class="field"><label>Weather</label><select data-s="env.weather" data-a="e"><option value="clear">Clear</option><option value="rain">Rain</option><option value="storm">Thunderstorm</option></select></div>
      <p class="help">Scrub the time slider to study shadows across the day.</p>
    </div>
    <div class="sect"><div class="stitle">Resilience lab</div>
      <div class="field"><label>Flood water level <output></output></label><input type="range" data-s="env.flood" data-a="f" data-u=" m" min="0" max="4" step="0.1"></div>
      <div class="field"><label>Earthquake intensity</label><select data-s="env.quake"><option value="none">None</option><option value="low">Low</option><option value="moderate">Moderate</option><option value="high">High</option></select></div>
      <button id="readinessBtn" style="width:100%;margin-bottom:8px">Readiness Report</button><div id="readinessReport" class="help" aria-live="polite"></div><button id="simBtn" class="primary" style="width:100%">▶ Run simulation</button>
      <div class="sim" id="simReport">Simulation is off. This is a visual study only — not a safety certification.</div>
    </div>
  </div>
</aside>

<main class="workspace">
  <div class="toolbar">
    <div class="seg"><button id="v3d" class="on">3D model</button><button id="v2d">2D plan</button><button id="vTop">Top</button></div>
    <select id="floorSelect" aria-label="Floor shown in the plan"></select>
    <div class="seg"><button id="gridBtn" class="on">Grid</button><button id="snapBtn" class="on">Snap</button><button id="labelBtn" class="on">Labels</button></div>
    <button id="roofToggleBtn" aria-pressed="true" title="Show or hide the roof">Roof: Shown</button>
    <select id="compareMode" aria-label="Compare design"><option value="off">Comparison off</option><option value="ghost">Ghost suggestion</option><option value="split">Split plans</option></select><button id="fitBtn">⤢ Fit</button>
    <div class="sp"></div>
    <button id="playBtn" class="primary"><img src="assets/figma/studio-imgPlay.svg" alt="">Walk through</button>
  </div>
  <div class="canvas-wrap" id="canvasWrap">
    <canvas id="view3d"></canvas><canvas id="plan2d"></canvas>
    <div id="comparePanel" style="display:none;position:absolute;right:12px;top:12px;width:44%;background:#101821;border:1px solid #5c946f;z-index:5;padding:8px;pointer-events:none"><b>Suggested concept · 2D</b><canvas id="workspaceCompare" width="520" height="320" style="width:100%"></canvas></div><div class="hud"><b id="viewTitle">3D model</b><span id="hint"></span></div>
    <div class="simbar" id="simBar">● Simulation running</div>
    <div class="toast" id="toast"></div>
    <div class="pills"><div class="pill" id="pSite"></div><div class="pill" id="pRooms"></div><div class="pill" id="pObjs"></div></div>
    <div class="pill coords" id="coords" style="display:none"></div>
  </div>
  <div class="foot">Concept design tool — not construction-ready CAD. Verify dimensions, structure, drainage and local codes with qualified professionals.</div>
</main>

<aside class="side right" id="right">
  <div class="card"><h3 class="properties-heading">Properties<img src="assets/figma/studio-imgSlidersHorizontal.svg" alt=""></h3><div id="inspector"></div></div>
  <div class="card"><h3>Project summary</h3>
    <div class="stat"><span>Storeys</span><b id="sFloors">1</b></div>
    <div class="stat"><span>Rooms</span><b id="sRooms">0</b></div>
    <div class="stat"><span>Bedrooms</span><b id="sBeds">0</b></div>
    <div class="stat"><span>Floor area</span><b id="sArea">0 m²</b></div>
    <div class="stat"><span>Plot coverage</span><b id="sCov">0%</b></div>
    <div class="stat"><span>Doors / windows</span><b id="sOpen">0 / 0</b></div>
    <div class="stat"><span>Furniture &amp; plants</span><b id="sObjs">0</b></div>
  </div>
  <div class="card"><h3>Outliner</h3><div class="list" id="outliner"></div></div>
  <div class="card"><h3>Nudge selection</h3><div class="padgrid"><button class="u" data-nudge="0,-1">↑</button><button class="l" data-nudge="-1,0">←</button><button class="d" data-nudge="0,1">↓</button><button class="r" data-nudge="1,0">→</button></div>
  <p class="help" style="margin-top:10px;text-align:center">Arrow keys nudge · Shift = fine · R rotates · Del removes · Ctrl+D duplicates</p></div>
</aside>
</div>
</div>

<div class="modal" id="galleryModal"><div class="mbox">
  <div class="mhead"><div><h2>Sample designs</h2><div class="help">Start from a complete house, then make it your own.</div></div><button id="closeGallery">✕ Close</button></div>
  <div class="gallery" id="gallery"></div>
</div></div>

<!-- Blueprint upload modal -->
<div class="modal" id="bpModal"><div class="mbox" style="width:min(560px,100%)">
  <div class="mhead"><div><h2>Upload Blueprint</h2><div class="help">Choose the type and configure the blueprint overlay.</div></div><button id="closeBpModal">✕ Close</button></div>
  <div style="margin-bottom:14px">
    <div class="help" style="margin-bottom:8px">Blueprint type:</div>
    <div class="chips">
      <button id="bpTypeHouse" class="on" data-bptype="house">🏠 House plan</button>
      <button id="bpTypeLot" class="on" data-bptype="lot" style="background:transparent;border-color:var(--line2);color:var(--text)">🗺 Lot / site plan</button>
    </div>
  </div>
  <div class="field" id="bpFloorField"><label for="bpFloor">Floor this plan belongs to</label><select id="bpFloor"></select></div>
  <div id="bpPreviewWrap" style="text-align:center;margin-bottom:14px;background:#0b1118;border-radius:12px;padding:16px;border:1px solid var(--line)">
    <img id="bpPreviewImg" src="" alt="Blueprint preview" style="max-width:100%;max-height:300px;border-radius:8px;display:none">
    <div id="bpPreviewEmpty" class="empty">No image selected yet.</div>
  </div>
  <div class="field" id="pdfPicker" hidden><label for="pdfPage">PDF page</label><select id="pdfPage"></select></div>
  <div class="help" id="batchStatus"></div>
  <button id="calibrateBp">Calibrate: click two points on image</button>
  <div class="row"><div class="field"><label for="realDistance">Real distance (m)</label><input id="realDistance" type="number" min="0.01" step="any"></div><button id="applyCalibration">Set scale</button></div>
  <div id="calibrationStatus" class="help">Uncalibrated. Scale slider is a visual adjustment only.</div>
  <div class="help">Lot: calibrated image extent sets a rectangular boundary and plot size. For irregular lots, mark boundary vertices on the image.</div>
  <button id="markBoundary">Mark lot boundary</button><button id="clearBoundary">Use image rectangle</button>
  <div class="field"><label>Blueprint name</label><input type="text" id="bpNameInput" maxlength="60" placeholder="e.g. Ground floor plan"></div>
  <div class="row">
    <div class="field"><label>Opacity <output id="bpOpOut">50%</output></label><input type="range" id="bpOpacity" min="5" max="100" step="5" value="50"></div>
    <div class="field"><label>Scale <output id="bpScOut">1.0×</output></label><input type="range" id="bpScale" min="0.1" max="5" step="0.1" value="1"></div>
  </div>
  <div class="row">
    <div class="field"><label>Offset X <output id="bpOxOut">0 m</output></label><input type="range" id="bpOffsetX" min="-30" max="30" step="0.5" value="0"></div>
    <div class="field"><label>Offset Z <output id="bpOzOut">0 m</output></label><input type="range" id="bpOffsetZ" min="-30" max="30" step="0.5" value="0"></div>
  </div>
  <div class="field"><label>Rotation <output id="bpRotOut">0°</output></label><input type="range" id="bpRotation" min="0" max="360" step="1" value="0"></div>
  <button id="bpConfirmBtn" class="primary" style="width:100%;margin-top:8px" disabled>Add blueprint to project</button>
</div></div>

<!-- Confirm a rule-based concept after adding a blueprint -->
<div class="modal" id="designSuggestModal" role="dialog" aria-modal="true" aria-labelledby="suggestTitle">
 <div class="mbox" style="width:min(940px,100%)">
  <div class="mhead"><div><h2 id="suggestTitle">Suggested design for your blueprint</h2><div class="help" id="suggestBlueprintName"></div></div><button id="suggestClose" aria-label="Keep current design and close">✕ Close</button></div>
  <p class="suggest-intro" id="suggestReason"></p>
  <div class="suggest-grid">
   <div class="suggest-card"><div class="suggest-card-title"><span>Current design</span><span class="chip">Keep</span></div><canvas id="currentDesignPreview" width="520" height="320" aria-label="Current floor plan preview"></canvas><div id="currentDesignMeta" class="suggest-meta"></div></div>
   <div class="suggest-card proposed"><div class="suggest-card-title"><span id="suggestDesignName">Suggested layout</span><span class="chip">Concept</span></div><canvas id="suggestedDesignPreview" width="520" height="320" aria-label="Suggested floor plan preview"></canvas><div id="suggestDesignMeta" class="suggest-meta"></div></div>
  </div>
  <div class="suggest-note">Conceptual suggestion only: this uses the blueprint image's proportions and visual detail plus the plot size to adapt a starter layout. It does <strong>not</strong> identify exact rooms, read dimensions, or reproduce structural plans. Check all dimensions, wall positions, and applicable building requirements with a professional. The rule selects one of three starter templates using image aspect ratio and line density, then fits it to the plot. Applying saves the current design and the suggestion as separate restorable versions.</div>
  <div class="suggest-actions"><button id="suggestKeep">Keep current design</button><button id="suggestUse" class="primary">Use suggested design</button></div>
 </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js"></script>
<dialog id="reviewSubmissionDialog"><div class="guide-heading"><h2>Submit for human review</h2><button type="button" id="closeReviewSubmission" aria-label="Close review form">×</button></div><form action="submit-project.php" method="post" id="reviewSubmissionForm"><input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>"><input type="hidden" name="snapshot" id="reviewSnapshot"><label>Project name<input id="reviewName" name="name" maxlength="100" required></label><label>Building type<select name="kind"><option>House</option><option>Commercial</option><option>Mixed use</option></select></label><label>Review request<textarea name="request" maxlength="2000" rows="4" placeholder="What would you like your reviewer to focus on?"></textarea></label><p>This shares a saved snapshot and its blueprint images with Admins and your assigned reviewer. Later changes remain in your studio until you submit again.</p><p id="reviewSubmissionStatus" role="status"></p><button class="primary" id="confirmReviewSubmission">Submit for review</button></form></dialog>
<script>
(()=>{'use strict';
const $=id=>document.getElementById(id);
const clamp=(v,a,b)=>Math.max(a,Math.min(b,v));
const lerp=(a,b,t)=>a+(b-a)*t;
const sm=t=>t*t*(3-2*t);
const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const r1=n=>Math.round(n*100)/100;
const projectStorageKey = <?= json_encode('archispace-v3-user-' . $currentUser['public_id']) ?>;
const csrf = <?= json_encode($_SESSION['csrf']) ?>;
const T=.15, FLOOR=.14;
const hx=h=>[parseInt(h.slice(1,3),16),parseInt(h.slice(3,5),16),parseInt(h.slice(5,7),16)];
const toHex=a=>'#'+a.map(v=>clamp(Math.round(v),0,255).toString(16).padStart(2,'0')).join('');
const mixA=(a,b,t)=>a.map((v,i)=>lerp(v,b[i],t));
const shade=(h,f)=>{const a=hx(h);return toHex(f<1?a.map(v=>v*f):a.map(v=>v+(255-v)*(f-1)))};
const numHex=n=>'#'+Number(n).toString(16).padStart(6,'0');

/* ---------- catalogue ---------- */
const ROOMS={'Bedroom':{w:4,d:4,c:'#7ea6d8'},'Living room':{w:5.5,d:4.5,c:'#6fd0be'},'Kitchen':{w:3.5,d:3.5,c:'#ecbd6a'},'Bathroom':{w:2.5,d:2.5,c:'#bba4e6'},'Garage':{w:5.5,d:6,c:'#98a8bc'},'Dining':{w:4,d:3.5,c:'#e49a86'},'Office':{w:3,d:3,c:'#9ccb86'},'Hallway':{w:1.6,d:4,c:'#c4bfb0'},'Custom':{w:3.5,d:3.5,c:'#8fb0c9'}};
const CAT={
 Door:{g:'Openings',w:.9,d:.2,h:2.1,e:0,c:'#8a6a4a'},
 Window:{g:'Openings',w:1.2,d:.2,h:1.1,e:.9,c:'#9fd8f0'},
 Bed:{g:'Bedroom',w:1.6,d:2,h:.6,e:0,c:'#8aa4c4'},
 Wardrobe:{g:'Bedroom',w:1.2,d:.6,h:2,e:0,c:'#b89b7a'},
 Desk:{g:'Bedroom',w:1.4,d:.7,h:.75,e:0,c:'#a7845e'},
 Sofa:{g:'Living',w:2,d:.9,h:.85,e:0,c:'#5f8bb8'},
 Table:{g:'Living',w:1.4,d:.9,h:.75,e:0,c:'#a0764f'},
 Chair:{g:'Living',w:.5,d:.5,h:.9,e:0,c:'#c79a6b'},
 Rug:{g:'Living',w:2.4,d:1.6,h:.02,e:0,c:'#b86b6b'},
 Plant:{g:'Living',w:.5,d:.5,h:1.1,e:0,c:'#4f9a62'},
 Toilet:{g:'Kitchen & bath',w:.4,d:.7,h:.8,e:0,c:'#f2f4f6'},
 Sink:{g:'Kitchen & bath',w:.6,d:.5,h:.9,e:0,c:'#d9dfe5'},
 Bathtub:{g:'Kitchen & bath',w:.8,d:1.7,h:.55,e:0,c:'#f5f7f8'},
 Counter:{g:'Kitchen & bath',w:2.4,d:.65,h:.9,e:0,c:'#e8e4dc'},
 Fridge:{g:'Kitchen & bath',w:.7,d:.7,h:1.8,e:0,c:'#d6dde4'},
 Tree:{g:'Outdoor',w:2,d:2,h:4.5,e:0,c:'#3f8f5a',out:1},
 Bush:{g:'Outdoor',w:1.1,d:1.1,h:.8,e:0,c:'#4c8f4a',out:1},
 Car:{g:'Outdoor',w:1.9,d:4.4,h:1.45,e:0,c:'#9b3d3d',out:1}
};
const isOpening=t=>t==='Door'||t==='Window';
const PAL={grass:[[72,116,62],[132,170,92]],sand:[[190,168,120],[226,208,156]],stone:[[100,108,118],[158,166,176]],soil:[[110,82,58],[160,126,92]],snow:[[198,208,222],[247,251,255]]};

/* ---------- state ---------- */
function blank(){return{name:'Untitled house',site:{width:26,depth:28,ground:0,hills:.8,scale:1,seed:3,material:'grass',floorRaise:0,wall:2.8,roof:'off',wallColor:'#ebe6dc',roofColor:'#41546d',kind:'House'},floors:[{h:null}],river:{pts:[],width:3,depth:.5},env:{hour:12.5,weather:'clear',flood:.6,quake:'moderate',sim:false},rooms:[],objects:[],blueprints:[],versions:[],hazard:null,location:null,nextId:1,nextBpId:1}}
let S=blank();
let sel=null,view='3d',showGrid=true,snapOn=true,showLabels=true,riverMode=false,playMode=false,drag=null,nudgeTimer=null,quakeUntil=0,flash=0,lastT=0,planCache=null;
// Each view starts with its own default roof visibility: 3D shows it; Top hides it.
// The user can override the current view, and the override resets on view change.
let roofOverride=null;
const roofMeshes=[];
const bpImages=new Map(); // id -> HTMLImageElement (runtime cache, not serialized)
let renderer,scene,camera,world,sun,moon,hemi,stars,rain,stage,stageGrid,selBox,floodMesh,raycaster,skyTex=null,baseHemi=1;
const glassMats=new Set();let matCache=new Map();
const cam={tx:0,ty:0,tz:0,dist:40,theta:.75,phi:1};
const play={x:0,z:0,yaw:0,pitch:0,y:0};const keys={};
const wrap=$('canvasWrap'),cv3=$('view3d'),plan=$('plan2d'),pctx=plan.getContext('2d');
const getItem=s=>s&&(s.kind==='room'?S.rooms:S.objects).find(i=>i.id===s.id)||null;
const G=.25;

/* ---------- history ---------- */
let hist=[],hi=-1;
function commit(){renderReadiness();const j=JSON.stringify(S);if(hist[hi]===j)return;hist=hist.slice(0,hi+1);hist.push(j);if(hist.length>80)hist.shift();hi=hist.length-1;try{localStorage.setItem(projectStorageKey,JSON.stringify({application:'ARCHIspace',version:'3.0',state:S}))}catch(e){}updateUndo()}
function restore(j){S=JSON.parse(j);bpImages.clear();if(sel&&!getItem(sel))sel=null;syncUI();rebuild();renderPanels()}
function undo(){if(hi>0){hi--;restore(hist[hi]);updateUndo()}}
function redo(){if(hi<hist.length-1){hi++;restore(hist[hi]);updateUndo()}}
function updateUndo(){$('undoBtn').disabled=hi<=0;$('redoBtn').disabled=hi>=hist.length-1}

/* ---------- terrain ---------- */
function baseH(x,z){const s=S.site,f=.28*s.scale,p=s.seed*1.7;return s.ground+s.hills*(.5*Math.sin(x*f+p)*Math.cos(z*f*.9+p*.6)+.3*Math.sin((x+z)*f*.55+p*1.3)+.2*Math.sin(x*f*1.9-z*f*1.3+p*2.1))}
function segDist(px,pz,ax,az,bx,bz){const dx=bx-ax,dz=bz-az,l2=dx*dx+dz*dz;let t=l2?((px-ax)*dx+(pz-az)*dz)/l2:0;t=clamp(t,0,1);return Math.hypot(px-(ax+t*dx),pz-(az+t*dz))}
function riverDist(x,z){const p=S.river.pts;let b=1e9;for(let i=1;i<p.length;i++){const d=segDist(x,z,p[i-1].x,p[i-1].z,p[i].x,p[i].z);if(d<b)b=d}return b}
function terrainH(x,z){let h=baseH(x,z);if(S.river.pts.length>1){const rw=S.river.width/2,bank=rw+2.2+S.river.depth*2.5,d=riverDist(x,z);if(d<bank){const t=d<=rw?1:1-sm((d-rw)/(bank-rw));h=lerp(h,S.site.ground-S.river.depth,t)}}return h}
function terrainColor(x,z,h){const pal=PAL[S.site.material]||PAL.grass;let t=clamp((h-S.site.ground+S.site.hills)/(S.site.hills*2+.01),0,1);t=clamp(t+Math.sin(x*3.1+z*2.3)*Math.sin(x*1.7-z*2.9)*.07,0,1);let c=mixA(pal[0],pal[1],t);if(S.river.pts.length>1){const rw=S.river.width/2,d=riverDist(x,z);if(d<rw+.4)c=[48,112,150];else if(d<rw+1.6)c=mixA(c,[198,180,132],.65*(1-(d-rw-.4)/1.2))}return c}
function roomSamples(r){const a=[];for(const sx of[-1,0,1])for(const sz of[-1,0,1])a.push(terrainH(r.x+sx*r.w/2,r.z+sz*r.d/2));return a}
const roomPad=r=>Math.max(...roomSamples(r))+(Number(S.site.floorRaise)||0);
function inRoom(r,x,z,m){return Math.abs(x-r.x)<=r.w/2+m&&Math.abs(z-r.z)<=r.d/2+m}
/* ---------- storeys ---------- */
// Floors are numbered from 0 in data and shown as Floor 1, 2, 3… to users.
const MAX_FLOORS=30,MAX_ROOMS=500;
let curFloor=0,datumCache=null;
const lvl=it=>clamp(Math.round(Number(it&&it.level)||0),0,MAX_FLOORS-1);
const onFloor=(it,f)=>lvl(it)===f;
const floorName=i=>'Floor '+(i+1);
function kindHeight(kind,i){return kind==='Commercial'?(i===0?4.5:3.5):kind==='Mixed use'?(i===0?4.5:2.8):null}
function floorH(i){const h=Number(S.floors?.[i]?.h);return Number.isFinite(h)&&h>0?clamp(h,2.2,6):S.site.wall}
function stackH(L){let t=0;for(let i=0;i<L;i++)t+=floorH(i)+FLOOR;return t}
function ensureFloors(){if(!Array.isArray(S.floors)||!S.floors.length)S.floors=[{h:null}];S.floors=S.floors.slice(0,MAX_FLOORS).map(f=>({h:Number.isFinite(Number(f&&f.h))&&Number(f.h)>0?clamp(Number(f.h),2.2,6):null}));const top=Math.max(0,...S.rooms.map(lvl),...S.objects.map(lvl),...(S.blueprints||[]).map(lvl));while(S.floors.length<=top&&S.floors.length<MAX_FLOORS)S.floors.push({h:kindHeight(S.site.kind,S.floors.length)});curFloor=clamp(curFloor,0,S.floors.length-1)}
// Upper floors share one datum: the highest finished-floor pad on Floor 1.
function groundDatum(){if(datumCache!==null)return datumCache;const g=S.rooms.filter(r=>lvl(r)===0);datumCache=g.length?Math.max(...g.map(roomPad)):(Number(S.site.floorRaise)||0)+Math.max(S.site.ground,...S.rooms.map(r=>Math.max(...roomSamples(r))));return datumCache}
function roomBase(r){const L=lvl(r);return L?groundDatum()+stackH(L):roomPad(r)}
// 0 = open to the sky, 1 = partly built over, 2 = fully built over (sampled on a 5×5 grid).
function coverAbove(r){const L=lvl(r)+1,up=S.rooms.filter(o=>lvl(o)===L&&Math.abs(o.x-r.x)<(o.w+r.w)/2-.01&&Math.abs(o.z-r.z)<(o.d+r.d)/2-.01);if(!up.length)return 0;for(let i=0;i<5;i++)for(let j=0;j<5;j++){const x=r.x+(i/4-.5)*(r.w-.1),z=r.z+(j/4-.5)*(r.d-.1);if(!up.some(o=>inRoom(o,x,z,.01)))return 1}return 2}
function totalHeight(){return stackH(S.floors.length-1)+floorH(S.floors.length-1)+FLOOR}
function floorTopAt(x,z,m=.12,L=0){let best=null;for(const r of S.rooms)if(lvl(r)===L&&inRoom(r,x,z,m)){const v=roomBase(r)+FLOOR;if(best===null||v>best)best=v}if(best!==null)return best;return L?groundDatum()+stackH(L)+FLOOR:terrainH(x,z)}

/* ---------- three.js ---------- */
function mat(color,o={}){const k=color+JSON.stringify(o);let m=matCache.get(k);if(!m){m=new THREE.MeshStandardMaterial({color,roughness:o.r??.75,metalness:o.m??0,transparent:o.t!==undefined,opacity:o.t??1,flatShading:!!o.flat});if(o.glass){m.emissive=new THREE.Color(0xffd48a);m.emissiveIntensity=0;glassMats.add(m)}matCache.set(k,m)}return m}
function init3D(){
 if(!window.THREE){toast('3D engine could not load — check your internet connection');return}
 renderer=new THREE.WebGLRenderer({canvas:cv3,antialias:true});renderer.setPixelRatio(Math.min(devicePixelRatio||1,2));renderer.shadowMap.enabled=true;renderer.shadowMap.type=THREE.PCFSoftShadowMap;renderer.outputColorSpace=THREE.SRGBColorSpace;renderer.toneMapping=THREE.ACESFilmicToneMapping;renderer.toneMappingExposure=1.05;
 scene=new THREE.Scene();scene.fog=new THREE.Fog(0x9cc4e6,70,230);
 camera=new THREE.PerspectiveCamera(46,1,.1,500);
 hemi=new THREE.HemisphereLight(0xcfe6ff,0x40513a,1.6);scene.add(hemi);
 sun=new THREE.DirectionalLight(0xfff0d5,3);sun.castShadow=true;sun.shadow.mapSize.set(2048,2048);sun.shadow.bias=-.0004;sun.shadow.normalBias=.05;scene.add(sun,sun.target);
 moon=new THREE.DirectionalLight(0x8fb0ff,.4);moon.position.set(-20,30,18);scene.add(moon);
 const sg=new THREE.BufferGeometry(),sp=new Float32Array(900);for(let i=0;i<300;i++){const a=Math.random()*Math.PI*2,e=Math.random()*1.2+.15,rr=250;sp[i*3]=Math.cos(a)*Math.cos(e)*rr;sp[i*3+1]=Math.sin(e)*rr;sp[i*3+2]=Math.sin(a)*Math.cos(e)*rr}sg.setAttribute('position',new THREE.BufferAttribute(sp,3));
 stars=new THREE.Points(sg,new THREE.PointsMaterial({color:0xffffff,size:1.6,sizeAttenuation:false,transparent:true,opacity:0,fog:false}));scene.add(stars);
 stage=new THREE.Mesh(new THREE.CircleGeometry(160,64),new THREE.MeshStandardMaterial({color:0x101926,roughness:1}));stage.rotation.x=-Math.PI/2;stage.receiveShadow=true;scene.add(stage);
 stageGrid=new THREE.GridHelper(300,150,0x25364c,0x172334);scene.add(stageGrid);
 const rg=new THREE.BufferGeometry(),rp=new Float32Array(1400*3);for(let i=0;i<1400;i++){rp[i*3]=(Math.random()-.5)*90;rp[i*3+1]=Math.random()*30;rp[i*3+2]=(Math.random()-.5)*90}rg.setAttribute('position',new THREE.BufferAttribute(rp,3));
 rain=new THREE.Points(rg,new THREE.PointsMaterial({color:0xaed4ff,size:.09,transparent:true,opacity:.7}));rain.visible=false;scene.add(rain);
 world=new THREE.Group();scene.add(world);raycaster=new THREE.Raycaster();
 resize();fitCamera();rebuild();animate(0)}
function clearWorld(){if(!world)return;world.traverse(n=>{if(n.geometry)n.geometry.dispose();if(n.isSprite){n.material.map?.dispose();n.material.dispose()}});matCache.forEach(m=>m.dispose());matCache.clear();glassMats.clear();roofMeshes.length=0;world.clear();floodMesh=null;if(selBox){scene.remove(selBox);selBox.geometry.dispose();selBox=null}}
function makeLabel(text){const c=document.createElement('canvas');c.width=512;c.height=96;const x=c.getContext('2d');x.fillStyle='rgba(10,16,24,.82)';x.beginPath();x.roundRect?x.roundRect(6,10,500,76,38):x.rect(6,10,500,76);x.fill();x.strokeStyle='#f2b84b';x.lineWidth=3;x.stroke();x.fillStyle='#f4f7fb';x.font='600 36px Inter,system-ui,sans-serif';x.textAlign='center';x.textBaseline='middle';x.fillText(text,256,50,470);const tex=new THREE.CanvasTexture(c);tex.colorSpace=THREE.SRGBColorSpace;const s=new THREE.Sprite(new THREE.SpriteMaterial({map:tex,transparent:true,depthTest:false,fog:false}));s.scale.set(3.4,.64,1);s.renderOrder=10;return s}
function rebuild(){
 planCache=null;datumCache=null;ensureFloors();updateSummary();renderFloorUI();if(!scene){updateRoofVisibility();draw2D();return}
 clearWorld();buildTerrain();buildRiver();buildLotBoundary3D();S.rooms.forEach(buildRoom);S.objects.forEach(buildObject);buildFlood();buildComparison();
 updateRoofVisibility();
 const m=Math.max(S.site.width,S.site.depth)*.8;const c=sun.shadow.camera;c.left=-m;c.right=m;c.top=m;c.bottom=-m;c.near=1;c.far=160;c.updateProjectionMatrix();
 const base=baseY();stage.position.y=base-.05;stageGrid.position.y=base-.04;
 applyEnv();applySelection3D();draw2D()}
const baseY=()=>S.site.ground-S.site.hills-S.river.depth-1.4;
function buildTerrain(){
 const sw=S.site.width,sd=S.site.depth,nx=clamp(Math.round(sw*2),20,100),nz=clamp(Math.round(sd*2),20,100);
 const geo=new THREE.PlaneGeometry(sw,sd,nx,nz);geo.rotateX(-Math.PI/2);const pos=geo.attributes.position,col=new Float32Array(pos.count*3),tc=new THREE.Color();
 for(let i=0;i<pos.count;i++){const x=pos.getX(i),z=pos.getZ(i),h=terrainH(x,z);pos.setY(i,h);const c=terrainColor(x,z,h);tc.setRGB(c[0]/255,c[1]/255,c[2]/255,THREE.SRGBColorSpace);col[i*3]=tc.r;col[i*3+1]=tc.g;col[i*3+2]=tc.b}
 geo.setAttribute('color',new THREE.BufferAttribute(col,3));geo.computeVertexNormals();
 const m=new THREE.Mesh(geo,new THREE.MeshStandardMaterial({vertexColors:true,roughness:1}));m.receiveShadow=true;m.castShadow=true;world.add(m);
 const by=baseY(),pts=[],edge=(ax,az,bx,bz)=>{const n=Math.max(8,Math.round(Math.hypot(bx-ax,bz-az)*2));for(let i=0;i<n;i++)pts.push([lerp(ax,bx,i/n),lerp(az,bz,i/n),lerp(ax,bx,(i+1)/n),lerp(az,bz,(i+1)/n)])};
 edge(-sw/2,-sd/2,sw/2,-sd/2);edge(sw/2,-sd/2,sw/2,sd/2);edge(sw/2,sd/2,-sw/2,sd/2);edge(-sw/2,sd/2,-sw/2,-sd/2);
 const p=[];pts.forEach(([x0,z0,x1,z1])=>{const y0=terrainH(x0,z0),y1=terrainH(x1,z1);p.push(x0,y0,z0,x1,y1,z1,x0,by,z0,x1,y1,z1,x1,by,z1,x0,by,z0)});
 const sg=new THREE.BufferGeometry();sg.setAttribute('position',new THREE.BufferAttribute(new Float32Array(p),3));sg.computeVertexNormals();
 world.add(new THREE.Mesh(sg,new THREE.MeshStandardMaterial({color:0x54402f,roughness:1,side:THREE.DoubleSide})));
 const bp=new THREE.Mesh(new THREE.PlaneGeometry(sw,sd),new THREE.MeshStandardMaterial({color:0x2b2118,roughness:1}));bp.rotation.x=-Math.PI/2;bp.position.y=by;world.add(bp)}
function buildRiver(){
 const pts=S.river.pts;if(pts.length<2)return;const hw=S.river.width/2+.3,y=S.site.ground-S.river.depth*.4,v=[],idx=[];
 for(let i=0;i<pts.length;i++){const a=pts[Math.max(0,i-1)],b=pts[Math.min(pts.length-1,i+1)];let tx=b.x-a.x,tz=b.z-a.z;const l=Math.hypot(tx,tz)||1;tx/=l;tz/=l;const nx=-tz,nz=tx;v.push(pts[i].x+nx*hw,y,pts[i].z+nz*hw,pts[i].x-nx*hw,y,pts[i].z-nz*hw);if(i>0){const k=(i-1)*2;idx.push(k,k+2,k+1,k+1,k+2,k+3)}}
 const g=new THREE.BufferGeometry();g.setAttribute('position',new THREE.BufferAttribute(new Float32Array(v),3));g.setIndex(idx);g.computeVertexNormals();
 world.add(new THREE.Mesh(g,new THREE.MeshStandardMaterial({color:0x2f93d1,roughness:.12,metalness:.25,transparent:true,opacity:.86,side:THREE.DoubleSide})))}
function buildFlood(){floodMesh=null;if(!S.env.sim||S.env.flood<=0)return;const m=new THREE.Mesh(new THREE.PlaneGeometry(S.site.width,S.site.depth),new THREE.MeshStandardMaterial({color:0x2f8fd0,transparent:true,opacity:.42,roughness:.1,metalness:.2,side:THREE.DoubleSide}));m.rotation.x=-Math.PI/2;m.position.y=S.site.ground+S.env.flood;world.add(m);floodMesh=m}
function openingsOnWall(r,side){const out=[];const hz=side.horiz;for(const o of S.objects){if(!isOpening(o.type)||lvl(o)!==lvl(r))continue;const orient=Math.round(((o.rot%180)+180)%180)===0;if(orient!==hz)continue;let u=null;if(hz){if(Math.abs(o.z-(r.z+side.off))<.35&&Math.abs(o.x-r.x)<=r.w/2)u=o.x-r.x}else{if(Math.abs(o.x-(r.x+side.off))<.35&&Math.abs(o.z-r.z)<=r.d/2)u=-(o.z-r.z)}if(u!==null)out.push({u,w:o.w,e:o.e||0,h:o.h})}return out.sort((a,b)=>a.u-b.u)}
function buildRoom(r){
 const g=new THREE.Group();g.userData={kind:'room',id:r.id};const L=lvl(r),pad=roomBase(r),wh=floorH(L);g.position.set(r.x,pad,r.z);
 if(!L){const low=Math.min(...roomSamples(r)),fh=Math.max(.25,pad-low+.35),fnd=new THREE.Mesh(new THREE.BoxGeometry(r.w+.3,fh,r.d+.3),mat('#8a8f96',{r:.95}));fnd.position.y=-fh/2;fnd.castShadow=fnd.receiveShadow=true;g.add(fnd)}
 const covered=coverAbove(r);
 const fl=new THREE.Mesh(new THREE.BoxGeometry(r.w,FLOOR,r.d),mat(shade(r.c,.78),{r:.55}));fl.position.y=FLOOR/2;fl.receiveShadow=true;fl.castShadow=true;g.add(fl);
 const wm=mat(S.site.wallColor,{r:.9});
 const sides=[{horiz:true,off:-r.d/2,L:r.w+T},{horiz:true,off:r.d/2,L:r.w+T},{horiz:false,off:-r.w/2,L:r.d-T},{horiz:false,off:r.w/2,L:r.d-T}];
 for(const sd of sides){const L=sd.L,sh=new THREE.Shape();sh.moveTo(-L/2,0);sh.lineTo(L/2,0);sh.lineTo(L/2,wh);sh.lineTo(-L/2,wh);sh.closePath();
  let last=-L/2+.05;for(const op of openingsOnWall(r,sd)){const u0=clamp(op.u-op.w/2,-L/2+.1,L/2-.1),u1=clamp(op.u+op.w/2,-L/2+.1,L/2-.1),y0=clamp(op.e,.02,wh-.3),y1=Math.min(wh-.05,y0+op.h);if(u1-u0<.2||y1-y0<.2||u0<last+.05)continue;last=u1;const h=new THREE.Path();h.moveTo(u0,y0);h.lineTo(u1,y0);h.lineTo(u1,y1);h.lineTo(u0,y1);h.closePath();sh.holes.push(h)}
  const geo=new THREE.ExtrudeGeometry(sh,{depth:T,bevelEnabled:false});geo.translate(0,0,-T/2);const m=new THREE.Mesh(geo,wm);m.castShadow=m.receiveShadow=true;m.position.set(sd.horiz?0:sd.off,FLOOR,sd.horiz?sd.off:0);m.rotation.y=sd.horiz?0:Math.PI/2;g.add(m)}
 if(covered===2){}
 // Partly built over: a flat slab (terrace) instead of a roof, flush with the floor above.
 else if(covered===1){if(S.site.roof!=='off'){const m=new THREE.Mesh(new THREE.BoxGeometry(r.w+.15,FLOOR-.01,r.d+.15),mat(S.site.roofColor,{r:.8}));m.position.y=FLOOR+wh+(FLOOR-.01)/2;m.castShadow=m.receiveShadow=true;m.userData.isRoof=true;roofMeshes.push(m);g.add(m)}}
 else if(S.site.roof==='flat'){const m=new THREE.Mesh(new THREE.BoxGeometry(r.w+.5,.2,r.d+.5),mat(S.site.roofColor,{r:.8}));m.position.y=FLOOR+wh+.1;m.castShadow=m.receiveShadow=true;m.userData.isRoof=true;roofMeshes.push(m);g.add(m)}
 else if(S.site.roof==='gable'){const rx=r.w>=r.d,span=(rx?r.d:r.w)+.7,len=(rx?r.w:r.d)+.7,rise=span*.26,sh=new THREE.Shape();sh.moveTo(-span/2,0);sh.lineTo(span/2,0);sh.lineTo(0,rise);sh.closePath();const geo=new THREE.ExtrudeGeometry(sh,{depth:len,bevelEnabled:false});geo.translate(0,0,-len/2);const m=new THREE.Mesh(geo,mat(S.site.roofColor,{r:.8}));m.rotation.y=rx?Math.PI/2:0;m.position.y=FLOOR+wh;m.castShadow=m.receiveShadow=true;m.userData.isRoof=true;roofMeshes.push(m);g.add(m)}
 world.add(g);
 if(showLabels){const lb=makeLabel(r.name);lb.position.set(r.x,pad+FLOOR+wh+.7,r.z);lb.userData.labelFor=r.id;world.add(lb)}}
function buildModel(o){
 const g=new THREE.Group(),{w,d,h}=o,c=o.color||CAT[o.type].c;
 const box=(bw,bh,bd,x,y,z,col,op)=>{const m=new THREE.Mesh(new THREE.BoxGeometry(bw,bh,bd),mat(col,op));m.position.set(x,y+bh/2,z);m.castShadow=m.receiveShadow=true;g.add(m);return m};
 const cyl=(rt,rb,hh,x,y,z,col,seg=10,op)=>{const m=new THREE.Mesh(new THREE.CylinderGeometry(rt,rb,hh,seg),mat(col,op));m.position.set(x,y+hh/2,z);m.castShadow=m.receiveShadow=true;g.add(m);return m};
 switch(o.type){
 case'Door':{const fw=.07,tt=T+.04;box(fw,h,tt,-w/2+fw/2,0,0,'#6d5238');box(fw,h,tt,w/2-fw/2,0,0,'#6d5238');box(w,fw,tt,0,h-fw,0,'#6d5238');box(w-fw*2,h-fw,.05,0,0,0,c);box(.04,.04,.1,w/2-.16,h*.47,0,'#cfd6dd',{m:.8,r:.3});break}
 case'Window':{const fw=.06,tt=T+.04,y0=o.e||0;box(w,fw,tt,0,y0,0,'#f1f4f7');box(w,fw,tt,0,y0+h-fw,0,'#f1f4f7');box(fw,h,tt,-w/2+fw/2,y0,0,'#f1f4f7');box(fw,h,tt,w/2-fw/2,y0,0,'#f1f4f7');box(fw*.6,h-fw*2,tt*.6,0,y0+fw,0,'#f1f4f7');box(w-fw*2,h-fw*2,.025,0,y0+fw,0,'#a9dcf5',{glass:1,t:.38,r:.05,m:.1});box(w+.14,.05,tt+.12,0,y0-.05,0,'#dfe4ea');break}
 case'Bed':box(w,.22,d,0,.08,0,'#6b5646');box(w-.06,.22,d-.08,0,.3,.02,'#f3f1ec');box(w,h+.35,.08,0,.08,-d/2+.04,'#6b5646');box(w/2-.12,.1,.42,-w/4,.52,-d/2+.38,'#ffffff');box(w/2-.12,.1,.42,w/4,.52,-d/2+.38,'#ffffff');box(w-.04,.06,d*.55,0,.52,d*.17,c);break;
 case'Sofa':box(w,.4,d,0,.1,0,c);box(w-.5,.14,d-.3,0,.5,.1,shade(c,1.15));box(w-.1,h-.35,.25,0,.4,-d/2+.13,c);box(.22,.65,d,-(w/2-.11),.1,0,shade(c,.85));box(.22,.65,d,w/2-.11,.1,0,shade(c,.85));break;
 case'Table':box(w,.06,d,0,h-.06,0,c);for(const sx of[-1,1])for(const sz of[-1,1])box(.07,h-.06,.07,sx*(w/2-.08),0,sz*(d/2-.08),shade(c,.75));break;
 case'Chair':box(w,.05,d,0,.45,0,c);box(w,.45,.05,0,.5,-d/2+.03,c);for(const sx of[-1,1])for(const sz of[-1,1])box(.04,.45,.04,sx*(w/2-.04),0,sz*(d/2-.04),shade(c,.7));break;
 case'Wardrobe':box(w,h,d,0,0,0,c);box(.012,h*.88,.01,0,h*.06,d/2+.003,shade(c,.55));box(.04,.2,.02,-.07,h*.45,d/2+.01,'#2b2b2b');box(.04,.2,.02,.07,h*.45,d/2+.01,'#2b2b2b');break;
 case'Desk':box(w,.05,d,0,h-.05,0,c);box(.04,h-.05,d-.05,-(w/2-.03),0,0,shade(c,.8));box(.04,h-.05,d-.05,w/2-.03,0,0,shade(c,.8));box(.55,.32,.03,0,h+.12,-d/2+.15,'#1d2229');box(.06,.12,.06,0,h,-d/2+.15,'#333');break;
 case'Rug':box(w,h,d,0,0,0,c);break;
 case'Plant':cyl(w*.38,w*.28,.3,0,0,0,'#a1694b');{const m=new THREE.Mesh(new THREE.IcosahedronGeometry(w*.62,1),mat('#3f9457',{flat:1}));m.position.y=.3+(h-.3)*.55;m.scale.y=(h-.3)/(w*1.1);m.castShadow=true;g.add(m)}break;
 case'Toilet':box(w*.85,.4,d*.6,0,0,d*.18,'#f2f4f6');box(w,.45,.2,0,.35,-d/2+.1,'#f2f4f6');box(w*.85,.03,d*.58,0,.4,d*.18,'#e1e6ea');break;
 case'Sink':box(w,h-.1,d,0,0,0,c);box(w+.02,.05,d+.02,0,h-.1,0,'#f0f3f6');box(w*.55,.02,d*.5,0,h-.05,0,'#b9d1e3');cyl(.015,.015,.14,0,h-.05,-d*.3,'#c0c7ce',6);break;
 case'Bathtub':box(w,h,d,0,0,0,c);box(w-.16,.03,d-.16,0,h-.015,0,'#bcd5e8');break;
 case'Counter':box(w,h-.04,d,0,0,0,c);box(w+.02,.04,d+.02,0,h-.04,0,'#2f3a46');for(const x of[-w*.25,w*.05])cyl(.12,.12,.012,x,h,0,'#1b1f24',16);box(w*.22,.012,d*.6,w*.3,h,0,'#9aa7b5',{m:.8,r:.3});break;
 case'Fridge':box(w,h,d,0,0,0,c);box(w-.02,.012,.01,0,h*.66,d/2+.003,'#8c97a3');box(.03,.5,.03,w/2-.08,h*.7,d/2+.02,'#8a949e');box(.03,.3,.03,w/2-.08,h*.3,d/2+.02,'#8a949e');break;
 case'Tree':{const s=w/2;cyl(.1*s,.17*s,h*.38,0,0,0,'#7a5639',8);const tiers=[[.52,.46,.28],[.4,.38,.5],[.28,.32,.7]];tiers.forEach(([rr,hh,yy],i)=>{const m=new THREE.Mesh(new THREE.ConeGeometry(w*rr,h*hh,9),mat(shade(c,1-i*.08),{flat:1,r:.9}));m.position.y=h*yy+h*hh*.35;m.castShadow=true;g.add(m)})}break;
 case'Bush':{const m=new THREE.Mesh(new THREE.IcosahedronGeometry(w/2,1),mat(c,{flat:1,r:.95}));m.scale.y=h/w;m.position.y=h/2;m.castShadow=true;g.add(m)}break;
 case'Car':box(w,.5,d,0,.28,0,c,{m:.5,r:.3});box(w*.86,.46,d*.5,0,.78,-d*.04,shade(c,.9),{m:.4,r:.3});box(w*.82,.34,d*.46,0,.84,-d*.04,'#1b2733',{r:.1,m:.6});for(const sx of[-1,1])for(const sz of[-1,1]){const m=new THREE.Mesh(new THREE.CylinderGeometry(.33,.33,.22,14),mat('#15181c'));m.rotation.z=Math.PI/2;m.position.set(sx*(w/2-.05),.33,sz*(d/2-.85));m.castShadow=true;g.add(m)}break;
 }
 return g}
function buildObject(o){
 const g=buildModel(o);g.userData={kind:'object',id:o.id};g.position.set(o.x,floorTopAt(o.x,o.z,.12,lvl(o)),o.z);g.rotation.y=(o.rot||0)*Math.PI/180;world.add(g)}
function applySelection3D(){if(selBox){scene.remove(selBox);selBox.geometry.dispose();selBox=null}if(!sel||!world)return;const g=world.children.find(c=>c.userData.kind===sel.kind&&c.userData.id===sel.id);if(g){selBox=new THREE.BoxHelper(g,0xf2b84b);scene.add(selBox)}}
function syncLive(kind,it){const g=world.children.find(c=>c.userData.kind===kind&&c.userData.id===it.id);if(!g)return;g.position.x=it.x;g.position.z=it.z;if(kind==='object'){g.position.y=floorTopAt(it.x,it.z,.12,lvl(it));g.rotation.y=it.rot*Math.PI/180}else{const pad=roomBase(it);g.position.y=pad;const lb=world.children.find(c=>c.userData.labelFor===it.id);if(lb)lb.position.set(it.x,pad+FLOOR+floorH(lvl(it))+.7,it.z)}}

/* ---------- environment ---------- */
function applyEnv(){
 if(!scene)return;const h=S.env.hour,w=S.env.weather,e=Math.sin((h-6)/12*Math.PI),day=clamp(e*2.4,0,1),warm=1-clamp(e*3,0,1),dim={clear:1,rain:.55,storm:.33}[w]||1;
 const az=(h-6)/12*Math.PI;sun.position.set(-Math.cos(az)*38,Math.max(e,.05)*38+3,-12);sun.target.position.set(0,0,0);
 sun.color.setRGB(1,lerp(1,.6,warm),lerp(.94,.35,warm));sun.intensity=e>0?(.4+2.9*Math.pow(e,.55))*dim:0;moon.intensity=(1-day)*.55;
 baseHemi=(.28+1.5*day)*(w==='clear'?1:.8);hemi.intensity=baseHemi;hemi.color.setHex(day>.2?0xcfe6ff:0x6f86b5);
 const topN=[5,9,18],horN=[22,36,58],topD=[70,148,222],horD=[190,222,246],topS=[48,60,120],horS=[255,150,100],grey=[100,112,126];
 let top=mixA(topN,topD,day),hor=mixA(horN,horD,day);top=mixA(top,topS,warm*day*.8);hor=mixA(hor,horS,warm*day*.85);
 if(w!=='clear'){const k=w==='storm'?.7:.45;top=mixA(top,mixA(grey,[20,24,32],1-day),k);hor=mixA(hor,mixA(grey,[24,28,36],1-day),k)}
 const c=document.createElement('canvas');c.width=4;c.height=256;const x=c.getContext('2d'),gr=x.createLinearGradient(0,0,0,256);gr.addColorStop(0,toHex(top));gr.addColorStop(.62,toHex(hor));gr.addColorStop(1,toHex(hor));x.fillStyle=gr;x.fillRect(0,0,4,256);
 if(skyTex)skyTex.dispose();skyTex=new THREE.CanvasTexture(c);skyTex.colorSpace=THREE.SRGBColorSpace;scene.background=skyTex;scene.fog.color.setRGB(hor[0]/255,hor[1]/255,hor[2]/255,THREE.SRGBColorSpace);
 stars.material.opacity=(1-day)*(w==='clear'?1:.2);rain.visible=w!=='clear';rain.material.opacity=w==='storm'?.8:.55;
 glassMats.forEach(m=>{m.emissiveIntensity=(1-day)*1.3});updateSim();}
function updateFlood(){if(floodMesh)floodMesh.position.y=S.site.ground+S.env.flood;updateSim()}
function updateSim(){
 $('simBar').style.display=S.env.sim?'block':'none';$('simBtn').textContent=S.env.sim?'■ Stop simulation':'▶ Run simulation';$('simBtn').classList.toggle('primary',!S.env.sim);
 const el=$('simReport');if(!S.env.sim){el.textContent='Simulation is off. This is a visual study only — not a safety certification.';return}
 const lvl=S.site.ground+S.env.flood,wet=S.rooms.filter(r=>roomBase(r)+FLOOR<lvl),h=S.env.hour,hh=String(Math.floor(h)).padStart(2,'0')+':'+String(Math.round((h%1)*60)).padStart(2,'0');
 let t=`<b>Conditions:</b> ${hh}, ${esc(S.env.weather)}.<br><b>Flood plane:</b> +${S.env.flood.toFixed(1)} m above datum. `;
 t+=wet.length?`<span class="bad">${wet.length} room${wet.length>1?'s':''} sit below this level: ${wet.map(r=>esc(r.name)).join(', ')}.</span> Consider raising the finished floor or regrading.`:'<span class="ok">All finished floors sit above the flood plane.</span>';
 t+=`<br><b>Earthquake:</b> ${esc(S.env.quake)} shaking shown visually. <i>This is not an engineering analysis.</i>`;el.innerHTML=t}
function startQuake(){if(S.env.quake!=='none')quakeUntil=performance.now()+5000}

/* ---------- camera ---------- */
function updateCam(){if(!camera||playMode)return;camera.position.set(cam.tx+cam.dist*Math.sin(cam.phi)*Math.sin(cam.theta),cam.ty+cam.dist*Math.cos(cam.phi),cam.tz+cam.dist*Math.sin(cam.phi)*Math.cos(cam.theta));camera.lookAt(cam.tx,cam.ty,cam.tz)}
function fitCamera(top){const tall=S.floors&&S.floors.length>1?totalHeight():0;Object.assign(cam,{tx:0,ty:S.site.ground+1+(top?0:tall*.35),tz:0,dist:Math.max(S.site.width,S.site.depth,tall*1.1)*(top?1.25:1.35),theta:top?0:.75,phi:top?.0001:1.0});updateCam()}
function resize(){const w=wrap.clientWidth,h=wrap.clientHeight;if(renderer&&w&&h){renderer.setSize(w,h,false);camera.aspect=w/h;camera.updateProjectionMatrix()}fit2D();draw2D()}
function animate(t){requestAnimationFrame(animate);if(!renderer)return;const dt=Math.min(.05,(t-lastT)/1000||0);lastT=t;if(view==='2d')return;
 if(playMode)stepPlay(dt);
 if(rain.visible){const a=rain.geometry.attributes.position,sp=S.env.weather==='storm'?26:16;for(let i=0;i<a.count;i++){let y=a.array[i*3+1]-sp*dt;if(y<0)y=30;a.array[i*3+1]=y}a.needsUpdate=true}
 if(S.env.weather==='storm'&&Math.random()<.006)flash=1;if(flash>0){hemi.intensity=baseHemi+flash*4;flash-=dt*5;if(flash<=0)hemi.intensity=baseHemi}
 if(floodMesh)floodMesh.position.y=S.site.ground+S.env.flood+Math.sin(t*.002)*.03;
 if(t<quakeUntil){const a={low:.03,moderate:.08,high:.16}[S.env.quake]||0;world.position.set(Math.sin(t*.09)*a,0,Math.cos(t*.12)*a);world.rotation.z=Math.sin(t*.07)*a*.012}else if(world.position.x||world.position.z){world.position.set(0,0,0);world.rotation.z=0}
 if(selBox&&!playMode){selBox.update();selBox.visible=true}else if(selBox)selBox.visible=false;
 renderer.render(scene,camera)}

/* ---------- play mode ---------- */
function enterPlay(){if(!renderer)return;if(view!=='3d')setView('3d');playMode=true;play.x=0;play.z=Math.min(S.site.depth/2-2,(S.rooms.length?Math.max(...S.rooms.map(r=>r.z+r.d/2)):0)+4);play.yaw=0;play.pitch=0;play.y=floorTopAt(play.x,play.z,.12,0)+1.65;camera.rotation.order='YXZ';$('playBtn').textContent='■ Exit walk';$('viewTitle').textContent='Walk-through';$('hint').textContent='WASD / arrows move · mouse look (click scene to capture) · Shift run · Esc exit';cv3.requestPointerLock?.();toast('Walk-through started')}
function exitPlay(){playMode=false;document.exitPointerLock?.();$('playBtn').textContent='▶ Walk through';updateCam();setView(view)}
function stepPlay(dt){const sp=(keys.shift?6:3)*dt;let f=0,s=0;if(keys.w||keys.arrowup)f++;if(keys.s||keys.arrowdown)f--;if(keys.d||keys.arrowright)s++;if(keys.a||keys.arrowleft)s--;
 const fx=-Math.sin(play.yaw),fz=-Math.cos(play.yaw),rx=Math.cos(play.yaw),rz=-Math.sin(play.yaw);play.x=clamp(play.x+(fx*f+rx*s)*sp,-S.site.width/2+.4,S.site.width/2-.4);play.z=clamp(play.z+(fz*f+rz*s)*sp,-S.site.depth/2+.4,S.site.depth/2-.4);
 play.y=lerp(play.y,floorTopAt(play.x,play.z,0,0)+1.65,Math.min(1,dt*10));camera.position.set(play.x,play.y,play.z);camera.rotation.set(play.pitch,play.yaw,0)}

/* ---------- geometry helpers ---------- */
function clampRoom(r){const sw=S.site.width,sd=S.site.depth;r.w=Math.min(r.w,sw);r.d=Math.min(r.d,sd);r.x=clamp(r.x,-sw/2+r.w/2,sw/2-r.w/2);r.z=clamp(r.z,-sd/2+r.d/2,sd/2-r.d/2)}
function clampRooms(){S.rooms.forEach(clampRoom);S.objects.forEach(o=>{o.x=clamp(o.x,-S.site.width/2+.2,S.site.width/2-.2);o.z=clamp(o.z,-S.site.depth/2+.2,S.site.depth/2-.2)});S.river.pts.forEach(p=>{p.x=clamp(p.x,-S.site.width/2,S.site.width/2);p.z=clamp(p.z,-S.site.depth/2,S.site.depth/2)})}
const objectsInRoom=r=>S.objects.filter(o=>lvl(o)===lvl(r)&&inRoom(r,o.x,o.z,T));
function moveRoomTo(r,x,z){const sw=S.site.width,sd=S.site.depth;x=clamp(x,-sw/2+r.w/2,sw/2-r.w/2);z=clamp(z,-sd/2+r.d/2,sd/2-r.d/2);const dx=x-r.x,dz=z-r.z;if(!dx&&!dz)return[];const at=objectsInRoom(r);at.forEach(o=>{o.x+=dx;o.z+=dz});r.x=x;r.z=z;return at}
function edgeSnap(r,x,z){const th=.35;let bx=th,bz=th,nx=x,nz=z;for(const o of S.rooms){if(o===r||lvl(o)!==lvl(r))continue;if(Math.abs(z-o.z)<(r.d+o.d)/2+th)for(const tx of[o.x+o.w/2+r.w/2,o.x-o.w/2-r.w/2,o.x-o.w/2+r.w/2,o.x+o.w/2-r.w/2]){const dd=Math.abs(tx-x);if(dd<bx){bx=dd;nx=tx}}if(Math.abs(x-o.x)<(r.w+o.w)/2+th)for(const tz of[o.z+o.d/2+r.d/2,o.z-o.d/2-r.d/2,o.z-o.d/2+r.d/2,o.z+o.d/2-r.d/2]){const dd=Math.abs(tz-z);if(dd<bz){bz=dd;nz=tz}}}return[nx,nz]}
function wallSnap(o,x,z){let best=null,bd=.9;for(const r of S.rooms.filter(q=>lvl(q)===lvl(o)))for(const sd of[{h:1,off:-r.d/2,rot:0},{h:1,off:r.d/2,rot:180},{h:0,off:-r.w/2,rot:90},{h:0,off:r.w/2,rot:270}]){let d,px,pz;if(sd.h){const z0=r.z+sd.off,lo=r.x-r.w/2+o.w/2+.1,hi=r.x+r.w/2-o.w/2-.1;if(lo>hi)continue;d=Math.hypot(Math.max(0,Math.abs(x-r.x)-r.w/2),z-z0);px=clamp(Math.round(x*10)/10,lo,hi);pz=z0}else{const x0=r.x+sd.off,lo=r.z-r.d/2+o.w/2+.1,hi=r.z+r.d/2-o.w/2-.1;if(lo>hi)continue;d=Math.hypot(x-x0,Math.max(0,Math.abs(z-r.z)-r.d/2));pz=clamp(Math.round(z*10)/10,lo,hi);px=x0}if(d<bd){bd=d;const keep=Math.round(((o.rot%180)+180)%180)===(sd.h?0:90);best={x:px,z:pz,rot:keep?o.rot:sd.rot}}}return best}
function placeObject(o,x,z){if(isOpening(o.type)){const w=wallSnap(o,x,z);if(w){o.x=w.x;o.z=w.z;o.rot=w.rot;return}}if(snapOn){x=Math.round(x/G)*G;z=Math.round(z/G)*G}o.x=clamp(x,-S.site.width/2+.2,S.site.width/2-.2);o.z=clamp(z,-S.site.depth/2+.2,S.site.depth/2-.2)}
function placeRoom(r,x,z){if(snapOn){x=Math.round((x-r.w/2)/G)*G+r.w/2;z=Math.round((z-r.d/2)/G)*G+r.d/2}[x,z]=edgeSnap(r,x,z);return moveRoomTo(r,x,z)}
function freeSpot(w,d,L=curFloor){const sw=S.site.width,sd=S.site.depth,c=[];for(let z=-sd/2+d/2;z<=sd/2-d/2+.01;z+=.5)for(let x=-sw/2+w/2;x<=sw/2-w/2+.01;x+=.5)c.push([x,z]);c.sort((a,b)=>Math.hypot(a[0],a[1])-Math.hypot(b[0],b[1]));for(const[x,z]of c)if(!S.rooms.some(o=>lvl(o)===L&&Math.abs(o.x-x)<(o.w+w)/2-.01&&Math.abs(o.z-z)<(o.d+d)/2-.01)&&(S.river.pts.length<2||riverDist(x,z)>S.river.width/2+Math.max(w,d)/2+1))return{x:Math.round(x*4)/4,z:Math.round(z*4)/4};return{x:0,z:0}}

/* ---------- add / remove ---------- */
function addRoom(type){if(S.rooms.length>=MAX_ROOMS)return toast('A project can have up to '+MAX_ROOMS+' rooms');const p=ROOMS[type]||ROOMS.Custom,pos=freeSpot(p.w,p.d),r={id:S.nextId++,name:type==='Custom'?'Room':type,w:p.w,d:p.d,c:p.c,x:pos.x,z:pos.z,level:curFloor};S.rooms.push(r);select({kind:'room',id:r.id});rebuild();commit();renderPanels();toast(r.name+' added to '+floorName(curFloor)+' — drag to position, edit size in the inspector')}
function addObject(type){const c=CAT[type],o={id:S.nextId++,type,x:0,z:0,rot:0,w:c.w,d:c.d,h:c.h,e:c.e,level:c.out?0:curFloor};
 const fr=S.rooms.filter(r=>lvl(r)===o.level),selRoom=sel&&sel.kind==='room'&&lvl(getItem(sel))===o.level?getItem(sel):(sel&&getItem(sel)&&lvl(getItem(sel))===o.level?fr.find(r=>inRoom(r,getItem(sel).x,getItem(sel).z,0)):null),room=selRoom||fr[fr.length-1];
 if(isOpening(type)&&room){const side=type==='Door'?{n:'s',z:room.z+room.d/2,rot:180}:{n:'n',z:room.z-room.d/2,rot:0},L=room.w;let px=room.x;for(const off of[0,-1.4,1.4,-2.8,2.8,-4.2,4.2]){const x=room.x+off;if(Math.abs(off)+o.w/2+.2>L/2)continue;if(!S.objects.some(q=>isOpening(q.type)&&lvl(q)===o.level&&Math.abs(q.z-side.z)<.3&&Math.abs(q.x-x)<(q.w+o.w)/2+.1)){px=x;break}}o.x=px;o.z=side.z;o.rot=side.rot}
 else if(c.out||!room){const rw=S.river.width/2;let ok=false;for(let i=0;i<80&&!ok;i++){const x=(Math.random()-.5)*(S.site.width-3),z=(Math.random()-.5)*(S.site.depth-3);if(S.rooms.some(r=>inRoom(r,x,z,1.2)))continue;if(S.river.pts.length>1&&riverDist(x,z)<rw+1.2)continue;o.x=Math.round(x*2)/2;o.z=Math.round(z*2)/2;ok=true}}
 else{let done=false;const rad=Math.max(c.w,c.d)/2;for(let r=0;r<=2&&!done;r+=.5)for(let a=0;a<12&&!done;a++){const x=room.x+Math.cos(a)*r,z=room.z+Math.sin(a)*r;if(Math.abs(x-room.x)>room.w/2-rad-.1||Math.abs(z-room.z)>room.d/2-rad-.1)continue;if(S.objects.some(q=>lvl(q)===o.level&&!isOpening(q.type)&&q.type!=='Rug'&&Math.hypot(q.x-x,q.z-z)<rad+Math.max(q.w,q.d)/2-.1))continue;o.x=Math.round(x*4)/4;o.z=Math.round(z*4)/4;done=true}if(!done){o.x=room.x;o.z=room.z}}
 S.objects.push(o);select({kind:'object',id:o.id});rebuild();commit();renderPanels();toast(type+' added'+(c.out&&curFloor?' at ground level':''))}
function removeSel(){if(!sel)return;if(sel.kind==='room')S.rooms=S.rooms.filter(r=>r.id!==sel.id);else S.objects=S.objects.filter(o=>o.id!==sel.id);sel=null;rebuild();commit();renderPanels()}
function dupSel(){const it=getItem(sel);if(!it)return;if(sel.kind==='room'&&S.rooms.length>=MAX_ROOMS)return toast('A project can have up to '+MAX_ROOMS+' rooms');const n=JSON.parse(JSON.stringify(it));n.id=S.nextId++;n.x+=.75;n.z+=.75;if(sel.kind==='room'){clampRoom(n);S.rooms.push(n)}else S.objects.push(n);select({kind:sel.kind,id:n.id});rebuild();commit();renderPanels()}
function rotSel(){const it=getItem(sel);if(!it||sel.kind==='room'){if(it){[it.w,it.d]=[it.d,it.w];clampRoom(it);rebuild();commit();renderPanels()}return}it.rot=(it.rot+90)%360;rebuild();commit();renderPanels()}
function select(s){sel=s;const it=getItem(s);if(it&&lvl(it)!==curFloor){curFloor=lvl(it);renderFloorUI()}applySelection3D();renderInspector();renderOutliner();draw2D()}

/* ---------- 2D plan ---------- */
const pv={s:10,ox:0,oy:0};
function fit2D(){const W=wrap.clientWidth,H=wrap.clientHeight;pv.s=Math.max(2,Math.min((W-130)/S.site.width,(H-130)/S.site.depth));pv.ox=W/2;pv.oy=H/2}
function toW(e){const r=plan.getBoundingClientRect();return{x:(e.clientX-r.left-pv.ox)/pv.s,z:(e.clientY-r.top-pv.oy)/pv.s}}
function rr(c,x,y,w,h,r){r=Math.min(r,Math.abs(w)/2,Math.abs(h)/2);c.beginPath();c.moveTo(x+r,y);c.arcTo(x+w,y,x+w,y+h,r);c.arcTo(x+w,y+h,x,y+h,r);c.arcTo(x,y+h,x,y,r);c.arcTo(x,y,x+w,y,r);c.closePath()}
function buildPlanCache(){const sw=S.site.width,sd=S.site.depth,res=2,w=Math.round(sw*res),h=Math.round(sd*res),c=document.createElement('canvas');c.width=w;c.height=h;const x=c.getContext('2d'),img=x.createImageData(w,h);
 for(let j=0;j<h;j++)for(let i=0;i<w;i++){const wx=-sw/2+(i+.5)/res,wz=-sd/2+(j+.5)/res,hg=terrainH(wx,wz),sh=clamp((terrainH(wx-.5,wz-.5)-terrainH(wx+.5,wz+.5))*.28,-.3,.3),col=terrainColor(wx,wz,hg),k=(j*w+i)*4;img.data[k]=clamp(col[0]*(1+sh)*.8,0,255);img.data[k+1]=clamp(col[1]*(1+sh)*.8,0,255);img.data[k+2]=clamp(col[2]*(1+sh)*.8,0,255);img.data[k+3]=255}
 x.putImageData(img,0,0);planCache=c}
function sym(ctx,type,w,d,col,lw){const hw=w/2,hd=d/2;ctx.lineWidth=lw||1.2;ctx.strokeStyle='#e4ecf6';ctx.fillStyle=col+'77';ctx.lineJoin='round';
 const R=(x,y,ww,hh,r)=>{rr(ctx,x,y,ww,hh,r??2);ctx.fill();ctx.stroke()},L=(a,b,c,e)=>{ctx.beginPath();ctx.moveTo(a,b);ctx.lineTo(c,e);ctx.stroke()},C=(x,y,r)=>{ctx.beginPath();ctx.arc(x,y,Math.max(.5,r),0,7);ctx.fill();ctx.stroke()};
 switch(type){
 case'Bed':R(-hw,-hd,w,d,3);ctx.fillStyle='#ffffff66';R(-hw+w*.08,-hd+d*.06,w*.38,d*.17,2);R(hw-w*.46,-hd+d*.06,w*.38,d*.17,2);L(-hw,-hd+d*.4,hw,-hd+d*.4);break;
 case'Sofa':R(-hw,-hd,w,d,3);R(-hw,-hd,w,d*.3,3);R(-hw,-hd,w*.1,d,3);R(hw-w*.1,-hd,w*.1,d,3);break;
 case'Table':R(-hw,-hd,w,d,2);ctx.setLineDash([3,3]);R(-hw+w*.1,-hd+d*.12,w*.8,d*.76,1);ctx.setLineDash([]);break;
 case'Chair':R(-hw,-hd,w,d,3);L(-hw,-hd+d*.22,hw,-hd+d*.22);break;
 case'Wardrobe':R(-hw,-hd,w,d,1);L(0,-hd,0,hd);L(-hw,-hd,0,hd);L(hw,-hd,0,hd);break;
 case'Desk':R(-hw,-hd,w,d,1);R(-w*.22,-hd+d*.1,w*.44,d*.16,1);break;
 case'Rug':R(-hw,-hd,w,d,4);ctx.setLineDash([4,3]);R(-hw+w*.07,-hd+d*.1,w*.86,d*.8,3);ctx.setLineDash([]);break;
 case'Plant':C(0,0,Math.min(hw,hd));for(let i=0;i<6;i++){const a=i*Math.PI/3;L(0,0,Math.cos(a)*hw*.9,Math.sin(a)*hd*.9)}break;
 case'Toilet':ctx.beginPath();ctx.ellipse(0,hd*.22,hw*.85,hd*.7,0,0,7);ctx.fill();ctx.stroke();R(-hw*.9,-hd,w*.9,d*.28,1);break;
 case'Sink':R(-hw,-hd,w,d,2);ctx.beginPath();ctx.ellipse(0,0,hw*.55,hd*.5,0,0,7);ctx.stroke();break;
 case'Bathtub':R(-hw,-hd,w,d,Math.min(hw,hd)*.5);R(-hw+w*.1,-hd+d*.05,w*.8,d*.9,Math.min(hw,hd)*.6);C(0,hd*.78,Math.min(hw,hd)*.08);break;
 case'Counter':R(-hw,-hd,w,d,1);C(-w*.25,0,Math.min(hd*.28,w*.06));C(w*.02,0,Math.min(hd*.28,w*.06));R(w*.2,-hd*.55,w*.24,d*.55*1.6,2);break;
 case'Fridge':R(-hw,-hd,w,d,1);L(-hw,-hd+d*.35,hw,-hd+d*.35);L(-hw,hd,hw,-hd);break;
 case'Tree':ctx.fillStyle='#4f9a6299';C(0,0,hw);ctx.fillStyle='#5fb17a66';C(0,0,hw*.62);ctx.fillStyle='#8a5a38';C(0,0,Math.max(1.5,hw*.1));break;
 case'Bush':ctx.fillStyle='#5a9a50aa';for(let i=0;i<7;i++){const a=i*Math.PI*2/7;C(Math.cos(a)*hw*.5,Math.sin(a)*hd*.5,hw*.5)}C(0,0,hw*.55);break;
 case'Car':R(-hw,-hd,w,d,Math.min(hw,hd)*.45);R(-hw*.78,-hd*.5,w*.78,d*.2,3);R(-hw*.78,hd*.35,w*.78,d*.15,3);break;
 case'Door':L(-hw,0,-hw,w);ctx.beginPath();ctx.arc(-hw,0,w,0,Math.PI/2);ctx.stroke();break;
 case'Window':L(-hw,-lw*1.5,hw,-lw*1.5);L(-hw,0,hw,0);L(-hw,lw*1.5,hw,lw*1.5);break;
 default:R(-hw,-hd,w,d,2)}}
function drawIcon(cv,kind,name){const x=cv.getContext('2d'),S0=44,dpr=2;cv.width=S0*dpr;cv.height=S0*dpr;x.scale(dpr,dpr);x.translate(S0/2,S0/2);
 if(kind==='room'){const p=ROOMS[name],k=34/Math.max(p.w,p.d),w=p.w*k,d=p.d*k;x.fillStyle=p.c;rr(x,-w/2,-d/2,w,d,3);x.fill();x.strokeStyle='#f1f4f8';x.lineWidth=2.5;x.stroke();if(name==='Custom'){x.strokeStyle='#0b1118';x.lineWidth=2;x.beginPath();x.moveTo(-6,0);x.lineTo(6,0);x.moveTo(0,-6);x.lineTo(0,6);x.stroke()}return}
 const c=CAT[name];let w=c.w,d=c.d;if(isOpening(name)){x.translate(0,-8);const k=30/w;w*=k;sym(x,name,w,4,c.c,2);return}const k=34/Math.max(w,d,.1);sym(x,name,Math.max(6,w*k),Math.max(6,d*k),c.c,1.4)}
function draw2D(){
 if(view!=='2d')return;const W=wrap.clientWidth,H=wrap.clientHeight,dpr=Math.min(devicePixelRatio||1,2);if(plan.width!==Math.round(W*dpr)||plan.height!==Math.round(H*dpr)){plan.width=Math.round(W*dpr);plan.height=Math.round(H*dpr)}
 const ctx=pctx;ctx.setTransform(dpr,0,0,dpr,0,0);ctx.fillStyle='#f0f2e9';ctx.fillRect(0,0,W,H);
 const s=pv.s,sw=S.site.width,sd=S.site.depth,x0=pv.ox-sw*s/2,y0=pv.oy-sd*s/2;
 if(!planCache)buildPlanCache();ctx.imageSmoothingEnabled=true;ctx.drawImage(planCache,x0,y0,sw*s,sd*s);
 drawBlueprintOverlays(ctx,s);drawLotBoundary(ctx,s);drawGhostPlan(ctx,s);
 if(showGrid){for(let gx=Math.ceil(-sw/2);gx<=Math.floor(sw/2);gx++){const maj=gx%5===0;if(!maj&&s<7)continue;ctx.strokeStyle=maj?'rgba(255,255,255,.2)':'rgba(255,255,255,.07)';ctx.lineWidth=1;const px=pv.ox+gx*s;ctx.beginPath();ctx.moveTo(px,y0);ctx.lineTo(px,y0+sd*s);ctx.stroke()}for(let gz=Math.ceil(-sd/2);gz<=Math.floor(sd/2);gz++){const maj=gz%5===0;if(!maj&&s<7)continue;ctx.strokeStyle=maj?'rgba(255,255,255,.2)':'rgba(255,255,255,.07)';const py=pv.oy+gz*s;ctx.beginPath();ctx.moveTo(x0,py);ctx.lineTo(x0+sw*s,py);ctx.stroke()}}
 ctx.setLineDash([8,5]);ctx.strokeStyle='#3d6652';ctx.lineWidth=1.6;ctx.strokeRect(x0,y0,sw*s,sd*s);ctx.setLineDash([]);
 ctx.fillStyle='#3d6652';ctx.font='500 11px "JetBrains Mono",monospace';ctx.textAlign='center';ctx.fillText(sw.toFixed(1)+' m',pv.ox,y0-10);ctx.save();ctx.translate(x0-12,pv.oy);ctx.rotate(-Math.PI/2);ctx.fillText(sd.toFixed(1)+' m',0,0);ctx.restore();
 const bgGap='#0e151e',fRooms=S.rooms.filter(r=>lvl(r)===curFloor),fObjs=S.objects.filter(o=>lvl(o)===curFloor);
 if(curFloor>0){ctx.save();ctx.setLineDash([5,4]);ctx.strokeStyle='rgba(36,55,47,.75)';ctx.lineWidth=1.5;ctx.fillStyle='rgba(36,55,47,.08)';S.rooms.filter(r=>lvl(r)===curFloor-1).forEach(r=>{const x=pv.ox+(r.x-r.w/2)*s,y=pv.oy+(r.z-r.d/2)*s;ctx.fillRect(x,y,r.w*s,r.d*s);ctx.strokeRect(x,y,r.w*s,r.d*s)});ctx.restore()}
 fRooms.forEach(r=>{const x=pv.ox+(r.x-r.w/2)*s,y=pv.oy+(r.z-r.d/2)*s;ctx.fillStyle=r.c;ctx.fillRect(x,y,r.w*s,r.d*s);ctx.strokeStyle='#f3f6fa';ctx.lineWidth=Math.max(T*s,2.5);ctx.lineJoin='miter';ctx.strokeRect(x,y,r.w*s,r.d*s)});
 fRooms.forEach(r=>{const cx=pv.ox+r.x*s,cy=pv.oy+r.z*s;ctx.fillStyle='#0b1118';ctx.textAlign='center';ctx.font='700 '+clamp(s*.42,10,15)+'px Inter,system-ui,sans-serif';ctx.fillText(r.name,cx,cy-(s>9?-0:0)-Math.min(10,s*.3),Math.max(30,r.w*s-8));ctx.font='500 '+clamp(s*.32,9,12)+'px "JetBrains Mono",monospace';ctx.globalAlpha=.75;ctx.fillText((r.w*r.d).toFixed(1)+' m²',cx,cy+Math.min(10,s*.38)+2,Math.max(30,r.w*s-8));ctx.globalAlpha=1});
 const drawObj=o=>{ctx.save();ctx.translate(pv.ox+o.x*s,pv.oy+o.z*s);ctx.rotate(-(o.rot||0)*Math.PI/180);const w=o.w*s,d=Math.max(o.d*s,2);
  if(isOpening(o.type)){ctx.fillStyle=o.type==='Window'?'#1c2a3a':bgGap;ctx.fillRect(-w/2,-(T*s/2+1),w,T*s+2);sym(ctx,o.type,w,d,o.type==='Door'?'#c08850':'#7ed7ef',Math.max(1,s*.07))}else sym(ctx,o.type,w,d,o.color||CAT[o.type].c,Math.max(1,Math.min(1.8,s*.1)));ctx.restore()};
 fObjs.filter(o=>o.type==='Rug').forEach(drawObj);fObjs.filter(o=>o.type!=='Rug'&&!isOpening(o.type)).forEach(drawObj);fObjs.filter(o=>isOpening(o.type)).forEach(drawObj);
 const it=getItem(sel);
 if(it){ctx.strokeStyle='#3d6652';ctx.lineWidth=2;ctx.setLineDash([6,4]);if(sel.kind==='room'){const x=pv.ox+(it.x-it.w/2)*s,y=pv.oy+(it.z-it.d/2)*s;ctx.strokeRect(x-3,y-3,it.w*s+6,it.d*s+6);ctx.setLineDash([]);ctx.fillStyle='#3d6652';ctx.beginPath();ctx.arc(x+it.w*s,y+it.d*s,7,0,7);ctx.fill();ctx.strokeStyle='#17120a';ctx.lineWidth=1.5;ctx.stroke();
   ctx.strokeStyle='#3d6652';ctx.fillStyle='#3d6652';ctx.lineWidth=1;ctx.font='500 11px "JetBrains Mono",monospace';const dy=y-14;ctx.beginPath();ctx.moveTo(x,dy);ctx.lineTo(x+it.w*s,dy);ctx.moveTo(x,dy-4);ctx.lineTo(x,dy+4);ctx.moveTo(x+it.w*s,dy-4);ctx.lineTo(x+it.w*s,dy+4);ctx.stroke();ctx.textAlign='center';ctx.fillText(it.w.toFixed(2)+' m',x+it.w*s/2,dy-6);const dx=x+it.w*s+16;ctx.beginPath();ctx.moveTo(dx,y);ctx.lineTo(dx,y+it.d*s);ctx.moveTo(dx-4,y);ctx.lineTo(dx+4,y);ctx.moveTo(dx-4,y+it.d*s);ctx.lineTo(dx+4,y+it.d*s);ctx.stroke();ctx.save();ctx.translate(dx+14,y+it.d*s/2);ctx.rotate(Math.PI/2);ctx.fillText(it.d.toFixed(2)+' m',0,0);ctx.restore()}
  else{ctx.save();ctx.translate(pv.ox+it.x*s,pv.oy+it.z*s);ctx.rotate(-(it.rot||0)*Math.PI/180);const w=it.w*s,d=Math.max(it.d*s,10);ctx.strokeRect(-w/2-4,-d/2-4,w+8,d+8);ctx.restore();ctx.setLineDash([]);ctx.fillStyle='#3d6652';ctx.font='500 11px "JetBrains Mono",monospace';ctx.textAlign='left';ctx.fillText(it.type+' '+it.w.toFixed(2)+'×'+it.d.toFixed(2),pv.ox+it.x*s+Math.max(it.w,it.d)*s/2+8,pv.oy+it.z*s-6)}ctx.setLineDash([])}
 if(drag&&drag.mode==='river'&&drag.pts.length>1){ctx.strokeStyle='#3da5e8';ctx.globalAlpha=.8;ctx.lineWidth=Math.max(3,S.river.width*s);ctx.lineCap='round';ctx.lineJoin='round';ctx.beginPath();drag.pts.forEach((p,i)=>{const px=pv.ox+p.x*s,py=pv.oy+p.z*s;i?ctx.lineTo(px,py):ctx.moveTo(px,py)});ctx.stroke();ctx.globalAlpha=1;ctx.lineCap='butt'}
 // compass
 const cx=W-48,cy=56;ctx.fillStyle='#fafbf8e6';ctx.beginPath();ctx.arc(cx,cy,28,0,7);ctx.fill();ctx.strokeStyle='#dde2d9';ctx.lineWidth=1;ctx.stroke();ctx.fillStyle='#3d6652';ctx.beginPath();ctx.moveTo(cx,cy-20);ctx.lineTo(cx+7,cy+8);ctx.lineTo(cx,cy+3);ctx.lineTo(cx-7,cy+8);ctx.closePath();ctx.fill();ctx.fillStyle='#24372f';ctx.font='700 11px Inter,sans-serif';ctx.textAlign='center';ctx.fillText('N',cx,cy+22);
 // scale bar
 let L=1;for(const v of[1,2,5,10,20])if((L=v)*s>=80)break;const bx=W-30-L*s,by=H-26;ctx.fillStyle='#24372f';ctx.fillRect(bx,by,L*s,3);ctx.fillRect(bx,by-4,1.5,11);ctx.fillRect(bx+L*s-1.5,by-4,1.5,11);ctx.font='500 11px "JetBrains Mono",monospace';ctx.textAlign='center';ctx.fillText(L+' m',bx+L*s/2,by-8);
 plan.style.cursor=riverMode?'crosshair':'default'}
function hitPlan(e){const p=toW(e);const pad=8/pv.s;for(let i=S.objects.length-1;i>=0;i--){if(lvl(S.objects[i])!==curFloor)continue;const o=S.objects[i],a=(o.rot||0)*Math.PI/180,dx=p.x-o.x,dz=p.z-o.z,lx=dx*Math.cos(a)-dz*Math.sin(a),lz=dx*Math.sin(a)+dz*Math.cos(a),op=isOpening(o.type);if(Math.abs(lx)<=o.w/2+pad*.4&&Math.abs(lz)<=Math.max(o.d/2,op?.3:0)+pad*.4)return{kind:'object',id:o.id}}for(let i=S.rooms.length-1;i>=0;i--){const r=S.rooms[i];if(lvl(r)!==curFloor)continue;if(Math.abs(p.x-r.x)<=r.w/2&&Math.abs(p.z-r.z)<=r.d/2)return{kind:'room',id:r.id}}return null}
function onHandle(e){const r=sel&&sel.kind==='room'&&getItem(sel);if(!r)return false;const rect=plan.getBoundingClientRect(),hx=pv.ox+(r.x+r.w/2)*pv.s+rect.left,hy=pv.oy+(r.z+r.d/2)*pv.s+rect.top;return Math.hypot(e.clientX-hx,e.clientY-hy)<14}

/* ---------- plan pointer ---------- */
plan.addEventListener('pointerdown',e=>{plan.setPointerCapture(e.pointerId);const p=toW(e);
 if(riverMode&&e.button===0){drag={mode:'river',pts:[{x:clamp(p.x,-S.site.width/2,S.site.width/2),z:clamp(p.z,-S.site.depth/2,S.site.depth/2)}]};return}
 if(e.button===1||e.button===2){drag={mode:'pan',px:e.clientX,py:e.clientY};return}
 if(onHandle(e)){drag={mode:'resize'};return}
 const hit=hitPlan(e);select(hit);
 if(hit){const it=getItem(hit);drag={mode:'move',kind:hit.kind,id:hit.id,ox:p.x-it.x,oz:p.z-it.z,moved:false}}else drag={mode:'pan',px:e.clientX,py:e.clientY}});
plan.addEventListener('pointermove',e=>{const p=toW(e);const c=$('coords');c.style.display='block';c.textContent=`x ${p.x.toFixed(2)}  z ${p.z.toFixed(2)}  elev ${terrainH(p.x,p.z).toFixed(2)} m`;if(!drag)return;
 if(drag.mode==='river'){const l=drag.pts[drag.pts.length-1],q={x:clamp(p.x,-S.site.width/2,S.site.width/2),z:clamp(p.z,-S.site.depth/2,S.site.depth/2)};if(Math.hypot(q.x-l.x,q.z-l.z)>.3)drag.pts.push(q)}
 else if(drag.mode==='pan'){pv.ox+=e.clientX-drag.px;pv.oy+=e.clientY-drag.py;drag.px=e.clientX;drag.py=e.clientY}
 else if(drag.mode==='resize'){const r=getItem(sel);if(r){const sx=r.x-r.w/2,sz=r.z-r.d/2;let nw=p.x-sx,nd=p.z-sz;if(snapOn){nw=Math.round(nw/G)*G;nd=Math.round(nd/G)*G}r.w=clamp(nw,1,S.site.width);r.d=clamp(nd,1,S.site.depth);r.x=sx+r.w/2;r.z=sz+r.d/2;clampRoom(r);drag.moved=true}}
 else if(drag.mode==='move'){const it=getItem({kind:drag.kind,id:drag.id});if(!it)return;drag.moved=true;if(drag.kind==='room')placeRoom(it,p.x-drag.ox,p.z-drag.oz);else placeObject(it,p.x-drag.ox,p.z-drag.oz)}
 draw2D()});
function endDrag(){if(!drag)return;const d=drag;drag=null;
 if(d.mode==='river'){riverMode=false;$('riverBtn').classList.remove('on');$('riverBtn').textContent='〰 Draw river on plan';if(d.pts.length>2){S.river.pts=smoothPath(d.pts);rebuild();commit();toast('River created — adjust width and depth in Site')}else draw2D();return}
 if((d.mode==='move'||d.mode==='resize')&&d.moved){rebuild();commit();renderPanels()}}
plan.addEventListener('pointerup',endDrag);plan.addEventListener('pointercancel',endDrag);plan.addEventListener('contextmenu',e=>e.preventDefault());
plan.addEventListener('wheel',e=>{e.preventDefault();const r=plan.getBoundingClientRect(),mx=e.clientX-r.left,my=e.clientY-r.top,f=e.deltaY>0?.9:1.1,ns=clamp(pv.s*f,2,120),k=ns/pv.s;pv.ox=mx-(mx-pv.ox)*k;pv.oy=my-(my-pv.oy)*k;pv.s=ns;draw2D()},{passive:false});
function smoothPath(p){const out=[p[0]];for(const q of p)if(Math.hypot(q.x-out[out.length-1].x,q.z-out[out.length-1].z)>.7)out.push(q);if(out.length<2)return p;let a=out;for(let it=0;it<2;it++){const b=[a[0]];for(let i=0;i<a.length-1;i++){b.push({x:a[i].x*.75+a[i+1].x*.25,z:a[i].z*.75+a[i+1].z*.25},{x:a[i].x*.25+a[i+1].x*.75,z:a[i].z*.25+a[i+1].z*.75})}b.push(a[a.length-1]);a=b}return a.map(q=>({x:r1(q.x),z:r1(q.z)}))}

/* ---------- 3D pointer ---------- */
const ptrs=new Map();let pinch=0;
function ndc(e){const r=cv3.getBoundingClientRect();return new THREE.Vector2((e.clientX-r.left)/r.width*2-1,-(e.clientY-r.top)/r.height*2+1)}
function pick3D(e){raycaster.setFromCamera(ndc(e),camera);const hits=raycaster.intersectObjects(world.children.filter(c=>c.userData.kind),true);let best=null,bd=1e9;for(const h of hits){let n=h.object;while(n&&!n.userData.kind)n=n.parent;if(!n)continue;const d=h.distance-(n.userData.kind==='object'?.8:0);if(d<bd){bd=d;best={kind:n.userData.kind,id:n.userData.id}}}return best}
function groundPt(e,y){raycaster.setFromCamera(ndc(e),camera);const ry=raycaster.ray;if(Math.abs(ry.direction.y)<1e-4)return null;const t=(y-ry.origin.y)/ry.direction.y;if(t<0)return null;return{x:ry.origin.x+ry.direction.x*t,z:ry.origin.z+ry.direction.z*t}}
cv3.addEventListener('pointerdown',e=>{cv3.setPointerCapture(e.pointerId);ptrs.set(e.pointerId,{x:e.clientX,y:e.clientY});if(ptrs.size===2){const[a,b]=[...ptrs.values()];pinch=Math.hypot(a.x-b.x,a.y-b.y);drag={mode:'pinch'};return}
 if(playMode){if(document.pointerLockElement!==cv3)cv3.requestPointerLock?.();drag={mode:'look'};return}
 const hit=e.button===0?pick3D(e):null;
 if(hit){select(hit);const it=getItem(hit),y=hit.kind==='room'?roomBase(it)+FLOOR:floorTopAt(it.x,it.z,.12,lvl(it)),p=groundPt(e,y);drag={mode:'move',kind:hit.kind,id:hit.id,y,ox:p?p.x-it.x:0,oz:p?p.z-it.z:0,moved:false};toast('Selected '+(hit.kind==='room'?it.name:it.type)+' — drag to move')}
 else{if(e.button===0&&!e.shiftKey)select(null);drag={mode:(e.shiftKey||e.button===2||e.button===1)?'pan':'orbit',px:e.clientX,py:e.clientY}}});
cv3.addEventListener('pointermove',e=>{if(ptrs.has(e.pointerId))ptrs.set(e.pointerId,{x:e.clientX,y:e.clientY});
 if(playMode){if(document.pointerLockElement===cv3||(drag&&drag.mode==='look')){play.yaw-=(e.movementX||0)*.0025;play.pitch=clamp(play.pitch-(e.movementY||0)*.0025,-1.3,1.3)}return}
 if(!drag)return;
 if(drag.mode==='pinch'&&ptrs.size===2){const[a,b]=[...ptrs.values()],d=Math.hypot(a.x-b.x,a.y-b.y);cam.dist=clamp(cam.dist*pinch/(d||1),6,400);pinch=d;updateCam();return}
 if(drag.mode==='move'){const it=getItem({kind:drag.kind,id:drag.id}),p=groundPt(e,drag.y);if(!it||!p)return;drag.moved=true;let carried=[];if(drag.kind==='room')carried=placeRoom(it,p.x-drag.ox,p.z-drag.oz);else placeObject(it,p.x-drag.ox,p.z-drag.oz);syncLive(drag.kind,it);carried.forEach(o=>syncLive('object',o));return}
 const dx=e.clientX-drag.px,dy=e.clientY-drag.py;drag.px=e.clientX;drag.py=e.clientY;
 if(drag.mode==='orbit'){cam.theta-=dx*.007;cam.phi=clamp(cam.phi-dy*.006,.0001,1.5);updateCam()}
 else if(drag.mode==='pan'){const k=cam.dist*.0017;cam.tx-=Math.cos(cam.theta)*dx*k;cam.tz+=Math.sin(cam.theta)*dx*k;cam.tx+=-Math.sin(cam.theta)*dy*k*-1*-1;cam.tz+=-Math.cos(cam.theta)*dy*k*-1*-1;updateCam()}});
function end3D(e){ptrs.delete(e.pointerId);if(ptrs.size<2&&drag&&drag.mode==='pinch')drag=null;if(drag&&drag.mode==='move'&&drag.moved){rebuild();commit();renderPanels()}if(ptrs.size===0)drag=null}
cv3.addEventListener('pointerup',end3D);cv3.addEventListener('pointercancel',end3D);cv3.addEventListener('contextmenu',e=>e.preventDefault());
cv3.addEventListener('wheel',e=>{e.preventDefault();if(playMode)return;cam.dist=clamp(cam.dist*(e.deltaY>0?1.08:.92),6,400);updateCam()},{passive:false});
document.addEventListener('pointerlockchange',()=>{if(playMode&&document.pointerLockElement!==cv3&&!drag)exitPlay()});

/* ---------- roof visibility ---------- */
function updateRoofVisibility(){
 const configured=S.site.roof!=='off';
 const modeSupported=view==='3d'||view==='top';
 const visible=configured&&modeSupported&&(roofOverride===null?view==='3d':roofOverride);
 roofMeshes.forEach(mesh=>mesh.visible=visible);
 const button=$('roofToggleBtn');
 button.disabled=!configured||!modeSupported;
 button.classList.toggle('on',visible);
 button.setAttribute('aria-pressed',String(visible));
 button.textContent=visible?'Roof: Shown':'Roof: Hidden';
 button.title=!configured?'Choose Flat roof or Gable roof in Site → Building first':
  !modeSupported?'Roof toggle is available in 3D and Top views':
  `Click to ${visible?'hide':'show'} the roof in ${view==='top'?'Top':'3D'} view`;
}

/* ---------- views ---------- */
function setView(v){if(playMode&&v!==view)exitPlay();if(v!==view)roofOverride=null;view=v;const is2=v==='2d';cv3.style.display=is2?'none':'block';plan.style.display=is2?'block':'none';$('coords').style.display='none';
 ['3d','2d','Top'].forEach(k=>$('v'+k).classList.toggle('on',(k==='3d'&&v==='3d')||(k==='2d'&&v==='2d')||(k==='Top'&&v==='top')));
 $('viewTitle').textContent=v==='3d'?'3D model':v==='2d'?'Floor plan · '+floorName(curFloor):'Top view';
 $('hint').textContent=is2?'Drag to move · corner handle resizes a room · drag empty space or right-drag to pan · scroll to zoom':'Drag items to move · drag empty space to orbit · Shift/right-drag to pan · scroll to zoom';
 if(v==='top')fitCamera(true);else if(v==='3d'&&!playMode)fitCamera();if(is2){fit2D();draw2D()}updateRoofVisibility()}

/* ---------- UI ---------- */
function toast(m){const t=$('toast');t.textContent=m;t.classList.add('show');clearTimeout(toast.t);toast.t=setTimeout(()=>t.classList.remove('show'),2300)}
function fmtOut(inp){const v=Number(inp.value);if(inp.dataset.f==='hour')return String(Math.floor(v)).padStart(2,'0')+':'+String(Math.round((v%1)*60)).padStart(2,'0');return v.toFixed(Number(inp.step)<1?(Number(inp.step)<.1?2:1):0)+(inp.dataset.u||'')}
const getP=p=>{const[a,b]=p.split('.');return S[a][b]},setP=(p,v)=>{const[a,b]=p.split('.');S[a][b]=v};
function syncUI(){for(const k of ['width','depth']){const e=document.querySelector('[data-s="site.'+k+'"]');e.min=.1;e.max=Math.max(60,S.site[k]);e.step=.1}document.querySelector('[data-s="env.flood"]').max=Math.max(4,S.env.flood);if(S.location){$('siteLat').value=S.location.lat;$('siteLng').value=S.location.lng;$('hazardPeriod').value=S.location.period||100;$('surgeScenario').value=S.location.surge||4}else{$('siteLat').value='';$('siteLng').value=''}document.querySelectorAll('[data-s]').forEach(i=>{i.value=getP(i.dataset.s);const o=i.closest('.field')?.querySelector('output');if(o)o.textContent=fmtOut(i)});$('projName').value=S.name;updateSim()}
function onSetting(p,a){if(p==='site.width'||p==='site.depth'){clampRooms();fit2D()}if(a==='r')rebuild();else if(a==='e')applyEnv();else if(a==='f')updateFlood()}
document.addEventListener('input',e=>{const t=e.target;if(!t.dataset||!t.dataset.s)return;setP(t.dataset.s,t.type==='range'?Number(t.value):t.value);const o=t.closest('.field')?.querySelector('output');if(o)o.textContent=fmtOut(t);onSetting(t.dataset.s,t.dataset.a)});
document.addEventListener('change',e=>{const t=e.target;if(!t.dataset||!t.dataset.s)return;setP(t.dataset.s,t.type==='range'?Number(t.value):t.value);onSetting(t.dataset.s,t.dataset.a);commit()});
$('projName').addEventListener('change',e=>{S.name=e.target.value.trim()||'Untitled house';commit()});
document.querySelectorAll('.tab').forEach(b=>b.onclick=()=>{document.querySelectorAll('.tab').forEach(x=>x.classList.toggle('on',x===b));document.querySelectorAll('.pane').forEach(p=>p.classList.toggle('on',p.dataset.pane===b.dataset.tab))});
document.querySelectorAll('[data-hour]').forEach(b=>b.onclick=()=>{S.env.hour=Number(b.dataset.hour);syncUI();applyEnv();commit()});
$('shuffleBtn').onclick=()=>{S.site.seed=Math.round(Math.random()*1000)/10;rebuild();commit();toast('Terrain reshuffled')};
$('riverBtn').onclick=()=>{riverMode=!riverMode;$('riverBtn').classList.toggle('on',riverMode);$('riverBtn').textContent=riverMode?'● Drag on the plan to sketch…':'〰 Draw river on plan';if(riverMode){setView('2d');toast('Drag across the plan to sketch a river')}};
$('clearRiverBtn').onclick=()=>{S.river.pts=[];rebuild();commit();toast('River removed')};
$('resetBtn').onclick=()=>{if(confirm('Remove all rooms, objects and the river?')){const n=S.name;S=blank();S.name=n;sel=null;syncUI();rebuild();fitCamera();commit();renderPanels()}};
$('simBtn').onclick=()=>{S.env.sim=!S.env.sim;if(S.env.sim)startQuake();rebuild();commit()};
$('v3d').onclick=()=>setView('3d');$('v2d').onclick=()=>setView('2d');$('vTop').onclick=()=>setView('top');
$('gridBtn').onclick=()=>{showGrid=!showGrid;$('gridBtn').classList.toggle('on',showGrid);if(stageGrid)stageGrid.visible=showGrid;draw2D()};
$('snapBtn').onclick=()=>{snapOn=!snapOn;$('snapBtn').classList.toggle('on',snapOn);toast('Snap '+(snapOn?'on (0.25 m grid, wall & edge snapping)':'off'))};
$('labelBtn').onclick=()=>{showLabels=!showLabels;$('labelBtn').classList.toggle('on',showLabels);rebuild()};
$('roofToggleBtn').onclick=()=>{if(S.site.roof==='off'||view==='2d')return;const currentlyVisible=roofOverride===null?view==='3d':roofOverride;roofOverride=!currentlyVisible;updateRoofVisibility()};
$('fitBtn').onclick=()=>{if(view==='2d'){fit2D();draw2D()}else fitCamera(view==='top')};
$('playBtn').onclick=()=>playMode?exitPlay():enterPlay();
$('undoBtn').onclick=undo;$('redoBtn').onclick=redo;
$('toolsToggle').onclick=()=>$('left').classList.toggle('open');$('inspToggle').onclick=()=>$('right').classList.toggle('open');
document.querySelectorAll('[data-nudge]').forEach(b=>b.onclick=()=>nudge(...b.dataset.nudge.split(',').map(Number),false));
function nudge(dx,dz,fine){const it=getItem(sel);if(!it){toast('Select a room or object first');return}const st=fine?.05:G;let carried=[];if(sel.kind==='room')carried=moveRoomTo(it,it.x+dx*st,it.z+dz*st);else{it.x=clamp(it.x+dx*st,-S.site.width/2+.2,S.site.width/2-.2);it.z=clamp(it.z+dz*st,-S.site.depth/2+.2,S.site.depth/2-.2)}if(world)syncLive(sel.kind,it),carried.forEach(o=>syncLive('object',o));draw2D();clearTimeout(nudgeTimer);nudgeTimer=setTimeout(()=>{rebuild();commit();renderPanels()},350)}
window.addEventListener('keydown',e=>{const tg=(e.target.tagName||'');if(['INPUT','TEXTAREA','SELECT'].includes(tg)&&!(e.ctrlKey||e.metaKey))return;const k=e.key.toLowerCase();keys[k]=true;keys.shift=e.shiftKey;
 if(playMode){if(['w','a','s','d','arrowup','arrowdown','arrowleft','arrowright'].includes(k))e.preventDefault();if(k==='escape')exitPlay();return}
 if((e.ctrlKey||e.metaKey)&&k==='z'){e.preventDefault();e.shiftKey?redo():undo();return}if((e.ctrlKey||e.metaKey)&&k==='y'){e.preventDefault();redo();return}
 if((e.ctrlKey||e.metaKey)&&k==='d'){e.preventDefault();dupSel();return}if((e.ctrlKey||e.metaKey)&&k==='s'){e.preventDefault();$('saveBtn').click();return}
 if(['INPUT','TEXTAREA','SELECT'].includes(tg))return;
 if(k==='delete'||k==='backspace'){e.preventDefault();removeSel()}else if(k==='r'){rotSel()}else if(k==='escape'){if(riverMode)$('riverBtn').click();else select(null)}
 else if(k.startsWith('arrow')){e.preventDefault();nudge(k==='arrowleft'?-1:k==='arrowright'?1:0,k==='arrowup'?-1:k==='arrowdown'?1:0,e.shiftKey)}});
window.addEventListener('keyup',e=>{keys[e.key.toLowerCase()]=false;keys.shift=e.shiftKey});window.addEventListener('blur',()=>{for(const k in keys)keys[k]=false});
window.addEventListener('resize',resize);if(window.ResizeObserver)new ResizeObserver(resize).observe(wrap);

/* storeys UI */
function renderFloorUI(){
 if(!S.floors)return;const opts=S.floors.map((_,i)=>{const n=S.rooms.filter(r=>lvl(r)===i).length;return[i,floorName(i)+' · '+n+' room'+(n===1?'':'s')]});
 for(const id of['floorSelect','floorPanelSelect']){const e=$(id);e.replaceChildren(...opts.map(([i,t])=>new Option(t,i)));e.value=String(curFloor)}
 const h=S.floors[curFloor]?.h;$('floorHeight').value=h??'';$('floorHeightOut').textContent=floorH(curFloor).toFixed(1)+' m';$('buildingKind').value=S.site.kind||'House';
 $('removeFloorBtn').disabled=S.floors.length<2;$('addFloorBtn').disabled=$('copyFloorBtn').disabled=S.floors.length>=MAX_FLOORS;
 if(view==='2d')$('viewTitle').textContent='Floor plan · '+floorName(curFloor)}
function setFloor(i){curFloor=clamp(Number(i)||0,0,S.floors.length-1);const it=getItem(sel);if(it&&lvl(it)!==curFloor)select(null);renderFloorUI();draw2D()}
$('floorSelect').onchange=e=>setFloor(e.target.value);$('floorPanelSelect').onchange=e=>setFloor(e.target.value);
$('addFloorBtn').onclick=()=>{if(S.floors.length>=MAX_FLOORS)return toast('Up to '+MAX_FLOORS+' floors are supported');S.floors.push({h:kindHeight(S.site.kind,S.floors.length)});curFloor=S.floors.length-1;sel=null;rebuild();commit();renderPanels();toast(floorName(curFloor)+' added — add rooms or upload its blueprint')};
$('copyFloorBtn').onclick=()=>{if(S.floors.length>=MAX_FLOORS)return toast('Up to '+MAX_FLOORS+' floors are supported');const src=curFloor,rooms=S.rooms.filter(r=>lvl(r)===src),objs=S.objects.filter(o=>lvl(o)===src&&!CAT[o.type]?.out);if(!rooms.length)return toast(floorName(src)+' has no rooms to copy');if(S.rooms.length+rooms.length>MAX_ROOMS)return toast('Copying would pass the '+MAX_ROOMS+'-room limit');const L=S.floors.length;S.floors.push({h:S.floors[src].h});rooms.forEach(r=>S.rooms.push({...structuredClone(r),id:S.nextId++,level:L}));objs.forEach(o=>S.objects.push({...structuredClone(o),id:S.nextId++,level:L}));curFloor=L;sel=null;rebuild();commit();renderPanels();toast(floorName(src)+' copied to '+floorName(L))};
$('removeFloorBtn').onclick=()=>{if(S.floors.length<2)return;const L=curFloor,n=S.rooms.filter(r=>lvl(r)===L).length,b=S.blueprints.filter(bp=>bp.type==='house'&&lvl(bp)===L).length;if(!confirm('Remove '+floorName(L)+' with its '+n+' rooms, fixtures and '+b+' blueprint(s)? Floors above move down one level.'))return;
 S.rooms=S.rooms.filter(r=>lvl(r)!==L);S.objects=S.objects.filter(o=>lvl(o)!==L);S.blueprints=S.blueprints.filter(bp=>bp.type!=='house'||lvl(bp)!==L);for(const it of[...S.rooms,...S.objects,...S.blueprints])if(lvl(it)>L)it.level=lvl(it)-1;S.floors.splice(L,1);curFloor=Math.max(0,L-1);sel=null;bpImages.clear();rebuild();commit();renderPanels();toast('Floor removed')};
$('floorHeight').onchange=e=>{const v=e.target.value.trim()===''?null:Number(e.target.value);S.floors[curFloor].h=v===null||!Number.isFinite(v)?null:clamp(v,2.2,6);rebuild();commit()};
$('buildingKind').onchange=e=>{S.site.kind=e.target.value;S.floors.forEach((f,i)=>f.h=kindHeight(S.site.kind,i));rebuild();commit();toast(S.site.kind==='House'?'Floor heights follow the wall height in Site':'Floor heights set to rough '+S.site.kind+' concept defaults; edit any floor')};

/* palettes */
function buildPalettes(){
 const rp=$('roomPalette');Object.keys(ROOMS).forEach(n=>{const b=document.createElement('button');b.className='tile';b.title='Add '+n;const c=document.createElement('canvas');drawIcon(c,'room',n);const l=document.createElement('span');l.textContent=n==='Custom'?'Custom':n;const sm=document.createElement('small');sm.textContent=ROOMS[n].w+'×'+ROOMS[n].d;b.append(c,l,sm);b.onclick=()=>addRoom(n);rp.append(b)});
 const op=$('objPalette'),groups={};Object.entries(CAT).forEach(([n,c])=>(groups[c.g]=groups[c.g]||[]).push(n));
 Object.entries(groups).forEach(([g,list])=>{const h=document.createElement('div');h.className='subhead';h.textContent=g;const t=document.createElement('div');t.className='tiles';list.forEach(n=>{const b=document.createElement('button');b.className='tile';b.title='Add '+n;const c=document.createElement('canvas');drawIcon(c,'obj',n);const l=document.createElement('span');l.textContent=n;b.append(c,l);b.onclick=()=>addObject(n);t.append(b)});op.append(h,t)})}

/* inspector & panels */
function renderInspector(){
 const el=$('inspector'),it=getItem(sel);if(!it){el.innerHTML='<div class="empty">Nothing selected.<br>Click a room or object in the 3D model or floor plan to edit its dimensions, position and colour.</div>';return}
 const room=sel.kind==='room',F=(l,k,v,st,mn,mx,full)=>`<label class="if${full?' full':''}"><span>${l}</span><input type="number" data-k="${k}" value="${r1(v)}" step="${st}" min="${mn}" max="${mx}"></label>`;
 let h=`<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px"><span class="chip">${room?'Room':esc(it.type)}</span><span class="help">#${it.id}</span></div><div class="insp-grid">`;
 if(room)h+=`<label class="if full"><span>Name</span><input type="text" data-k="name" value="${esc(it.name)}" maxlength="40"></label>`;
 if(room||!CAT[it.type]?.out)h+=`<label class="if full"><span>Floor${room?' (moves its fixtures too)':''}</span><select data-k="level">${S.floors.map((_,i)=>`<option value="${i}"${i===lvl(it)?' selected':''}>${floorName(i)}</option>`).join('')}</select></label>`;
 h+=F('Width (m)','w',it.w,.1,room?1:.05,40)+F('Depth (m)','d',it.d,.1,room?1:.05,40);
 if(!room)h+=F('Height (m)','h',it.h,.1,.01,10);if(!room&&isOpening(it.type))h+=F('Sill height (m)','e',it.e||0,.05,0,6);
 h+=F('Position X (m)','x',it.x,.25,-100,100)+F('Position Z (m)','z',it.z,.25,-100,100);
 if(!room)h+=F('Rotation (°)','rot',it.rot,15,0,359);
 if(room||it.type!=='Window')h+=`<label class="if full"><span>${room?'Floor colour':'Colour'}</span><input type="color" data-k="${room?'c':'color'}" value="${room?it.c:(it.color||CAT[it.type].c)}"></label>`;
 h+='</div>';if(room)h+=`<div class="help" style="margin-bottom:6px">Area ${(it.w*it.d).toFixed(1)} m² · perimeter ${(2*(it.w+it.d)).toFixed(1)} m</div>`;
 h+=`<div class="btnrow"><button data-act="rot">⟳ ${room?'Swap W/D':'Rotate 90°'}</button><button data-act="dup">⧉ Duplicate</button><button data-act="del" class="danger">🗑 Delete</button></div>`;el.innerHTML=h}
$('inspector').addEventListener('change',e=>{const k=e.target.dataset.k;if(!k)return;const it=getItem(sel);if(!it)return;const room=sel.kind==='room';let v=e.target.value;
 if(k==='level'){const L=clamp(Math.round(Number(v))||0,0,S.floors.length-1);if(room)objectsInRoom(it).forEach(o=>o.level=L);it.level=L;curFloor=L;toast((room?it.name:it.type)+' moved to '+floorName(L))}
 else if(k==='name')it.name=String(v).slice(0,40)||'Room';else if(k==='c'||k==='color')it[k]=v;else{v=Number(v);if(!Number.isFinite(v))return;
  if(k==='x'||k==='z'){if(room)moveRoomTo(it,k==='x'?v:it.x,k==='z'?v:it.z);else it[k]=v}else if(k==='rot')it.rot=((v%360)+360)%360;else if(k==='w'||k==='d'){it[k]=clamp(v,room?1:.05,40);if(room)clampRoom(it)}else if(k==='h')it.h=clamp(v,.01,10);else if(k==='e')it.e=clamp(v,0,6)}
 rebuild();commit();renderPanels()});
$('inspector').addEventListener('click',e=>{const a=e.target.closest('[data-act]')?.dataset.act;if(a==='rot')rotSel();else if(a==='dup')dupSel();else if(a==='del')removeSel()});
function renderOutliner(){const el=$('outliner');el.innerHTML='';if(!S.rooms.length&&!S.objects.length){el.innerHTML='<div class="empty">Add a room to begin.</div>';return}
 const row=(kind,it,color,title,sub)=>{const d=document.createElement('div');d.className='li'+(sel&&sel.kind===kind&&sel.id===it.id?' sel':'');d.innerHTML=`<i style="background:${color}"></i><div>${esc(title)}<small>${esc(sub)}</small></div>`;const b=document.createElement('button');b.className='ghost';b.textContent='✕';b.title='Delete';b.onclick=ev=>{ev.stopPropagation();sel={kind,id:it.id};removeSel()};d.onclick=()=>select({kind,id:it.id});d.append(b);el.append(d)};
 const fl=it=>S.floors.length>1?'F'+(lvl(it)+1)+' · ':'';
 S.rooms.forEach(r=>row('room',r,r.c,r.name,`${fl(r)}${r.w.toFixed(1)} × ${r.d.toFixed(1)} m · ${(r.w*r.d).toFixed(1)} m²`));S.objects.forEach(o=>row('object',o,o.color||CAT[o.type].c,o.type,`${fl(o)}${o.w.toFixed(2)} × ${o.d.toFixed(2)} m`))}
function updateSummary(){const area=S.rooms.reduce((s,r)=>s+r.w*r.d,0),foot=S.rooms.filter(r=>lvl(r)===0).reduce((s,r)=>s+r.w*r.d,0),doors=S.objects.filter(o=>o.type==='Door').length,wins=S.objects.filter(o=>o.type==='Window').length;
 $('sRooms').textContent=S.rooms.length;$('sBeds').textContent=S.rooms.filter(r=>/bed/i.test(r.name)).length;$('sArea').textContent=area.toFixed(1)+' m²';$('sCov').textContent=(foot/(S.site.width*S.site.depth)*100).toFixed(1)+'%';$('sFloors').textContent=(S.floors||[1]).length;$('sOpen').textContent=doors+' / '+wins;$('sObjs').textContent=S.objects.length-doors-wins;
 $('pSite').textContent='SITE '+S.site.width+' × '+S.site.depth+' m';$('pRooms').textContent=S.rooms.length+' ROOM'+(S.rooms.length===1?'':'S');$('pObjs').textContent=S.objects.length+' OBJECT'+(S.objects.length===1?'':'S')}
function renderPanels(){renderInspector();renderOutliner();updateSummary();renderBpList();renderVersions();renderReadiness()}

/* save / load */
function normalize(d){
 if(!d)throw Error('Empty file');if(d.version==='3.0'&&d.state)d=d.state;const s=blank();
 if(d.rooms&&d.site&&d.site.width!==undefined&&d.name!==undefined&&d.river){Object.assign(s.site,d.site);Object.assign(s.river,d.river);Object.assign(s.env,d.env||{});s.name=d.name}
 else if(d.site){const o=d.site;const m={width:'width',depth:'depth',ground:'groundElevation',hills:'hills',wall:'wallHeight'};for(const k in m)if(o[m[k]]!==undefined)s.site[k]=o[m[k]];if(o.terrainMaterial&&PAL[o.terrainMaterial])s.site.material=o.terrainMaterial;if(o.roof)s.site.roof=o.roof==='on'?'flat':o.roof==='off'?'off':o.roof;if(o.weather)s.env.weather=o.weather;if(o.floodLevel!==undefined)s.env.flood=o.floodLevel;if(o.quakeLevel)s.env.quake=o.quakeLevel;if(o.riverWidth)s.river.width=o.riverWidth;if(o.timeOfDay)s.env.hour={day:12.5,sunset:17.75,night:22}[o.timeOfDay]||12.5;s.river.pts=(d.riverPoints||[]).map(p=>({x:+p.x,z:+p.z}))}
 if(!Array.isArray(d.rooms))throw Error('Invalid project file');
 let id=1;const nid=()=>id++;s.rooms=d.rooms.map(r=>{const c=typeof r.c==='number'?numHex(r.c):(typeof r.c==='string'&&/^#[0-9a-f]{6}$/i.test(r.c)?r.c:'#8fb0c9');return{id:nid(),name:String(r.name||'Room'),w:clamp(+(r.w??r.width)||3,1,40),d:clamp(+(r.d??r.depth)||3,1,40),c,x:+r.x||0,z:+r.z||0,level:lvl(r)}});
 s.objects=(d.objects||[]).filter(o=>CAT[o.type]).map(o=>{const c=CAT[o.type],q={id:nid(),type:o.type,level:c.out?0:lvl(o),x:+o.x||0,z:+o.z||0,rot:+(o.rot??o.rotation)||0,w:+o.w||c.w,d:+(o.d??o.depth)||c.d,h:+o.h||c.h,e:+(o.e??o.elevation)||c.e};if(isOpening(o.type))q.d=c.d;if(o.color)q.color=o.color;return q});
 s.floors=Array.isArray(d.floors)&&d.floors.length?d.floors.slice(0,MAX_FLOORS).map(f=>({h:Number.isFinite(Number(f&&f.h))&&Number(f.h)>0?clamp(Number(f.h),2.2,6):null})):[{h:null}];if(!['House','Commercial','Mixed use'].includes(s.site.kind))s.site.kind='House';
 s.nextId=id;s.versions=Array.isArray(d.versions)?d.versions:[];s.hazard=d.hazard||null;s.location=d.location||null;
 // restore blueprints
 s.blueprints=(d.blueprints||[]).map(bp=>({...bp,id:bp.id||s.nextBpId++,name:bp.name||'Blueprint',type:bp.type==='lot'?'lot':'house',data:typeof bp.data==='string'&&(/^(data:image\/|blueprint\.php\?id=[a-f0-9]{48}$)/.test(bp.data))?bp.data:'',opacity:bp.opacity??50,scale:bp.scale??1,offsetX:bp.offsetX??0,offsetZ:bp.offsetZ??0,rotation:bp.rotation??0,visible:bp.visible!==false,w:bp.w||0,h:bp.h||0,level:bp.type==='lot'?0:lvl(bp)}));
 s.nextBpId=Math.max(s.nextBpId||1,...s.blueprints.map(b=>b.id+1));
 return s}
function loadState(d){try{S=normalize(d);bpImages.clear();comparisonSuggestion=null;comparisonBlueprint=null;$('compareMode').value='off';sel=null;clampRooms();syncUI();rebuild();fitCamera(view==='top');fit2D();renderPanels();commit();return true}catch(e){toast('Could not load project: '+e.message);return false}}
$('submitReviewBtn').onclick=()=>{$('reviewMenu').open=false;$('reviewSubmissionForm').elements.kind.value=S.site.kind||'House';$('reviewName').value=S.name||'Untitled project';$('reviewSubmissionStatus').textContent='';$('reviewSubmissionDialog').showModal()};
$('closeReviewSubmission').onclick=()=>$('reviewSubmissionDialog').close();
$('reviewSubmissionForm').onsubmit=async e=>{
 e.preventDefault();const button=$('confirmReviewSubmission');button.disabled=true;$('reviewSubmissionStatus').textContent='Saving blueprint images and preparing your snapshot…';
 try{for(const bp of S.blueprints)if(bp.data.startsWith('data:')){bp.data=await storeBlueprint(bp.data);bpImages.delete(bp.id)}commit();renderPanels();
 const snapshot=JSON.stringify({application:'ARCHIspace',version:'3.0',state:S});
 if(new Blob([snapshot]).size>4*1024*1024)throw new Error('This snapshot exceeds 4 MB. Reduce project history or blueprint data before submitting.');
 localStorage.setItem(projectStorageKey,snapshot);$('reviewSnapshot').value=snapshot;HTMLFormElement.prototype.submit.call($('reviewSubmissionForm'));
 }catch(error){$('reviewSubmissionStatus').textContent='Submission failed: '+error.message;button.disabled=false}
};
$('saveBtn').onclick=async()=>{try{for(const bp of S.blueprints)if(bp.data.startsWith('data:')){bp.data=await storeBlueprint(bp.data);bpImages.delete(bp.id)}commit();renderPanels();localStorage.setItem(projectStorageKey,JSON.stringify({application:'ARCHIspace',version:'3.0',state:S}));toast('Project saved; blueprint images stored on server')}catch(e){toast('Save failed: '+e.message)}};
$('loadBtn').onclick=()=>$('loadFile').click();
$('loadFile').onchange=async e=>{const f=e.target.files[0];if(!f)return;try{if(loadState(JSON.parse(await f.text())))toast('Project loaded')}catch(err){toast('That file is not valid project JSON')}e.target.value=''};
function download(url,name){const a=document.createElement('a');a.href=url;a.download=name;a.click()}
$('exportJson').onclick=()=>{const blob=new Blob([JSON.stringify({application:'ARCHIspace',version:'3.0',state:S},null,2)],{type:'application/json'}),u=URL.createObjectURL(blob);download(u,(S.name.replace(/[^\w-]+/g,'-')||'project')+'.json');setTimeout(()=>URL.revokeObjectURL(u),1500);$('exportMenu').open=false;toast('Project exported')};
$('exportPng').onclick=()=>{$('exportMenu').open=false;try{let u;if(view==='2d'){draw2D();u=plan.toDataURL('image/png')}else{renderer.render(scene,camera);u=cv3.toDataURL('image/png')}download(u,(S.name.replace(/[^\w-]+/g,'-')||'view')+'-'+view+'.png');toast('Image exported')}catch(e){toast('Could not export image')}};
document.addEventListener('click',e=>{if(!$('exportMenu').contains(e.target))$('exportMenu').open=false});

/* ---------- sample designs ---------- */
function makeTemplate(def){
 const st=blank();st.name=def.name;Object.assign(st.site,def.site||{});if(def.river)st.river.pts=def.river.map(p=>({...p}));
 const rs=[];let z=0;def.rows.forEach(row=>{let x=0,md=0;row.forEach(([t,w,d,l])=>{rs.push({t,w,d,l,x:x+w/2,z:z+d/2});x+=w;md=Math.max(md,d)});z+=md});
 const minx=Math.min(...rs.map(r=>r.x-r.w/2)),maxx=Math.max(...rs.map(r=>r.x+r.w/2)),minz=Math.min(...rs.map(r=>r.z-r.d/2)),maxz=Math.max(...rs.map(r=>r.z+r.d/2)),cx=(minx+maxx)/2-(def.at?def.at[0]:0),cz=(minz+maxz)/2-(def.at?def.at[1]:0);
 rs.forEach(r=>{r.x-=cx;r.z-=cz;st.rooms.push({id:st.nextId++,name:r.l||r.t,w:r.w,d:r.d,c:(ROOMS[r.t]||ROOMS.Custom).c,x:r.x,z:r.z})});
 const SIDE={n:[0,-1,0],s:[0,1,180],w:[-1,0,90],e:[1,0,270]},mk=(t,x,zz,rot,ov)=>{const c=CAT[t];st.objects.push({id:st.nextId++,type:t,x,z:zz,rot,w:c.w,d:c.d,h:c.h,e:c.e,...(ov||{})})};
 (def.ops||[]).forEach(([t,i,sd,off,ov])=>{const r=rs[i],[ux,uz,rot]=SIDE[sd];mk(t,ux===0?r.x+off:r.x+ux*r.w/2,ux===0?r.z+uz*r.d/2:r.z+off,rot,ov)});
 (def.items||[]).forEach(([t,i,dx,dz,rot,ov])=>mk(t,rs[i].x+dx,rs[i].z+dz,rot||0,ov));(def.out||[]).forEach(([t,x,zz,rot,ov])=>mk(t,x,zz,rot||0,ov));return st}
const TEMPLATES=[
{name:'Coastal Retreat',desc:'Open living space, two bedrooms and a garden beside a winding river.',tags:['2 bed','river','gable roof'],def:{name:'Coastal Retreat',site:{width:32,depth:34,hills:1.1,seed:5,roof:'gable',wallColor:'#f1ede4',roofColor:'#3f5671'},river:[{x:-12,z:-17},{x:-11,z:-9},{x:-13.5,z:-1},{x:-12,z:7},{x:-13,z:12},{x:-11.5,z:17}],
 rows:[[['Living room',6,5],['Kitchen',3.5,5]],[['Bedroom',4,4,'Bedroom'],['Bathroom',2.5,4],['Bedroom',3.5,4,'Guest bedroom']]],
 ops:[['Door',0,'n',-1.5],['Window',0,'n',1.6,{w:1.8}],['Window',0,'w',0,{w:2.4,h:1.6,e:.5}],['Door',0,'e',1.2],['Window',1,'n',0],['Window',1,'e',0],['Door',0,'s',-2],['Door',3,'w',0],['Door',4,'n',-.3],['Window',2,'w',0],['Window',2,'s',0],['Window',3,'s',0,{w:.7,e:1.3,h:.7}],['Window',4,'e',0],['Window',4,'s',0]],
 items:[['Sofa',0,1.2,1.95,180],['Rug',0,.6,.3,0,{w:3,d:2}],['Table',0,.6,.3,0,{w:1.1,d:.6,h:.42,color:'#8a6f55'}],['Chair',0,-1.9,0,90],['Plant',0,-2.6,-2],['Plant',0,2.6,-2],
  ['Counter',1,0,2.1,180],['Fridge',1,-1.3,2.1,180],['Table',1,0,-.6],['Chair',1,0,-1.4,0],['Chair',1,0,.2,180],
  ['Bed',2,0,-.9,0],['Wardrobe',2,1.65,.9,270],['Plant',2,-1.5,1.5],
  ['Toilet',3,.7,-1.6,0],['Sink',3,-.6,-1.7,0],['Bathtub',3,.75,.9,0],
  ['Bed',4,-.7,.4,90],['Desk',4,.9,1.5,180]],
 out:[['Tree',-6,-12],['Tree',9,-11],['Tree',10,10],['Tree',-7,12],['Bush',-4,8],['Bush',6,-6],['Bush',8,5],['Car',7.5,12.5,0,{color:'#2f5d8c'}]]}},
{name:'Compact City Home',desc:'A smart starter home: open living and kitchen, bedroom and bathroom.',tags:['1 bed','flat roof'],def:{name:'Compact City Home',site:{width:22,depth:24,hills:.4,seed:2,roof:'flat',wallColor:'#e6e1d8',roofColor:'#59616b'},
 rows:[[['Living room',5,4.5],['Kitchen',3.5,4.5]],[['Bedroom',4.5,4],['Bathroom',2.5,4]]],
 ops:[['Door',0,'n',-1.3],['Window',0,'n',1.2],['Window',0,'w',0],['Door',0,'e',-1],['Window',1,'n',0],['Window',1,'e',0],['Door',0,'s',-1.5],['Window',2,'w',0],['Window',2,'s',-1],['Door',3,'w',0],['Window',3,'s',0,{w:.7,e:1.3,h:.7}]],
 items:[['Sofa',0,-.2,-1.65,0],['Rug',0,-.2,-.2,0,{w:2.4,d:1.6}],['Table',0,-.2,-.2,0,{w:1.1,d:.6,h:.42,color:'#8a6f55'}],['Chair',0,1.6,.9,270],['Plant',0,2.1,-1.8],
  ['Counter',1,.5,1.9,180],['Fridge',1,-1.3,1.85,180],['Table',1,0,-.4],['Chair',1,0,-1.1,0],['Chair',1,0,.3,180],
  ['Bed',2,-1.15,0,90],['Wardrobe',2,1.2,-1.65,0],['Plant',2,1.8,1.4],
  ['Toilet',3,.7,-1.6,0],['Sink',3,-.6,-1.65,0],['Bathtub',3,.8,1,0]],
 out:[['Tree',-8,-8],['Tree',8,7],['Tree',-8,6],['Bush',7,-8],['Bush',-6,9]]}},
{name:'Modern Family House',desc:'Four-bedroom plan with an attached garage and a home office.',tags:['3 bed','garage','gable roof'],def:{name:'Modern Family House',site:{width:36,depth:34,hills:.6,seed:8,roof:'gable',wallColor:'#f2f0ea',roofColor:'#2f3b4a'},
 rows:[[['Living room',6.5,6],['Kitchen',4,6],['Garage',5.5,6]],[['Bedroom',4.5,4.5,'Master bedroom'],['Bathroom',2.5,4.5],['Bedroom',4,4.5],['Office',5,4.5]]],
 ops:[['Door',0,'n',1.5],['Window',0,'n',-1.6,{w:1.8}],['Window',0,'w',0,{w:2.2,h:1.5,e:.6}],['Door',0,'e',1.5],['Door',0,'s',-1],['Window',1,'n',0],['Door',1,'s',.3],['Door',2,'n',0,{w:3,h:2.3}],['Door',2,'w',1.8],['Window',3,'w',0],['Window',3,'s',0],['Door',4,'w',0],['Window',4,'s',0,{w:.7,e:1.3,h:.7}],['Window',5,'s',0],['Door',5,'e',0],['Window',6,'s',0],['Window',6,'e',0]],
 items:[['Sofa',0,0,2.3,180],['Rug',0,0,.6,0,{w:3.2,d:2}],['Table',0,0,.6,0,{w:1.2,d:.7,h:.42,color:'#8a6f55'}],['Chair',0,-2.3,.5,90],['Plant',0,-2.8,-2.4],['Plant',0,2.8,-2.4],
  ['Counter',1,0,-2.62,0],['Fridge',1,1.4,-2.55,0],['Table',1,-.2,.4,0,{w:1.8}],['Chair',1,-.9,-.5,0],['Chair',1,.5,-.5,0],['Chair',1,-.9,1.3,180],['Chair',1,.5,1.3,180],
  ['Car',2,0,.2,0],
  ['Bed',3,0,-.6,0,{w:1.8}],['Wardrobe',3,-1.45,1.8,0],
  ['Toilet',4,.7,-1.9,0],['Sink',4,-.6,-2,0],['Bathtub',4,.7,.7,0],
  ['Bed',5,-.4,-.4,90],['Wardrobe',5,1.6,-1.7,0],
  ['Desk',6,0,-1.8,0],['Chair',6,0,-1,0],['Plant',6,2,1.6]],
 out:[['Tree',-11,-11],['Tree',12,-12],['Tree',-12,10],['Tree',13,10],['Tree',0,13],['Bush',-4,11],['Bush',5,11],['Bush',-9,-4],['Bush',10,-5],['Car',-10.5,3,0,{color:'#2e7a67'}]]}}
];
function thumb(cv,st){const w=cv.width=520,h=cv.height=300,x=cv.getContext('2d');x.fillStyle='#16241c';x.fillRect(0,0,w,h);const k=Math.min((w-30)/st.site.width,(h-30)/st.site.depth),ox=w/2,oy=h/2;x.fillStyle='#38603f';x.fillRect(ox-st.site.width*k/2,oy-st.site.depth*k/2,st.site.width*k,st.site.depth*k);
 if(st.river.pts.length>1){x.strokeStyle='#3d9fd8';x.lineWidth=st.river.width*k;x.lineCap='round';x.lineJoin='round';x.beginPath();st.river.pts.forEach((p,i)=>{const px=ox+p.x*k,py=oy+p.z*k;i?x.lineTo(px,py):x.moveTo(px,py)});x.stroke()}
 st.rooms.forEach(r=>{x.fillStyle=r.c;x.fillRect(ox+(r.x-r.w/2)*k,oy+(r.z-r.d/2)*k,r.w*k,r.d*k);x.strokeStyle='#f3f6fa';x.lineWidth=Math.max(2,T*k);x.strokeRect(ox+(r.x-r.w/2)*k,oy+(r.z-r.d/2)*k,r.w*k,r.d*k)});
 st.objects.filter(o=>o.type==='Tree').forEach(o=>{x.fillStyle='#2f7a4a';x.beginPath();x.arc(ox+o.x*k,oy+o.z*k,o.w*k/2,0,7);x.fill()})}
function openGallery(){const g=$('gallery');g.innerHTML='';
 TEMPLATES.concat([{name:'Blank plot',desc:'Start from scratch on an empty site.',tags:['empty'],def:null}]).forEach(t=>{const c=document.createElement('div');c.className='gcard';const cv=document.createElement('canvas');const st=t.def?makeTemplate(t.def):blank();thumb(cv,st);const info=document.createElement('div');info.className='ginfo';info.innerHTML=`<h3>${esc(t.name)}</h3><p>${esc(t.desc)}</p><div class="tags">${t.tags.map(x=>`<span>${esc(x)}</span>`).join('')}${t.def?`<span>${st.rooms.reduce((s,r)=>s+r.w*r.d,0).toFixed(0)} m²</span>`:''}</div>`;const b=document.createElement('button');b.className='primary';b.style.width='100%';b.textContent=t.def?'Use this design':'Start blank';b.onclick=()=>{S=st;sel=null;$('galleryModal').classList.remove('open');syncUI();rebuild();setView('3d');fitCamera();renderPanels();commit();toast(t.name+' loaded — make it your own')};info.append(b);c.append(cv,info);g.append(c)});
 $('galleryModal').classList.add('open')}
$('galleryBtn').onclick=openGallery;$('closeGallery').onclick=()=>$('galleryModal').classList.remove('open');$('galleryModal').onclick=e=>{if(e.target===$('galleryModal'))$('galleryModal').classList.remove('open')};

/* ---------- blueprint upload system ---------- */
let pendingBpData=null,pendingBpType='house';
function fillBpFloor(selected){const e=$('bpFloor');e.replaceChildren(...S.floors.map((_,i)=>new Option(floorName(i),i)));if(S.floors.length<MAX_FLOORS)e.add(new Option('New floor ('+floorName(S.floors.length)+')','new'));e.value=String(selected);if(!e.value)e.value='0'}
function chosenBpLevel(){if(pendingBpType==='lot')return 0;const v=$('bpFloor').value;if(v==='new'){if(S.floors.length<MAX_FLOORS)S.floors.push({h:kindHeight(S.site.kind,S.floors.length)});return S.floors.length-1}return clamp(Number(v)||0,0,S.floors.length-1)}
function initBpUI(){
  const dropzone=$('bpDropzone'),fileInput=$('bpFileInput');
  dropzone.onclick=()=>fileInput.click();
  dropzone.addEventListener('dragover',e=>{e.preventDefault();dropzone.classList.add('dragover')});
  dropzone.addEventListener('dragleave',()=>dropzone.classList.remove('dragover'));
  dropzone.addEventListener('drop',e=>{e.preventDefault();dropzone.classList.remove('dragover');if(e.dataTransfer.files.length)handleBpFiles(e.dataTransfer.files)});
  fileInput.onchange=e=>{if(e.target.files.length)handleBpFiles(e.target.files);e.target.value=''};

  // modal controls
  $('closeBpModal').onclick=cancelBlueprint;
  $('bpModal').onclick=e=>{if(e.target===$('bpModal'))cancelBlueprint()};
  $('bpTypeHouse').onclick=()=>{pendingBpType='house';$('bpFloorField').hidden=false;$('bpTypeHouse').classList.add('on');$('bpTypeHouse').style.cssText='';$('bpTypeLot').classList.remove('on');$('bpTypeLot').style.cssText='background:transparent;border-color:var(--line2);color:var(--text)'};
  $('bpTypeLot').onclick=()=>{pendingBpType='lot';$('bpFloorField').hidden=true;$('bpTypeLot').classList.add('on');$('bpTypeLot').style.cssText='';$('bpTypeHouse').classList.remove('on');$('bpTypeHouse').style.cssText='background:transparent;border-color:var(--line2);color:var(--text)'};
  $('bpOpacity').oninput=()=>$('bpOpOut').textContent=$('bpOpacity').value+'%';
  $('bpScale').oninput=()=>$('bpScOut').textContent=Number($('bpScale').value).toFixed(1)+'×';
  $('bpOffsetX').oninput=()=>$('bpOxOut').textContent=$('bpOffsetX').value+' m';
  $('bpOffsetZ').oninput=()=>$('bpOzOut').textContent=$('bpOffsetZ').value+' m';
  $('bpRotation').oninput=()=>$('bpRotOut').textContent=$('bpRotation').value+'°';
  $('bpConfirmBtn').onclick=confirmBpUpload;
}
// PDF blueprints render the selected page as an overlay.
async function blueprintDataUrl(file,pageNumber=1){
  if(file.type==='application/pdf'||/\.pdf$/i.test(file.name)){
    if(!window.pdfjsLib)throw Error('PDF preview library is unavailable. Try PNG or JPG.');
    pdfjsLib.GlobalWorkerOptions.workerSrc='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    const doc=await pdfjsLib.getDocument({data:new Uint8Array(await file.arrayBuffer())}).promise;
    try{
      const page=await doc.getPage(pageNumber),natural=page.getViewport({scale:1});
      const viewport=page.getViewport({scale:Math.min(2.2,1800/Math.max(natural.width,natural.height))});
      const canvas=document.createElement('canvas');canvas.width=Math.ceil(viewport.width);canvas.height=Math.ceil(viewport.height);
      const ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);
      await page.render({canvasContext:ctx,viewport}).promise;
      return canvas.toDataURL('image/jpeg',.91);
    }finally{await doc.destroy()}
  }
  return await new Promise((resolve,reject)=>{
    const reader=new FileReader();reader.onload=()=>resolve(reader.result);
    reader.onerror=()=>reject(Error('Could not read blueprint file'));reader.readAsDataURL(file);
  });
}
// Large photo scans are reduced so typical uploads fit browser storage.
async function compactBlueprintData(data,file){
  if(data.length<1500000||/\.svg$/i.test(file.name))return data;
  try{
    const img=await new Promise((resolve,reject)=>{const i=new Image();i.onload=()=>resolve(i);i.onerror=reject;i.src=data});
    const ratio=Math.min(1,1800/Math.max(img.naturalWidth,img.naturalHeight));
    const canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(img.naturalWidth*ratio));canvas.height=Math.max(1,Math.round(img.naturalHeight*ratio));
    const ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.drawImage(img,0,0,canvas.width,canvas.height);
    const smaller=canvas.toDataURL('image/jpeg',.86);
    return smaller.length<data.length?smaller:data;
  }catch(e){return data}
}
async function handleBpFiles(files){
  bpQueue.push(...Array.from(files));if(bpBusy)return;await nextBlueprint();
}
async function nextBlueprint(){
  const file=bpQueue.shift();if(!file){bpBusy=false;return}if(file.size>10*1024*1024){toast('File too large (max 10 MB)');return nextBlueprint()}bpBusy=true;activeBpFile=file;editBpId=null;calibration=null;boundaryPoints=[];calibrationPoints=[];
  $('bpScale').disabled=false;drawImageMarkers();$('pdfPicker').hidden=true;$('batchStatus').textContent=`${bpQueue.length} more files queued`;$('calibrationStatus').textContent='Uncalibrated';
  if(file.type==='application/pdf'||/\.pdf$/i.test(file.name)){try{const doc=await pdfjsLib.getDocument({data:new Uint8Array(await file.arrayBuffer())}).promise;const select=$('pdfPage');select.replaceChildren();for(let i=1;i<=doc.numPages;i++)select.add(new Option('Page '+i,i));await doc.destroy();$('pdfPicker').hidden=false;}catch(e){toast('PDF could not be read');return nextBlueprint()}}
  if(file.size>10*1024*1024){toast('File too large (max 10 MB)');return nextBlueprint()}
  if(!file.type.startsWith('image/')&&!/\.(png|jpe?g|webp|gif|svg|pdf)$/i.test(file.name)){
    toast('Use a JPG, PNG, SVG, or PDF blueprint');return nextBlueprint();
  }
  try{
    pendingBpData=await blueprintDataUrl(file);
    pendingBpData=await compactBlueprintData(pendingBpData,file);
    $('bpPreviewImg').src=pendingBpData;
    $('bpPreviewImg').style.display='block';$('bpPreviewEmpty').style.display='none';
    $('bpNameInput').value=file.name.replace(/\.[^.]+$/,'');
    $('bpConfirmBtn').textContent='Add blueprint to project';
    $('bpConfirmBtn').onclick=confirmBpUpload;
    $('bpConfirmBtn').disabled=false;
    $('bpOpacity').value=50;$('bpOpOut').textContent='50%';
    $('bpScale').value=1;$('bpScOut').textContent='1.0×';
    $('bpOffsetX').value=0;$('bpOxOut').textContent='0 m';
    $('bpOffsetZ').value=0;$('bpOzOut').textContent='0 m';
    $('bpRotation').value=0;$('bpRotOut').textContent='0°';
    pendingBpType='house';
    // Default floor: the one being edited if it has no plan yet, else the lowest floor without one, else a new floor.
    {const has=i=>S.blueprints.some(b=>b.type==='house'&&lvl(b)===i),free=S.floors.findIndex((_,i)=>!has(i));fillBpFloor(!has(curFloor)?curFloor:free>=0?free:S.floors.length<MAX_FLOORS?'new':curFloor)}$('bpFloorField').hidden=false;
    $('bpTypeHouse').classList.add('on');$('bpTypeHouse').style.cssText='';
    $('bpTypeLot').classList.remove('on');$('bpTypeLot').style.cssText='background:transparent;border-color:var(--line2);color:var(--text)';
    $('bpModal').classList.add('open');
  }catch(e){toast(e.message||'Blueprint could not be opened');console.error(e);nextBlueprint()}
}
async function confirmBpUpload(){
  if(!pendingBpData)return;
  if(S.blueprints.length>=20){toast('A project can hold up to 20 blueprint images. Use Copy floor for repeated typical floors.');return}
  if(!validateBlueprint())return;
  $('bpConfirmBtn').disabled=true;
  try{pendingBpData=await storeBlueprint(pendingBpData)}catch(e){$('bpConfirmBtn').disabled=false;toast(e.message);return}
  const img=new Image();
  img.onload=()=>{
    const bp={
      id:S.nextBpId++,
      name:$('bpNameInput').value.trim()||'Blueprint',
      type:pendingBpType,
      data:pendingBpData,
      opacity:Number($('bpOpacity').value),
      scale:Number($('bpScale').value),
      offsetX:Number($('bpOffsetX').value),
      offsetZ:Number($('bpOffsetZ').value),
      rotation:Number($('bpRotation').value),
      metersPerPixel:calibration,boundary:boundaryPoints.slice(),
      level:chosenBpLevel(),
      visible:true,
      w:img.naturalWidth,
      h:img.naturalHeight
    };
    S.blueprints.push(bp);if(bp.type==='house')curFloor=bp.level;
    bpImages.set(bp.id,img);
    pendingBpData=null;
    applyLotBlueprint(bp);$('bpModal').classList.remove('open');
    rebuild();renderBpList();
    draw2D();
    commit();
    // Every house plan gets a suggestion for its own floor; other floors are never changed.
    const suggest=bp.type==='house';
    if(suggest){comparisonSuggestion=makeBlueprintSuggestion(bp,img);comparisonBlueprint=bp}if(bpQueue.length)nextBlueprint();else{bpBusy=false;if(suggest)showDesignSuggestion(bp,img)}
  };
  img.onerror=()=>{$('bpConfirmBtn').disabled=false;toast('Image could not be retrieved; retry or reload')};
  img.src=pendingBpData;
}
function ensureBpImage(bp){
  if(bpImages.has(bp.id))return bpImages.get(bp.id);
  if(!bp.data)return null;
  const img=new Image();
  img.onload=()=>{if(view==='2d')draw2D()};img.onerror=()=>toast('Blueprint unavailable in this server session; re-upload the image');
  img.src=bp.data;
  bpImages.set(bp.id,img);
  return img;
}
function drawBlueprintOverlays(ctx,s){
  if(!S.blueprints||!S.blueprints.length)return;
  for(const bp of S.blueprints){
    if(!bp.visible||(bp.type==='house'&&lvl(bp)!==curFloor))continue;
    const img=ensureBpImage(bp);
    if(!img||!img.complete||!img.naturalWidth)continue;
    ctx.save();
    ctx.globalAlpha=bp.opacity/100;
    // position: center of plot + offset
    const cx=pv.ox+bp.offsetX*s;
    const cy=pv.oy+bp.offsetZ*s;
    ctx.translate(cx,cy);
    ctx.rotate((bp.rotation||0)*Math.PI/180);
    // size: scale relative to plot. default: image maps to plot width
    const imgW=(bp.metersPerPixel?bp.w*bp.metersPerPixel:S.site.width)*s*bp.scale;
    const imgH=imgW*(img.naturalHeight/img.naturalWidth);
    ctx.drawImage(img,-imgW/2,-imgH/2,imgW,imgH);
    ctx.restore();
  }
  ctx.globalAlpha=1;
}
function renderBpList(){
  const el=$('bpList');
  if(!el)return;
  el.innerHTML='';
  if(!S.blueprints||!S.blueprints.length)return;
  S.blueprints.forEach(bp=>{
    const div=document.createElement('div');
    div.className='bp-item';
    const img=ensureBpImage(bp);
    const thumbSrc=img&&img.src?img.src:'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg"/>';
    div.innerHTML=`
      <img class="bp-thumb" src="${esc(thumbSrc)}" alt="">
      <div class="bp-info">
        <div class="bp-badge ${bp.type}">${bp.type==='house'?'🏠 House':'🗺 Lot'}</div>
        <div class="bp-name">${esc(bp.name)}</div>
        <div class="bp-meta">${bp.type==='house'?floorName(lvl(bp))+' · ':''}${bp.opacity}% opacity · ${bp.scale.toFixed(1)}× scale</div>
      </div>
      <button class="bp-suggest" data-bpid="${bp.id}" title="Suggest a design from this blueprint" aria-label="Suggest a design">✦</button>
      <button class="bp-toggle ${bp.visible?'on':''}" data-bpid="${bp.id}" title="Toggle visibility">${bp.visible?'👁':'🚫'}</button>
      <button class="bp-remove" data-bpid="${bp.id}" title="Remove">✕</button>
    `;
    div.querySelector('.bp-suggest').onclick=function(){
      const image=ensureBpImage(bp);
      if(image&&image.complete&&image.naturalWidth)showDesignSuggestion(bp,image);
      else if(image){image.addEventListener('load',()=>showDesignSuggestion(bp,image),{once:true});toast('Loading blueprint preview…')}
      else toast('This blueprint has no preview image');
    };
    // toggle visibility
    div.querySelector('.bp-toggle').onclick=function(){const b=S.blueprints.find(x=>x.id===bp.id);if(b){b.visible=!b.visible;renderBpList();draw2D();commit()}};
    // remove
    div.querySelector('.bp-remove').onclick=function(){S.blueprints=S.blueprints.filter(x=>x.id!==bp.id);bpImages.delete(bp.id);renderBpList();draw2D();commit();toast('Blueprint removed')};
    // click to edit controls
    div.querySelector('.bp-info').style.cursor='pointer';
    div.querySelector('.bp-info').onclick=function(){openBpEdit(bp.id)};
    el.append(div);
  });
}
function openBpEdit(id){
  if(bpBusy)return toast('Finish or skip the queued uploads first');
  const bp=S.blueprints.find(x=>x.id===id);
  if(!bp)return;
  editBpId=id;calibration=bp.metersPerPixel||null;$('bpScale').disabled=!!calibration;boundaryPoints=(bp.boundary||[]).slice();calibrationPoints=[];$('pdfPicker').hidden=true;$('calibrationStatus').textContent=calibration?calibration.toFixed(5)+' m/pixel':'Uncalibrated';
  pendingBpData=bp.data;
  $('bpPreviewImg').src=bp.data;
  $('bpPreviewImg').style.display='block';
  $('bpPreviewEmpty').style.display='none';
  $('bpNameInput').value=bp.name;
  $('bpOpacity').value=bp.opacity;$('bpOpOut').textContent=bp.opacity+'%';
  $('bpScale').value=bp.scale;$('bpScOut').textContent=bp.scale.toFixed(1)+'×';
  $('bpOffsetX').value=bp.offsetX;$('bpOxOut').textContent=bp.offsetX+' m';
  $('bpOffsetZ').value=bp.offsetZ;$('bpOzOut').textContent=bp.offsetZ+' m';
  $('bpRotation').value=bp.rotation;$('bpRotOut').textContent=bp.rotation+'°';
  pendingBpType=bp.type;fillBpFloor(lvl(bp));$('bpFloorField').hidden=bp.type==='lot';
  if(bp.type==='house'){$('bpTypeHouse').classList.add('on');$('bpTypeHouse').style.cssText='';$('bpTypeLot').classList.remove('on');$('bpTypeLot').style.cssText='background:transparent;border-color:var(--line2);color:var(--text)'}
  else{$('bpTypeLot').classList.add('on');$('bpTypeLot').style.cssText='';$('bpTypeHouse').classList.remove('on');$('bpTypeHouse').style.cssText='background:transparent;border-color:var(--line2);color:var(--text)'}
  // change confirm button to "Update"
  $('bpConfirmBtn').textContent='Update blueprint';
  $('bpConfirmBtn').disabled=false;
  $('bpConfirmBtn').onclick=function(){
    if(!validateBlueprint())return;
    bp.metersPerPixel=calibration;bp.boundary=boundaryPoints.slice();
    bp.name=$('bpNameInput').value.trim()||'Blueprint';
    bp.type=pendingBpType;bp.level=chosenBpLevel();if(bp.type==='house')curFloor=bp.level;
    bp.opacity=Number($('bpOpacity').value);
    bp.scale=Number($('bpScale').value);
    bp.offsetX=Number($('bpOffsetX').value);
    bp.offsetZ=Number($('bpOffsetZ').value);
    bp.rotation=Number($('bpRotation').value);
    applyLotBlueprint(bp);$('bpModal').classList.remove('open');
    rebuild();renderBpList();
    draw2D();
    commit();
    toast('Blueprint updated');
    // reset confirm button for next add
    $('bpConfirmBtn').textContent='Add blueprint to project';
    $('bpConfirmBtn').onclick=confirmBpUpload;
  };
  $('bpModal').classList.add('open');
}
/* ---------- Blueprint-assisted design recommendation ---------- */
let pendingDesignSuggestion=null;

// Contrast and line changes indicate drawing density, not recognized wall labels.
// This runs locally in the browser; no image or floor plan is sent to a service.
function inspectBlueprintImage(img,bp){
  let aspect=clamp(img.naturalWidth/Math.max(1,img.naturalHeight),.5,2.5),detail=0;
  try{
    const canvas=document.createElement('canvas');canvas.width=128;canvas.height=128;
    const c=canvas.getContext('2d',{willReadFrequently:true});c.drawImage(img,0,0,128,128);
    const data=c.getImageData(0,0,128,128).data,lum=new Float32Array(128*128);
    for(let i=0;i<lum.length;i++){const k=i*4;lum[i]=(data[k]*.2126+data[k+1]*.7152+data[k+2]*.0722)*(data[k+3]/255)+255*(1-data[k+3]/255)}
    let edges=0,comparisons=0;
    for(let y=2;y<126;y++)for(let x=2;x<126;x++){
      const i=y*128+x;
      if(Math.abs(lum[i]-lum[i+1])>48)edges++;
      if(Math.abs(lum[i]-lum[i+128])>48)edges++;
      comparisons+=2;
    }
    detail=clamp(edges/comparisons*5,0,1);
  }catch(e){detail=0} // Canvas may reject a complex externally-linked SVG.
  // Quarter-turns swap the effective dimensions of the blueprint overlay.
  const turn=((bp.rotation%180)+180)%180;
  if(turn>45&&turn<135)aspect=1/aspect;
  return{aspect,detail};
}
function roomLayoutBounds(rooms){
  const xs=rooms.map(r=>r.x-r.w/2),xe=rooms.map(r=>r.x+r.w/2),zs=rooms.map(r=>r.z-r.d/2),ze=rooms.map(r=>r.z+r.d/2);
  return{x0:Math.min(...xs),x1:Math.max(...xe),z0:Math.min(...zs),z1:Math.max(...ze)};
}
function makeBlueprintSuggestion(bp,img){
  const info=inspectBlueprintImage(img,bp),plotArea=S.site.width*S.site.depth;
  let templateIndex;
  if(bp.type==='lot'||plotArea<440||S.site.width<19||S.site.depth<16||info.aspect<.82)templateIndex=1; // compact home
  else if(info.aspect>1.32||info.detail>.36)templateIndex=2; // wider family arrangement
  else templateIndex=0; // medium layout
  // Upper floors: no garage template, and the layout is fitted to the outline of the floor below.
  const L=bp.type==='house'?lvl(bp):0,below=L?S.rooms.filter(r=>lvl(r)===L-1):[];
  if(L&&templateIndex===2)templateIndex=0;
  const source=TEMPLATES[templateIndex],starter=makeTemplate(source.def),b=roomLayoutBounds(starter.rooms);
  const width=b.x1-b.x0,depth=b.z1-b.z0;
  const targetW=Math.min(S.site.width-2.5,S.site.width*(bp.type==='lot'?.46:.58)*clamp(bp.scale,.75,1.2));
  const targetD=Math.min(S.site.depth-2.5,targetW/Math.max(.55,info.aspect));
  let sx=clamp(targetW/width,.90,Math.min(1.45,(S.site.width-1.5)/width));
  let sz=clamp(targetD/depth,.90,Math.min(1.45,(S.site.depth-1.5)/depth));
  const midx=(b.x0+b.x1)/2,midz=(b.z0+b.z1)/2;
  let moveX=clamp(bp.offsetX,-S.site.width/2+width*sx/2+.5,S.site.width/2-width*sx/2-.5);
  let moveZ=clamp(bp.offsetZ,-S.site.depth/2+depth*sz/2+.5,S.site.depth/2-depth*sz/2-.5);
  if(below.length){const fb=roomLayoutBounds(below);sx=clamp((fb.x1-fb.x0)/width,.5,1.6);sz=clamp((fb.z1-fb.z0)/depth,.5,1.6);moveX=(fb.x0+fb.x1)/2;moveZ=(fb.z0+fb.z1)/2}
  for(const r of starter.rooms){r.x=(r.x-midx)*sx+moveX;r.z=(r.z-midz)*sz+moveZ;r.w=r.w*sx;r.d=r.d*sz}
  // Keep fixture dimensions realistic. Their positions follow the scaled rooms.
  starter.objects=starter.objects.filter(o=>!CAT[o.type]?.out);
  for(const o of starter.objects){o.x=(o.x-midx)*sx+moveX;o.z=(o.z-midz)*sz+moveZ;o.level=L}
  for(const r of starter.rooms)r.level=L;
  const nBeds=starter.rooms.filter(r=>/bed/i.test(r.name)).length;
  const reason=bp.type==='lot'
    ?'A compact, site-conscious home concept is proposed for the uploaded lot plan.'
    :info.aspect>1.32?'A wider house concept is proposed to reflect the blueprint’s visual proportions.'
    :info.aspect<.82?'A compact layout is proposed for this portrait-oriented blueprint.'
    :info.detail>.36?'A larger room arrangement is proposed for a line-dense blueprint.'
    :'A balanced home concept is proposed for the uploaded blueprint.';
  const where=L?`For ${floorName(L)}: ${below.length?'the layout is fitted to the outline of '+floorName(L-1)+'. ':''}`:'';
  return {name:source.name,rooms:starter.rooms,objects:starter.objects,roof:source.def.site.roof,reason:where+reason,nBeds,level:L};
}
function paintConceptPreview(canvas,rooms,objects,bp){
  const ctx=canvas.getContext('2d'),w=canvas.width,h=canvas.height,sc=Math.min((w-35)/S.site.width,(h-35)/S.site.depth);
  const ox=w/2,oy=h/2,x0=ox-S.site.width*sc/2,y0=oy-S.site.depth*sc/2;
  ctx.clearRect(0,0,w,h);ctx.fillStyle='#0b131d';ctx.fillRect(0,0,w,h);
  ctx.fillStyle='#294936';ctx.fillRect(x0,y0,S.site.width*sc,S.site.depth*sc);
  const img=ensureBpImage(bp);
  if(img&&img.complete&&img.naturalWidth){
    ctx.save();ctx.beginPath();ctx.rect(x0,y0,S.site.width*sc,S.site.depth*sc);ctx.clip();
    ctx.globalAlpha=Math.min(.28,bp.opacity/100);
    ctx.translate(ox+bp.offsetX*sc,oy+bp.offsetZ*sc);
    ctx.rotate((bp.rotation||0)*Math.PI/180);
    const iw=(bp.metersPerPixel?bp.w*bp.metersPerPixel:S.site.width)*sc*bp.scale,ih=iw*img.naturalHeight/img.naturalWidth;
    ctx.drawImage(img,-iw/2,-ih/2,iw,ih);ctx.restore();
  }
  ctx.lineWidth=1.8;ctx.strokeStyle='#e8bb57';ctx.strokeRect(x0,y0,S.site.width*sc,S.site.depth*sc);
  for(const r of rooms){
    const x=ox+(r.x-r.w/2)*sc,y=oy+(r.z-r.d/2)*sc;
    ctx.fillStyle=r.c;ctx.globalAlpha=.92;ctx.fillRect(x,y,r.w*sc,r.d*sc);ctx.globalAlpha=1;
    ctx.strokeStyle='#f3f6fa';ctx.lineWidth=Math.max(2,T*sc);ctx.strokeRect(x,y,r.w*sc,r.d*sc);
    ctx.fillStyle='#101820';ctx.font='bold 11px Inter,system-ui,sans-serif';ctx.textAlign='center';
    ctx.fillText(r.name,ox+r.x*sc,oy+r.z*sc+4,Math.max(15,r.w*sc-3));
  }
  // Doors and windows show as small markers over the suggested wall positions.
  for(const o of objects.filter(o=>isOpening(o.type))){
    ctx.save();ctx.translate(ox+o.x*sc,oy+o.z*sc);ctx.rotate(-o.rot*Math.PI/180);
    ctx.strokeStyle=o.type==='Door'?'#f6c888':'#77def1';ctx.lineWidth=3;
    ctx.beginPath();ctx.moveTo(-o.w*sc/2,0);ctx.lineTo(o.w*sc/2,0);ctx.stroke();ctx.restore();
  }
}
function closeDesignSuggestion(){
  $('designSuggestModal').classList.remove('open');pendingDesignSuggestion=null;
}
function keepCurrentDesign(){
  closeDesignSuggestion();setView('2d');toast('Current design kept — blueprint overlay is available in Site');
}
function applySuggestedDesign(){
  const suggestion=pendingDesignSuggestion;if(!suggestion)return;
  // Preserve site, environment, river, uploaded blueprints and outdoor objects.
  // Only the suggestion's own floor is replaced; every other floor and outdoor item is kept.
  const L=suggestion.level||0,keep=S.objects.filter(o=>CAT[o.type]?.out||lvl(o)!==L);
  if(S.rooms.length-S.rooms.filter(r=>lvl(r)===L).length+suggestion.rooms.length>MAX_ROOMS){toast('Applying would pass the '+MAX_ROOMS+'-room limit');return}
  saveVersion('Before suggestion');
  S.rooms=S.rooms.filter(r=>lvl(r)!==L).concat(suggestion.rooms.map(r=>({...r,level:L,id:S.nextId++})));
  S.objects=keep.concat(suggestion.objects.map(o=>({...o,level:L,id:S.nextId++})));curFloor=L;
  if(!L)S.site.roof=suggestion.roof;sel=null;saveVersion('Suggested design');comparisonSuggestion=suggestion;
  closeDesignSuggestion();syncUI();rebuild();renderPanels();commit();setView('2d');
  toast('Suggested design applied — edit it on the floor plan or 3D view');
}
function showDesignSuggestion(bp,img){
  pendingDesignSuggestion=makeBlueprintSuggestion(bp,img);comparisonSuggestion=pendingDesignSuggestion;comparisonBlueprint=bp;
  const suggested=pendingDesignSuggestion,area=rs=>rs.reduce((sum,r)=>sum+r.w*r.d,0);
  $('suggestBlueprintName').textContent='Uploaded blueprint: '+bp.name;
  $('suggestReason').textContent=suggested.reason+' Compare both layouts before making a choice.';
  $('suggestDesignName').textContent=suggested.name+' concept';
  const L=suggested.level||0,curRooms=S.rooms.filter(r=>lvl(r)===L),curObjs=S.objects.filter(o=>lvl(o)===L&&!CAT[o.type]?.out);
  $('suggestTitle').textContent='Suggested design for '+floorName(L);
  $('currentDesignMeta').textContent=`${floorName(L)}: ${curRooms.length} rooms · ${area(curRooms).toFixed(1)} m² · ${curObjs.length} objects. Your design will remain unchanged if you keep it. Other floors are never changed.`;
  $('suggestDesignMeta').textContent=`${suggested.rooms.length} rooms (${suggested.nBeds} bedrooms) · ${area(suggested.rooms).toFixed(1)} m² · ${suggested.objects.length} fixtures/openings. Saved as a new design version if applied.`;
  // Readiness compares the whole building with and without the suggested floor.
  const withRooms=S.rooms.filter(r=>lvl(r)!==L).concat(suggested.rooms),withObjs=S.objects.filter(o=>CAT[o.type]?.out||lvl(o)!==L).concat(suggested.objects);
  $('currentDesignMeta').textContent+=' · Readiness '+assessReadiness(S.rooms,S.objects,S.site.roof).score+'/100';$('suggestDesignMeta').textContent+=' · Readiness '+assessReadiness(withRooms,withObjs,L?S.site.roof:suggested.roof).score+'/100';
  paintConceptPreview($('currentDesignPreview'),curRooms,curObjs,bp);
  paintConceptPreview($('suggestedDesignPreview'),suggested.rooms,suggested.objects,bp);
  $('designSuggestModal').classList.add('open');
}
$('suggestKeep').onclick=keepCurrentDesign;
$('suggestClose').onclick=keepCurrentDesign;
$('suggestUse').onclick=applySuggestedDesign;
$('designSuggestModal').onclick=e=>{if(e.target===$('designSuggestModal'))keepCurrentDesign()};
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&$('designSuggestModal').classList.contains('open')){e.preventDefault();e.stopPropagation();keepCurrentDesign()}},true);

/* EXTENSIONS */
let bpQueue=[],bpBusy=false,activeBpFile=null,editBpId=null,calibration=null,calibrationPoints=[],boundaryPoints=[],imageClickMode=null;
let comparisonSuggestion=null,comparisonBlueprint=null,hazardRequest=0;
async function storeBlueprint(data){
 if(!data.startsWith('data:'))return data;
 const blob=await (await fetch(data)).blob();
 // Rasterize all formats, including SVG, so active content is never served.
 const img=await new Promise((resolve,reject)=>{const i=new Image();i.onload=()=>resolve(i);i.onerror=()=>reject(Error('Invalid blueprint'));i.src=data});
 const c=document.createElement('canvas');if(Math.max(img.naturalWidth,img.naturalHeight)>8000)throw Error('Blueprint must be at most 8000 pixels per side');const k=1;c.width=Math.round(img.naturalWidth*k);c.height=Math.round(img.naturalHeight*k);c.getContext('2d').drawImage(img,0,0,c.width,c.height);
 const raster=await new Promise(resolve=>c.toBlob(resolve,'image/png'));
 const f=new FormData();f.append('file',raster,'blueprint.png');f.append('csrf',csrf);
 const res=await fetch('blueprint.php',{method:'POST',body:f});const out=await res.json();if(!res.ok)throw Error(out.error||'Upload failed');
 // Calibration uses the preview pixel dimensions; preserve those dimensions.
 if(calibration)calibration*=img.naturalWidth/c.width;
 return out.url;
}
$('pdfPage').onchange=async()=>{if(!activeBpFile)return;$('bpConfirmBtn').disabled=true;try{pendingBpData=await blueprintDataUrl(activeBpFile,Number($('pdfPage').value));$('bpPreviewImg').src=pendingBpData;calibration=null;boundaryPoints=[];calibrationPoints=[];$('bpScale').disabled=false;$('calibrationStatus').textContent='New page: calibrate again';}catch(e){toast(e.message)}finally{$('bpConfirmBtn').disabled=false}};
$('calibrateBp').onclick=()=>{imageClickMode='scale';calibrationPoints=[];drawImageMarkers();$('calibrationStatus').textContent='Click the first and second reference point on the image';};
$('markBoundary').onclick=()=>{imageClickMode='boundary';boundaryPoints=[];drawImageMarkers();$('calibrationStatus').textContent='Click each lot corner in order. Set scale separately; corners save on confirmation.';};
$('clearBoundary').onclick=()=>{boundaryPoints=[];imageClickMode=null;drawImageMarkers();toast('Image rectangle will be used')};
$('bpPreviewImg').onclick=e=>{if(!imageClickMode)return;const b=e.target.getBoundingClientRect(),q={x:(e.clientX-b.left)/b.width,z:(e.clientY-b.top)/b.height};if(imageClickMode==='scale'){calibrationPoints.push(q);if(calibrationPoints.length===2){imageClickMode=null;$('calibrationStatus').textContent='Two points selected. Enter their real distance, then Set scale.'}}else{boundaryPoints.push(q);$('calibrationStatus').textContent=boundaryPoints.length+' boundary corners selected';}drawImageMarkers()};
$('applyCalibration').onclick=()=>{const d=Number($('realDistance').value),img=$('bpPreviewImg');if(calibrationPoints.length!==2||!Number.isFinite(d)||d<=0)return toast('Select two points and enter a positive distance');const [a,b]=calibrationPoints,px=Math.hypot((a.x-b.x)*img.naturalWidth,(a.z-b.z)*img.naturalHeight);if(px<2)return toast('Choose points farther apart');calibration=d/px;$('bpScale').value=1;$('bpScale').disabled=true;$('bpScOut').textContent='1.0×';$('calibrationStatus').textContent=calibration.toFixed(5)+' m/pixel · calibrated';};
// Cancel advances the queue and never silently drops the remaining files.
function validateBlueprint(){
 if(pendingBpType==='lot'&&!calibration){toast('Calibrate the lot blueprint before applying its dimensions');return false}
 if(boundaryPoints.length>0&&boundaryPoints.length<3){toast('Mark at least three boundary corners or use the image rectangle');return false}
 if(boundaryPoints.length>=3){const area=Math.abs(boundaryPoints.reduce((sum,p,i)=>{const q=boundaryPoints[(i+1)%boundaryPoints.length];return sum+p.x*q.z-q.x*p.z},0));if(area<.0001){toast('The boundary must enclose an area');return false}}
 return true;
}
function drawImageMarkers(){const wrap=$('bpPreviewWrap'),img=$('bpPreviewImg');wrap.style.position='relative';wrap.querySelectorAll('.bp-marker').forEach(n=>n.remove());const rect=img.getBoundingClientRect(),base=wrap.getBoundingClientRect();[...boundaryPoints,...calibrationPoints].forEach((p,i)=>{const m=document.createElement('span');m.className='bp-marker';m.textContent=i+1;m.style.cssText=`position:absolute;pointer-events:none;left:${rect.left-base.left+p.x*rect.width}px;top:${rect.top-base.top+p.z*rect.height}px;transform:translate(-50%,-50%);background:#ffdf78;color:#111;border:1px solid #111;border-radius:50%;min-width:18px;font-size:11px`;wrap.append(m)})}
$('bpPreviewImg').addEventListener('load',drawImageMarkers);
function cancelBlueprint(){pendingBpData=null;$('bpModal').classList.remove('open');if(bpBusy)nextBlueprint()}
function applyLotBlueprint(bp){
 if(bp.type!=='lot')return;
 if(!bp.metersPerPixel){toast('Lot saved as overlay: calibrate it to set plot dimensions');return}
 const points=bp.boundary?.length>=3?bp.boundary:[{x:0,z:0},{x:1,z:0},{x:1,z:1},{x:0,z:1}];
 const a=(bp.rotation||0)*Math.PI/180,w=bp.w*bp.metersPerPixel*bp.scale,d=bp.h*bp.metersPerPixel*bp.scale;
 const worldPoints=points.map(p=>{const x=(p.x-.5)*w,z=(p.z-.5)*d;return{x:x*Math.cos(a)-z*Math.sin(a)+bp.offsetX,z:x*Math.sin(a)+z*Math.cos(a)+bp.offsetZ}});
 S.site.boundary=worldPoints;S.site.width=Math.max(...worldPoints.map(p=>Math.abs(p.x)))*2;S.site.depth=Math.max(...worldPoints.map(p=>Math.abs(p.z)))*2;
 document.querySelector('[data-s="site.width"]').min=.1;document.querySelector('[data-s="site.depth"]').min=.1;
 syncUI();rebuild();fit2D();
}
function lineMaterial(color,opacity=1){const key='line-'+color+opacity;if(!matCache.has(key))matCache.set(key,new THREE.LineBasicMaterial({color,transparent:opacity<1,opacity}));return matCache.get(key)}
function buildLotBoundary3D(){if(!S.site.boundary?.length)return;const pts=S.site.boundary.concat(S.site.boundary[0]).map(p=>new THREE.Vector3(p.x,terrainH(p.x,p.z)+.15,p.z));const g=new THREE.BufferGeometry().setFromPoints(pts);world.add(new THREE.Line(g,lineMaterial('#ffdf78')))}
function drawLotBoundary(ctx,s){if(!S.site.boundary?.length)return;ctx.save();ctx.strokeStyle='#ffdf78';ctx.lineWidth=3;ctx.beginPath();S.site.boundary.forEach((p,i)=>ctx[i?'lineTo':'moveTo'](pv.ox+p.x*s,pv.oy+p.z*s));ctx.closePath();ctx.stroke();ctx.restore()}
function saveVersion(name){S.versions??=[];S.versions.push({name:name+' · '+new Date().toLocaleString(),rooms:structuredClone(S.rooms),objects:structuredClone(S.objects),roof:S.site.roof,floors:structuredClone(S.floors)});}
function renderVersions(){const e=$('versionList');e.replaceChildren();(S.versions||[]).forEach((v,i)=>e.add(new Option(v.name,i)));$('restoreVersion').disabled=!e.options.length;}
$('restoreVersion').onclick=()=>{const v=S.versions?.[Number($('versionList').value)];if(!v)return;saveVersion('Before restore');S.rooms=structuredClone(v.rooms);S.objects=structuredClone(v.objects);S.site.roof=v.roof;if(v.floors)S.floors=structuredClone(v.floors);S.nextId=Math.max(1,...S.rooms.concat(S.objects).map(o=>o.id+1));saveVersion('Restored '+v.name);sel=null;syncUI();rebuild();renderPanels();commit()};
function drawGhostPlan(ctx,s){if($('compareMode').value!=='ghost'||!comparisonSuggestion||(comparisonSuggestion.level||0)!==curFloor)return;ctx.save();ctx.globalAlpha=.5;ctx.strokeStyle='#60ffd2';ctx.lineWidth=3;for(const r of comparisonSuggestion.rooms)ctx.strokeRect(pv.ox+(r.x-r.w/2)*s,pv.oy+(r.z-r.d/2)*s,r.w*s,r.d*s);ctx.restore()}
function buildComparison(){
 const mode=$('compareMode').value;$('comparePanel').style.display=mode==='split'&&comparisonSuggestion?'block':'none';
 if(mode==='split'&&comparisonSuggestion&&comparisonBlueprint)paintConceptPreview($('workspaceCompare'),comparisonSuggestion.rooms,comparisonSuggestion.objects,comparisonBlueprint);
 if(!world||mode!=='ghost'||!comparisonSuggestion)return;
 const g=new THREE.Group();g.userData.comparison=true;for(const r of comparisonSuggestion.rooms){const wh=floorH(lvl(r)),geo=new THREE.BoxGeometry(r.w,wh,r.d),edge=new THREE.EdgesGeometry(geo);geo.dispose();const mesh=new THREE.LineSegments(edge,lineMaterial('#60ffd2',.6));mesh.position.set(r.x,roomBase(r)+FLOOR+wh/2,r.z);g.add(mesh)}world.add(g);
}
$('compareMode').onchange=()=>{if(!comparisonSuggestion){$('compareMode').value='off';return toast('Generate a suggestion with the ✦ button beside a blueprint first')}rebuild()};
function assessReadiness(rooms=S.rooms,objects=S.objects,roof=S.site.roof){
 const water=S.site.ground+S.env.flood,points=rooms.flatMap(r=>[-1,0,1].flatMap(a=>[-1,0,1].map(b=>({x:r.x+a*r.w/2,z:r.z+b*r.d/2}))));
 let slope=0;for(const p of points){const gx=terrainH(p.x+.25,p.z)-terrainH(p.x-.25,p.z),gz=terrainH(p.x,p.z+.25)-terrainH(p.x,p.z-.25);slope=Math.max(slope,Math.atan(Math.hypot(gx,gz)/.5)*180/Math.PI)}
 const elevations=points.map(p=>terrainH(p.x,p.z)),river=points.length?Math.min(...points.map(p=>riverDist(p.x,p.z))):Infinity;
 const clearance=rooms.length?Math.min(...rooms.map(r=>roomBase(r)+FLOOR-water)):null;
 // A door connects rooms only when its segment lies on both room edges;
 // room adjacency alone is never treated as a traversable route.
 // Doors and windows only belong to rooms on their own floor.
 const touches=(r,o)=>{if(lvl(r)!==lvl(o))return false;const dx=Math.abs(o.x-r.x),dz=Math.abs(o.z-r.z),horizontal=Math.abs(Math.sin((o.rot||0)*Math.PI/180))<.5;return horizontal?Math.abs(dz-r.d/2)<.22&&dx+o.w/2<=r.w/2+.15:Math.abs(dx-r.w/2)<.22&&dz+o.w/2<=r.d/2+.15};
 const graph=rooms.map(()=>new Set()),exitRooms=new Set();let exits=0;
 for(const o of objects.filter(o=>o.type==='Door'&&o.w>=.75)){const ids=rooms.map((r,i)=>touches(r,o)?i:-1).filter(i=>i>=0);if(ids.length===1){if(!lvl(o)){exits++;exitRooms.add(ids[0])}}else if(ids.length===2){graph[ids[0]].add(ids[1]);graph[ids[1]].add(ids[0]);}}
 const reached=new Set(exitRooms),queue=[...reached];while(queue.length){const i=queue.shift();for(const j of graph[i])if(!reached.has(j)){reached.add(j);queue.push(j)}}
 const noWindow=rooms.filter(r=>!objects.some(o=>o.type==='Window'&&touches(r,o))).length,narrow=rooms.filter(r=>/hall/i.test(r.name)&&Math.min(r.w,r.d)<1.2).length,unreachable=rooms.filter((r,i)=>!lvl(r)&&!reached.has(i)).length,upper=rooms.filter(r=>lvl(r)>0).length;
 const beds=rooms.filter(r=>/bed/i.test(r.name)&&roomBase(r)+FLOOR<=water).length;
 // The footprint check uses Floor 1, the part of the building that meets the ground.
 const ground=rooms.filter(r=>!lvl(r));
 const minX=Math.min(...ground.map(r=>r.x-r.w/2)),maxX=Math.max(...ground.map(r=>r.x+r.w/2)),minZ=Math.min(...ground.map(r=>r.z-r.d/2)),maxZ=Math.max(...ground.map(r=>r.z+r.d/2));
 // Sample occupancy in the bounding box; this avoids double-counting overlapping rooms.
 let occupied=0;for(let x=0;x<30;x++)for(let z=0;z<30;z++)if(ground.some(r=>inRoom(r,minX+(x+.5)*(maxX-minX)/30,minZ+(z+.5)*(maxZ-minZ)/30,0)))occupied++;
 const irregular=ground.length>0&&occupied/900<.75,large=rooms.some(r=>/garage/i.test(r.name))||objects.some(o=>o.type==='Door'&&o.w>2.4);
 const hz=S.hazard?.results||{},floodClass=hz.flood?.level,landClass=hz.landslide?.level,surgeClass=hz.surge?.level;
 const terrainRisk=Math.min(100,(slope>25?55:slope>10?25:5)+(river<10?25:river<30?10:0)+(elevations.some(h=>h<water)?20:0));
 const structureRisk=Math.min(100,(S.site.wall>3.3?20:5)+(roof==='off'?25:roof==='gable'?15:5)+(irregular?25:0)+(large?20:0)+(clearance!==null&&clearance<.3?25:0));
 const planRisk=Math.min(100,(exits<2?25:0)+(unreachable?35:0)+(narrow?15:0)+Math.min(15,noWindow*5)+(beds?20:0));
 const score=rooms.length?Math.max(0,Math.round(100-(terrainRisk+structureRisk+planRisk)/3)):0;
 const rating=n=>n>=60?'High':n>=30?'Medium':'Low';
 const flood=Math.max(clearance===null?50:clearance<0?80:clearance<.3?45:10,floodClass===3?80:floodClass===2?50:floodClass===1?25:0);
 const rows=[['Flood',rating(flood),clearance!==null&&clearance<.3?'Raise the floor at least '+(.3-clearance).toFixed(2)+' m for the selected water scenario and 0.3 m screening margin.':'Verify drainage, local flood datum and an evacuation route.'],['Landslide',rating(Math.max(terrainRisk,landClass===3?85:landClass===2?55:landClass===1?25:0)),'Obtain geotechnical slope review; move away from unstable slopes and river banks.'],['Storm surge',surgeClass==null?'Unknown':rating(surgeClass>=3?85:surgeClass===2?50:20),'Verify coastal evacuation and design elevation with local authorities.'],['Typhoon wind',rating(structureRisk),'Have an engineer design roof tie-downs, opening protection and the continuous load path.'],['Earthquake',rating(structureRisk+(irregular?15:0)),'Obtain PHIVOLCS fault/liquefaction screening and engineering review of walls and large openings.']];
 return {elevationRange:elevations.length?[Math.min(...elevations),Math.max(...elevations)]:null,score,rows,terrainRisk,structureRisk,planRisk,slope,river,clearance,exits,noWindow,narrow,unreachable,upper,beds,irregular,large,points:rooms.map((r,i)=>({name:(lvl(r)?floorName(lvl(r))+' · ':'')+r.name,route:lvl(r)?null:reached.has(i)})),hazardKnown:[floodClass,landClass,surgeClass].filter(v=>v!=null).length};
}
function renderReadiness(){const a=assessReadiness();$('readinessReport').innerHTML=`<b>Readiness ${a.score}/100 · ${a.hazardKnown}/3 hazard layers available</b><p>Flood and landslide ratings combine model assumptions and any available map class. Wind and earthquake ratings assess design features only; storm surge is unknown without a map. Higher scores mean fewer flagged features. These thresholds are illustrative, not code requirements.</p><p>Heuristic design score, not a probability or safety certification. Model terrain uses relative elevations; it is not a surveyed terrain model.</p><p>Area scores: terrain ${100-a.terrainRisk}; structure ${100-a.structureRisk}; floor plan ${100-a.planRisk} /100.</p><p>Terrain elevation ${a.elevationRange?a.elevationRange.map(v=>v.toFixed(2)).join('–')+' m':'no footprint'} · maximum sampled slope ${a.slope.toFixed(1)}° · river ${a.river<1e8?a.river.toFixed(1)+' m':'not drawn'} · floor clearance ${a.clearance===null?'no house':a.clearance.toFixed(2)+' m'} above selected water level.</p><p>${a.exits} modeled exits · ${a.narrow} hallways below 1.2 m · ${a.noWindow} rooms without modeled windows · ${a.beds} flood-exposed bedrooms · ${a.irregular?'irregular':'regular'} footprint · ${a.large?'large opening/garage':'no large opening detected'} · ${S.floors.length} storey${S.floors.length>1?'s':''} · wall ${S.site.wall} m · roof ${esc(S.site.roof)}.</p>${a.upper?`<p>${a.upper} room${a.upper>1?'s':''} on upper floors: routes between floors are not checked yet because stairs are not modeled. These rooms are left out of the exit check.</p>`:''}${a.rows.map(([h,r,f])=>`<p><b>${h}: ${r} risk</b><br>${esc(f)}</p>`).join('')}<p>${a.exits<2?'Add a second independent exterior exit. ':''}${a.unreachable?'Connect '+a.unreachable+' rooms to an exit with usable doors. ':''}${a.narrow?'Widen narrow hallways to at least the 1.2 m screening target. ':''}${a.noWindow?'Add suitable windows where missing. ':''}${a.beds?'Move flood-exposed bedrooms above the flood scenario.':''}</p><details><summary>Room-to-exit paths</summary>${a.points.map(p=>`<div>${esc(p.name)}: ${p.route===null?'not checked (upper floor; stairs not modeled yet)':p.route?'door graph reaches an exterior exit':'no modeled route'}</div>`).join('')}<p>Connectivity ignores furniture blockage, door swing, stairs and travel distance. Check routes physically.</p></details><p>NOAH lot badges: ${['flood','landslide','surge'].map(k=>k+': '+(S.hazard?.results?.[k]?.level==null?'Unavailable':S.hazard.results[k].level===0?'Low':S.hazard.results[k].level===1?'Low':S.hazard.results[k].level===2?'Medium':'High')).join(' · ')}. Project NOAH and contributors. Earthquake rating is design-only; PHIVOLCS data is not connected.</p><p>This is a screening tool and does not replace an engineer’s assessment. Missing maps never mean no hazard.</p>`;}
$('readinessBtn').onclick=renderReadiness;
async function fetchHazard(){const request=++hazardRequest;const lat=Number($('siteLat').value),lng=Number($('siteLng').value);if(!$('siteLat').value||!$('siteLng').value||!Number.isFinite(lat)||!Number.isFinite(lng)||Math.abs(lat)>90||Math.abs(lng)>180)return toast('Enter valid latitude and longitude');const period=Number($('hazardPeriod').value),surge=Number($('surgeScenario').value);S.location={lat,lng,period,surge};S.hazard=null;$('hazardStatus').textContent='Checking cached layers…';try{const r=await fetch(`hazard.php?lat=${lat}&lng=${lng}&period=${period}&surge=${surge}`),h=await r.json();if(!r.ok)throw Error(h.error||'Hazard lookup failed');if(request!==hazardRequest)return;S.hazard=h;const f=h.results.flood;if(f?.level===0){S.env.flood=0;syncUI();rebuild()}if(f?.level>0){const depth=f.depth_m??({1:.5,2:1.5,3:1.6}[f.level]);S.env.flood=depth;$('hazardStatus').textContent=`Flood scenario set to ${depth} m. `+(f.level===3?'Level 3 exceeds 1.5 m; 1.6 m is a lower-bound visualization, not a predicted depth.':'Class upper bound used unless measured depth is supplied.');syncUI();rebuild()}else $('hazardStatus').textContent='Lookup complete. '+Object.entries(h.results).map(([k,v])=>k+': '+v.status).join(' · ');renderReadiness();commit()}catch(e){if(request!==hazardRequest)return;$('hazardStatus').textContent=e.message;renderReadiness();commit()}}
$('fetchHazard').onclick=fetchHazard;
for(const id of ['siteLat','siteLng','hazardPeriod','surgeScenario'])$(id).onchange=()=>{hazardRequest++;S.hazard=null;S.location=null;$('hazardStatus').textContent='Location or scenario changed; check again.';renderReadiness();commit()};
// Settings and layout changes refresh the report, without rebuilding the scene.
document.addEventListener('change',()=>renderReadiness());

initBpUI();

/* ---------- boot ---------- */
buildPalettes();
let loaded=false;try{const raw=localStorage.getItem(projectStorageKey);if(raw){S=normalize(JSON.parse(raw));loaded=true}}catch(e){loaded=false;S=blank()}
if(!loaded)S=makeTemplate(TEMPLATES[0].def);
syncUI();init3D();setView('3d');renderPanels();commit();
window.__archi={get S(){return S},select,rebuild,addRoom,addObject,setView,loadState,TEMPLATES,makeTemplate};
})();
</script>
<dialog id="studioGuide"><div class="guide-heading"><h2>A clearer perspective, from the first plan.</h2><button id="closeStudioGuide" aria-label="Close guide">×</button></div><ol><li><b>Import blueprint:</b> choose house or lot files, select PDF pages, and calibrate a known distance.</li><li><b>Design site:</b> use Build for rooms and objects, Site for terrain and rivers. Switch between 2D and 3D in the toolbar.</li><li><b>Simulate:</b> set time, weather, flood level and earthquake intensity, then run the scenario.</li><li><b>Review readiness:</b> review the screening report and suggested improvements.</li></ol><p>Save stores this project in your browser and uploads blueprint images to your account. Export a project file for backup or transfer. This is concept planning, not structural certification.</p></dialog>
</body>
</html>