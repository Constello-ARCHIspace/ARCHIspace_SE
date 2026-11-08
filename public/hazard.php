<?php
/** Cached, preprocessed WGS84 GeoJSON lookup. No undocumented remote API. */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
requireUser(true);
header('Content-Type: application/json'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: no-store');
function badHazard(string $message): never { http_response_code(400); echo json_encode(['error'=>$message]); exit; }
$lat=filter_input(INPUT_GET,'lat',FILTER_VALIDATE_FLOAT); $lng=filter_input(INPUT_GET,'lng',FILTER_VALIDATE_FLOAT);
if ($lat===false || $lat===null || $lng===false || $lng===null || abs($lat)>90 || abs($lng)>180) badHazard('Valid lat and lng are required');
$period=(string)($_GET['period']??'100'); $surge=(string)($_GET['surge']??'4');
if (!in_array($period,['5','25','100'],true) || !in_array($surge,['1','2','3','4'],true)) badHazard('Invalid scenario');
// Boundary points count as inside. Polygon holes and MultiPolygon are supported.
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
$root=getenv('ARCHISPACE_HAZARD_DIR') ?: dirname(__DIR__) . '/hazard-data';
$results=[];
try {
 foreach (['flood'=>'flood-'.$period,'landslide'=>'landslide','surge'=>'surge-'.$surge] as $kind=>$file) {
    $path=$root.'/'.$file.'.geojson';
    $result=['status'=>'unavailable','level'=>null,'depth_m'=>null];
    if (is_file($path)) {
        $data=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        if (($data['type']??'')!=='FeatureCollection' || !is_array($data['features']??null)) throw new RuntimeException('Invalid FeatureCollection');
        $level=null;$depth=null;
        foreach ($data['features'] as $feature) {
            $props=$feature['properties']??[];
            if (!isset($props['level']) || !is_int($props['level']) || $props['level']<0 || $props['level']>3) throw new RuntimeException('Normalize hazard levels to integers 0–3');
            if (isset($feature['bbox']) && ($lng<$feature['bbox'][0] || $lat<$feature['bbox'][1] || $lng>$feature['bbox'][2] || $lat>$feature['bbox'][3])) continue;
            if (!inGeometry($lng,$lat,$feature['geometry']??[])) continue;
            if ($level===null || $props['level']>$level) {$level=$props['level'];$depth=null;}
            if ($props['level']===$level && isset($props['depth_m']) && is_numeric($props['depth_m']) && $props['depth_m']>=0) $depth=max($depth??0,(float)$props['depth_m']);
        }
        // No polygon match is unknown coverage, never automatically low hazard.
        $result=['status'=>$level===null?'no_coverage':'matched','level'=>$level,'depth_m'=>$depth,'dataset'=>$file,'modified'=>gmdate('c',filemtime($path))];
    }
    $results[$kind]=$result;
 }
 echo json_encode(['location'=>['lat'=>$lat,'lng'=>$lng],'period'=>(int)$period,'surge_scenario'=>(int)$surge,'results'=>$results,'source'=>'Project NOAH and contributors','scope'=>'Single point; beta; missing coverage is not low risk','earthquake'=>['status'=>'unavailable','source_required'=>'PHIVOLCS active faults and liquefaction']],JSON_THROW_ON_ERROR);
} catch (Throwable $e) { http_response_code(503); echo json_encode(['error'=>'Hazard cache is invalid or unreadable; ask the administrator to validate the normalized GeoJSON.']); }
