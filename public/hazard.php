<?php
/**
 * Hazard lookup for a lot and its surroundings, from locally stored Project NOAH data.
 * No remote API is called.
 *
 * GET lat, lng, period (5|25|100), surge (1|2|3|4)
 *
 * Data lives in hazard-data/ (or ARCHISPACE_HAZARD_DIR), one folder per layer:
 *   flood-5, flood-25, flood-100, landslide, surge-1 ... surge-4
 * Each folder is made by tools/noah_to_cells.py: index.json plus one file per
 * 0.01 degree cell. A point is inside a NOAH record when it is inside an odd
 * number of that record's rings. Levels: 1 low, 2 medium, 3 high.
 * The older single-file layout (hazard-data/<layer>.geojson from
 * tools/prepare_noah.py) is still read for the lot point when no folder exists.
 *
 * No polygon at a point means NOAH mapped no hazard there or has no coverage;
 * it is never reported as low hazard.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
requireUser(true);
session_write_close();
header('Content-Type: application/json'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: no-store');
function badHazard(string $message): never { http_response_code(400); echo json_encode(['error'=>$message]); exit; }
$lat=filter_input(INPUT_GET,'lat',FILTER_VALIDATE_FLOAT); $lng=filter_input(INPUT_GET,'lng',FILTER_VALIDATE_FLOAT);
if ($lat===false || $lat===null || $lng===false || $lng===null || abs($lat)>85 || abs($lng)>180) badHazard('Valid lat and lng are required');
$period=(string)($_GET['period']??'100'); $surge=(string)($_GET['surge']??'4');
if (!in_array($period,['5','25','100'],true) || !in_array($surge,['1','2','3','4'],true)) badHazard('Invalid scenario');

const RING_RADII = [100, 250, 500];   // metres around the lot
const SAMPLE_SPACING = 20;            // metres between sample points

// ---- Older single-file layout (lot point only). Boundary points count as inside.
function inRing(float $x,float $y,array $ring): bool {
    $inside=false; $n=count($ring); if ($n<4) return false;
    for ($i=0,$j=$n-1;$i<$n;$j=$i++) {
        [$ax,$ay]=$ring[$i]; [$bx,$by]=$ring[$j];
        $cross=($x-$ax)*($by-$ay)-($y-$ay)*($bx-$ax);
        if (abs($cross)<1e-10 && $x>=min($ax,$bx)-1e-10 && $x<=max($ax,$bx)+1e-10 && $y>=min($ay,$by)-1e-10 && $y<=max($ay,$by)+1e-10) return true;
        if (($ay>$y)!==($by>$y) && $x<($bx-$ax)*($y-$ay)/($by-$ay)+$ax) $inside=!$inside;
    } return $inside;
}
function inPolygon(float $x,float $y,array $rings): bool {
    if (!$rings || !inRing($x,$y,$rings[0])) return false;
    foreach (array_slice($rings,1) as $hole) if (inRing($x,$y,$hole)) return false;
    return true;
}
function inGeometry(float $x,float $y,array $g): bool {
    if (($g['type']??'')==='Polygon') return inPolygon($x,$y,$g['coordinates']);
    if (($g['type']??'')==='MultiPolygon') foreach ($g['coordinates'] as $p) if (inPolygon($x,$y,$p)) return true;
    return false;
}
function legacyLookup(string $path,string $file,float $lng,float $lat): array {
    $data=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    if (($data['type']??'')!=='FeatureCollection' || !is_array($data['features']??null)) throw new RuntimeException('Invalid FeatureCollection');
    $level=null;$depth=null;
    foreach ($data['features'] as $feature) {
        $props=$feature['properties']??[];
        if (!isset($props['level']) || !is_int($props['level']) || $props['level']<0 || $props['level']>3) throw new RuntimeException('Normalize hazard levels to integers 0-3');
        if (isset($feature['bbox']) && ($lng<$feature['bbox'][0] || $lat<$feature['bbox'][1] || $lng>$feature['bbox'][2] || $lat>$feature['bbox'][3])) continue;
        if (!inGeometry($lng,$lat,$feature['geometry']??[])) continue;
        if ($level===null || $props['level']>$level) {$level=$props['level'];$depth=null;}
        if ($props['level']===$level && isset($props['depth_m']) && is_numeric($props['depth_m']) && $props['depth_m']>=0) $depth=max($depth??0,(float)$props['depth_m']);
    }
    return ['status'=>$level===null?'no_coverage':'matched','level'=>$level,'depth_m'=>$depth,'dataset'=>$file,'modified'=>gmdate('c',filemtime($path)),'rings'=>null];
}

// ---- Cell layout
/** Rings of one cell, grouped by NOAH record: [[level, [[minX,minY,maxX,maxY,[x1,y1,...]], ...]], ...] */
function loadCell(string $dir,int $ix,int $iy): array {
    static $cache=[];
    $key="$dir/{$ix}_{$iy}";
    if (isset($cache[$key])) return $cache[$key];
    $path=$key.'.json';
    if (!is_file($path)) return $cache[$key]=[];
    $data=json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR);
    if (!is_array($data['f']??null)) throw new RuntimeException('Invalid hazard cell');
    $out=[];
    foreach ($data['f'] as $f) {
        if (!is_int($f['l']??null) || $f['l']<1 || $f['l']>3 || !is_array($f['r']??null)) throw new RuntimeException('Invalid hazard cell entry');
        $out[]=[$f['l'],$f['r']];
    }
    return $cache[$key]=$out;
}
/** Highest hazard level at a point, or 0 when no polygon covers it. */
function levelAt(string $dir,float $cell,float $x,float $y): int {
    $best=0;
    foreach (loadCell($dir,(int)floor($x/$cell),(int)floor($y/$cell)) as [$level,$rings]) {
        if ($level<=$best) continue;
        $odd=false;
        foreach ($rings as $r) {
            if ($x<$r[0] || $y<$r[1] || $x>$r[2] || $y>$r[3]) continue;
            $c=$r[4]; $n=count($c); $inside=false;
            for ($i=0,$j=$n-2;$i<$n;$j=$i,$i+=2) {
                $ay=$c[$i+1]; $by=$c[$j+1];
                if (($ay>$y)!==($by>$y) && $x<($c[$j]-$c[$i])*($y-$ay)/($by-$ay)+$c[$i]) $inside=!$inside;
            }
            if ($inside) $odd=!$odd;
        }
        if ($odd) $best=$level;
    }
    return $best;
}
function cellLookup(string $dir,float $lng,float $lat): array {
    $meta=json_decode(file_get_contents($dir.'/index.json'),true,8,JSON_THROW_ON_ERROR);
    $cell=$meta['cell_deg']??null;
    if (($meta['format']??'')!=='archispace-hazard-cells-1' || !is_numeric($cell) || $cell<=0) throw new RuntimeException('Unknown hazard data format');
    $cell=(float)$cell;
    $info=['dataset'=>(string)($meta['dataset']??basename($dir)),'converted'=>(string)($meta['converted']??'')];
    $bb=$meta['clip_bbox']??null;
    if (is_array($bb) && count($bb)===4 && ($lng<$bb[0] || $lat<$bb[1] || $lng>$bb[2] || $lat>$bb[3]))
        return ['status'=>'outside_area','level'=>null,'depth_m'=>null,'rings'=>null]+$info;
    $point=levelAt($dir,$cell,$lng,$lat);
    // Sample a 20 m grid over the 500 m circle; each ring summary covers the whole circle of that radius.
    $mLat=111320.0; $mLng=111320.0*cos(deg2rad($lat)); $max=max(RING_RADII); $steps=intdiv($max,SAMPLE_SPACING);
    $counts=[]; foreach (RING_RADII as $r) $counts[$r]=[0,0,0,0];
    for ($i=-$steps;$i<=$steps;$i++) for ($j=-$steps;$j<=$steps;$j++) {
        $dx=$i*SAMPLE_SPACING; $dy=$j*SAMPLE_SPACING; $d2=$dx*$dx+$dy*$dy;
        if ($d2>$max*$max) continue;
        $lv=levelAt($dir,$cell,$lng+$dx/$mLng,$lat+$dy/$mLat);
        foreach (RING_RADII as $r) if ($d2<=$r*$r) $counts[$r][$lv]++;
    }
    $rings=[];
    foreach (RING_RADII as $r) {
        [$n0,$n1,$n2,$n3]=$counts[$r]; $n=$n0+$n1+$n2+$n3;
        $rings[]=['radius_m'=>$r,'max_level'=>$n3?3:($n2?2:($n1?1:null)),
            'share'=>['none'=>round(100*$n0/$n),'low'=>round(100*$n1/$n),'medium'=>round(100*$n2/$n),'high'=>round(100*$n3/$n)]];
    }
    return ['status'=>$point?'matched':'no_coverage','level'=>$point?:null,'depth_m'=>null,'rings'=>$rings]+$info;
}

