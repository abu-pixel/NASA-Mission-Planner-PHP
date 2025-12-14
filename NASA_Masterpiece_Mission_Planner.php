<?php
/**
 * NASA Masterpiece Mission Planner
 * Single-file PHP-only masterpiece designed to impress: orbital mechanics simulator,
 * Hohmann transfer optimizer, visualization (SVG), mission timeline, telemetry report generator.
 * No JavaScript, no external libs — pure PHP, single file.
 **/

declare(strict_types=1);

// -----------------------------
// Utility classes
// -----------------------------
class Vec2 {
    public float $x;
    public float $y;
    public function __construct(float $x=0.0, float $y=0.0){ $this->x=$x; $this->y=$y; }
    public function add(Vec2 $o): Vec2{ return new Vec2($this->x+$o->x, $this->y+$o->y); }
    public function sub(Vec2 $o): Vec2{ return new Vec2($this->x-$o->x, $this->y-$o->y); }
    public function mul(float $s): Vec2{ return new Vec2($this->x*$s, $this->y*$s); }
    public function mag(): float{ return sqrt($this->x*$this->x + $this->y*$this->y); }
}

// -----------------------------
// Orbital mechanics core (2D planar approximation)
// -----------------------------
class Orbit {
    public float $mu; // gravitational parameter (km^3/s^2)
    public float $a;  // semi-major axis (km)
    public float $e;  // eccentricity
    public float $i;  // inclination (deg) - used only for reporting
    public float $raan; // right ascension of ascending node (deg) - reporting
    public float $argp; // argument of perigee (deg) - reporting

    public function __construct(float $mu, float $a, float $e=0.0, float $i=0.0, float $raan=0.0, float $argp=0.0){
        $this->mu=$mu; $this->a=$a; $this->e=$e; $this->i=$i; $this->raan=$raan; $this->argp=$argp;
    }

    public function period(): float{
        return 2*M_PI*sqrt(pow($this->a,3)/$this->mu);
    }

    // Mean motion n
    public function meanMotion(): float{ return sqrt($this->mu / pow($this->a,3)); }

    // Solve Kepler's equation for E given mean anomaly M (radians)
    public function solveE(float $M,float $tol=1e-9,int $maxIter=200): float{
        $e=$this->e;
        if ($e<1e-8) return $M; // circular
        // initial guess
        $E = ($e<0.8) ? $M : M_PI;
        for($k=0;$k<$maxIter;$k++){
            $f = $E - $e*sin($E) - $M;
            $fp = 1 - $e*cos($E);
            $d = -$f/$fp;
            $E += $d;
            if (abs($d) < $tol) break;
        }
        return $E;
    }

    // Position (km) in orbital plane for given mean anomaly M (rad)
    public function positionFromM(float $M): Vec2{
        $E = $this->solveE($M);
        $r = $this->a * (1 - $this->e * cos($E));
        // True anomaly
        $cosf = (cos($E) - $this->e) / (1 - $this->e * cos($E));
        $sinf = (sqrt(1 - $this->e*$this->e) * sin($E)) / (1 - $this->e * cos($E));
        $f = atan2($sinf, $cosf);
        // position in orbital plane
        $x = $r * cos($f);
        $y = $r * sin($f);
        return new Vec2($x, $y);
    }

    // Velocity magnitude at distance r
    public function velocityAtR(float $r): float{
        return sqrt($this->mu * (2/$r - 1/$this->a));
    }
}

// -----------------------------
// Mission planning utilities: Hohmann transfer calculator (coplanar)
// -----------------------------
class Transfer {
    public static function hohmannDVs(float $mu, float $r1, float $r2): array{
        // r1, r2 in km
        $v1 = sqrt($mu / $r1);
        $v2 = sqrt($mu / $r2);
        $aTrans = 0.5*($r1 + $r2);
        $vPerigee = sqrt($mu*(2/$r1 - 1/$aTrans));
        $vApogee  = sqrt($mu*(2/$r2 - 1/$aTrans));
        $dv1 = abs($vPerigee - $v1);
        $dv2 = abs($v2 - $vApogee);
        $total = $dv1 + $dv2;
        // time of flight (half period)
        $tof = M_PI * sqrt(pow($aTrans,3)/$mu);
        return ['dv1'=>$dv1,'dv2'=>$dv2,'dv_total'=>$total,'tof'=>$tof,'aTrans'=>$aTrans];
    }
}

