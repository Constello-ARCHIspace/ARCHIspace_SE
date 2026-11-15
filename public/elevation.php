<?php
/**
 * Ground heights around a point, from locally stored Copernicus DEM GLO-30 cells.
 * No remote API is called.
 *
 * GET lat, lng
 * Returns a north-up grid of heights (metres above the EGM2008 geoid) centred on
 * the point: GRID_N x GRID_N values, GRID_STEP metres apart, row by row from the
 * south edge, each row from the west edge, plus the height at the point itself.
 *
 * Data lives in hazard-data/elevation/ (or ARCHISPACE_HAZARD_DIR/elevation), made
 * by tools/dem_to_cells.py: index.json plus one file per 0.01 degree cell holding
 * the native 1 arc-second samples in decimetres. Heights between samples are
 * interpolated (bilinear). 30 m data shows the shape of the land around a lot,
 * not the small slopes inside it. Concept screening only.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
requireUser(true);
session_write_close();
header('Content-Type: application/json'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: no-store');
function badElevation(string $message): never { http_response_code(400); echo json_encode(['error'=>$message]); exit; }
$lat=filter_input(INPUT_GET,'lat',FILTER_VALIDATE_FLOAT); $lng=filter_input(INPUT_GET,'lng',FILTER_VALIDATE_FLOAT);
if ($lat===false || $lat===null || $lng===false || $lng===null || abs($lat)>85 || abs($lng)>180) badElevation('Valid lat and lng are required');

const GRID_N = 41;      // samples per side
const GRID_STEP = 26;   // metres between samples: 41 x 26 m covers 520 m each way
const ARCSEC = 3600;

/** Native sample at whole arc-second indices, in metres, or null when its cell is missing. */
function nativeSample(string $dir,int $n,int $latAs,int $lngAs): ?float {
    static $cache=[];
    $cy=(int)floor($latAs/$n); $cx=(int)floor($lngAs/$n);
    $key=$cx.'_'.$cy;
    if (!array_key_exists($key,$cache)) {
        $path="$dir/$key.json"; $cache[$key]=null;
        if (is_file($path)) {
            $data=json_decode(file_get_contents($path),true,4,JSON_THROW_ON_ERROR);
            if (!is_array($data['h']??null) || count($data['h'])!==$n*$n) throw new RuntimeException('Invalid elevation cell');
            $cache[$key]=$data['h'];
        }
    }
    if ($cache[$key]===null) return null;
    $v=$cache[$key][($latAs-$cy*$n)*$n+($lngAs-$cx*$n)];
    if (!is_int($v)) throw new RuntimeException('Invalid elevation value');
    return $v/10;
}
/** Bilinear height at a point, or null when any of the four samples around it is missing. */
function heightAt(string $dir,int $n,float $lat,float $lng): ?float {
    $fy=$lat*ARCSEC; $fx=$lng*ARCSEC; $y0=(int)floor($fy); $x0=(int)floor($fx); $ty=$fy-$y0; $tx=$fx-$x0;
    $a=nativeSample($dir,$n,$y0,$x0); $b=nativeSample($dir,$n,$y0,$x0+1); $c=nativeSample($dir,$n,$y0+1,$x0); $d=nativeSample($dir,$n,$y0+1,$x0+1);
    if ($a===null || $b===null || $c===null || $d===null) return null;
    return ($a*(1-$tx)+$b*$tx)*(1-$ty)+($c*(1-$tx)+$d*$tx)*$ty;
}

$root=(getenv('ARCHISPACE_HAZARD_DIR') ?: dirname(__DIR__) . '/hazard-data').'/elevation';
$out=['location'=>['lat'=>$lat,'lng'=>$lng],'n'=>GRID_N,'step_m'=>GRID_STEP,'heights'=>null,'point_m'=>null,
    'datum'=>'metres above the EGM2008 geoid (close to mean sea level)',
    'scope'=>'30 m data: shows the shape of the surrounding land, not small slopes inside the lot; concept screening only'];
try {
    if (!is_file($root.'/index.json')) {
        $out['status']='unavailable';
        $out['setup']='No elevation data is installed on this server. Download the Copernicus DEM GLO-30 tiles for the area and convert them with tools/dem_to_cells.py into hazard-data/elevation/.';
        echo json_encode($out,JSON_THROW_ON_ERROR); exit;
    }
    $meta=json_decode(file_get_contents($root.'/index.json'),true,8,JSON_THROW_ON_ERROR);
    $n=$meta['n']??null; $cell=$meta['cell_deg']??null;
    if (($meta['format']??'')!=='archispace-elevation-cells-1' || !is_int($n) || $n<1 || !is_numeric($cell) || abs($cell*ARCSEC-$n)>1e-6) throw new RuntimeException('Unknown elevation data format');
    $out['source']=(string)($meta['source']??'Copernicus DEM GLO-30'); $out['attribution']=(string)($meta['attribution']??'');
    $mLat=111320.0; $mLng=111320.0*max(.2,cos(deg2rad($lat))); $half=intdiv(GRID_N-1,2);
    $point=heightAt($root,$n,$lat,$lng); $heights=[]; $ok=$point!==null;
    for ($i=-$half; $ok && $i<=$half; $i++) for ($j=-$half; $j<=$half; $j++) {
        $h=heightAt($root,$n,$lat+$i*GRID_STEP/$mLat,$lng+$j*GRID_STEP/$mLng);
        if ($h===null) { $ok=false; break; }
        $heights[]=round($h,1);
    }
    if (!$ok) { $out['status']='outside_area'; echo json_encode($out,JSON_THROW_ON_ERROR); exit; }
    $out['status']='ok'; $out['heights']=$heights; $out['point_m']=round($point,1);
    echo json_encode($out,JSON_THROW_ON_ERROR);
} catch (Throwable $e) { http_response_code(503); echo json_encode(['error'=>'Elevation data is invalid or unreadable; ask the administrator to rebuild it with tools/dem_to_cells.py.']); }