$root=getenv('ARCHISPACE_HAZARD_DIR') ?: dirname(__DIR__) . '/hazard-data';
$results=[]; $found=0;
try {
    foreach (['flood'=>'flood-'.$period,'landslide'=>'landslide','surge'=>'surge-'.$surge] as $kind=>$file) {
        if (is_file("$root/$file/index.json")) { $results[$kind]=cellLookup("$root/$file",$lng,$lat); $found++; }
        elseif (is_file("$root/$file.geojson")) { $results[$kind]=legacyLookup("$root/$file.geojson",$file,$lng,$lat); $found++; }
        else $results[$kind]=['status'=>'unavailable','level'=>null,'depth_m'=>null,'rings'=>null];
    }
    $out=['location'=>['lat'=>$lat,'lng'=>$lng],'period'=>(int)$period,'surge_scenario'=>(int)$surge,'results'=>$results,
        'radii_m'=>RING_RADII,'source'=>'Project NOAH and contributors',
        'scope'=>'Lot point plus circles of 100, 250 and 500 m sampled every '.SAMPLE_SPACING.' m; beta; no mapped hazard is not low risk',
        'earthquake'=>['status'=>'unavailable','source_required'=>'PHIVOLCS active faults and liquefaction']];
    if (!$found) $out['setup']='No hazard data is installed on this server. Download the province files from the official Project NOAH site (noah.up.edu.ph, Downloads), convert them with tools/noah_to_cells.py and put the folders in hazard-data/.';
    echo json_encode($out,JSON_THROW_ON_ERROR);
} catch (Throwable $e) { http_response_code(503); echo json_encode(['error'=>'Hazard data is invalid or unreadable; ask the administrator to rebuild it with tools/noah_to_cells.py.']); }