// -----------------------------
// Renderer: builds SVG visualization server-side
// -----------------------------
class SVGRenderer {
    public static function orbitSVG(Orbit $orbit, int $size=700, int $margin=40, int $pts=720, Vec2 $scCenter=null, float $scale=null, float $tNow=null): string{
        $positions = [];
        // sample mean anomaly from 0 to 2pi
        for($k=0;$k<$pts;$k++){
            $M = 2*M_PI*$k/$pts;
            $p = $orbit->positionFromM($M);
            $positions[] = $p;
        }
        // find bounding box
        $minx = PHP_FLOAT_MAX; $maxx=-PHP_FLOAT_MAX; $miny=PHP_FLOAT_MAX; $maxy=-PHP_FLOAT_MAX;
        foreach($positions as $p){ $minx=min($minx,$p->x); $maxx=max($maxx,$p->x); $miny=min($miny,$p->y); $maxy=max($maxy,$p->y); }
        // include central body
        $minx = min($minx, -1.1*$orbit->a); $maxx = max($maxx, 1.1*$orbit->a);
        $miny = min($miny, -1.1*$orbit->a); $maxy = max($maxy, 1.1*$orbit->a);
        $width = $maxx - $minx; $height = $maxy - $miny;
        $scale = $scale ?? min(($size-2*$margin)/$width, ($size-2*$margin)/$height);
        $cx = $margin + ($size-2*$margin)/2; $cy = $margin + ($size-2*$margin)/2;

        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='$size' height='$size' viewBox='0 0 $size $size'>";
        // background
        $svg .= "<rect width='100%' height='100%' fill='#050517' rx='12'/>";
        // central body (Earth-like)
        $earthR = 6371; // km
        $earthDisplayR = max(4, $earthR * $scale * 0.0005);
        $svg .= "<circle cx='$cx' cy='$cy' r='$earthDisplayR' fill='#1463ff' stroke='#ffffff11'/>";
        // orbit path
        $path = [];
        foreach($positions as $p){
            $sx = $cx + ($p->x - ($minx+$width/2)) * $scale;
            $sy = $cy - ($p->y - ($miny+$height/2)) * $scale;
            $path[] = sprintf('%.2f,%.2f',$sx,$sy);
        }
        $svg .= "<polyline fill='none' stroke='#ffffff55' stroke-width='1' points='".implode(' ',$path)."'/>";

        // current spacecraft position
        if ($tNow !== null){
            $n = $orbit->meanMotion();
            $M = ($n * $tNow) % (2*M_PI);
            $p = $orbit->positionFromM($M);
            $sx = $cx + ($p->x - ($minx+$width/2)) * $scale;
            $sy = $cy - ($p->y - ($miny+$height/2)) * $scale;
            $svg .= "<circle cx='".sprintf('%.2f',$sx)."' cy='".sprintf('%.2f',$sy)."' r='4' fill='#ffcc33'/>";
            $svg .= "<text x='".sprintf('%.2f',$sx+8)."' y='".sprintf('%.2f',$sy-8)."' font-family='monospace' font-size='12' fill='#ffffffcc'>Spacecraft</text>";
        }

        $svg .= "</svg>";
        return $svg;
    }
}

// -----------------------------
// Web UI / Controller
// -----------------------------
function safeFloat($key, $default){
    if (!isset($_REQUEST[$key])) return $default;
    $v = trim($_REQUEST[$key]);
    if ($v === '') return $default;
    // remove commas
    $v = str_replace(',', '', $v);
    return floatval($v);
}

// Defaults
$muEarth = 398600.4418; // km^3/s^2
$defaultA = 6771; // LEO ~ 400km altitude -> Earth radius 6371 + 400
$defaultE = 0.001;
$defaultI = 28.5;
$defaultRAAN = 0;
$defaultArgP = 0;

// Gather inputs
$a = max(1600.0, safeFloat('a', $defaultA));
$e = max(0.0, min(0.9999, safeFloat('e', $defaultE)));
$i = safeFloat('i', $defaultI);
$raan = safeFloat('raan', $defaultRAAN);
$argp = safeFloat('argp', $defaultArgP);
$visualize = isset($_REQUEST['visualize']) || isset($_REQUEST['run_default']);
$toRadius = safeFloat('target_radius', 42164); // default GEO radius (km)

$orbit = new Orbit($muEarth, $a, $e, $i, $raan, $argp);

// compute Hohmann if target provided
$transferReport = Transfer::hohmannDVs($muEarth, $a, $toRadius);

// time now for animation (seconds since epoch chosen arbitrary)
$tNow = safeFloat('t', 0.0);

// Robust error handling and input validation summary
$errors = [];
if ($a <= 6371) $errors[] = 'Semi-major axis a must be larger than Earth radius (6371 km).';
if ($e < 0 || $e >= 1) $errors[] = 'Eccentricity must satisfy 0 <= e < 1 for bound orbit.';

// -----------------------------
// Produce HTML page (generated by PHP) — no external assets
// -----------------------------
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>NASA Masterpiece — Mission Planner (PHP-only)</title>
<style>
    :root{--bg:#07071a;--card:#0f1226;--muted:#cbd5e1aa;--accent:#7ee3a7}
    body{font-family: Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; margin:0; background:linear-gradient(180deg,#020212 0%, #0b0b1f 100%); color:#e7eefc}
    header{padding:22px 28px; display:flex; gap:18px; align-items:center}
    h1{margin:0;font-size:20px}
    .container{display:grid; grid-template-columns: 420px 1fr; gap:18px; padding:18px}
    .card{background:var(--card); padding:14px; border-radius:10px; box-shadow: 0 6px 18px #00000055}
    label{display:block;font-size:13px;margin-top:8px;color:var(--muted)}
    input[type='text'], input[type='number']{width:100%; padding:8px;margin-top:6px;border-radius:6px;border:1px solid #ffffff11;background:transparent;color:inherit}
    button{margin-top:10px;padding:10px 12px;border-radius:8px;border:0;background:linear-gradient(90deg,#42a5f5,#7ee3a7);color:#001;text-transform:uppercase;font-weight:600}
    pre{background:#00000033;padding:10px;border-radius:6px;overflow:auto}
    .muted{color:var(--muted);font-size:13px}
    footer{padding:14px;text-align:center;color:#ffffff66}
    .kv{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px dashed #ffffff08}
</style>
</head>
<body>
<header>
    <div>
        <h1>NASA — Single-file Masterpiece: Mission Planner (PHP only)</h1>
        <div class="muted">Orbital simulator • Hohmann optimizer • Server-side SVG visualization • Mission timeline</div>
    </div>
</header>
<main class="container">
    <section class="card">
        <form method="post">
            <label>Gravitational parameter μ (km³/s²)</label>
            <input type="text" name="mu" value="<?php echo htmlspecialchars((string)$muEarth);?>" disabled>

            <label>Semi-major axis a (km)</label>
            <input type="text" name="a" value="<?php echo htmlspecialchars((string)$a);?>">

            <label>Eccentricity e</label>
            <input type="text" name="e" value="<?php echo htmlspecialchars((string)$e);?>">

            <label>Inclination i (deg)</label>
            <input type="text" name="i" value="<?php echo htmlspecialchars((string)$i);?>">

            <label>Target orbit radius (km) — Hohmann target</label>
            <input type="text" name="target_radius" value="<?php echo htmlspecialchars((string)$toRadius);?>">

            <label>Time offset t (s) for spacecraft position (optional)</label>
            <input type="text" name="t" value="<?php echo htmlspecialchars((string)$tNow);?>">

            <button type="submit" name="visualize">Generate Mission</button>
            <button type="submit" name="run_default">Run Default Demo</button>
        </form>

        <?php if (!empty($errors)): ?>
            <div style="margin-top:12px;color:#ff7a7a;background:#2a0b0b;padding:10px;border-radius:6px">
                <strong>Input errors:</strong>
                <ul><?php foreach($errors as $er) echo '<li>'.htmlspecialchars($er)."</li>"; ?></ul>
            </div>
        <?php else: ?>
            <div style="margin-top:12px">
                <div class="kv"><div>Orbit semi-major axis (a)</div><div><?php echo number_format($a,2);?> km</div></div>
                <div class="kv"><div>Eccentricity</div><div><?php echo number_format($e,5);?></div></div>
                <div class="kv"><div>Orbital period</div><div><?php echo number_format($orbit->period()/3600,4);?> hours</div></div>
                <div class="kv"><div>Mean motion (rad/s)</div><div><?php echo number_format($orbit->meanMotion(),8);?></div></div>
            </div>
        <?php endif; ?>

        <hr style="border:none;height:1px;background:#ffffff08;margin:12px 0">
        <h3 style="margin:6px 0">Transfer report (Hohmann coplanar)</h3>
        <div class="muted">From radius a (assumed circular) to target radius</div>
        <div style="margin-top:8px">
            <div class="kv"><div>Target radius</div><div><?php echo number_format($toRadius,2);?> km</div></div>
            <div class="kv"><div>Δv1 (kick)</div><div><?php echo number_format($transferReport['dv1'],5);?> km/s</div></div>
            <div class="kv"><div>Δv2 (circularize)</div><div><?php echo number_format($transferReport['dv2'],5);?> km/s</div></div>
            <div class="kv"><div>Total Δv</div><div><?php echo number_format($transferReport['dv_total'],5);?> km/s</div></div>
            <div class="kv"><div>Time of flight (half period)</div><div><?php echo number_format($transferReport['tof']/3600,5);?> hours</div></div>
        </div>

    </section>

    <section class="card">
        <h3 style="margin-top:0">Visualization & Mission Output</h3>
        <?php if (empty($errors) && $visualize): ?>
            <div style="display:flex;gap:12px;flex-wrap:wrap">
                <div style="flex:1 1 720px" class="card">
                    <?php echo SVGRenderer::orbitSVG($orbit,700,28,540,null,$tNow); ?>
                </div>
                <div style="flex:0 0 320px" class="card">
                    <h4>Mission Timeline</h4>
                    <div class="muted">Automatically generated server-side timeline (events approximate)</div>
                    <?php
                        $now = time();
                        $launchT = $now + 3600*24*3; // demo: launch in 3 days
                        $tof = $transferReport['tof'];
                        $arrival = $launchT + intval($tof);
                        $timeline = [
                            ['t'=>$launchT,'title'=>'Launch (T+0)','desc'=>'Ground launch to parking orbit'],
                            ['t'=>$launchT + 3600*0.5,'title'=>'Parking orbit insertion','desc'=>'Circularize to parking orbit'],
                            ['t'=>$launchT + 3600*2,'title'=>'Transfer burn (Δv1)','desc'=>'First burn to enter transfer ellipse — Δv ≈ '.number_format($transferReport['dv1'],5).' km/s'],
                            ['t'=>$arrival,'title'=>'Apogee arrival / Circularize (Δv2)','desc'=>'Second burn to circularize — Δv ≈ '.number_format($transferReport['dv2'],5).' km/s'],
                            ['t'=>$arrival + 3600,'title'=>'Mission ops begin','desc'=>'Begin mission operations and telemetry']
                        ];
                    ?>
                    <div style="margin-top:8px">
                        <?php foreach($timeline as $ev): ?>
                            <div style="margin-bottom:10px">
                                <strong><?php echo date('Y-m-d H:i',$ev['t']);?></strong>
                                <div style="font-weight:700"><?php echo htmlspecialchars($ev['title']);?></div>
                                <div class="muted" style="font-size:13px"><?php echo htmlspecialchars($ev['desc']);?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr>
                    <h4>Telemetry Snapshot</h4>
                    <div class="muted">Server computed values for reporting / inclusion in portfolio</div>
                    <?php
                        $r_now = $orbit->a * (1 - $orbit->e * cos($orbit->solveE(($orbit->meanMotion()*$tNow) % (2*M_PI))));
                    ?>
                    <div class="kv"><div>Range from central body</div><div><?php echo number_format($r_now,3);?> km</div></div>
                    <div class="kv"><div>Velocity magnitude</div><div><?php echo number_format($orbit->velocityAtR($r_now),5);?> km/s</div></div>

                    <hr>
                    <h4>Generated Mission Report (copy-paste)</h4>
                    <div class="muted">This report is ideal to include in a portfolio document or e-mail to recruiters.</div>
                    <pre><?php
                        $report = "MISSION REPORT\n";
                        $report .= "Mission: PHP-only Orbital Transfer Demo\n";
                        $report .= "Semi-major axis (a): ".number_format($a,2)." km\n";
                        $report .= "Eccentricity: ".number_format($e,6)."\n";
                        $report .= "Orbital period: ".number_format($orbit->period()/3600,6)." hours\n";
                        $report .= "Hohmann transfer to r=".number_format($toRadius,2)." km -> Δv_total=".number_format($transferReport['dv_total'],6)." km/s, TOF=".number_format($transferReport['tof']/3600,6)." hours\n";
                        $report .= "Notes: Server-side SVG visualization produced; Kepler solver uses Newton-Raphson with robust handling; mission timeline and telemetry generated.\n";
                        echo htmlspecialchars($report);
                    ?></pre>
                </div>
            </div>

        <?php else: ?>
            <div class="muted">Click "Generate Mission" to compute and visualize an orbit, or click "Run Default Demo" for a preset demonstration.</div>
        <?php endif; ?>

    </section>
</main>
<footer>
    Built with pure PHP — single file. For local testing run: <code>php -S localhost:8000</code> and open this file in your browser.
</footer>
</body>
</html>
<?php
// End of file
