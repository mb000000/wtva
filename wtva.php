<?php
/*
 * THIS FILE IS PART OF THE WTVA PROJECT - AN OPEN SOURCE PROJECT
 *
 * USE AS YOU WISH, BUT THERE ARE NO WARRANTIES OF ANY KIND AS TO ACCURACY OR SUITABILITY FOR ANY PURPOSE.
 *
 * Created under Creative Commons By Attribution-ShareAlike 4.0 International
 * https://creativecommons.org/licenses/by-sa/4.0/
 *
 */

if (!function_exists('mb_trim')) {
    function mb_trim(string $string, ?string $characters = null): string {
        if ($characters !== null) {
            $chars = preg_quote($characters, '/');
            return preg_replace('/^[' . $chars . ']+|[' . $chars . ']+$/u', '', $string);
        }
        return preg_replace('/^[\s\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}\x{FEFF}]+|[\s\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}\x{FEFF}]+$/u', '', $string);
    }
}

require_once 'common_constants.php';

class wtva
{
    protected array $data = [];
    protected array $locations = [];
    protected array $messages = [];

    protected float $safe_blade_length_between_towers_ratio = 2.5;

    protected float $visible_limit_m = 0.01; // 1cm
    protected int $circle_facets = 32;
    protected float $min_blade_depth_m = 1.0;
    protected float $min_blade_offset_from_tower_m = 1.0;
    protected int $blade_count = 0;
    protected float $blade_depth_m = 0.0;
    protected float $blade_twist_rad = 0.523598776; // 30 degrees
    protected float $blade2_chord_percent_of_length = 0.05;
    protected float $blade2_max_chord_percent_of_length = 0.07; // the "blip" point - this is the cross section "length"
    protected float $blade2_max_chord_position_percent_of_length = 0.07; // the "blip" point - this is where the "blip" is
    protected float $blade2_min_chord_height_at_blip_percent_of_chord = 0.5; // the "blip" point - how thin
    protected float $blade2_min_chord_height_at_tip_percent_of_chord = 0.1; // how thin should the blade end up
    protected float $nacelle_yoffset_m = 0.0;
    protected float $nacelle_length_m = 0.0;
    protected float $adjusted_blade_length_m = 0.0;
    protected float $axle_diameter_m = 0.0;
    protected float $axle_length_m = 0.0;
    protected $blade_root_diameter_m = 0.0;
    protected $tower_top_diameter_m = 0.0;
    protected bool $z_up = true; // If false, Y is up

    // This array contains the actual transforms
    protected array $transform_entries = [];

    // This array contains the transform sets which are lists (arrays) of references to transform entries
    protected array $transform_sets = [];

    protected array $vertices = [];
    protected array $normals = [];
    protected array $triangles = [];

    protected string $kmz_subdir = 'files';
    protected string $collada_model_filename = 'turbine.dae';
    protected string $kml_filename = 'doc.kml';

    protected float $two_pi = M_PI * 2.0;

    protected string $DEBUGFILE = '';

    protected bool $transform_sets_append_adds = false;
    protected bool $USE_TRANSFORM_SETS = true; // false; //true;
    public const invalid_transform_set = -1;

    protected bool $DEBUG1 = false;
    protected bool $DEBUG2 = false;

    protected float $circle_start_angle_r = 0.0;
    protected float $circle_current_angle_r = 0.0;
    protected float $circle_end_angle_r = 0.0;

    protected float $cylinder_start_height_m = 0.0;
    protected float $cylinder_current_height_m = 0.0;
    protected float $cylinder_end_height_m = 0.0;

    protected float $cylinder_start_radius_m = 0.0;
    protected float $cylinder_current_radius_m = 0.0;
    protected float $cylinder_end_radius_m = 0.0;

    protected float $sphere_start_height_m = 0.0;
    protected float $sphere_current_height_m = 0.0;
    protected float $sphere_end_height_m = 0.0;

    protected float $sphere_start_radius_m = 0.0;
    protected float $sphere_current_radius_m = 0.0;
    protected float $sphere_end_radius_m = 0.0;

    // --------- UK OS Grid datum parameters - Airy1830 -------------
    protected float $os_a_m = 6377563.396; // Airy 1830
    protected float $os_b_m = 6356256.909; // Airy 1830
    protected float $os_n = 0.00167322032898749 ; // (a-b) / (a+b)
    protected float $os_T1 = 1.0016767257674; // 1 + n + n^2 * 5/4 + n^3 * 5/4
    protected float $os_T2 = 0.0050280722824741; // 3n + 3n^2 + n^3 * 21/8
    protected float $os_T3 = 5.25815761472485E-06; // n^2 * 15/8 + n^3 * 15/8
    protected float $os_T4 = 6.83150200284311E-09; // n^3 * 35/24
    protected float $os_e_squared = 0.00667054007414908; // (a^2 - b^2) / a^2

    // See https://www.ordnancesurvey.co.uk/documents/resources/guide-coordinate-systems-great-britain.pdf
    // Section A.2
    protected float $os_F0 = 0.9996012717; // UK national Grid Reference System - Airy1830
    protected float $os_phi0_deg = 49.0; // UK national Grid Reference System
    protected float $os_phi0_rad = 0; // UK national Grid Reference System
    protected float $os_lambda0_deg = -2.0;
    protected float $os_lambda0_rad = 0;

    protected float $os_E0 =  400000.0;
    protected float $os_N0 = -100000.0;

    // --------- WGS84 datum parameters ------------------------
    protected float $wgs84_a_m = 6378137.0;
    protected float $wgs84_f = 1.0 / 298.257223563;
    protected float $wgs84_b_m = 6356752.3141; // $this->wgs84_a_m * (1.0 - $this->wgs84_f);
    protected float $wgs84_n = 0.00167922039780298 ; // (a-b) / (a+b)
    protected float $wgs84_T1 = 1.00168275104303; // 1 + n + n^2 * 5/4 + n^3 * 5/4
    protected float $wgs84_T2 = 0.00504613296630642; // 3n + 3n^2 + n^3 * 21/8
    protected float $wgs84_T3 = 5.29596783452365E-06; // n^2 * 15/8 + n^3 * 15/8
    protected float $wgs84_T4 = 6.90525793856016E-09; // n^3 * 35/24

    // See https://www.ordnancesurvey.co.uk/documents/resources/guide-coordinate-systems-great-britain.pdf
    // Section B.1 - equation B1
    protected float $wgs84_e_squared = 0.00669438003551284; // (a^2 - b^2) / a^2

    // Hexagonal packing efficiency - about 90% - quite impressive
    protected float $hexagonal_packing_efficiency = 0.9;

    // Land areas
    protected float $land_area_sun_m2     = 6.09E18; // but it's not really land is it? Also, er, wind....?
    protected float $land_area_earth_m2   = 148940000 * 1000000; // 148.94 km^2
    protected float $land_area_asia_m2    =  44579000 * 1000000; //  44.579 km^2
    protected float $land_area_russia_m2  =  17098242 * 1000000; //  17.098242 km^2
    protected float $land_area_uk_m2      =    244376 * 1000000;

    // -----------------------------------------------------------------------------------------------------------------
    public function __construct()
    {
        $this->DEBUGFILE = __DIR__. DIRECTORY_SEPARATOR . 'DEBUG_'. date('Y-m-d_H-i-s').'.txt';
        $this->os_phi0_rad = (double)deg2rad($this->os_phi0_deg);
        $this->os_lambda0_rad = deg2rad($this->os_lambda0_deg);
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function clamp_int_field(
        string $field,
        int $min,
        ?int $max,
        string $label
    ) : void
    {
        if (!array_key_exists($field, $this->data) || ($this->data[$field] === '') || ($this->data[$field] === null))
        {
            return;
        }

        $original = (int) $this->data[$field];
        $adjusted = max($min, $original);
        if ($max !== null) {
            $adjusted = min($adjusted, $max);
        }

        if ($adjusted !== $original) {
            $this->messages[] = sprintf('Adjusted %s from %d to %d.', $label, $original, $adjusted);
        }

        $this->data[$field] = $adjusted;
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function earthRadiusAtLatTriple(
        float $lat_deg
    ) : array
    {
        // radcur
        $a_m     = $this->wgs84_a_m;
        $b_m     = $this->wgs84_b_m;

        $asq   = $a_m * $a_m;
        $bsq   = $b_m * $b_m;
        $eccsq  =  1 - $bsq / $asq;
        //$ecc = sqrt( $eccsq );

        $clat  =  cos(deg2rad( $lat_deg ) );
        $slat  =  sin(deg2rad( $lat_deg ) );

        $dsq   =  1.0 - $eccsq * $slat * $slat;
        $d     =  sqrt( $dsq );

        $rn    =  $a_m / $d;
        $rm    =  $rn * (1.0 - $eccsq ) / $dsq;

        $rho   =  $rn * $clat;
        $z     =  (1.0 - $eccsq ) * $rn * $slat;
        $rsq   =  $rho * $rho + $z * $z;
        $r_m   =  sqrt( $rsq );

        return array( $r_m, $rn, $rm );
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function earthRadiusAtLat_m(
        float $lat_deg
    ) : float
    {
        // rearth
        list( $r_m, $rn, $rm ) = $this->earthRadiusAtLatTriple( $lat_deg );
        return $r_m;
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function normaliseLat_deg(
        float $lat_deg
    ) : float
    {
        if ($lat_deg > 90.0)
        {
            $lat_deg = 180.0 - $lat_deg;

        } elseif( $lat_deg < -90.0 )
        {
            $lat_deg = -180.0 - $lat_deg;
        }
        return $lat_deg;
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function normaliseLon_deg(
        float $lon_deg
    ) : float
    {
        if ($lon_deg > 180.0) 
        {
            $lon_deg = $lon_deg - 360.0;

        } elseif( $lon_deg < -180.0 ) 
        {
            $lon_deg = $lon_deg + 360.0;
        }
        return $lon_deg;
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function ll2FromLL1WithBearingAndDistance_deg(
        float $lat1_rad,
        float $lon1_rad,
        float $bearing_rad,
        float $distance_m
    ) : array
    {
        // From https://www.igismap.com/formula-to-find-bearing-or-heading-angle-between-two-points-latitude-longitude/

        // From https://www.movable-type.co.uk/scripts/latlong.html#dest-point
        $angular_distance = $distance_m / $this->earthRadiusAtLat_m( $lat1_rad );

        $lat2_rad = asin( sin( $lat1_rad ) * cos($angular_distance ) +
            cos( $lat1_rad ) * sin($angular_distance ) * cos( $bearing_rad ) );
        $lon2_rad = $lon1_rad + atan2(sin( $bearing_rad ) * sin( $angular_distance ) * cos( $lat1_rad ),
                cos( $angular_distance ) - sin( $lat1_rad ) * sin( $lat1_rad ) );
        return [
            $this->normaliseLat_deg( rad2deg( $lat2_rad ) ),
            $this->normaliseLon_deg( rad2deg( $lon2_rad ) )
        ];
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function convert_lat_lon_to_xyz(
        array $lat_lon,
        float $earth_a_m,
        float $earth_e_squared
    ) : array
    {
        $H = 0; // Height above ellipsoid
        $v = $earth_a_m / sqrt( 1 - $earth_e_squared * pow( sin( $lat_lon[0] ), 2 ));
        $x = ($v + $H) * cos( $lat_lon[0] ) * cos( $lat_lon[1] );
        $y = ($v + $H) * cos( $lat_lon[0] ) * sin( $lat_lon[1] );
        $z = ((1- $earth_e_squared) * $v + $H) * sin( $lat_lon[0] );
        return array( $x, $y, $z );
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function convert_os_en_to_dms(
        int $E,
        int $N
    ) : array
    {
        // See https://www.ordnancesurvey.co.uk/documents/resources/guide-coordinate-systems-great-britain.pdf
        // Section C.2
        $M = (float)0.0;
        $phi_tick = $this->os_phi0_rad;
/*
echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'E=%0d, N=%0d', $E, $N).PHP_EOL;
echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'a=%0.10f', $this->os_a_m).PHP_EOL;
echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'b=%0.10f', $this->os_b_m).PHP_EOL;
echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'phi0=%0.10f', $this->os_phi0_rad).PHP_EOL;
echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'lambda0=%0.10f', $this->os_lambda0_rad).PHP_EOL;
echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'E0=%0.10f', $this->os_E0). PHP_EOL;
echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'N0=%0.10f', $this->os_N0).PHP_EOL;
echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'F0=%0.10f', $this->os_F0).PHP_EOL;
echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'e2=%0.10f', $this->os_e_squared).PHP_EOL;
echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'n=%0.10f', $this->os_n).PHP_EOL;
*/

        do {
            $phi_tick = (float)( $N - $this->os_N0 - $M) / ($this->os_a_m * $this->os_F0) + $phi_tick;
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'phi_tick=%0.10f', $phi_tick).PHP_EOL;

            $M  = $this->os_T1 * ($phi_tick - $this->os_phi0_rad)
                - $this->os_T2 * sin($phi_tick - $this->os_phi0_rad) * cos($phi_tick + $this->os_phi0_rad)
                + $this->os_T3 * sin(2 * ($phi_tick - $this->os_phi0_rad)) * cos(2 * ($phi_tick + $this->os_phi0_rad))
                - $this->os_T4 * sin(3 * ($phi_tick - $this->os_phi0_rad)) * cos(3 * ($phi_tick + $this->os_phi0_rad));
            $M *= $this->os_b_m * $this->os_F0;
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'M=%0.10f', $M).PHP_EOL;

        } while ( abs( $N - $this->os_N0 - $M ) > 0.00001 );

        $nu =  ($this->os_a_m * $this->os_F0) * pow( 1 - $this->os_e_squared * pow( sin( $phi_tick ), 2 ), -0.5 );
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'nu=%0.10f', $nu).PHP_EOL;
        $rho = ($this->os_a_m * $this->os_F0) *    (1 - $this->os_e_squared)      * pow( 1 - $this->os_e_squared * pow( sin( $phi_tick ), 2 ), -1.5 );
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'rho=%0.10f', $rho).PHP_EOL;
        $eta_squared = $nu / $rho - 1;
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'eta_squared=%0.10f', $eta_squared).PHP_EOL;
        $tan_phi_tick = tan( $phi_tick);
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'tan_phi_tick=%0.10f', $tan_phi_tick).PHP_EOL;
        $tan_phi_tick_squared = pow( $tan_phi_tick, 2);
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'tan_phi_tick_squared=%0.10f', $tan_phi_tick_squared).PHP_EOL;
        $VII      = $tan_phi_tick / (  2 * $rho * $nu);
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'VII=%0.10e', $VII).PHP_EOL;
        $VIII     = $tan_phi_tick / ( 24 * $rho * pow( $nu, 3 )) * ( 5 +  3 * $tan_phi_tick_squared + $eta_squared - 9 * $eta_squared * $tan_phi_tick_squared);
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'VIII=%0.10e', $VIII).PHP_EOL;
        $IX       = $tan_phi_tick / (720 * $rho * pow( $nu, 5 )) * (61 + 90 * $tan_phi_tick_squared + 45 * pow( $tan_phi_tick_squared, 2 ));
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'IX=%0.10e', $IX).PHP_EOL;
        // secant - I've NEVER used this - fortunately, it's just the reciprocal of cosine
        $X        = 1 / (        cos( $phi_tick ) * $nu );
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'X=%0.10e', $X).PHP_EOL;
        $XI       = 1 / (    6 * cos( $phi_tick ) * pow( $nu, 3 )) * ($nu / $rho + 2 * $tan_phi_tick_squared);
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'XI=%0.10e', $XI).PHP_EOL;
        $XII      = 1 / (  120 * cos( $phi_tick ) * pow( $nu, 5 )) * ( 5 +  28 * $tan_phi_tick_squared +   24 * pow( $tan_phi_tick_squared, 2 ));
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'XII=%0.10e', $XII).PHP_EOL;
        $XII_A    = 1 / ( 5040 * cos( $phi_tick ) * pow( $nu, 7 )) * (61 + 662 * $tan_phi_tick_squared + 1320 * pow( $tan_phi_tick_squared, 2 ) + 720 * pow( $tan_phi_tick_squared, 3 ));
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'XII_A=%0.10e', $XII_A).PHP_EOL;

        $ellipsoidal_os_phi_r    = $phi_tick             - $VII * pow( $E - $this->os_E0, 2) + $VIII * pow( $E - $this->os_E0, 4) - $IX  * pow( $E - $this->os_E0, 6);
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'phi=%0.10f', $ellipsoidal_os_phi_r).PHP_EOL;
        $ellipsoidal_os_lambda_r = $this->os_lambda0_rad + $X   *    ( $E - $this->os_E0 )                 - $XI   * pow( $E - $this->os_E0, 3) + $XII * pow( $E - $this->os_E0, 5) - $XII_A * pow( $E - $this->os_E0, 7);
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'lambda=%0.10f', $ellipsoidal_os_lambda_r).PHP_EOL;

        // Now need to convert to ECEF (3D Earth Centred Earth Fixed) coordinates
        // See https://www.ordnancesurvey.co.uk/documents/resources/guide-coordinate-systems-great-britain.pdf
        // Section B.1
        list( $x_os, $y_os, $z_os) = $this->convert_lat_lon_to_xyz(
            array( $ellipsoidal_os_phi_r, $ellipsoidal_os_lambda_r ),
            $this->os_a_m,
            $this->os_e_squared
        );
        /*
        $H = 0; // Height above ellipsoid
        $v = $this->os_a_m / sqrt( 1 - $this->os_e_squared * pow( sin( $ellipsoidal_os_phi_r ), 2 ));
        $x_os = ($v + $H) * cos( $ellipsoidal_os_phi_r ) * cos( $ellipsoidal_os_lambda_r );
        $y_os = ($v + $H) * cos( $ellipsoidal_os_phi_r ) * sin( $ellipsoidal_os_lambda_r );
        $z_os = ((1- $this->os_e_squared) * $v + $H) * sin( $ellipsoidal_os_phi_r );
        //*/
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 'x_os=%0.10f, y_os=%0.10f, z_os=%0.10f', $x_os, $y_os, $z_os).PHP_EOL;
        // Now change the datum from OS GB to WGS84
        // See https://www.ordnancesurvey.co.uk/documents/resources/guide-coordinate-systems-great-britain.pdf
        // Section 6.2

        // These are the transform values when converting TO OS
        // tx        ty       tz         s        rx        ry        rz
        // -446.448, 125.157, -542.060,  20.4894, -0.1502,  -0.2470,  -0.8421

        // We're converting FROM, so every value is negated
        $s = -20.4894 / 1e6 + 1;
        $rx = deg2rad( 0.1502 / 3600 );
        $ry = deg2rad( 0.2470 / 3600 );
        $rz = deg2rad( 0.8421 / 3600 );
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( 's=%0.10f, rx=%0.10e, ry=%0.10e, rz=%0.10e', $s, $rx, $ry, $rz).PHP_EOL;

        /* - The approximation method
        $x_wgs84 =  446.448 + $x_os * $s  - $y_os * $rz + $z_os * $ry;
        $y_wgs84 = -125.157 + $x_os * $rz + $y_os * $s  - $z_os * $rx;
        $z_wgs84 =  542.060 - $x_os * $ry + $y_os * $rx + $z_os * $s;
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( '%0.10f + %0.12f -                  %0.12f + %0.12f',  446.448,  $x_os * $s,              $y_os * $rz, $z_os * $ry).PHP_EOL;
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( '%0.10f + %0.12f +                  %0.12f - %0.12f', -125.157,  $x_os * $rz,             $y_os * $s,  $z_os * $rx).PHP_EOL;
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( '%0.10f - %0.12f + %0.12f * %0.12f (%0.12f) + %0.12f',  542.060, $x_os * $ry, $y_os, $rx, $y_os * $rx, $z_os * $s).PHP_EOL;
echo __FUNCTION__.'/'.__LINE__.' '.sprintf( '(n) x_wgs84=%0.10f, y_wgs84=%0.10f, z_wgs84=%0.10f', $x_wgs84, $y_wgs84, $z_wgs84).PHP_EOL;
        //*/

        //*
        $transformed_xyz = $this->rotate3DPoint( [ $x_os, $y_os, $z_os], [ $rx, $ry, $rz ] );
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( '(r) x_wgs84=%0.10f, y_wgs84=%0.10f, z_wgs84=%0.10f', $transformed_xyz[0], $transformed_xyz[1], $transformed_xyz[2]).PHP_EOL;
        $transformed_xyz = $this->scale3DPoint( $transformed_xyz, [ $s, $s, $s ]);
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( '(s) x_wgs84=%0.10f, y_wgs84=%0.10f, z_wgs84=%0.10f', $transformed_xyz[0], $transformed_xyz[1], $transformed_xyz[2]).PHP_EOL;
        $transformed_xyz = $this->translate3DPoint( $transformed_xyz, [ 446.448, -125.157, 542.060]);
//echo __FUNCTION__.'/'.__LINE__.' '.sprintf( '(t) x_wgs84=%0.10f, y_wgs84=%0.10f, z_wgs84=%0.10f', $transformed_xyz[0], $transformed_xyz[1], $transformed_xyz[2]).PHP_EOL;
        list( $x_wgs84, $y_wgs84, $z_wgs84 ) = $transformed_xyz;
        //*/

        // Finally convert from WGS84 3D coordinates to lat, lon
        // See https://www.ordnancesurvey.co.uk/documents/resources/guide-coordinate-systems-great-britain.pdf
        // Section B.2
        $lambda_wgs84_r = atan2( $y_wgs84, $x_wgs84 );
        $p_wgs84 = sqrt( $x_wgs84 * $x_wgs84 + $y_wgs84 * $y_wgs84 );

        $phi_wgs84_r = atan2( $z_wgs84, $p_wgs84 * (1 - $this->wgs84_e_squared) );

        do {
            $prev_phi_wgs84_r = $phi_wgs84_r;
            $sin_phi_wgs84 = sin($phi_wgs84_r);
            $v_wgs84 = $this->wgs84_a_m / sqrt(1 - $this->wgs84_e_squared * $sin_phi_wgs84 * $sin_phi_wgs84);
            $phi_wgs84_r = atan2($z_wgs84 + $this->wgs84_e_squared * $v_wgs84 * $sin_phi_wgs84, $p_wgs84);
        } while( abs( $phi_wgs84_r - $prev_phi_wgs84_r ) > 1e-6 );

        $H_wgs84 = $p_wgs84 / cos( $phi_wgs84_r ) - $v_wgs84;

        return [ rad2deg( $phi_wgs84_r ), rad2deg( $lambda_wgs84_r ), $phi_wgs84_r, $lambda_wgs84_r, $x_wgs84, $y_wgs84, $z_wgs84 ];
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function convert_osg_to_dms(
        string $square,
        int $E,
        int $N
    ) : array
    {
        $l1 = mb_substr( $square,0, 1 );
        $l2 = mb_substr( $square,1, 1 );
        $easting_offset = 0;
        $northing_offset = 0;
        $l1_offsets = [
            'H' => [     0,1000000],
            'J' => [500000,1000000],
            'N' => [     0, 500000],
            'O' => [500000, 500000],
            'S' => [     0,      0],
            'T' => [500000,      0]
        ];
        $l2_offsets = [
            'A' => [     0, 400000],
            'B' => [100000, 400000],
            'C' => [200000, 400000],
            'D' => [300000, 400000],
            'E' => [400000, 400000],
            'F' => [     0, 300000],
            'G' => [100000, 300000],
            'H' => [200000, 300000],
            'J' => [300000, 300000],
            'K' => [400000, 300000],
            'L' => [     0, 200000],
            'M' => [100000, 200000],
            'N' => [200000, 200000],
            'O' => [300000, 200000],
            'P' => [400000, 200000],
            'Q' => [     0, 100000],
            'R' => [100000, 100000],
            'S' => [200000, 100000],
            'T' => [300000, 100000],
            'U' => [400000, 100000],
            'V' => [     0,      0],
            'W' => [100000,      0],
            'X' => [200000,      0],
            'Y' => [300000,      0],
            'Z' => [400000,      0],
        ];
        if (array_key_exists( $l1, $l1_offsets))
        {
            $easting_offset  += $l1_offsets[$l1][0];
            $northing_offset += $l1_offsets[$l1][1];
        }
        if (array_key_exists( $l2, $l2_offsets))
        {
            $easting_offset  += $l2_offsets[$l2][0];
            $northing_offset += $l2_offsets[$l2][1];
        }
/*
var_dump( $E + $easting_offset,
    $N + $northing_offset );
//*/

        return $this->convert_os_en_to_dms(
            $E + $easting_offset,
            $N + $northing_offset
        );
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function test_coordinate(
        string $coordinate_to_test
    ) : array
    {
        $coord_okay = false;
        $lat = null;
        $lon = null;
        $lat_r = null;
        $lon_r = null;
        $x = null;
        $y = null;
        $z = null;
        $repeat_type = null;
        $repeat_count = null;

        //$re_osg  = '^[HJNOST][A-HJ-Z] ?[0-9]{5} ?[0-9]{5}$';
        $re_osg  = '^(H[L-Z]|J[LMQRVW]|[NS][A-HJ-Z]|[OT][ABFGLMQRVW]) ?([0-9]{5}) ?([0-9]{5})';
        $re_osen = '^([0-9]{6,7}) ?([0-9]{6,7})';
        $re_dms1 = '^([0-9]{1,3})[°] ?([0-9]{1,2})[\'] ?([0-9]{1,2}(?:\.[0-9]+)?)["]'; //([NESWnesw])';
        $re_dms2 = '^([0-9]{1,3})[:] ?([0-9]{1,2})[:] ?([0-9]{1,2}(?:\.[0-9]+)?)'; //([NESWnesw])';
        $re_dd   = '^-?\d{1,3}(?:\.\d+)?';
        $re_suffix = '^ *((?: *\*)+|(?: *x)+) *(\d+)$';

        $rest = '';

        $match = mb_ereg( $re_osg, $coordinate_to_test, $matches );
        if ($match)
        {
            list( $lat, $lon, $lat_r, $lon_r, $x, $y, $z ) = $this->convert_osg_to_dms( $matches[1], (int)$matches[2], (int)$matches[3] );
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' converted %s (%s, %s) to Coordinates %0.6f, %0.6f', $coordinate_to_test, $matches[2], $matches[3], $lat, $lon) .PHP_EOL, FILE_APPEND);
            $rest = mb_substr( $coordinate_to_test, mb_strlen( $matches[0] ) );
            $coord_okay = true;
        }
        if (! $match)
        {
            $match = mb_ereg( $re_osen, $coordinate_to_test, $matches );
            if ($match)
            {
                list( $lat, $lon, $lat_r, $lon_r, $x, $y, $z ) = $this->convert_os_en_to_dms( (int)$matches[1], (int)$matches[2] );
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' converted %s (%s, %s) to Coordinates %0.6f, %0.6f', $coordinate_to_test, $matches[1], $matches[2], $lat, $lon) .PHP_EOL, FILE_APPEND);
                $rest = mb_substr( $coordinate_to_test, mb_strlen( $matches[0] ) );
                $coord_okay = true;
            }
        }
        if (! $match)
        {
            $match1 = mb_ereg( $re_dms1, $coordinate_to_test, $matches1 );
            $match2 = mb_ereg( $re_dms2, $coordinate_to_test, $matches2 );
            if ($match1 || $match2)
            {
                $matches = $match1 ? $matches1 : $matches2;
                $match_len = mb_strlen( $matches[0] );
                $hemisphere = mb_substr( $coordinate_to_test, $match_len - 1, 1 );
                $hemisphere_match = mb_ereg( '[NSns]', $hemisphere );
                if ($hemisphere_match)
                {
                    $lat = (float)$matches[1] + (float)$matches[2] / 60 + (float)$matches[3] / 3600;
                    if (mb_strtoupper( $hemisphere) == 'S')
                    {
                        $lat = -$lat;
                    }
                    $coord_part2 = mb_trim( mb_substr( $coordinate_to_test, $match_len ) );
                    $match = mb_ereg( $re_dms1, $coord_part2, $matches );
                    if ($match)
                    {
                        $match_len = mb_strlen( $matches[0] );
                        $hemisphere = mb_substr( $coord_part2, $match_len - 1, 1 );
                        $hemisphere_match = mb_ereg( '[EWew]', $hemisphere );
                        if ($hemisphere_match)
                        {
                            $lon = (float)$matches[1] + (float)$matches[2] / 60 + (float)$matches[3] / 3600;
                            if (mb_strtoupper( $hemisphere) == 'W')
                            {
                                $rest = mb_substr( $coord_part2, mb_strlen( $match_len + 1 ) );
                                $lon = -$lon;
                                $coord_okay = true;
                                $lat_r = deg2rad( $lat );
                                $lon_r = deg2rad( $lon );
                                list( $x, $y, $z ) = $this->convert_lat_lon_to_xyz(
                                    array( $lat_r, $lon_r ),
                                    $this->wgs84_a_m,
                                    $this->wgs84_e_squared
                                );
                            }
                        }
                    }
                }
            }
        }
        if (! $match)
        {
            $match = mb_ereg( $re_dd, $coordinate_to_test, $matches );
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' coord=%s match=%s matches=%s', $coordinate_to_test, print_r( $match, true ), print_r( $matches, true ) ) .PHP_EOL, FILE_APPEND);
            if ($match)
            {
                $lat = (float)$matches[0];
                $match_len = mb_strlen( $matches[0] );
                $coord_part2 = mb_trim( mb_substr( $coordinate_to_test, $match_len ) );
                $match = mb_ereg( $re_dd, $coord_part2, $matches );
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' coord=%s match=%s matches=%s', $coord_part2, print_r( $match, true ), print_r( $matches, true ) ) .PHP_EOL, FILE_APPEND);
                if ($match)
                {
                    $rest = mb_substr( $coord_part2, mb_strlen( $matches[0] ) );
                    $lon = (float)$matches[0];
                    $coord_okay = true;
                    $lat_r = deg2rad( $lat );
                    $lon_r = deg2rad( $lon );
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' rest=%s lat=%0.6f lon=%0.6f', $rest, $lat, $lon ) .PHP_EOL, FILE_APPEND);
                    list( $x, $y, $z ) = $this->convert_lat_lon_to_xyz(
                        array( $lat_r, $lon_r ),
                        $this->wgs84_a_m,
                        $this->wgs84_e_squared
                    );
                }
            }
        }
        if ($coord_okay)
        {
            // Now test if the repetition suffix is valid
            $rest = mb_trim( $rest );
            if (mb_strlen( $rest ) > 0)
            {
                $match = mb_ereg( $re_suffix, $rest, $matches );
                if ( ! $match)
                {
                    $coord_okay = true;
                } else
                {
                    $repeat_type = mb_ereg_replace( ' ', '' , $matches[1] );
                    $repeat_count = (int)$matches[2];
                }
            }
        }
        return [ $coord_okay, $lat, $lon, $lat_r, $lon_r, $x, $y, $z, $repeat_type, $repeat_count ];
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function guard_size_m(
        string $repeat_type,
        float $blade_length_m
    ) : float
    {
        return  $blade_length_m * ($this->safe_blade_length_between_towers_ratio - 1 + max( mb_strlen( $repeat_type ) * 2 / 3, 1 ));
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function hexagonal_packing_content_for_shell(
        int $n
    ) : int
    {
        return 3 * $n * ( $n - 1 ) + 1;
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function hexagonal_packing_for(
        int $Tn
    ) : array
    {
        // First need to solve Tn = 3n(n-1) + 1
        $n = (3 + sqrt( 12 * $Tn -3)) / 6;
        if ( $n != floor( $n ) )
        {
            $n = floor( $n ) + 1;
            $Tn = $this->hexagonal_packing_content_for_shell( $n );
        }
        return [ $n, $Tn ];
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function radius_of_enclosing_circle_for_repeats(
        int $repeat_count,
        float $tower_footprint_diameter_m
    ) : float
    {
        $r = $tower_footprint_diameter_m / 2;
        $Tn = $repeat_count;

        /*
        /*
         * The following solution is based on using TRIANGLES as the clustering arrangement

        // First need to find the next higher triangular number (unless repeat_count is already one)
        $n = (-1 + sqrt(1 + 8 * $Tn)) / 2;
        if ( $n != floor( $n ) )
        {
            $n = floor( $n ) + 1;
            $Tn = $n * ( $n + 1 ) / 2;
        }

        // This is the smallest enclosing circle for a cluster of the most densely packed circles (turbine footprints)

        return $r * (1 + 2* ($n - 1) * ( ($n % 1) ? 1 : sqrt(3)));

        */

        // THe following code is for using hexagons as the clustering arrangement. Apparently only proven in 1998
        // by Thomas Hales ("Honeycomb Conjecture") to be the most efficient packing of circles. Fabulous.
        // Apparently gives about 90% efficiency. So, total area used is 1/0.9 = 1.1111111111111112 * r

        list( $n, $Tn ) = $this->hexagonal_packing_for( $Tn );
        return $r * (1 + 2 * ($n - 1));
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function create_repeated_turbines(
        string $base_name,
        array $lat_lon,
        int $Tn,
        float $tower_footprint_m
    ) : bool
    {
        $okaySoFar = true;
        /*
                      v This "vertex" is 30 deg bearing from C and distance (2*r * 3)
               . . . .
              . . . . .
             . . . . . .<- this cell is on a bearing of 150 from the vertex above, distance (2*r * 2)
            . . . C . . .
             . . . . . .
              . . . . .
               . . . .

        */
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' base_name=%s lat=%0.6f lon=%0.6f Tn=%d tower_footprint_m=%0.1f', $base_name, $lat_lon[0], $lat_lon[1], $Tn, $tower_footprint_m) .PHP_EOL, FILE_APPEND);

        $centre_lat_r = deg2rad($lat_lon[0]);
        $centre_lon_r = deg2rad($lat_lon[1]);
        $this->locations[] = [
            true,
            $base_name .'/1',
            $lat_lon[0], $lat_lon[1],
            $centre_lat_r, $centre_lon_r,
            null, null, null,
            null, null
        ];
        $cells = 1; // already created the first one above
        $shell = 2; // now working on the second shell

        while ($cells < $Tn)
        {
            $first_hexagon_vertex = count( $this->locations );

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' cells=%d first_hexgon_vertex=%d', $cells, $first_hexagon_vertex) .PHP_EOL, FILE_APPEND);

            for ($i = 0; $i < 6; $i++)
            {
                // This fills in the "vertices" of the hexagon for the shell
                list( $lat, $lon ) = $this->ll2FromLL1WithBearingAndDistance_deg(
                    $centre_lat_r, $centre_lon_r,
                    deg2rad( 60 * $i - 30),
                    $tower_footprint_m * 2 * ($shell - 1)
                );

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' shell=%d i=%d name=%s lat=%0.6f lon=%0.6f', $shell, $i, $base_name .sprintf( '/%d', $cells), $lat, $lon) .PHP_EOL, FILE_APPEND);

                $this->locations[] = [
                    true,
                    $base_name .sprintf( '/%d', $cells),
                    $lat, $lon,
                    deg2rad($lat), deg2rad($lon),
                    null, null, null,
                    null, null
                ];
                $cells++;
                if ($cells >= $Tn)
                {
                    break;
                }
            }
            if (($cells < $Tn)
                && ($shell >= 3)
            ) {
                // Now have to "in fill" with the cells between the vertices in the outer shell, for each vertex
                for ($i = 0; $i < 6; $i++)
                {
                    $vertex_lat_r = $this->locations[$first_hexagon_vertex + $i][4];
                    $vertex_lon_r = $this->locations[$first_hexagon_vertex + $i][5];
                    // This is how many in fills there are
                    for ($c = 0; $c < $shell - 2; $c++)
                    {
                        list( $lat, $lon ) = $this->ll2FromLL1WithBearingAndDistance_deg(
                            $vertex_lat_r, $vertex_lon_r,
                            deg2rad( 60 * ($i + 2) - 30),
                            $tower_footprint_m * 2 * ($c + 1)
                        );
                        $this->locations[] = [
                            true,
                            $base_name .sprintf( '/%d', $cells),
                            $lat, $lon,
                            deg2rad($lat), deg2rad($lon),
                            null, null, null,
                            null, null
                        ];
                        $cells++;
                        if ($cells >= $Tn)
                        {
                            break;
                        }
                    }
                }
            }
            $shell++;
        }

        return $okaySoFar;
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function check_coordinates() : bool
    {
        $okaySoFar = true;
        $repeat_type = null;
        $repeat_count = null;
        $tower_count = 0;
        $repeats = [];

        if (is_string($this->data[K_LOCATIONS]))
        {
            $this->data[K_LOCATIONS] = preg_split( '/(\r\n|\n)/u', $this->data[K_LOCATIONS]);
        }
        if ( ! is_array( $this->data[K_LOCATIONS] ) || empty( $this->data[K_LOCATIONS] ))
        {
            $this->messages[] = 'No suitable locations provided';
            $okaySoFar = false;
        }
        else
        {
            $temp_locations = [];
            foreach ($this->data[K_LOCATIONS] as $location_num => $location)
            {
                $location = mb_trim($location);
                if (! empty($location))
                {
                    $temp_locations[] = $location;
                }
            }
            if (empty($temp_locations))
            {
                $this->messages[] = 'No suitable locations provided';
                $okaySoFar = false;
            } else
            {
                foreach ($temp_locations as $location_num => $location)
                {
                    $location = mb_trim($location);
                    $location_parts = mb_split( ',', $location );
                    foreach( $location_parts as $lk => $lv )
                    {
                        $location_parts[ $lk ] = mb_trim( $lv );
                        if (empty( $location_parts[ $lk ] ))
                        {
                            $this->messages[] = 'Invalid location provided (blank part): ' . $location;
                            $okaySoFar = false;
                        }
                    }
                    if ($okaySoFar)
                    {
                        if ( (count( $location_parts) > 2) || (count( $location_parts) < 1) )
                        {
                            $this->messages[] = 'Invalid location provided (more than just name and coordinates): ' . $location;
                            $okaySoFar = false;
                        }
                        else
                        {
                            $location_name = sprintf( '%d', $location_num + 1);
                            if (count( $location_parts) == 2)
                            {
                                $n = $location_parts[0];
                                if ( (mb_substr( $n, 0, 1) == '[')
                                    && (mb_substr( $n, -1, 1) == ']')
                                ) {
                                    $location_name = mb_substr( $n, 1, -1);
                                }
                                else
                                {
                                    $this->messages[] = 'Invalid location name provided (needs to be [name] with the square brackets): ' . $location;
                                    $okaySoFar = false;
                                }
                                array_shift( $location_parts );
                            }
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf(' location_parts=%s', print_r( $location_parts, true ) ) .PHP_EOL, FILE_APPEND);
                            list( $coord_okay,
                                $lat, $lon,
                                $lat_r, $lon_r,
                                $x, $y, $z,
                                $repeat_type, $repeat_count
                                ) = $this->test_coordinate( $location_parts[0] );
                            if (! $coord_okay)
                            {
                                $this->messages[] = 'Invalid coordinate provided: '. $location_parts[0];
                                $okaySoFar = false;
                            }
                            else
                            {
                                $this->locations[ $location_num ] = [
                                    true,                       // [0]
                                    $location_name,             // [1]
                                    $lat, $lon,                 // [2], [3]
                                    $lat_r, $lon_r,             // [4], [5]
                                    $x, $y, $z,                 // [6], [7], [8]
                                    $repeat_type, $repeat_count // [9], [10]
                                ];
                            }
                        }
                    }

                    if ( ! is_string($location) || empty($location))
                    {
                        $this->messages[] = 'Invalid location provided';
                        $okaySoFar = false;
                    }
                }
            }
            if ($okaySoFar)
            {
                // Now need to check the distanced between the towers to ensure they're not too close to each other
                // otherwise the blades could / will foul with each other - expensive

                $safe_tower_distance_m = $this->guard_size_m(
                    '*',
                    $this->data[K_BLADELENGTH]
                );

                $towers_footprint_total_m2 = 0.0;

                for ($loc_i = 0; $loc_i < count($this->locations); $loc_i++)
                {
                    $name_i = $this->locations[$loc_i][1];
                    if ( empty( $this->locations[$loc_i][9] ))
                    {
                        $safe_tower_distance_i_m = $safe_tower_distance_m / 2;
                    }
                    else
                    {
                        $safe_tower_distance_i_m = $this->radius_of_enclosing_circle_for_repeats(
                            $this->locations[$loc_i][10],
                            $this->guard_size_m(
                                $this->locations[$loc_i][9],
                                $this->data[K_BLADELENGTH]
                            )
                        );
                        $name_i .= ' (cluster of '. $this->locations[$loc_i][10] .' turbines)';
                        if ($this->locations[$loc_i][10] > 1)
                        {
                            $this->locations[$loc_i][0] = false;
                            $repeats[] = [
                                $this->locations[$loc_i][1], // name
                                $this->locations[$loc_i][2], $this->locations[$loc_i][3], // lat, lon
                                $this->locations[$loc_i][9], $this->locations[$loc_i][10] // repeat type, repeat count
                            ];
                        }
                    }
                    $towers_footprint_total_m2 += M_PI * $safe_tower_distance_i_m * $safe_tower_distance_i_m;

                    for ($loc_j = $loc_i+1; $loc_j < count($this->locations); $loc_j++)
                    {
                        $dist_m = $this->distanceBetweenLLH_m(
                            $this->locations[$loc_i][2],
                            $this->locations[$loc_i][3],
                            null,
                            $this->locations[$loc_j][2],
                            $this->locations[$loc_j][3],
                            null
                        );
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' tower distance check i='. $loc_i .' ('. $this->locations[$loc_i][2] .','. $this->locations[$loc_i][3] .') j='. $loc_j .' ('. $this->locations[$loc_j][2] .','. $this->locations[$loc_j][3] .') dist_m='. $dist_m .PHP_EOL, FILE_APPEND);

                        $name_j = $this->locations[$loc_j][1];
                        if ( empty( $this->locations[$loc_j][9] ))
                        {
                            $safe_tower_distance_j_m = $safe_tower_distance_m / 2;
                        }
                        else
                        {
                            $safe_tower_distance_j_m = $this->radius_of_enclosing_circle_for_repeats(
                                $this->locations[$loc_j][10],
                                $this->guard_size_m(
                                    $this->locations[$loc_j][9],
                                    $this->data[K_BLADELENGTH]
                                )
                            );
                            $name_j .= ' (cluster of '. $this->locations[$loc_j][10] .' turbines)';
                        }

                        if ( $dist_m < ( $safe_tower_distance_i_m + $safe_tower_distance_j_m ) )
                        {
                            $this->locations[$loc_j][0] = false;// "Disable" generation for this turbine
                            $this->messages[] = sprintf('Towers are too close together: %s and %s (%0.1fm) - blades (%dm long) could clash',
                                $name_i,
                                $name_j,
                                $dist_m,
                                $this->data[K_BLADELENGTH]
                            );
                            $okaySoFar = false;
                        }
                    }
                }
            }
            if ($okaySoFar)
            {
                // last check - have we got so many turbines, there isn't enough land for them????
                $areas = [
                    [ $this->land_area_sun_m2,
                        'Too many turbines / spacing for the Sun - you do know what solar wind is, don\'t you? - %0.1f m2 of "land", %0.1f m2 of towers' ],
                    [ $this->land_area_earth_m2,
                        'Too many turbines / spacing for the Earth - thinking big, nice try - %0.1f m2 of land+sea, %0.1f m2 of towers' ],
                    [ $this->land_area_asia_m2,
                        'Too many turbines / spacing to fit in Asia (the largest continent) - interesting idea, but... - %0.1f m2 of land+lakes, %0.1f m2 of towers' ],
                    [ $this->land_area_russia_m2,
                        'Too many turbines / spacing to fit in Russia (the largest country) - would solve many other problems, though - %0.1f m2 of land+lakes, %0.1f m2 of towers' ],
                    [ $this->land_area_uk_m2,
                        'Too many turbines / spacing to fit in the UK - where would people live? - %0.1f m2 of land+water, %0.1f m2 of towers' ],
                ];
                foreach($areas as list( $area_m2, $message))
                {
                    if ($towers_footprint_total_m2 / $this->hexagonal_packing_efficiency > $area_m2)
                    {
                        $this->messages[] = sprintf($message, $area_m2, $towers_footprint_total_m2 / $this->hexagonal_packing_efficiency);
                        $okaySoFar = false;
                        break;
                    }
                }
            }
            if ($okaySoFar
                && ! empty( $repeats )
            ) {
                // So, everything is "okay" (depending on how broad one defines that - perhaps beyond the scope of
                // non-sentient software.....)
                // ... now need to generate the repeated turbines.

                foreach( $repeats as $this_repeat )
                {
                    $this->create_repeated_turbines(
                        $this_repeat[0],
                        [ $this_repeat[1], $this_repeat[2] ],
                        $this_repeat[4],
                        $this->guard_size_m(
                            $this_repeat[3],
                            $this->data[K_BLADELENGTH]
                        )
                    );
                }
            }
        }
        return $okaySoFar;
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function normalise_input(
    ) : bool
    {
        $okaySoFar = true;
        $this->messages = [];

        if (isset($this->data[K_SITENAME])) {
            $trimmed = trim((string) $this->data[K_SITENAME]);
            if ($trimmed !== $this->data[K_SITENAME])
            {
                $this->messages[] = 'Trimmed the site name.';
            }
            $this->data[K_SITENAME] = $trimmed;

        } else
        {
            $this->messages[] = 'Set the site name.';
            $this->data[K_SITENAME] = 'Wind Turbine Visualisation';
        }

        $this->clamp_int_field( K_TOWERHEIGHT, 6, null, 'tower height' );
        $this->clamp_int_field( K_TOWERBASEDIAMETER, 1, null, 'tower base diameter' );

        if (array_key_exists(K_TOWERTOPDIAMETER, $this->data)
            && ($this->data[K_TOWERTOPDIAMETER] !== '')
            && ($this->data[K_TOWERTOPDIAMETER] !== null)
        ) {
            $original = (int) $this->data[K_TOWERTOPDIAMETER];
            $adjusted = max(1, $original);
            if (isset($this->data[K_TOWERBASEDIAMETER])
                && ((int) $this->data[K_TOWERBASEDIAMETER] > 0)
            ) {
                $adjusted = min($adjusted, (int) $this->data[K_TOWERBASEDIAMETER]);
            }
            if ($adjusted !== $original)
            {
                $this->messages[] = sprintf('Adjusted tower top diameter from %d to %d.', $original, $adjusted);
            }
            $this->data[K_TOWERTOPDIAMETER] = $adjusted;
        }

        $nacelleMin = isset($this->data[K_TOWERTOPDIAMETER]) ? (int) $this->data[K_TOWERTOPDIAMETER] : 1;

        if ($this->data[K_NACELLESHAPE] == V_NACELLE_CYLINDER)
        {
            $this->clamp_int_field( K_NACELLEDIAMETER, $nacelleMin, null, 'nacelle diameter' );
            $this->clamp_int_field( K_NACELLECYLINDERLENGTH, $nacelleMin, null, 'nacelle cylinder length' );
        }
        elseif ($this->data[K_NACELLESHAPE] == V_NACELLE_BOX)
        {
            $this->clamp_int_field( K_NACELLEBOXLENGTH, $nacelleMin, null, 'nacelle box length' );
            $this->clamp_int_field( K_NACELLEBOXWIDTH, $nacelleMin, null, 'nacelle box width' );
            $this->clamp_int_field( K_NACELLEBOXHEIGHT, $nacelleMin, null, 'nacelle box height' );
        }
        else
        {
            $this->messages[] = sprintf('Not a valid nacelle shape %s.', $this->data[K_NACELLESHAPE]);
            $okaySoFar = false;
        }

        $this->clamp_int_field( K_BLADECOUNT, 1, null, 'blade count' );

        if (array_key_exists(K_BLADELENGTH, $this->data)
            && ($this->data[K_BLADELENGTH] !== '')
            && ($this->data[K_BLADELENGTH] !== null)
        ){
            $original = (int) $this->data[K_BLADELENGTH];
            $adjusted = max(1, $original);
            if (isset($this->data[K_TOWERHEIGHT])
                && ((int) $this->data[K_TOWERHEIGHT] > 5)
            ) {
                $adjusted = min($adjusted, (int) $this->data[K_TOWERHEIGHT] - 5);
            }
            if ($adjusted !== $original) {
                $this->messages[] = sprintf('Adjusted blade length from %d to %d.', $original, $adjusted);
            }
            $this->data[K_BLADELENGTH] = $adjusted;
        }

        $this->clamp_int_field( K_BLADEROOTDIAMETER, 1, null, 'blade root diameter' );
        $this->clamp_int_field( K_BLADE1POSITION, 1, 12, 'first blade position' );
        $this->clamp_int_field( K_TOWERORIENTATION1, 0, 359, 'first turbine orientation' );
        $this->clamp_int_field( K_TOWERORIENTATIONCOUNT, 1, 9, 'orientation count' );

        $okaySoFar = $okaySoFar && $this->check_coordinates();

        return $okaySoFar;
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function checkData(
        array $d
    ) : array
    {
        $okaySoFar = true;
        $m = [];
        $required = [
            K_SITENAME,
            K_TOWERHEIGHT,
            K_TOWERBASEDIAMETER,
            K_TOWERTOPDIAMETER,
            K_NACELLESHAPE,
            K_NACELLEDIAMETER,
            K_NACELLECYLINDERLENGTH,
            K_NACELLEBOXLENGTH,
            K_NACELLEBOXWIDTH,
            K_BLADECOUNT,
            K_BLADELENGTH,
            K_BLADEROOTDIAMETER,
            K_BLADE1POSITION,
            K_TOWERORIENTATION1,
            K_TOWERORIENTATIONCOUNT,
            K_LOCATIONS
        ];

        foreach ($required as $field)
        {
            if (! array_key_exists( $field, $d))
            {
                $m[] = 'Missing required field: ' . $field;
                $okaySoFar = false;
            }
        }
        if ($okaySoFar)
        {
            $this->data = $d;
            $okaySoFar = $this->normalise_input();
            $d = $this->data;
            $m = $this->messages;
        }

        return [ $okaySoFar, $m, $d ];
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function xml_escape(
        string $value
    ) : string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function distanceBetweenLLH_m(
        float $lat1_deg,
        float $lon1_deg,
        float|null $elev1,
        float $lat2_deg,
        float $lon2_deg,
        float|null $elev2
    ) : float
    {
        // Excel formula:
        // 6371*2*ASIN(SQRT(SIN(PI()/180*(B49-B48)/2)^2+COS(PI()/180*(B48))*COS(PI()/180*(B49))*SIN(PI()/180*(C49-C48)/2)^2))*1000

        /*
        $d1 = sin( pi() / 180 * ( $lat2_deg - $lat1_deg ) / 2 ) ** 2 * 10 ** 15;
        $d2 = cos( pi() / 180 * $lat1_deg ) *
            cos( pi() / 180 * $lat2_deg ) *
            sin( pi() / 180 * ( $lon2_deg - $lon1_deg ) / 2 ) ** 2 * 10 ** 15;
        $d3 = sin( pi() / 180 * ( $lon2_deg - $lon1_deg ) / 2 ) * 10 ** 8;
        $d4 = pi() / 180 * ( $lon2_deg - $lon1_deg ) / 2 * 10 ** 8;
        $d5 = ( $lon2_deg - $lon1_deg ) / 2 * 10 ** 8;
        */

        if (is_float( $elev1 )
            && is_float($elev2)
            && ($elev1 == $elev2)
        ) {

            $elev1 = null;
            $elev2 = null;
        }
        if (is_int( $elev1 )) $elev1 = (float)$elev1;
        if (is_int( $elev2 )) $elev2 = (float)$elev2;

        // Earth radius_km -> diameter_km -> diameter_m

        // See http://www.movable-type.co.uk/scripts/latlong.html for haversine formula

        $a = sin(deg2rad($lat2_deg - $lat1_deg) / 2) ** 2 +
            cos(deg2rad($lat1_deg)) *
            cos(deg2rad($lat2_deg)) *
            sin(deg2rad($lon2_deg - $lon1_deg) / 2) ** 2;

        $distance_m = $this->wgs84_a_m * 2 *
            asin(sqrt( $a ));

        if ( is_float( $elev1 ) && is_float( $elev2 )) {

            $distance_m = sqrt( $distance_m * $distance_m + ($elev1 - $elev2) * ($elev1 - $elev2) );
        }
        return $distance_m;
    }

    // -----------------------------------------------------------------------------------------------------------------
    /**
     * Rotate a 3D point [x, y, z] about the origin by [a, b, c] degrees (Euler angles).
     * Uses the ZYX rotation order (intrinsic rotations: Z → Y → X).
     *
     * @param array $point      The point to rotate: [x, y, z].
     * @param array $angles_rad The rotation angles in radians: [a (X), b (Y), c (Z)].
     * @return array            The rotated point: [x', y', z'].
     */
    protected function rotate3DPoint(
        array $point,
        array $angles_rad
    ): array
    {
if ($this->DEBUG1 && $this->DEBUG2)
{
    file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' ROTATE    '. $point[0] .', '. $point[1] .', '. $point[2] .' by [ '. rad2deg( $angles_rad[0]) .', '. rad2deg( $angles_rad[1]) .', '. rad2deg( $angles_rad[2]) .' ]' .PHP_EOL, FILE_APPEND);
}

        $a = $angles_rad[0]; // X-axis rotation
        $b = $angles_rad[1]; // Y-axis rotation
        $c = $angles_rad[2]; // Z-axis rotation

        // Extract point coordinates
        $x = $point[0];
        $y = $point[1];
        $z = $point[2];

        // Rotation matrix for X-axis (roll)
        $rotX = [
            [1, 0, 0],
            [0, cos($a), -sin($a)],
            [0, sin($a), cos($a)]
        ];

        // Rotation matrix for Y-axis (pitch)
        $rotY = [
            [cos($b), 0, sin($b)],
            [0, 1, 0],
            [-sin($b), 0, cos($b)]
        ];

        // Rotation matrix for Z-axis (yaw)
        $rotZ = [
            [cos($c), -sin($c), 0],
            [sin($c), cos($c), 0],
            [0, 0, 1]
        ];

        // Apply rotations in ZYX order (Z → Y → X)
        // Step 1: Rotate by Z (yaw)
        $rotated = $this->multiplyMatrixVector($rotZ, [$x, $y, $z]);

        // Step 2: Rotate by Y (pitch)
        $rotated = $this->multiplyMatrixVector($rotY, $rotated);

        // Step 3: Rotate by X (roll)
        $rotated = $this->multiplyMatrixVector($rotX, $rotated);

        return $rotated;
    }

    // -----------------------------------------------------------------------------------------------------------------
    /**
     * Multiply a 3x3 matrix by a 3D vector.
     *
     * @param array $matrix The 3x3 matrix.
     * @param array $vector The 3D vector [x, y, z].
     * @return array        The resulting vector [x', y', z'].
     */
    protected function multiplyMatrixVector(
        array $matrix,
        array $vector
    ): array
    {
        $x = $matrix[0][0] * $vector[0] + $matrix[0][1] * $vector[1] + $matrix[0][2] * $vector[2];
        $y = $matrix[1][0] * $vector[0] + $matrix[1][1] * $vector[1] + $matrix[1][2] * $vector[2];
        $z = $matrix[2][0] * $vector[0] + $matrix[2][1] * $vector[1] + $matrix[2][2] * $vector[2];
        return [$x, $y, $z];
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function rotateXYZAboutXAxis(
        array $p,
        float $thetaRad
    ) : array
    {
        $magnitude = sqrt( $p[1] * $p[1] + $p[2] * $p[2] );
        $alphaRad = atan2( $p[1], $p[2] );

        $newX = $p[0];
        $newY = $magnitude * sin( $thetaRad + $alphaRad ); //$magnitude * sin( $thetaRad) * $this->sign( $p[1] ),
        $newZ = $magnitude * cos( $thetaRad + $alphaRad ); //$magnitude * cos( $thetaRad ) * $this->sign( $p[2] )


//printf( str_repeat( ' ', 49 + 5 ) .'  <!-- rotateAboutX(deg) %0.1f mag=%0.6f alpha(deg)=%0.6f sign(y)=%s sign(z)=%s -->'.PHP_EOL,
//    rad2deg( $thetaRad ), $magnitude, rad2deg( $alphaRad ), print_r( $this->sign( $p[1] ), true ), print_r( $this->sign( $p[2] ), true ) );
//printf( str_repeat( ' ', 49 + 5 ) .'  <!-- oldX=%0.6f oldY=%0.6f oldZ=%0.6f -->'.PHP_EOL, $p[0], $p[1], $p[2] );
//printf( str_repeat( ' ', 49 + 5 ) .'  <!-- newX=%0.6f newY=%0.6f newZ=%0.6f -->'.PHP_EOL, $newX, $newY, $newZ );

        return array( $newX, $newY, $newZ );
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function rotateXYZAboutYAxis(
        array $p,
        float $thetaRad
    ) : array
    {

        $magnitude = sqrt( $p[0] * $p[0] + $p[2] * $p[2] );
        $alphaRad = atan2( $p[2], $p[0] );

        $thetaRadGC = $thetaRad;
        //$thetaRadGC = rad2deg( $this->gd2gc( deg2rad( $thetaRad ), 2000 ) );

        $newX = $magnitude * cos( $thetaRadGC + $alphaRad ); //$magnitude * cos( $thetaRad ) * $this->sign( $p[0] ),
        $newY = $p[1];
        $newZ = $magnitude * sin( $thetaRadGC + $alphaRad ); //$magnitude * sin( $thetaRad ) * $this->sign( $p[2] )

//printf( str_repeat( ' ', 49 + 5 ) .'  <!-- rotateAboutY(deg) %0.10f (gc=%0.10f) mag=%0.10f alpha(deg)=%0.10f sign(x)=%s sign(z)=%s -->'.PHP_EOL,
//            rad2deg( $thetaRad ), rad2deg( $thetaRadGC ), $magnitude, rad2deg( $alphaRad ), print_r( $this->sign( $p[0] ), true ), print_r( $this->sign( $p[2] ), true ) );
//printf( str_repeat( ' ', 49 + 5 ) .'  <!-- oldX=%0.10f oldY=%0.10f oldZ=%0.10f -->'.PHP_EOL, $p[0], $p[1], $p[2] );
//printf( str_repeat( ' ', 49 + 5 ) .'  <!-- newX=%0.10f newY=%0.10f newZ=%0.10f -->'.PHP_EOL, $newX, $newY, $newZ );

        return array( $newX, $newY, $newZ );
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function rotateXYZAboutZAxis(
        array $p,
        float $thetaRad
    ) : array
    {

        $magnitude = sqrt( $p[0] * $p[0] + $p[1] * $p[1] );
        $alphaRad = atan2( $p[1], $p[0] );

        $newX = $magnitude * cos( $thetaRad + $alphaRad ); //$magnitude * sin( $thetaRad ) * $this->sign( $p[0] ),
        $newY = $magnitude * sin( $thetaRad + $alphaRad ); //$magnitude * cos( $thetaRad ) * $this->sign( $p[1] ),
        $newZ = $p[2];

//printf( str_repeat( ' ', 49 + 5 ) .'  <!-- rotateAboutZ(deg) %0.1f mag=%0.6f alpha(deg)=%0.6f sign(x)=%s sign(y)=%s -->'.PHP_EOL,
//    rad2deg( $thetaRad ), $magnitude, rad2deg( $alphaRad ), print_r( $this->sign( $p[0] ), true ), print_r( $this->sign( $p[1] ), true ) );
//printf( str_repeat( ' ', 49 + 5 ) .'  <!-- oldX=%0.6f oldY=%0.6f oldZ=%0.6f -->'.PHP_EOL, $p[0], $p[1], $p[2] );
//printf( str_repeat( ' ', 49 + 5 ) .'  <!-- newX=%0.6f newY=%0.6f newZ=%0.6f -->'.PHP_EOL, $newX, $newY, $newZ );

        return array( $newX, $newY, $newZ );
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function scale3DPoint(
        array $point,
        array $scaling
    ) : array
    {
if ($this->DEBUG1 && $this->DEBUG2)
{
    file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' SCALE     '. $point[0] .', '. $point[1] .', '. $point[2] .' by [ '. $scaling[0] .', '. $scaling[1] .', '. $scaling[2] .' ]' .PHP_EOL, FILE_APPEND);
}
        return [
            $point[0] * $scaling[0],
            $point[1] * $scaling[1],
            $point[2] * $scaling[2]
        ];
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function translate3DPoint(
        array $point,
        array $translation
    ) : array
    {
if ($this->DEBUG1 && $this->DEBUG2)
{
    file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' TRANSLATE  '. $point[0] .', '. $point[1] .', '. $point[2] .' by [ '. $translation[0] .', '. $translation[1] .', '. $translation[2] .' ]' .PHP_EOL, FILE_APPEND);
}
        return [
            $point[0] + $translation[0],
            $point[1] + $translation[1],
            $point[2] + $translation[2]
        ];
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function create_transform(
        array $new_transform
    ) : int
    {
        $this->transform_entries[] = $new_transform;
        return (count($this->transform_entries) - 1);
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function update_transform_parameters(
        int $transform_entry,
        array $new_value
    ) : bool
    {
        if ( ! array_key_exists( $transform_entry, $this->transform_entries ) )
        {
            return false;
        }
        $this->transform_entries[$transform_entry][1] = $new_value;
        return true;
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function new_transform_set(
    ) : int
    {
        $this->transform_sets[] = [];
        return (count($this->transform_sets) - 1);
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function clear_transform_set(
        int $transform_set
    ) : bool
    {
        if ( ! array_key_exists( $transform_set, $this->transform_sets ) )
        {
            return false;
        }
        $this->transform_sets[$transform_set] = [];
        return true;
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function copy_transform_set(
        int $transform_set
    ) : int
    {
        if ( ! array_key_exists( $transform_set, $this->transform_sets ) )
        {
            return self::invalid_transform_set;
        }
        $this->transform_sets[] = $this->transform_sets[$transform_set];
        return (count( $this->transform_sets ) - 1);
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function delete_transform_set(
        int $transform_set
    ) : bool
    {
        if ( ! array_key_exists( $transform_set, $this->transform_sets ) )
        {
            return false;
        }
        unset( $this->transform_sets[$transform_set] );
        return true;
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function append_transform(
        int $transform_set,
        array $new_transform
    ) : int
    {
        if ( ! array_key_exists( $transform_set, $this->transform_sets ) )
        {
            return self::invalid_transform_set;
        }
        $new_ref = $this->create_transform( $new_transform );
        if ($this->transform_sets_append_adds)
        {
            $this->transform_sets[$transform_set][] = $new_ref;
        }
        else
        {
            array_unshift($this->transform_sets[$transform_set], $new_ref);
        }
        return $new_ref;
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function prepend_transform(
        int $transform_set,
        array $new_transform
    ) : int
    {
        if ( ! array_key_exists( $transform_set, $this->transform_sets ) )
        {
            return self::invalid_transform_set;
        }
        $new_ref = $this->create_transform( $new_transform );
        if ($this->transform_sets_append_adds)
        {
            array_unshift($this->transform_sets[$transform_set], $new_ref);
        }
        else
        {
            $this->transform_sets[$transform_set][] = $new_ref;
        }
        return $new_ref;
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function insert_transform(
        int $transform_set,
        int $before,
        array $new_transform
    ) : int
    {
        if ( ! array_key_exists( $transform_set, $this->transform_sets ) )
        {
            return self::invalid_transform_set;
        }
        $keys = array_keys( $this->transform_sets[$transform_set], $before, true );
        if ( $before < 0 || (count( $keys ) != 1) )
        {
            return self::invalid_transform_set;
        }
        $new_ref = $this->create_transform( $new_transform );
        if ($this->transform_sets_append_adds)
        {
            if ($keys[0] == 0)
            {
                array_unshift($this->transform_sets[$transform_set], $new_ref);
            }
            else
            {
                $this->transform_sets[$transform_set] = array_slice($this->transform_sets[$transform_set], 0, $keys[0]) + array($new_ref) + array_slice($this->transform_sets[$transform_set], $keys[0]);
            }
        }
        else
        {
            if ($keys[0] == (count($this->transform_sets[$transform_set]) - 1))
            {
                $this->transform_sets[$transform_set][] = $new_ref;
            }
            else
            {
                $this->transform_sets[$transform_set] = array_slice($this->transform_sets[$transform_set], 0, $keys[0]+1) + array($new_ref) + array_slice($this->transform_sets[$transform_set], $keys[0]+1);
            }
        }
        return $new_ref;
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function remove_transform(
        int $transform_set,
        int $which_one
    ) : bool
    {
        if ( ! array_key_exists( $transform_set, $this->transform_sets ) )
        {
            return false;
        }
        $keys = array_keys( $this->transform_sets[$transform_set], $which_one, true );
        if ( $which_one < 0 || (count( $keys ) == 0) )
        {
            return false;
        }
        foreach( $keys as $this_key )
            unset( $this->transform_sets[$transform_set][ $this_key ] );
        return true;
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function apply_transform_set(
        array $point,
        int $transform_set
    ) : array
    {
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' apply_transform-set: transform_set='.$transform_set .PHP_EOL, FILE_APPEND);
        if ( ! array_key_exists( $transform_set, $this->transform_sets ) )
        {
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' apply_transform-set: transform_set not found transform_set='.$transform_set .PHP_EOL, FILE_APPEND);
            return $point;
        }
        $transformedPoint = $point;
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' apply_transform-set: this_transform loop from '.($this->transform_sets_append_adds ? 0 : (count( $this->transform_sets[ $transform_set ] ) - 1)) .'; '.($this_transform >= 0 && ($this_transform < count( $this->transform_sets[ $transform_set ] )) ? 'T':'f') .' ; '. ($this->transform_sets_append_adds ? 1 : -1) .PHP_EOL, FILE_APPEND);
        for($this_transform = ($this->transform_sets_append_adds ? 0 : (count( $this->transform_sets[ $transform_set ] ) - 1));
            $this_transform >= 0 && ($this_transform < count( $this->transform_sets[ $transform_set ] ));
            $this_transform += ($this->transform_sets_append_adds ? 1 : -1) )
        {
            if (array_key_exists($this->transform_sets[ $transform_set ][ $this_transform ], $this->transform_entries))
            {
                $transform = $this->transform_entries[ $this->transform_sets[ $transform_set ][ $this_transform ] ];
                if ( ! is_array( $transform ) )
                {
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' apply_transform-set: transform is NOT an array this_transform='.$this_transform .PHP_EOL, FILE_APPEND);
                    continue;
                }
                if (is_callable( $transform[0] ))
                {
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' apply_transform-set: transform[0] is callable  this_transform='.$this_transform .' transform[0][1]='. print_r( $transform[0][1], true) .PHP_EOL, FILE_APPEND);
                    $transformedPoint = call_user_func( $transform[0], $transformedPoint, $transform[1] );
                }
                else
                {
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' apply_transform-set: transform[0] is NOT callable  this_transform='.$this_transform .' transform[0]='. print_r( $transform[0], true) .PHP_EOL, FILE_APPEND);
                }
            }
            else
            {
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' apply_transform-set: this_transform='.$this_transform .' does not exist in transform_set='. $transform_set .PHP_EOL, FILE_APPEND);
            }
        }
        return $transformedPoint;
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function append_rotation(
        int $transform_set,
        array $rotations_rad
    ) : int
    {
        return $this->append_transform(
            $transform_set,
            [ [ $this, 'rotate3DPoint' ], $rotations_rad ] );
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function prepend_rotation(
        int $transform_set,
        array $rotations_rad
    ) : int
    {
        return $this->prepend_transform(
            $transform_set,
            [ [ $this, 'rotate3DPoint' ], $rotations_rad ] );
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function append_scaling(
        int $transform_set,
        array $scaling
    ) : int
    {
        return $this->append_transform(
            $transform_set,
            [ [ $this, 'scale3DPoint' ], $scaling ] );
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function prepend_scaling(
        int $transform_set,
        array $scaling
    ) : int
    {
        return $this->prepend_transform(
            $transform_set,
            [ [ $this, 'scale3DPoint' ], $scaling ] );
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function append_translation(
        int $transform_set,
        array $translations
    ) : int
    {
        return $this->append_transform(
            $transform_set,
            [ [ $this, 'translate3DPoint' ], $translations ] );
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function prepend_translation(
        int $transform_set,
        array $translations
    ) : int
    {
        return $this->prepend_transform(
            $transform_set,
            [ [ $this, 'translate3DPoint' ], $translations ] );
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function calculate_normal(
        array $p1,
        array $p2,
        array $p3
    ) : array
    {
        if ($this->z_up)
        {
            $v1 = [ $p2[0] - $p1[0], $p2[1] - $p1[1], $p2[2] - $p1[2] ];
            $v2 = [ $p3[0] - $p1[0], $p3[1] - $p1[1], $p3[2] - $p1[2] ];
            $cross = [
                $v1[1] * $v2[2] - $v1[2] * $v2[1],
                $v1[2] * $v2[0] - $v1[0] * $v2[2],
                $v1[0] * $v2[1] - $v1[1] * $v2[0]
            ];
        } else
        {
            $v1 = [ $p2[0] - $p1[0], $p2[2] - $p1[2], $p2[1] - $p1[1] ];
            $v2 = [ $p3[0] - $p1[0], $p3[2] - $p1[2], $p3[1] - $p1[1] ];
            $cross = [
                $v1[2] * $v2[1] - $v1[1] * $v2[2],
                $v1[0] * $v2[2] - $v1[2] * $v2[0],
                $v1[1] * $v2[0] - $v1[0] * $v2[1]
            ];
        }
        $length = sqrt( $cross[0] * $cross[0] + $cross[1] * $cross[1] + $cross[2] * $cross[2] );
        if ($length > 0) {
            $cross = [
                $cross[0] / $length,
                $cross[1] / $length,
                $cross[2] / $length
            ];
        }

        //$cross[0] = $cross[0] + $p1[0];
        //$cross[1] = $cross[1] + $p1[1];
        //$cross[2] = $cross[2] + $p1[2];

        return $cross;
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function normal_from_vertices(
        int $k1,
        int $k2,
        int $k3
    ) : array
    {
        $p1 = $this->vertices[$k1];
        $p2 = $this->vertices[$k2];
        $p3 = $this->vertices[$k3];
        if (! $this->z_up)
        {
            $p1 = [ $p1[0], $p1[2], $p1[1] ];
            $p2 = [ $p2[0], $p2[2], $p2[1] ];
            $p3 = [ $p3[0], $p3[2], $p3[1] ];
        }
        return $this->calculate_normal( $p1, $p2, $p3 );
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function add_vertex(
        array $xyz
    ) : void
    {
        $this->vertices[] = [
            $xyz[0],
            ($this->z_up ? $xyz[1] : $xyz[2]),
            ($this->z_up ? $xyz[2] : $xyz[1])
        ];
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function add_normal(
        array $xyz
    ) : void
    {
        $this->vertices[] = [
            $xyz[0],
            ($this->z_up ? $xyz[1] : $xyz[2]),
            ($this->z_up ? $xyz[2] : $xyz[1])
        ];
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function rationalise_start_end_angles_r(
        float $start_angle_r,
        float $end_angle_r,
        float $radius_m,
        int $segments,
    ) : array
    {
        while ($end_angle_r <= $start_angle_r)
            $end_angle_r += $this->two_pi;
        $angle_diff_r = ($end_angle_r - $start_angle_r);
        while( $angle_diff_r > $this->two_pi )
            $angle_diff_r -= $this->two_pi;

        // Look for the edge case where the circle is so close to complete that it might as well be complete
        /*
        if ( ($angle_diff_r < $this->two_pi)
            && ( ($this->two_pi - $angle_diff_r) * $radius_m < $this->visible_limit_m)
        ) {
            $angle_diff_r = $this->two_pi;
            $end_angle_r = $start_angle_r + $angle_diff_r;
            if ($segments == 0)
                $segments = $this->circle_facets;
        }
        //*/
        return [
            $start_angle_r,
            $end_angle_r,
            $angle_diff_r,
            $segments
        ];
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function circle_faces(
        int $first_vertex,
        int $last_vertex,
        bool $face_up,
        bool $pie,
        float $angle_diff_r
    ) : void
    {
        $first_normal = count( $this->normals );
        // As this is a circle, the normal is the same for all vertices
        $this->normals[] = $this->normal_from_vertices(
            $first_vertex,
            $first_vertex + ($face_up ? 1 : 2),
            $first_vertex + ($face_up ? 2 : 1)
        );

        $offset1 = $face_up ? 0 : 1;
        $offset2 = $face_up ? 1 : 0;

        //$last_vertex = count( $this->vertices ) - 1;
        $vertex_count = $last_vertex + 1 - $first_vertex;
        $pie_offset = $pie ? -1 : 0;
        $pie_and_full = ($pie && ($angle_diff_r == $this->two_pi)) ? 1 : 0;

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Crcl: first_vertex='. $first_vertex. ' last_vertex='. $last_vertex. ' vertext_count='. $vertex_count. ' not_closed_extra1='. $not_closed_extra1. ' pie_offset='. $pie_offset .PHP_EOL, FILE_APPEND);

        for($i = 0; $i < $vertex_count - 2 + $pie_and_full; $i++)
        {
            $v1 = $first_vertex;
            $v2 = $first_vertex + 1 + ( ($i + $offset1) % ($vertex_count + $pie_offset) );
            $v3 = $first_vertex + 1 + ( ($i + $offset2) % ($vertex_count + $pie_offset) );

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Crcl: i='. $i. ' v1='. $v1. ' v2='. $v2. ' v3='. $v3 .PHP_EOL, FILE_APPEND);

            if ( ($v2 > $last_vertex) || ($v3 > $last_vertex) )
                break;

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Crcl: i='. $i. ' v1='. $v1. ' v2='. $v2. ' v3='. $v3 .PHP_EOL, FILE_APPEND);

            $this->triangles[] = [
                $v1, $first_normal, // Circle centre
                $v2, $first_normal, // vertex x
                $v3, $first_normal, // vertex x + 1
            ];
        }

    }

    // -----------------------------------------------------------------------------------------------------------------
    /*
     * Origin of the box is in the centre of the bottom face
     */
    protected function collada_box(
        array $xyz,
        float $box_height_m,
        float $box_width_m,
        float $box_length_m,
        int $transform_set = self::invalid_transform_set
    ) : void
    {
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' h='. $box_height_m. ' w='. $box_width_m. ' l='. $box_length_m .PHP_EOL, FILE_APPEND);
        $x = $xyz[0] - $box_width_m / 2.0;  // we want the box centred on the middle of the bottom face
        $y = $xyz[1] - $box_length_m / 2.0; // we want the box centred on the middle of the bottom face
        $z = $xyz[2];

        $first_vertex = count( $this->vertices );
        $this->add_vertex( $this->apply_transform_set( [ $x,                $y,                 $z ],                 $transform_set ) );
        $this->add_vertex( $this->apply_transform_set( [ $x + $box_width_m, $y,                 $z ],                 $transform_set ) );
        $this->add_vertex( $this->apply_transform_set( [ $x,                $y,                 $z + $box_height_m ], $transform_set ) );
        $this->add_vertex( $this->apply_transform_set( [ $x + $box_width_m, $y,                 $z + $box_height_m ], $transform_set ) );
        $this->add_vertex( $this->apply_transform_set( [ $x,                $y + $box_length_m, $z ],                 $transform_set ) );
        $this->add_vertex( $this->apply_transform_set( [ $x + $box_width_m, $y + $box_length_m, $z ],                 $transform_set ) );
        $this->add_vertex( $this->apply_transform_set( [ $x,                $y + $box_length_m, $z + $box_height_m ], $transform_set ) );
        $this->add_vertex( $this->apply_transform_set( [ $x + $box_width_m, $y + $box_length_m, $z + $box_height_m ], $transform_set ) );

        $first_normal = count( $this->normals );
        $faces = [
            [0,1,3,2], // front face
            [0,4,5,1], // base
            [0,2,6,4], // left face
            [7,3,1,5], // right face
            [7,6,2,3], // top
            [7,5,4,6]  // back
        ];
        foreach( $faces as $face_n => $face )
        {
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' face_n='. $face_n. ' face='. print_r( $face, true ) .PHP_EOL, FILE_APPEND);
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' normal: k1='. ($first_vertex + $face[0]). ' k2='. ($first_vertex + $face[1]) . ' k3='. ($first_vertex + $face[2]) .PHP_EOL, FILE_APPEND);
            $this->normals[] = $this->normal_from_vertices( $first_vertex + $face[0],$first_vertex + $face[1], $first_vertex + $face[2] );
            $this->triangles[] = [ $first_vertex + $face[0], $first_normal, $first_vertex + $face[1], $first_normal, $first_vertex + $face[2], $first_normal ];
            $this->triangles[] = [ $first_vertex + $face[0], $first_normal, $first_vertex + $face[2], $first_normal, $first_vertex + $face[3], $first_normal ];
            $first_normal++;
        }
    }

    // -----------------------------------------------------------------------------------------------------------------
    /*
     * Origin of the circle is in the centre of the circle
     */
    protected function collada_circle(
        array $xyz,
        float $radius_m,
        int $segments,
        float $start_angle_r = 0,
        float $end_angle_r = 2 * M_PI,
        bool $pie = false,
        bool $face_up = true,
        int $transform_set = self::invalid_transform_set,
        bool $vertices_only = false
    ) : void
    {
        $x = $xyz[0];
        $y = $xyz[1];
        $z = $xyz[2];
        $first_vertex = count( $this->vertices );

        if ($pie)
        {
            // Centre of the circle
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Circle: adding centre of pie vertex' .PHP_EOL, FILE_APPEND);

            $this->add_vertex( $this->apply_transform_set( $xyz, $transform_set ) );
        }

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Circle: start_angle(deg)='.rad2deg($start_angle_r).' end_angle(deg)='.rad2deg($end_angle_r) .' angle_diff(deg)='.rad2deg($end_angle_r - $start_angle_r) .PHP_EOL, FILE_APPEND);

        list(
            $start_angle_r,
            $end_angle_r,
            $angle_diff_r,
            $segments
            ) = $this->rationalise_start_end_angles_r( $start_angle_r, $end_angle_r, $radius_m, $segments );

        // If not closed, and an extra vertex to ensure the circle completes to the end angle
        $not_closed_extra1 = 0; // ($angle_diff_r == $this->two_pi) ? 0 : 1;

        // Now work out how many segments to use, if we weren't told
        if ($segments == 0)
        {
            $segments_f = $this->circle_facets * $angle_diff_r / $this->two_pi;
            $segments = (int)$segments_f;
            $partial_needed = ($segments_f - $segments);
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Circle: partial_need='. $partial_needed .PHP_EOL, FILE_APPEND);
            // If the outer length ("circumference") of the partial segment is less than 0.01 (1cm), don't add it
            if ( ($partial_needed / $this->circle_facets * $this->two_pi * $radius_m ) < $this->visible_limit_m )
            {
                $partial_needed = 0;
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Circle: partial_needed set to 0'.PHP_EOL, FILE_APPEND);
            }

            $segment_angle_r = $angle_diff_r / $segments_f;
        } else
        {
            $segment_angle_r = $angle_diff_r / $segments;
            $partial_needed = ($angle_diff_r != $this->two_pi) ? 1 : 0;
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Circle: partial_need(2)='. $partial_needed .' start_angle(deg)='.rad2deg($start_angle_r).' end_angle(deg)='.rad2deg($end_angle_r) .' angle_diff(deg)='.rad2deg($angle_diff_r) .PHP_EOL, FILE_APPEND);
        }

        $this->circle_start_angle_r = $start_angle_r;
        $this->circle_end_angle_r = $end_angle_r;

        for($i = 0; $i < ($segments + $not_closed_extra1); $i++)
        {
            $angle_r = $start_angle_r + (float)$i * $segment_angle_r;
            $this->circle_current_angle_r = $angle_r;

            $vx = $x + cos($angle_r) * $radius_m;
            $vy = $y + (sin($angle_r) * $radius_m);
            $vz = $z;
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Circle: adding vertex at i='. $i .' not_closed_extra1='.$not_closed_extra1 .PHP_EOL, FILE_APPEND);
            $this->add_vertex( $this->apply_transform_set( [ $vx, $vy, $vz ], $transform_set ) );
        }
        if ($partial_needed > 0)
        {
            $angle_r = $end_angle_r;
            $this->circle_current_angle_r = $angle_r;
            $vx = $x + cos($angle_r) * $radius_m;
            $vy = $y + (sin($angle_r) * $radius_m);
            $vz = $z;
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Circle: partial_needed vertex' .PHP_EOL, FILE_APPEND);

            $this->add_vertex( $this->apply_transform_set( [ $vx, $vy, $vz ], $transform_set ) );
        }

        if (! $vertices_only)
        {
            $this->circle_faces(
                $first_vertex,
                count( $this->vertices ) - 1,
                $face_up,
                $pie,
                $angle_diff_r
            );
        }
    }

    // -----------------------------------------------------------------------------------------------------------------
    /*
     * Origin of the cylinder is in the centre of the bottom face
     */
    protected function collada_cylinder(
        array $xyz,
        float $base_diameter_m,
        float $top_diameter_m,
        float $height_m,
        array $base_start_end_angle_r = [ 0, 2 * M_PI ],
        array $top_start_end_angle_r = [ 0, 2 * M_PI ],
        bool $pie = false,
        bool $base_cap = true,
        bool $top_cap = true,
        int $transform_set = self::invalid_transform_set,
        bool $vertices_only = false,
        bool $unique_normals = false,
        int $existing_base_vertices_key = -1
    ) : void
    {
        //$x = $xyz[0];
        //$y = $xyz[1];
        //$z = $xyz[2];
        $base_radius_m = $base_diameter_m / 2.0;
        $top_radius_m = $top_diameter_m / 2.0;

        $this->cylinder_start_height_m = $xyz[2];
        $this->cylinder_end_height_m = $xyz[2] + $height_m;

        $this->cylinder_start_radius_m = $base_radius_m;
        $this->cylinder_end_radius_m = $top_radius_m;

        if ($transform_set == self::invalid_transform_set)
        {
            $base_ts = $this->new_transform_set();
            $top_ts = $this->new_transform_set();
        }
        else
        {
            $base_ts = $this->copy_transform_set( $transform_set );
            $top_ts = $this->copy_transform_set( $transform_set );
        }
        $this->prepend_translation( $base_ts, $xyz );
        $this->prepend_translation( $top_ts, [$xyz[0], $xyz[1], $xyz[2] + $height_m] );

        list(
            $base_start_angle_r,
            $base_end_angle_r,
            $base_angle_diff_r,
            $base_segments
            ) = $this->rationalise_start_end_angles_r(
            $base_start_end_angle_r[0],
            $base_start_end_angle_r[1],
            $base_radius_m,
            $this->circle_facets
        );

        $base_full = ($base_angle_diff_r == $this->two_pi);
        $base_segments_f = $this->circle_facets * $base_angle_diff_r / $this->two_pi;
        $base_segments = (int)ceil( $base_segments_f );

        list(
            $top_start_angle_r,
            $top_end_angle_r,
            $top_angle_diff_r,
            $top_segments
            ) = $this->rationalise_start_end_angles_r(
            $top_start_end_angle_r[0],
            $top_start_end_angle_r[1],
            $top_radius_m,
            $this->circle_facets
        );

        $top_full = ($top_angle_diff_r == $this->two_pi);
        $top_segments_f = $this->circle_facets * $top_angle_diff_r / $this->two_pi;
        $top_segments = (int)ceil( $top_segments_f );

        if ($base_segments > $top_segments)
        {
            $top_segments = $base_segments;

        } elseif ($top_segments > $base_segments)
        {
            $base_segments = $top_segments;
        }

        // Base cap - need the vertices if nothing else

        if ($existing_base_vertices_key >= 0)
        {
            $base_first_vertex = $existing_base_vertices_key;

        } else
        {
            $base_first_vertex = count( $this->vertices );

            $this->collada_circle(
                [0,0,0], //$xyz,
                $base_radius_m,
                $base_segments,
                $base_start_angle_r,
                $base_end_angle_r,
                $pie,
                false,
                $base_ts,
                $vertices_only || ! $base_cap //! $base_cap
            );

        }

        // Top cap - need the vertices if nothing else

        $top_first_vertex = count( $this->vertices );
        $this->collada_circle(
            [0,0,0], //[$x,$y,$z + $height_m],
            $top_radius_m,
            $top_segments,
            $top_start_angle_r,
            $top_end_angle_r,
            $pie,
            true,
            $top_ts,
            $vertices_only || ! $top_cap //! $top_cap
        );

        if (! $vertices_only)
        {

            // Now we have the vertices for the "caps" whether displayed or not
            // Can use the vertices to create the faces.

            $vertex_count = $top_first_vertex - $base_first_vertex;
            $base_less_face_than_vertices = ($base_full && $pie); // || (! $base_full && ! $pie);
            $top_less_face_than_vertices = ($top_full && $pie); // || (! $top_full && ! $pie);
            $one_less_face_than_vertices = ($base_less_face_than_vertices && $top_less_face_than_vertices) ? 1 : 0;

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Cyl: vertex_count='. $vertex_count. ' one_less_face_than_vertices='. $one_less_face_than_vertices  .PHP_EOL, FILE_APPEND);

            // Now the "tube"
            // Just use the circle vertices to make the faces (two tirangles per face)
            //*
            for( $i = 0; $i < $vertex_count - $one_less_face_than_vertices; $i++)
            {
                $v1 = $base_first_vertex + $one_less_face_than_vertices + ($i % ($vertex_count - $one_less_face_than_vertices));
                $v2 = $base_first_vertex + $one_less_face_than_vertices + (($i + 1) % ($vertex_count - $one_less_face_than_vertices));
                $v3 = $top_first_vertex  + $one_less_face_than_vertices + ($i % ($vertex_count - $one_less_face_than_vertices));
                $v4 = $top_first_vertex  + $one_less_face_than_vertices + (($i + 1) % ($vertex_count - $one_less_face_than_vertices));

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Cyl: i='. $i. ' v1='. $v1. ' v2='. $v2. ' v3='. $v3. ' v4='. $v4 .PHP_EOL, FILE_APPEND);

                $normal1_k = count( $this->normals );
                $normal2_k = $normal1_k;
                $this->normals[] = $this->normal_from_vertices( $v2, $v3, $v1);
                if ($unique_normals)
                {
                    $this->normals[] = $this->normal_from_vertices( $v4, $v3, $v2);
                    $normal2_k++;
                }

                $this->triangles[] = [
                    $v1, $normal1_k,
                    $v2, $normal1_k,
                    $v3, $normal1_k
                ];
                $this->triangles[] = [
                    $v2, $normal2_k,
                    $v4, $normal2_k,
                    $v3, $normal2_k
                ];
            }
        }
    }

    // -----------------------------------------------------------------------------------------------------------------
    /*
     * Origin of the sphere is in the centre of the sphere
     */
    protected function collada_sphere_pie(
        array $xyz,
        float $diameter_m,
        array $x_start_end_angle_r = [ 0, 2 * M_PI ],
        array $y_start_end_angle_r = [ 0, 2 * M_PI ],
        array $z_start_end_angle_r = [ 0, 2 * M_PI ],
        int $transform_set = self::invalid_transform_set,
        bool $vertices_only = false,
        bool $unique_normals = false
    ) : void
    {
        // We make a sphere by building it up from layers of frusta using the same number of steps as the number of circle facets
        // And we build the frusta just as we did for the cylinder. This way, we reuse each layer's top vertices for the base of the next layer.

        $pie = true;

        $radius_m = $diameter_m / 2.0;

        $facet_length_m = M_PI * $radius_m / $this->circle_facets; // The half-circumference divided by the facet count
        $facet_angle_rad = M_PI / $this->circle_facets; // 2 * pi / number of facets * 2 (full 360deg covers every frustum twice)
        $half_frusta_count = $this->circle_facets / 2;
        $current_height_offset_m = 0;
        $current_segment_angle_rad = $facet_angle_rad; //0;
        $base_diameter_m = $diameter_m;

        if ($transform_set == self::invalid_transform_set)
        {
            $sphere_upper_base_ts = $this->new_transform_set();
        }
        else
        {
            $sphere_upper_base_ts = $this->copy_transform_set($transform_set);
        }
        $this->prepend_translation($sphere_upper_base_ts, $xyz);

        $sphere_upper_top_ts = $this->copy_transform_set($sphere_upper_base_ts);
        $sphere_lower_base_ts = $this->copy_transform_set($sphere_upper_base_ts);
        $sphere_lower_top_ts = $this->copy_transform_set($sphere_upper_top_ts);

        $sphere_upper_top_translation_values = [0,0,0];
        $sphere_lower_top_translation_values = [0,0,0];
        $sphere_upper_top_translation_ref = $this->prepend_translation($sphere_upper_top_ts, $sphere_upper_top_translation_values );
        $sphere_lower_top_translation_ref = $this->prepend_translation($sphere_lower_top_ts, $sphere_lower_top_translation_values );

        list(
            $base_start_angle_r,
            $base_end_angle_r,
            $base_angle_diff_r,
            $base_segments
            ) = $this->rationalise_start_end_angles_r(
            $z_start_end_angle_r[0],
            $z_start_end_angle_r[1],
            $radius_m,
            $this->circle_facets
        );

        $base_full = ($base_angle_diff_r == $this->two_pi);
        $base_segments_f = $this->circle_facets * $base_angle_diff_r / $this->two_pi;
        $base_segments = (int)ceil( $base_segments_f );

        $top_start_angle_r = $base_start_angle_r;
        $top_end_angle_r = $base_end_angle_r;
        $top_angle_diff_r = $base_angle_diff_r;
        $top_segments = $base_segments;
        $top_full = $base_full;

        // Base ring of vertices

        $base_radius_m = $base_diameter_m / 2.0;

        $base_first_vertex_upper = count( $this->vertices );
        $this->collada_circle(
            [0,0, $current_height_offset_m], //$xyz,
            $base_radius_m,
            $base_segments,
            $base_start_angle_r,
            $base_end_angle_r,
            $pie,
            false,
            $sphere_upper_base_ts,
            true //! $base_cap
        );

        // And the same for the lower half

        $base_first_vertex_lower = count( $this->vertices );
        $this->collada_circle(
            [0,0, -$current_height_offset_m], //$xyz,
            $base_radius_m,
            $base_segments,
            $base_start_angle_r,
            $base_end_angle_r,
            $pie,
            false,
            $sphere_lower_base_ts,
            true //! $base_cap
        );

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sph: base_full='. ($base_full ? 'TRUE' : 'false') .' base_start_angle(deg)='.rad2deg( $base_start_angle_r) .' base_end_angle(deg)='.rad2deg( $base_end_angle_r) .' base_segments='. $base_segments .PHP_EOL, FILE_APPEND);

        for ($j = 0; $j < $half_frusta_count; $j++)
        {
            $top_radius_m = $radius_m * cos( $current_segment_angle_rad );
            $height_m = $radius_m * sin( $current_segment_angle_rad ) - $current_height_offset_m;

            // Set the transform that moves the top vertex ring to the correct height
            $sphere_upper_top_translation_values[2] = $height_m;
            $sphere_lower_top_translation_values[2] = -$height_m;
            $this->update_transform_parameters( $sphere_upper_top_translation_ref, $sphere_upper_top_translation_values );
            $this->update_transform_parameters( $sphere_lower_top_translation_ref, $sphere_lower_top_translation_values );

            // Top vertex ring for the upper half

            $top_first_vertex_upper = count( $this->vertices );
            $this->collada_circle(
                [0,0, $current_height_offset_m], //[$x,$y,$z + $height_m],
                $top_radius_m,
                $top_segments,
                $top_start_angle_r,
                $top_end_angle_r,
                $pie,
                true,
                $sphere_upper_top_ts,
                true //! $top_cap
            );

            // Top vertex ring for the lower half

            $top_first_vertex_lower = count( $this->vertices );
            $this->collada_circle(
                [0,0, -$current_height_offset_m], //[$x,$y,$z + $height_m],
                $top_radius_m,
                $top_segments,
                $top_start_angle_r,
                $top_end_angle_r,
                $pie,
                true,
                $sphere_lower_top_ts,
                true //! $top_cap
            );

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sph: j='. $j .' top_first_vertex_upper='.$top_first_vertex_upper .' top_first_vertex_lower='.$top_first_vertex_lower.' last_vertex='.(count( $this->vertices ) - 1) .PHP_EOL, FILE_APPEND);

            if (! $vertices_only)
            {
                // Now we have the vertices for the "caps" whether displayed or not
                // Can use the vertices to create the faces.

                $vertex_count_upper = $top_first_vertex_lower - $top_first_vertex_upper;
                $vertex_count_lower = count( $this->vertices ) - $top_first_vertex_lower;

                $vertex_count = $top_first_vertex_upper - $base_first_vertex_lower;
                $base_less_face_than_vertices = ($base_full && $pie); // || (! $base_full && ! $pie);
                $top_less_face_than_vertices = ($top_full && $pie); // || (! $top_full && ! $pie);
                $one_less_face_than_vertices = ($base_less_face_than_vertices && $top_less_face_than_vertices) ? 1 : 0;

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sph: vertex_count='. $vertex_count. ' one_less_face_than_vertices='. $one_less_face_than_vertices .' vertex_count_upper='. $vertex_count_upper .' vertex_count_lower='. $vertex_count_lower .PHP_EOL, FILE_APPEND);

                // Now the "tube"
                // Just use the circle vertices to make the faces (two triangles per face)
                for( $i = 0; $i < $vertex_count - $one_less_face_than_vertices; $i++)
                {
                    if ($i < $vertex_count_upper)
                    {
                        // The "upper" frustum
                        $v1 = $base_first_vertex_upper + $one_less_face_than_vertices + ( $i      % ($vertex_count_upper - $one_less_face_than_vertices));
                        $v2 = $base_first_vertex_upper + $one_less_face_than_vertices + (($i + 1) % ($vertex_count_upper - $one_less_face_than_vertices));
                        $v3 = $top_first_vertex_upper  + $one_less_face_than_vertices + ( $i      % ($vertex_count_upper - $one_less_face_than_vertices));
                        $v4 = $top_first_vertex_upper  + $one_less_face_than_vertices + (($i + 1) % ($vertex_count_upper - $one_less_face_than_vertices));

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sph upper: i='. $i. ' v1='. $v1. ' ( '.$base_first_vertex_upper.'+'.$one_less_face_than_vertices.'+('.$i.' % '.($vertex_count_upper - $one_less_face_than_vertices).')'
//    .'  v2='. $v2. ' ('.$base_first_vertex_upper.'+'.$one_less_face_than_vertices.'+(('.($i+1).') % '.($vertex_count_upper - $one_less_face_than_vertices).')'
//    .'  v3='. $v3. ' ('.$top_first_vertex_upper.'+'.$one_less_face_than_vertices.'+('.$i.' % '.($vertex_count_upper - $one_less_face_than_vertices).')'
//    .'  v4='. $v4 .' ('.$top_first_vertex_upper.'+'.$one_less_face_than_vertices.'+(('.($i+1).') % '.($vertex_count_upper - $one_less_face_than_vertices).')'.PHP_EOL, FILE_APPEND);

                        $normal1_k = count( $this->normals );
                        $normal2_k = $normal1_k;
                        $this->normals[] = $this->normal_from_vertices( $v2, $v3, $v1);
                        if ($unique_normals)
                        {
                            $this->normals[] = $this->normal_from_vertices( $v4, $v3, $v2);
                            $normal2_k++;
                        }

                        $this->triangles[] = [
                            $v1, $normal1_k,
                            $v2, $normal1_k,
                            $v3, $normal1_k
                        ];
                        $this->triangles[] = [
                            $v2, $normal2_k,
                            $v4, $normal2_k,
                            $v3, $normal2_k
                        ];
                    }

                    if ($i < $vertex_count_lower)
                    {
                        // The "lower" frustum
                        $v1 = $base_first_vertex_lower + $one_less_face_than_vertices + ( $i      % ($vertex_count_lower - $one_less_face_than_vertices));
                        $v2 = $base_first_vertex_lower + $one_less_face_than_vertices + (($i + 1) % ($vertex_count_lower - $one_less_face_than_vertices));
                        $v3 = $top_first_vertex_lower  + $one_less_face_than_vertices + ( $i      % ($vertex_count_lower - $one_less_face_than_vertices));
                        $v4 = $top_first_vertex_lower  + $one_less_face_than_vertices + (($i + 1) % ($vertex_count_lower - $one_less_face_than_vertices));

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sph lower: i='. $i. ' v1='. $v1. ' ( '.$base_first_vertex_lower.'+'.$one_less_face_than_vertices.'+('.$i.' % '.($vertex_count_lower - $one_less_face_than_vertices).')'
//    .'  v2='. $v2. ' ('.$base_first_vertex_lower.'+'.$one_less_face_than_vertices.'+(('.($i+1).') % '.($vertex_count_lower - $one_less_face_than_vertices).')'
//    .'  v3='. $v3. ' ('.$top_first_vertex_lower.'+'.$one_less_face_than_vertices.'+('.$i.' % '.($vertex_count_lower - $one_less_face_than_vertices).')'
//    .'  v4='. $v4 .' ('.$top_first_vertex_lower.'+'.$one_less_face_than_vertices.'+(('.($i+1).') % '.($vertex_count_lower - $one_less_face_than_vertices).')'.PHP_EOL, FILE_APPEND);

                        $normal1_k = count( $this->normals );
                        $normal2_k = $normal1_k;
                        $this->normals[] = $this->normal_from_vertices( $v1, $v3, $v2);
                        if ($unique_normals)
                        {
                            $this->normals[] = $this->normal_from_vertices( $v4, $v3, $v2);
                            $normal2_k++;
                        }

                        $this->triangles[] = [
                            $v1, $normal1_k,
                            $v3, $normal1_k,
                            $v2, $normal1_k
                        ];
                        $this->triangles[] = [
                            $v2, $normal2_k,
                            $v3, $normal2_k,
                            $v4, $normal2_k
                        ];
                    }
                }
            }

            $current_height_offset_m += $height_m;
            $current_segment_angle_rad += $facet_angle_rad;
            $base_first_vertex_upper = $top_first_vertex_upper;
            $base_first_vertex_lower = $top_first_vertex_lower;
        }
    }

    // -----------------------------------------------------------------------------------------------------------------
    /*
     * Origin of the sphere is in the centre of the BASE of the sphere, even for partial spheres where the bottom is
     * a circle. "Not pie" means where not a full 360deg, the "open"surface is flat(-ish) rather then like a pie.
     *
     * The shpere is made from the bottom up using frusta.
     *
     * @param array $xyz
     * @param float $diameter_m
     * @param array $x_start_end_angle_r
     * @param array $y_start_end_angle_r
     * @param array $z_start_end_angle_r
     * @param int $transform_set
     * @param bool $vertices_only
     * @param bool $unique_normals
     */
    protected function collada_sphere_not_pie(
        array $xyz,
        float $diameter_m,
        array $x_start_end_angle_r = [ 0, 2 * M_PI ],
        array $y_start_end_angle_r = [ 0, 2 * M_PI ],
        array $z_start_end_angle_r = [ 0, 2 * M_PI ],
        int $transform_set = self::invalid_transform_set,
        bool $vertices_only = false,
        bool $unique_normals = false
    ) : void
    {
        // We make a sphere by building it up from layers of frusta using the same number of steps as the number of circle facets
        // And we build the frusta just as we did for the cylinder. This way, we reuse each layer's top vertices for the base of the next layer.

        $pie = false;

        $radius_m = $diameter_m / 2.0;
        $frusta_count = $this->circle_facets;

        $frusta_start_end_angle_r = $x_start_end_angle_r;
        while ($frusta_start_end_angle_r[0] > $this->two_pi)
            $frusta_start_end_angle_r[0] -= $this->two_pi;
        while ($frusta_start_end_angle_r[1] > $this->two_pi)
            $frusta_start_end_angle_r[1] -= $this->two_pi;
        sort( $frusta_start_end_angle_r );
        $frusta_start_angle_r = $frusta_start_end_angle_r[0];
        $frusta_end_angle_r = $frusta_start_end_angle_r[1];

        // Angles are assumed to work clockwise from 12 o'clock
        // At this point, start < end
        if (($frusta_start_angle_r == 0)
            && ($frusta_end_angle_r = $this->two_pi))
        {
            $frusta_end_angle_r = $this->two_pi;
            $frusta_start_angle_r = M_PI;

        } elseif (($frusta_start_angle_r <= M_PI)
            && ($frusta_end_angle_r <= M_PI))
        {
            // Both between 12 o'clock and 6 o'clock, so "swap" them to between 6 and 12 ("left" side of clock face)
            $t = $frusta_end_angle_r;
            $frusta_end_angle_r = $this->two_pi - $frusta_start_angle_r;
            $frusta_start_angle_r = $this->two_pi - $t;

        } elseif (($frusta_start_angle_r >= M_PI)
            && ($frusta_end_angle_r >= M_PI))
        {
            // In this case, the angles are in "right" order - both on "left" side of clock face
            true;
        } elseif ($frusta_start_angle_r <= ($this->two_pi - $frusta_end_angle_r))
        {
            // Start is between 12 and 6 and end is "lower" on clock face on other side of clock face
            // So, make end first and swap start to other side
            $t = $frusta_end_angle_r;
            $frusta_end_angle_r = $this->two_pi - $frusta_start_angle_r;
            $frusta_start_angle_r = $t;
        } else
        {
            // Lastly, start on right (12-6) but "lower" than end on the left
            $frusta_start_angle_r = $this->two_pi - $frusta_start_angle_r;
        }
        // At this point start and should both should be 180deg-360deg (M_PI- 2*M_PI radians)

        $frusta_angle_diff_r = $frusta_end_angle_r - $frusta_start_angle_r;

        $facet_angle_rad = $frusta_angle_diff_r / $frusta_count; // 2 * pi / number of facets * 2 (full 360deg covers every frustum twice)
        $initial_height_offset_m = $radius_m * (1 - cos($frusta_start_angle_r - M_PI));
        $current_height_offset_m = $initial_height_offset_m;
        $current_segment_angle_rad = $frusta_start_angle_r + $facet_angle_rad; //0;
        $initial_base_diameter_m = 2 * $radius_m * sin($frusta_start_angle_r - M_PI);
        $base_diameter_m = $initial_base_diameter_m;

        // Move the completed sphere to its actual location
        if ($transform_set == self::invalid_transform_set)
        {
            $sphere_base_ts = $this->new_transform_set();
        }
        else
        {
            $sphere_base_ts = $this->copy_transform_set($transform_set);
        }
        $this->prepend_translation(
            $sphere_base_ts,
            [ $xyz[0], $xyz[1], $xyz[2] - $initial_height_offset_m - $radius_m ]
        );

        $sphere_top_ts = $this->copy_transform_set($sphere_base_ts);

        $sphere_top_translation_values = [0,0,0];
        $sphere_top_translation_ref = $this->prepend_translation($sphere_top_ts, $sphere_top_translation_values );

        list(
            $base_start_angle_r,
            $base_end_angle_r,
            $base_angle_diff_r,
            $base_segments
            ) = $this->rationalise_start_end_angles_r(
            $z_start_end_angle_r[0],
            $z_start_end_angle_r[1],
            $radius_m,
            $this->circle_facets
        );

        $base_full = ($base_angle_diff_r == $this->two_pi);
        $base_segments_f = $this->circle_facets * $base_angle_diff_r / $this->two_pi;
        $base_segments = (int)ceil( $base_segments_f );

        $top_start_angle_r = $base_start_angle_r;
        $top_end_angle_r = $base_end_angle_r;
        $top_angle_diff_r = $base_angle_diff_r;
        $top_segments = $base_segments;
        $top_full = $base_full;

        // Base ring of vertices

        $base_radius_m = $base_diameter_m / 2.0;

        $base_first_vertex = count( $this->vertices );
        $sphere_base_first_vertex = $base_first_vertex;
        $this->collada_circle(
            [0,0, $current_height_offset_m], //$xyz,
            $base_radius_m,
            $base_segments,
            $base_start_angle_r,
            $base_end_angle_r,
            $pie,
            false,
            $sphere_base_ts,
            true //! $base_cap
        );
        $sphere_base_last_vertex = count( $this->vertices ) - 1;

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sph: base_full='. ($base_full ? 'TRUE' : 'false') .' base_start_angle(deg)='.rad2deg( $base_start_angle_r) .' base_end_angle(deg)='.rad2deg( $base_end_angle_r) .' base_segments='. $base_segments .PHP_EOL, FILE_APPEND);

        for ($j = 0; $j < $frusta_count; $j++)
        {
            $top_radius_m = $radius_m * sin( $current_segment_angle_rad - M_PI );
            $height_m = $radius_m * (1 - cos( $current_segment_angle_rad - M_PI )) - $current_height_offset_m;

            // Set the transform that moves the top vertex ring to the correct height
            $sphere_top_translation_values[2] = $height_m;
            $this->update_transform_parameters( $sphere_top_translation_ref, $sphere_top_translation_values );

            // Top vertex ring

            $top_first_vertex = count( $this->vertices );
            $this->collada_circle(
                [0,0, $current_height_offset_m], //[$x,$y,$z + $height_m],
                $top_radius_m,
                $top_segments,
                $top_start_angle_r,
                $top_end_angle_r,
                $pie,
                true,
                $sphere_top_ts,
                true //! $top_cap
            );

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sph: j='. $j .' top_first_vertex_upper='.$top_first_vertex_upper .' top_first_vertex_lower='.$top_first_vertex_lower.' last_vertex='.(count( $this->vertices ) - 1) .PHP_EOL, FILE_APPEND);

            if (! $vertices_only)
            {
                // Now we have the vertices for the "caps" whether displayed or not
                // Can use the vertices to create the faces.

                $vertex_count = count( $this->vertices ) - $top_first_vertex;

                $base_less_face_than_vertices = ($base_full && $pie); // || (! $base_full && ! $pie);
                $top_less_face_than_vertices = ($top_full && $pie); // || (! $top_full && ! $pie);
                $one_less_face_than_vertices = ($base_less_face_than_vertices && $top_less_face_than_vertices) ? 1 : 0;

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sph: vertex_count='. $vertex_count. ' one_less_face_than_vertices='. $one_less_face_than_vertices .' vertex_count_upper='. $vertex_count_upper .' vertex_count_lower='. $vertex_count_lower .PHP_EOL, FILE_APPEND);

                // Now the "tube"
                // Just use the circle vertices to make the faces (two triangles per face)
                for( $i = 0; $i < $vertex_count - $one_less_face_than_vertices; $i++)
                {
                    if ($i < $vertex_count)
                    {
                        // The frustum
                        $v1 = $base_first_vertex + $one_less_face_than_vertices + ( $i      % ($vertex_count - $one_less_face_than_vertices));
                        $v2 = $base_first_vertex + $one_less_face_than_vertices + (($i + 1) % ($vertex_count - $one_less_face_than_vertices));
                        $v3 = $top_first_vertex  + $one_less_face_than_vertices + ( $i      % ($vertex_count - $one_less_face_than_vertices));
                        $v4 = $top_first_vertex  + $one_less_face_than_vertices + (($i + 1) % ($vertex_count - $one_less_face_than_vertices));

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sph upper: i='. $i. ' v1='. $v1. ' ( '.$base_first_vertex_upper.'+'.$one_less_face_than_vertices.'+('.$i.' % '.($vertex_count_upper - $one_less_face_than_vertices).')'
//    .'  v2='. $v2. ' ('.$base_first_vertex_upper.'+'.$one_less_face_than_vertices.'+(('.($i+1).') % '.($vertex_count_upper - $one_less_face_than_vertices).')'
//    .'  v3='. $v3. ' ('.$top_first_vertex_upper.'+'.$one_less_face_than_vertices.'+('.$i.' % '.($vertex_count_upper - $one_less_face_than_vertices).')'
//    .'  v4='. $v4 .' ('.$top_first_vertex_upper.'+'.$one_less_face_than_vertices.'+(('.($i+1).') % '.($vertex_count_upper - $one_less_face_than_vertices).')'.PHP_EOL, FILE_APPEND);

                        $normal1_k = count( $this->normals );
                        $normal2_k = $normal1_k;
                        $this->normals[] = $this->normal_from_vertices( $v2, $v3, $v1);
                        if ($unique_normals)
                        {
                            $this->normals[] = $this->normal_from_vertices( $v4, $v3, $v2);
                            $normal2_k++;
                        }

                        $this->triangles[] = [
                            $v1, $normal1_k,
                            $v2, $normal1_k,
                            $v3, $normal1_k
                        ];
                        $this->triangles[] = [
                            $v2, $normal2_k,
                            $v4, $normal2_k,
                            $v3, $normal2_k
                        ];
                    }
                }
            }

            $current_height_offset_m += $height_m;
            $current_segment_angle_rad += $facet_angle_rad;
            $base_first_vertex = $top_first_vertex;
        }
        if ( ! $vertices_only
            && ($frusta_start_angle_r > M_PI))
        {
            // Need to include the faces in the bottom

            $this->circle_faces(
                $sphere_base_first_vertex,
                $sphere_base_last_vertex,
                false,
                $pie,
                $base_angle_diff_r
            );
        }
        if ( ! $vertices_only
            && ($frusta_end_angle_r < $this->two_pi))
        {
            // Need to include the faces in the top

            $this->circle_faces(
                $top_first_vertex,
                count( $this->vertices ) - 1,
                true,
                $pie,
                $base_angle_diff_r
            );
        }
    }

    // -----------------------------------------------------------------------------------------------------------------
    /*
     * Origin of the sphere is in the centre of the BASE of the sphere, even for partial spheres where the bottom is
     * a circle
     */
    protected function collada_sphere(
        array $xyz,
        float $diameter_m,
        array $x_start_end_angle_r = [ 0, 2 * M_PI ],
        array $y_start_end_angle_r = [ 0, 2 * M_PI ],
        array $z_start_end_angle_r = [ 0, 2 * M_PI ],
        bool $pie = false,
        int $transform_set = self::invalid_transform_set,
        bool $vertices_only = false,
        bool $unique_normals = false
    ) : void
    {
        if ($pie)
        {
            $this->collada_sphere_pie(
                $xyz,
                $diameter_m,
                $x_start_end_angle_r,
                $y_start_end_angle_r,
                $z_start_end_angle_r,
                $transform_set,
                $vertices_only,
                $unique_normals
            );
        }
        else
        {
            $this->collada_sphere_not_pie(
                $xyz,
                $diameter_m,
                $x_start_end_angle_r,
                $y_start_end_angle_r,
                $z_start_end_angle_r,
                $transform_set,
                $vertices_only,
                $unique_normals
            );
        }
    }

    // -----------------------------------------------------------------------------------------------------------------
    /*
     * @param array $point
     * @param array $parameters         [0] - [],[],[] - scaling factors for x/y/z > 0
     *                                  [1] - [],[],[] - rotation angles_r for x/y/z > 0,
     *                                  [2] - [],[],[] - translation values for x/y/z > 0
     * @return array
     */
    protected function blade_type_2_transform_cb(
        array $point,
        array $parameters
    ) : array
    {
        $transformed_point = $point;
        list(
            $scaling_pos_xyz,
            //$rotations_pos_xyz,
            //$translations_pos_xyz
            ) = $parameters;

        if (! empty( $scaling_pos_xyz))
        {
            for ($k = 0; $k < 3; $k++)
            {
                if (! empty( $scaling_pos_xyz[$k])
                    && ($point[$k] > 0)
                )
                    $transformed_point = $this->scale3DPoint( $transformed_point, $scaling_pos_xyz[$k] );
            }
        }
        /*
        if (! empty( $rotations_pos_xyz))
        {
            for ($k = 0; $k < 3; $k++)
            {
                if (! empty( $rotations_pos_xyz[$k])
                    && ($point[$k] > 0)
                )
                    $transformed_point = $this->rotate3DPoint( $transformed_point, $rotations_pos_xyz[$k] );
            }
        }
        if (! empty( $translations_pos_xyz))
        {
            for ($k = 0; $k < 3; $k++)
            {
                if (! empty( $translations_pos_xyz[$k])
                    && ($point[$k] > 0)
                )
                    $transformed_point = $this->translate3DPoint( $transformed_point, $translations_pos_xyz[$k] );
            }
        }
        //*/

        return $transformed_point;
    }

    // -----------------------------------------------------------------------------------------------------------------
    /*
     * Origin of the blade2 is in the centre of the BASE of the blade2, even for partial blade2s where the bottom is
     * a circle. "Not pie" means where not a full 360deg, the "open"surface is flat(-ish) rather then like a pie.
     *
     * The origin is the middle of the bottom surface
     *
     * @param array $xyz
     * @param float $root_diameter_m
     * @param array $x_start_end_angle_r
     * @param array $y_start_end_angle_r
     * @param array $z_start_end_angle_r
     * @param int $transform_set
     * @param bool $vertices_only
     */
    protected function collada_blade_type_2(
        array $xyz,
        float $blade_length_m,
        float $root_diameter_m,
        int $transform_set = self::invalid_transform_set,
        bool $vertices_only = false
    ) : void
    {
        // We make this type of blade by building a hemisphere making it up from layers of frusta using the same number of steps as the number of circle facets
        // And we build the frusta just as we did for the cylinder. This way, we reuse each layer's top vertices for the base of the next layer.
        // What makes it a blade is by stretching the Z axis and manipulating the x and y axes as a function of height up the blade from the root
        // The stretching is done simply by adjusting the separating between each layer at the point of invoking the circle creation.
        // The x & y manipulations are done by a bespoke transformation function.

        $unique_normals = false;
        $pie = false;

        $deg270_r = deg2rad( 270);
        $x_start_end_angle_r = [ $deg270_r, 2 * M_PI ];
        $z_start_end_angle_r = [ 0, 2 * M_PI ];

        $radius_m = $root_diameter_m / 2.0;
        $height_scaling = $blade_length_m / $radius_m;

        $final_z_rotation_r = 0; //deg2rad( 90 );

        // Root to "blip" is section 1
        // "blip" to tip is section 2
        // Section 3 is the tip, which is most like a hemisphere

        // On the assumption that faces should by no longer than 1m between the root and the "blip", the height of the "blip"
        // determines how many frusta are required at the base.
        // On the further assumption that above the "blip" the blade cross section doesn't change dramatically, the number of layers is less critial.
        $section1_start_height_m = 0;
        $section1_height_m = $this->blade2_max_chord_position_percent_of_length * $blade_length_m;
        $section1_frusta = (int)max( ceil($section1_height_m), 3 );
        $section1_chord_m = $this->blade2_max_chord_percent_of_length * $blade_length_m;
        $section1_twist_percent = 0.1;

        $frusta_count = $this->circle_facets + $section1_frusta;
        if ($frusta_count % 2)
            $frusta_count++;
        $section = 1;

        $section3_chord_base_m = 0.5 * $root_diameter_m;
        //$section3_height_m = 0.95 * $blade_length_m;
        $section3_height_m = $section3_chord_base_m; // $blade_length_m - $section3_chord_base_m;
        $section3_start_frustum = (int)(0.9 * $frusta_count);
        $section3_frusta = $frusta_count - $section3_start_frustum + 1;

        $section2_start_height_m = $section1_height_m;
        $section2_height_m = $blade_length_m - $section1_height_m - $section3_height_m; //$section3_height_m - $section1_height_m;
        // We need to know this angle so that the tip (section 3) starts with an appropriate angle itself
        //$tan_theta = ($section1_chord_m - $section3_chord_base_m) / 2 / $section2_height_m;
        //$section2_angle = atan( $tan_theta );
        $section2_frusta = $frusta_count - $section1_frusta - $section3_frusta;
        $section2_start_frustum = $section1_frusta + 1;

        $section3_start_height_m = $section1_height_m + $section2_height_m;

        /* DEBUG ONLY - boxes to allow eyeballing in Google Earth to check heights */
        /*
        $this->collada_box( [ $xyz[0] + 11, $xyz[1] + 11, 0 ], $xyz[2] + $section1_start_height_m, 1, 1 );
        $this->collada_box( [ $xyz[0] + 10, $xyz[1] + 10, $xyz[2] + $section1_start_height_m ], $section1_height_m, 1, 1 );
        $this->collada_box( [ $xyz[0] +  9, $xyz[1] +  9, $xyz[2] + $section2_start_height_m ], $section2_height_m, 1, 1 );
        $this->collada_box( [ $xyz[0] +  8, $xyz[1] +  8, $xyz[2] + $section3_start_height_m ], $section3_height_m, 1, 1 );
        $this->collada_box( [ $xyz[0] +  7, $xyz[1] +  7, $xyz[2] + $section1_start_height_m ], $blade_length_m, 1, 1 );
        //*/

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Blade2: blade_length_m='. $blade_length_m
//    .' section1_start & height='. $section1_start_height_m .' / '. $section1_height_m .' ('. ($section1_start_height_m + $section1_height_m) .')'
//    .' section2_start & height='. $section2_start_height_m .' / '. $section2_height_m .' ('. ($section2_start_height_m + $section2_height_m) .')'
//    .' section3_start & height='. $section3_start_height_m .' / '. $section3_height_m .' ('. ($section3_start_height_m + $section3_height_m) .')'
//    .' s1h+s2h+s3h='. $section1_height_m + $section2_height_m + $section3_height_m
//    .PHP_EOL, FILE_APPEND);

        $frusta_start_angle_r = $x_start_end_angle_r[0];
        $frusta_end_angle_r = $x_start_end_angle_r[1];

        $frusta_angle_diff_r = $frusta_end_angle_r - $frusta_start_angle_r;

        $initial_height_offset_m = $radius_m * (1 - cos($frusta_start_angle_r - M_PI)); // Should be 0
        $current_height_offset_m = $initial_height_offset_m;
        $current_segment_angle_rad = $frusta_start_angle_r; //  + $section3_facet_angle_r; //0;
        $initial_base_diameter_m = 2 * $radius_m * sin($frusta_start_angle_r - M_PI);
        $base_diameter_m = $initial_base_diameter_m;

        // Move the completed blade2 to its actual location
        if ($transform_set == self::invalid_transform_set)
        {
            $blade2_base_ts = $this->new_transform_set();
        }
        else
        {
            $blade2_base_ts = $this->copy_transform_set($transform_set);
        }
        $this->prepend_translation(
            $blade2_base_ts,
            [ $xyz[0], $xyz[1], $xyz[2] - $initial_height_offset_m /* - $radius_m */ ]
        );
        $this->prepend_rotation($blade2_base_ts, [ 0,0, $final_z_rotation_r ]);

        $blade2_top_ts = $this->copy_transform_set($blade2_base_ts);

        $blade2_top_translation_values = [0,0,0];
        $blade2_top_translation_ref = $this->prepend_translation($blade2_top_ts, $blade2_top_translation_values );

        $blade2_top_rotation_values = [0,0,0];
        $blade2_top_rotation_ref = $this->prepend_rotation($blade2_top_ts, $blade2_top_rotation_values );

        $blade2_top_scaling_values = [1,1,1];
        $blade2_top_scaling_ref = $this->prepend_scaling($blade2_top_ts, $blade2_top_scaling_values );

        $blade2_top_custom_values = [];
        $blade2_top_custom_ref = $this->prepend_transform(
            $blade2_top_ts,
            [ [ $this, 'blade_type_2_transform_cb'], $blade2_top_custom_values ]
        );

        list(
            $base_start_angle_r,
            $base_end_angle_r,
            $base_angle_diff_r,
            $base_segments
            ) = $this->rationalise_start_end_angles_r(
            $z_start_end_angle_r[0],
            $z_start_end_angle_r[1],
            $radius_m,
            $this->circle_facets
        );

        $base_full = ($base_angle_diff_r == $this->two_pi);
        $base_segments_f = $this->circle_facets * $base_angle_diff_r / $this->two_pi;
        $base_segments = (int)ceil( $base_segments_f );

        $top_start_angle_r = $base_start_angle_r;
        $top_end_angle_r = $base_end_angle_r;
        $top_angle_diff_r = $base_angle_diff_r;
        $top_segments = $base_segments;
        $top_full = $base_full;

        // Base ring of vertices

        $base_radius_m = $base_diameter_m / 2.0;

        $base_first_vertex = count( $this->vertices );
        $blade2_base_first_vertex = $base_first_vertex;
        $this->collada_circle(
            [0,0, $current_height_offset_m], //$xyz,
            $base_radius_m,
            $base_segments,
            $base_start_angle_r,
            $base_end_angle_r,
            $pie,
            false,
            $blade2_base_ts,
            true //! $base_cap
        );
        $blade2_base_last_vertex = count( $this->vertices ) - 1;
        $current_total_height_m = 0;
        $prev_radius_m = $base_radius_m;

        $section3_facet_angle_r = 0;

        $top_radius_m = $radius_m + ($section1_chord_m / 2 - $radius_m) / $section1_frusta;
        $height_m = ($section1_height_m / $section1_frusta);
        $x_scale = $top_radius_m / $radius_m;
        $tan_theta = ($prev_radius_m - $top_radius_m * $x_scale) / $height_m;
        $y_scale = 1;

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Blade2: base_full='. ($base_full ? 'TRUE' : 'false') .' base_start_angle(deg)='.rad2deg( $base_start_angle_r) .' base_end_angle(deg)='.rad2deg( $base_end_angle_r) .' base_segments='. $base_segments .PHP_EOL, FILE_APPEND);

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' intitial_height_offset=%04.f', $initial_height_offset_m ) .PHP_EOL, FILE_APPEND);
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' radius_m=%04.f s1_c=%04f (%04.f) s3_c=%04f (%0.4f)', $radius_m, $section1_chord_m, $section1_chord_m / 2, $section3_chord_base_m, $section3_chord_base_m/ 2 ) .PHP_EOL, FILE_APPEND);
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' section1_height_m='.$section1_height_m .' section3_height_m='. $section3_height_m .PHP_EOL, FILE_APPEND);
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' frusta_start_angle(deg)='. rad2deg($frusta_start_angle_r) .' tan_theta='. $tan_theta .PHP_EOL, FILE_APPEND);
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' frusta_count='.$frusta_count .' section1_frusta='. $section1_frusta .' section2_start_frustum='. $section2_start_frustum .' section2_frusta='. $section2_frusta .' section3_start_frustum='. $section3_start_frustum .' section3_frusta='. $section3_frusta .PHP_EOL, FILE_APPEND);

        for ($f = 0; $f < $frusta_count; $f++)
        {
            if ( ($f + 1) == $section2_start_frustum )
            {
                $section = 2;
            }
            if ( ($f + 1) == $section3_start_frustum /* ($section1_frusta + $section2_frusta + 1) */ )
            {
                $tan_theta_copy = max(0, $tan_theta);
                $theta_r = atan( $tan_theta_copy );
                //$section3_facet_angle_r = ($final_z_rotation_r - $theta_r) / $section3_frusta; // 2 * pi / number of facets * 2 (full 360deg covers every frustum twice)
                $section3_facet_angle_r = M_PI / $section3_frusta; // 2 * pi / number of facets * 2 (full 360deg covers every frustum twice)
                $section3_virtual_base_radius_m = $section3_chord_base_m / 2 / cos( $theta_r );
                $section3_height_offset_m = $tan_theta_copy  * $section3_chord_base_m / 2;
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' frusta_start_angle(deg)='. rad2deg($frusta_start_angle_r) .' section3_facet_angle(deg)='. rad2deg($section3_facet_angle_r) .' tan_theta='. $tan_theta .PHP_EOL, FILE_APPEND);
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' section3_height_offset='. $section3_height_offset_m .' section3_facet_angle_rad='. rad2deg($section3_facet_angle_r) .' section3_virtual_base_radius='. $section3_virtual_base_radius_m .PHP_EOL, FILE_APPEND);

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. __LINE__ .'/ '.' f='.$f .' section='. $section .PHP_EOL, FILE_APPEND);
                $current_segment_angle_rad = $frusta_start_angle_r + $section3_facet_angle_r;
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. __LINE__ .'/ '.'current_segment_angle_rad='.rad2deg($current_segment_angle_rad) .PHP_EOL, FILE_APPEND);
                $section = 3;
            }
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' f='.$f .' section='. $section .PHP_EOL, FILE_APPEND);

            switch( $section )
            {
                case 1:
                    $current_segment_angle_rad = atan( $tan_theta );
                    $section_proportion = ($f + 1) / $section1_frusta;
                    // The effective radius (after x scaling) is the (increasing) proportion of the chord length as we go up this section
                    $top_radius_m = $radius_m + ($section1_chord_m / 2 - $radius_m) * $section_proportion;
                    $height_m = /* $section_proportion *  */ ($section1_height_m / $section1_frusta);
                    $x_scale = $top_radius_m / $radius_m;
                    // But the actual radius is the radius of the circle that has the chord length as its diameter - before x scaling
                    $top_radius_m = $radius_m;
                    $z_rot_r = - $this->blade_twist_rad * $section1_twist_percent * $section_proportion;
                    $y_scale = $this->blade2_min_chord_height_at_blip_percent_of_chord + (1 - $this->blade2_min_chord_height_at_blip_percent_of_chord) * (1 - $section_proportion);
                    break;

                case 2:
                    $section_proportion = ($f + 1 - $section1_frusta) / $section2_frusta;
                    // The effective radius (after x scaling) is the (decreasing) proportion of the chord length as we go up this section
                    $top_radius_m = ($section1_chord_m - $section3_chord_base_m) * (1 - $section_proportion**2) / 2 + $section3_chord_base_m / 2;
                    $height_m = $section2_height_m / $section2_frusta; // /* $section_proportion * */ ($section3_height_m - $section1_height_m) / (($section2_frusta));

                    //$x_scale = max( $top_radius_m / $radius_m, $section3_chord_base_m / (2 * $top_radius_m) );
                    //$top_radius_m = ($radius_m * 2 - $section3_chord_base_m) * (1 - $section_proportion**0.5) / 2 + $section3_chord_base_m / 2;
                    $x_scale = max( $top_radius_m / $radius_m, $section3_chord_base_m / (2 * $top_radius_m) ) ;
                    // The actual radius is linearly decreasing as we go up this section
                    $top_radius_m = ($radius_m * 2 - $section3_chord_base_m) * (1 - $section_proportion) / 2 + $section3_chord_base_m / 2;
                    $y_scale = $this->blade2_min_chord_height_at_tip_percent_of_chord + ($this->blade2_min_chord_height_at_blip_percent_of_chord - $this->blade2_min_chord_height_at_tip_percent_of_chord) * (1 - $section_proportion);
                    $z_rot_r = - $this->blade_twist_rad * (1 - $section1_twist_percent) * $section_proportion;
                    break;

                case 3:
                    $section_proportion = ($f + 1 - $section3_start_frustum) / ($frusta_count - $section3_start_frustum);
                    //$top_radius_m = $section3_chord_base_m * (1 - $section_proportion) / 2;
                    //$height_m = /*$section_proportion * */ ($blade_length_m - $section3_height_m) / ($frusta_count - $section3_start_frustum);

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' current_segment_angle_rad='.rad2deg($current_segment_angle_rad) .' section3_virtual_base_radius_m='. $section3_virtual_base_radius_m .PHP_EOL, FILE_APPEND);

                    $top_radius_m = $section3_virtual_base_radius_m * cos( $current_segment_angle_rad - $deg270_r );
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' %d / f=%3d section=%2d tot_height=%0.4f sect_prop=%0.4f top_rad=%0.4f (%0.4f) h=%0.4f x_sc=%0.4f y_s=%0.4f theta=%0.4f', __LINE__, $f, $section, $current_total_height_m, $section_proportion, $top_radius_m, $top_radius_m * $x_scale, $height_m, $x_scale, $y_scale, rad2deg( atan( $tan_theta)) ) .PHP_EOL, FILE_APPEND);
                    $height_m = $section3_virtual_base_radius_m * sin( $current_segment_angle_rad - $deg270_r );
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' %d / f=%3d section=%2d tot_height=%0.4f sect_prop=%0.4f top_rad=%0.4f (%0.4f) h=%0.4f x_sc=%0.4f y_s=%0.4f theta=%0.4f', __LINE__, $f, $section, $current_total_height_m, $section_proportion, $top_radius_m, $top_radius_m * $x_scale, $height_m, $x_scale, $y_scale, rad2deg( atan( $tan_theta)) ) .PHP_EOL, FILE_APPEND);
                    $section_proportion = $height_m * 2 / $section3_chord_base_m;
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' %d / f=%3d section=%2d tot_height=%0.4f sect_prop=%0.4f top_rad=%0.4f (%0.4f) h=%0.4f x_sc=%0.4f y_s=%0.4f theta=%0.4f', __LINE__, $f, $section, $current_total_height_m, $section_proportion, $top_radius_m, $top_radius_m * $x_scale, $height_m, $x_scale, $y_scale, rad2deg( atan( $tan_theta)) ) .PHP_EOL, FILE_APPEND);
                    $height_m -= $section3_height_offset_m;
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' %d / f=%3d section=%2d tot_height=%0.4f sect_prop=%0.4f top_rad=%0.4f (%0.4f) h=%0.4f x_sc=%0.4f y_s=%0.4f theta=%0.4f', __LINE__, $f, $section, $current_total_height_m, $section_proportion, $top_radius_m, $top_radius_m * $x_scale, $height_m, $x_scale, $y_scale, rad2deg( atan( $tan_theta)) ) .PHP_EOL, FILE_APPEND);
                    $section3_height_offset_m += $height_m;
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' %d / f=%3d section=%2d tot_height=%0.4f sect_prop=%0.4f top_rad=%0.4f (%0.4f) h=%0.4f x_sc=%0.4f y_s=%0.4f theta=%0.4f', __LINE__, $f, $section, $current_total_height_m, $section_proportion, $top_radius_m, $top_radius_m * $x_scale, $height_m, $x_scale, $y_scale, rad2deg( atan( $tan_theta)) ) .PHP_EOL, FILE_APPEND);
                    $x_scale = 1;
                    $z_rot_r = - $this->blade_twist_rad;
                    break;

                default:

            }
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' f=%3d section=%2d tot_height=%0.4f sect_prop=%0.4f top_rad=%0.4f (%0.4f) h=%0.4f x_sc=%0.4f y_s=%0.4f theta=%0.4f', $f, $section, $current_total_height_m, $section_proportion, $top_radius_m, $top_radius_m * $x_scale, $height_m, $x_scale, $y_scale, rad2deg( atan( $tan_theta)) ) .PHP_EOL, FILE_APPEND);

            $tan_theta = ($prev_radius_m - $top_radius_m * $x_scale) / $height_m;
            $prev_radius_m = $top_radius_m * $x_scale;
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. sprintf( ' f=%3d section=%2d tot_height=%0.4f sect_prop=%0.4f top_rad=%0.4f (%0.4f) h=%0.4f x_sc=%0.4f y_s=%0.4f theta=%0.4f', $f, $section, $current_total_height_m, $section_proportion, $top_radius_m, $top_radius_m * $x_scale, $height_m, $x_scale, $y_scale, rad2deg( atan( $tan_theta)) ) .PHP_EOL, FILE_APPEND);
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. '  f='. $f .' section='. $section .' tot_height='. sprintf( '%04f', $current_total_height_m ) .' section_proportion='.$section_proportion .' top_radius_m='. $top_radius_m .' height_m='. $height_m . ' x_scale='. $x_scale .' y_scale='. $y_scale .PHP_EOL, FILE_APPEND);

            //$top_radius_m = $radius_m * sin( $current_segment_angle_rad - M_PI );
            //$height_m = $radius_m * (1 - cos( $current_segment_angle_rad - M_PI )) - $current_height_offset_m;

            //                              SCALING                   - ROTATION    - TRANSLATION
            $blade2_top_custom_values = [ [ [ $x_scale,1,1 ],[],[] ] ]; //, [ [],[],[] ], [ [],[],[] ] ];

            $blade2_top_rotation_values = [ 0, 0, $z_rot_r ];
            $blade2_top_scaling_values = [1,$y_scale,1];
            // Set the transform that moves the top vertex ring to the correct height
            $blade2_top_translation_values[2] = $height_m;
            $this->update_transform_parameters( $blade2_top_translation_ref, $blade2_top_translation_values );
            $this->update_transform_parameters( $blade2_top_scaling_ref, $blade2_top_scaling_values );
            $this->update_transform_parameters( $blade2_top_rotation_ref, $blade2_top_rotation_values );
            $this->update_transform_parameters( $blade2_top_custom_ref, $blade2_top_custom_values );

            // Top vertex ring

            $top_first_vertex = count( $this->vertices );
            $this->collada_circle(
                [0,0, $current_height_offset_m], //[$x,$y,$z + $height_m],
                $top_radius_m,
                $top_segments,
                $top_start_angle_r,
                $top_end_angle_r,
                $pie,
                true,
                $blade2_top_ts,
                true //! $top_cap
            );

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Blade2: f='. $f .' top_first_vertex_upper='.$top_first_vertex_upper .' top_first_vertex_lower='.$top_first_vertex_lower.' last_vertex='.(count( $this->vertices ) - 1) .PHP_EOL, FILE_APPEND);

            if (! $vertices_only)
            {
                // Now we have the vertices for the "caps" whether displayed or not
                // Can use the vertices to create the faces.

                $vertex_count = count( $this->vertices ) - $top_first_vertex;

                $base_less_face_than_vertices = ($base_full && $pie); // || (! $base_full && ! $pie);
                $top_less_face_than_vertices = ($top_full && $pie); // || (! $top_full && ! $pie);
                $one_less_face_than_vertices = ($base_less_face_than_vertices && $top_less_face_than_vertices) ? 1 : 0;

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Blade2: vertex_count='. $vertex_count. ' one_less_face_than_vertices='. $one_less_face_than_vertices .' vertex_count_upper='. $vertex_count_upper .' vertex_count_lower='. $vertex_count_lower .PHP_EOL, FILE_APPEND);

                // Now the "tube"
                // Just use the circle vertices to make the faces (two triangles per face)
                for( $i = 0; $i < $vertex_count - $one_less_face_than_vertices; $i++)
                {
                    if ($i < $vertex_count)
                    {
                        // The frustum
                        $v1 = $base_first_vertex + $one_less_face_than_vertices + ( $i      % ($vertex_count - $one_less_face_than_vertices));
                        $v2 = $base_first_vertex + $one_less_face_than_vertices + (($i + 1) % ($vertex_count - $one_less_face_than_vertices));
                        $v3 = $top_first_vertex  + $one_less_face_than_vertices + ( $i      % ($vertex_count - $one_less_face_than_vertices));
                        $v4 = $top_first_vertex  + $one_less_face_than_vertices + (($i + 1) % ($vertex_count - $one_less_face_than_vertices));

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Blade2 upper: i='. $i. ' v1='. $v1. ' ( '.$base_first_vertex_upper.'+'.$one_less_face_than_vertices.'+('.$i.' % '.($vertex_count_upper - $one_less_face_than_vertices).')'
//    .'  v2='. $v2. ' ('.$base_first_vertex_upper.'+'.$one_less_face_than_vertices.'+(('.($i+1).') % '.($vertex_count_upper - $one_less_face_than_vertices).')'
//    .'  v3='. $v3. ' ('.$top_first_vertex_upper.'+'.$one_less_face_than_vertices.'+('.$i.' % '.($vertex_count_upper - $one_less_face_than_vertices).')'
//    .'  v4='. $v4 .' ('.$top_first_vertex_upper.'+'.$one_less_face_than_vertices.'+(('.($i+1).') % '.($vertex_count_upper - $one_less_face_than_vertices).')'.PHP_EOL, FILE_APPEND);

                        $normal1_k = count( $this->normals );
                        $normal2_k = $normal1_k;
                        $this->normals[] = $this->normal_from_vertices( $v2, $v3, $v1);
                        if ($unique_normals)
                        {
                            $this->normals[] = $this->normal_from_vertices( $v4, $v3, $v2);
                            $normal2_k++;
                        }

                        $this->triangles[] = [
                            $v1, $normal1_k,
                            $v2, $normal1_k,
                            $v3, $normal1_k
                        ];
                        $this->triangles[] = [
                            $v2, $normal2_k,
                            $v4, $normal2_k,
                            $v3, $normal2_k
                        ];
                    }
                }
            }

            $current_height_offset_m += $height_m;
            $current_total_height_m += $height_m;
            $current_segment_angle_rad += $section3_facet_angle_r;
            $base_first_vertex = $top_first_vertex;
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. __LINE__ .'/ '.'current_segment_angle_rad='.rad2deg($current_segment_angle_rad) .PHP_EOL, FILE_APPEND);
        }
        if ( ! $vertices_only
            && ($frusta_start_angle_r > M_PI))
        {
            // Need to include the faces in the bottom

            $this->circle_faces(
                $blade2_base_first_vertex,
                $blade2_base_last_vertex,
                false,
                $pie,
                $base_angle_diff_r
            );
        }
        if ( ! $vertices_only
            && ($frusta_end_angle_r < $this->two_pi))
        {
            // Need to include the faces in the top

            $this->circle_faces(
                $top_first_vertex,
                count( $this->vertices ) - 1,
                true,
                $pie,
                $base_angle_diff_r
            );
        }
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function format_float_array(
        array $floats,
        int $wrap_at_n = 0
    ) : string
    {
        $dp = 6;
        $format = '%.' . $dp . 'f';
        $all_zeros = '.'. str_repeat('0', $dp);
        $formatted = '';
        $n = 0;
        foreach ($floats as $index => $value)
        {
            foreach( $value as $vk => $vv )
            {
                $f = sprintf($format, $vv);
                if (mb_substr( $f, -($dp + 1)) === $all_zeros)
                    $f = mb_substr($f, 0, -($dp+1));
                $formatted .= $f;
                if (( $wrap_at_n > 0)
                    && (($n + 1) % $wrap_at_n === 0)
                ) {
                    $formatted .= "\n";
                } else {
                    $formatted .= ' ';
                }
                $n++;
            }
        }
        return trim($formatted);
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function format_int_array(
        array $ints,
        int $wrap_at_n = 0
    ) : string
    {
        $formatted = '';
        $n = 0;
        foreach ($ints as $index => $value)
        {
            foreach( $value as $vk => $vv )
            {
                $formatted .= sprintf( '%d', $vv );
                if (( $wrap_at_n > 0)
                    && (($n + 1) % $wrap_at_n === 0)
                ) {
                    $formatted .= "\n";
                } else {
                    $formatted .= ' ';
                }
                $n++;
            }
        }
        return trim($formatted);
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function generateTower(
        float $tower_height
    ) : void
    {
        $this->collada_cylinder(
            [ 0.0, 0.0, 0.0 ],
            (float)$this->data[K_TOWERBASEDIAMETER],
            $this->tower_top_diameter_m,
            $tower_height,
            [ 0, $this->two_pi ],
            [ 0, $this->two_pi ],
            false,
            false, // false when live
            true // true when live - the nacelle sits on top
        );
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function generateNacelle(
        float $nacelle_height_m,
        float $tower_height
    ) : void
    {
        $x_offset = 0.0;
        $y_offset = $this->nacelle_yoffset_m;
        $z_offset = $tower_height;

        $nacelle_ts = $this->new_transform_set();

        $requested_nacelle_length_m = ($this->data[K_NACELLESHAPE] == V_NACELLE_CYLINDER)
            ? (float)$this->data[K_NACELLECYLINDERLENGTH]
            : (float)$this->data[K_NACELLEBOXLENGTH];
        $this->nacelle_length_m = max( $requested_nacelle_length_m, $this->tower_top_diameter_m + 0.5 * $this->nacelle_yoffset_m );
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' nacelle_length_m='. $nacelle_length_m. ' max( '. $requested_nacelle_length_m .' , '. ($this->tower_top_diameter_m + 0.5 * $this->nacelle_yoffset_m) .' )' .PHP_EOL, FILE_APPEND);

        if ($this->data[K_NACELLESHAPE] == V_NACELLE_CYLINDER)
        {
            //*
            $this->prepend_rotation($nacelle_ts, [deg2rad(90.0), 0.0, 0.0]);

            $y_offset = $this->nacelle_yoffset_m + 0.5 * $this->tower_top_diameter_m;
            $z_offset += $nacelle_height_m / 2.0;

            $this->append_translation($nacelle_ts, [ $x_offset, $y_offset, $z_offset ]);

            $this->collada_cylinder(
                [ 0.0, 0.0, 0.0],
                $nacelle_height_m,
                $nacelle_height_m,
                $this->nacelle_length_m,
                [ 0, $this->two_pi ],
                [ 0, $this->two_pi ],
                false,
                true,
                true,
                $nacelle_ts
            );
            //*/
        } else
        {
            $nacelle_width_m = (float)$this->data[K_NACELLEBOXWIDTH]; // nacelle box width
            $y_offset = $this->nacelle_yoffset_m + -0.5 * ( $this->nacelle_length_m - $this->tower_top_diameter_m );
            //$x_offset -= 0;
            //$y_offset -= 1.5 * $nacelle_length_m;

            $this->append_translation($nacelle_ts, [ $x_offset, $y_offset, $z_offset ]);

            $this->collada_box(
                [0.0, 0.0, 0.0],
                $nacelle_height_m,
                $nacelle_width_m,
                $this->nacelle_length_m,
                $nacelle_ts
            );
        }
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function generateAxle(
        float $nacelle_height_m,
        float $tower_height
    ) : void
    {
        $axle_ts = $this->new_transform_set();
        $x_offset = 0;
        $y_offset = $this->axle_length_m + $this->tower_top_diameter_m / 2 + $this->nacelle_yoffset_m;
        $z_offset = $tower_height + $nacelle_height_m / 2.0;
        // Move the axle to the right place - top of the tower (and half of nacelle height) and horizontally adjusted too
        $translation = [ $x_offset, $y_offset, $z_offset ];
        $translation_ref = $this->append_translation( $axle_ts, $translation );

        // Rotate the axle from the vertical to horizontal
        $scaling = [ 1,1,1 ];
        $scaling_ref = $this->prepend_scaling( $axle_ts, $scaling );

        // Rotate the axle from the vertical to horizontal
        $rotation = [ deg2rad(90.0), 0.0, 0.0 ];
        $rotation_ref = $this->prepend_rotation( $axle_ts, $rotation );

        //$this->DEBUG1 = true;
        //$this->DEBUG2 = true;

        $axle_diameter_factor = 1.5; // make the axle bigger to allow for "hiding" the end of the blade in the axle

        // This is the axle
        $this->collada_cylinder(
            [ 0.0, 0.0, 0.0],
            $this->axle_diameter_m * $axle_diameter_factor,
            $this->axle_diameter_m * $axle_diameter_factor,
            $this->axle_length_m,
            [ 0, $this->two_pi ],
            [ 0, $this->two_pi ],
            false,
            true,
            true,
            $axle_ts
        );

        // Move the cap to the end of the axle
//        $translation[1] += 10.0;
//        $this->update_transform_parameters( $translation_ref, $translation );

        // Make the cap more cone like by "distorting" the axis of the sphere
        $scaling[1] = 1.5;
        $this->update_transform_parameters( $scaling_ref, $scaling );

        // Set the rotation accordingly for a half sphere
        $rotation[0] = deg2rad( 0.0);
        $this->update_transform_parameters( $rotation_ref, $rotation );

        // Make the half sphere as the cone on the front of the axle
        $this->collada_sphere(
            [0,0,0],
            $this->axle_diameter_m * $axle_diameter_factor,
            [0, $this->two_pi],
            [0, $this->two_pi],
            [0, M_PI],
            false,
            $axle_ts
        );
        $this->DEBUG1 = false;
        $this->DEBUG2 = false;
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function collada_blade_body_cb(
        array $point,
        array $parameters
    ) : array
    {
        // The blade body is made of several "sections", based on a hemisphere
        // 1 - root to "blip" (the point close to the root where the blade becomes more of an aerofoil)
        //     there is a small axial rotation (perhaps 30 degrees) and transition from circular to asymmetical elliptical
        //     (asymmetrical elliptical is to try to make the leading edge circular and the trailing edge flat)
        // 2 - "blip" to "tip" (the region over which the profile is like an aerofoil becoming "flat" at the tip
        //     The twist reduces along the length to 0. The asymmetric elliptical transition is becomes more pronounced

        $transformed_point = $point;

        //$from_root_ratio =
        return $transformed_point;
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function generateBlade(
        float $nacelle_height_m,
        float $tower_height,
        float $blade_angle_rad
    ) : void
    {
        $z_base_offset = $tower_height + $nacelle_height_m / 2.0;
        $x_dist_m = $this->axle_diameter_m / 2.0;
        $y_dist_m = $this->axle_length_m / 2.0;
        $z_dist_m = $x_dist_m;

        $x_offset = sin( $blade_angle_rad ) * $x_dist_m;
        $y_offset =  $this->tower_top_diameter_m / 2 + $this->nacelle_yoffset_m + $y_dist_m;
        $z_offset = $z_base_offset + cos( $blade_angle_rad ) * $z_dist_m;

        $blade_root_ts = $this->new_transform_set();
        $this->append_translation($blade_root_ts, [ $x_offset, $y_offset, $z_offset ]);
        $this->prepend_rotation($blade_root_ts, [ 0, $blade_angle_rad, 0 ] );

        $root_length = 1;
        // Make the root a 1m long cylinder
        $this->collada_cylinder(
            [ 0.0, 0.0, 0.0],
            $this->blade_root_diameter_m,
            $this->blade_root_diameter_m,
            $root_length,
            [ 0, $this->two_pi ],
            [ 0, $this->two_pi ],
            false,
            true,
            true,
            $blade_root_ts
        );

        $x_dist_m += $root_length;
        $z_dist_m += $root_length;

        $x_offset = sin( $blade_angle_rad ) * $x_dist_m;
        $y_offset = $this->tower_top_diameter_m / 2 + $this->nacelle_yoffset_m + $y_dist_m;
        $z_offset = $z_base_offset + cos( $blade_angle_rad ) * $z_dist_m;

        $blade_rest_ts = $this->new_transform_set();
        $this->append_translation($blade_rest_ts, [ $x_offset, $y_offset, $z_offset ]);
        $this->prepend_rotation($blade_rest_ts, [ 0, $blade_angle_rad, 0 ] );

        /*
        // Next, add a frustum (hurray!) which is the bulk of the blade-like thing
        $this->collada_cylinder(
            [ 0.0, 0.0, 0.0],
            $this->blade_root_diameter_m,
            0.5,
            $this->adjusted_blade_length_m - $root_length,
            [ 0, $this->two_pi ],
            [ 0, $this->two_pi ],
            false,
            true,
            true,
            $blade_rest_ts
        );
        //*/
        $this->collada_blade_type_2(
            [ 0.0, 0.0, 0.0],
            $this->adjusted_blade_length_m - $root_length,
            $this->blade_root_diameter_m,
            $blade_rest_ts
        );
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function generateBlades(
        float $nacelle_height_m,
        float $tower_height
    ) : void
    {
        $first_blade_angle_deg = (float)( (int)$this->data[K_BLADE1POSITION] % 12 ) * 360.0 / 12.0;

        for ( $i = 0; $i < $this->blade_count; $i++ )
        {
            $blade_angle_rad = - deg2rad($first_blade_angle_deg + ($i * 360.0 / $this->blade_count) );

            $this->generateBlade( $nacelle_height_m, $tower_height, $blade_angle_rad );
        }
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function DEBUG_test_primitives(): void
    {
        $p = [1.0,1.0,1.0];
        $r = [0.0, 0.0, 45.0 ];
        $r1x = $this->rotateXYZAboutXAxis( $p, deg2rad( -$r[0] ) );
        $r1y = $this->rotateXYZAboutYAxis( $p, deg2rad( -$r[1] ) );
        $r1z = $this->rotateXYZAboutZAxis( $p, deg2rad( $r[2] ) );
        $r2 = $this->rotate3DPoint( $p, $r );
        //$q = $this->eulerToQuaternion( $r );
        //$r3 = $this->rotatePointByQuaternion( $p, $q );
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' p: '. print_r( $p, true ) .PHP_EOL, FILE_APPEND);
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' r: '. print_r( $r, true ) .PHP_EOL, FILE_APPEND);
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' r1x: '. print_r( $r1x, true ) .PHP_EOL, FILE_APPEND);
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' r1y: '. print_r( $r1y, true ) .PHP_EOL, FILE_APPEND);
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' r1z: '. print_r( $r1z, true ) .PHP_EOL, FILE_APPEND);
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' r2: '. print_r( $r2, true ) .PHP_EOL, FILE_APPEND);
        //file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' r3: '. print_r( $r3, true ) .PHP_EOL, FILE_APPEND);
        //*/

        //* DEBUG
        $p0 = [0,0,0];
        $p1 = [ 0.0, 1.0, 1.0 ];
        $p2 = [ 1.0, 0.0, 1.0 ];
        $n1 = $this->calculate_normal( $p0, $p1, $p2 );
        $n2 = $this->calculate_normal( $p0, $p2, $p1 );
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' p0: '. print_r( $p0, true ) .PHP_EOL, FILE_APPEND);
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' p1: '. print_r( $p1, true ) .PHP_EOL, FILE_APPEND);
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' p2: '. print_r( $p2, true ) .PHP_EOL, FILE_APPEND);
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' n1: '. print_r( $n1, true ) .PHP_EOL, FILE_APPEND);
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' n2: '. print_r( $n2, true ) .PHP_EOL, FILE_APPEND);
        //*/
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function DEBUG_generate_test_circles(): void
    {
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Circle 1: 16segs, full not-pie' .PHP_EOL, FILE_APPEND);
        $this->collada_circle( [ 200,200,100 ], 50, $this->circle_facets, 0, $this->two_pi, false );
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Circle 2: 16segs, 45-245 not-pie' .PHP_EOL, FILE_APPEND);
        $this->collada_circle( [ 100,100,100 ], 50, $this->circle_facets, deg2rad(45 ), deg2rad( 245 ), false );
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Circle 3: 0segs, 45-245 not-pie' .PHP_EOL, FILE_APPEND);
        $this->collada_circle( [ 100,100,120 ], 50, 0, deg2rad(45 ), deg2rad( 245 ), false );

        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Circle 4: 16segs, full pie' .PHP_EOL, FILE_APPEND);
        $this->collada_circle( [ 400,400,100 ], 50, $this->circle_facets, 0, $this->two_pi, true );
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Circle 5: 16segs, 45-245 pie' .PHP_EOL, FILE_APPEND);
        $this->collada_circle( [ 300,300,100 ], 50, $this->circle_facets, deg2rad(45 ), deg2rad( 245 ), true );
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Circle 6: 0segs, 45-245 pie' .PHP_EOL, FILE_APPEND);
        $this->collada_circle( [ 300,300,120 ], 50, 0, deg2rad(45 ), deg2rad( 245 ), true );
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Circle 7: full' .PHP_EOL, FILE_APPEND);
        $this->collada_circle( [ 100,100,100 ], 50, $this->circle_facets, 0, $this->two_pi, false );
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function DEBUG_generate_test_cyclinders(): void
    {
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Cylinder 1: full, not-pie' .PHP_EOL, FILE_APPEND);
        $this->collada_cylinder( [ 500,500, 100 ], 50, 25, 100, [0, $this->two_pi], [0, $this->two_pi], false, true, true );
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Cylinder 2: full pie' .PHP_EOL, FILE_APPEND);
        $this->collada_cylinder( [ 600,600, 100 ], 50, 25, 100, [0, $this->two_pi], [0, $this->two_pi], true, true, true );

        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Cylinder 3: 45-185 not-pie' .PHP_EOL, FILE_APPEND);
        $this->collada_cylinder( [ 700,700, 100 ], 50, 25, 100, [deg2rad(45 ), deg2rad(185 )], [deg2rad(45 ), deg2rad(185 )], false, true, true );
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Cylinder 4: 45-185 pie' .PHP_EOL, FILE_APPEND);
        $this->collada_cylinder( [ 800,800, 100 ], 50, 25, 100, [deg2rad(45 ), deg2rad(185 )], [deg2rad(45 ), deg2rad(185 )], true, true, true );

        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Cylinder 5: 45-245 not-pie' .PHP_EOL, FILE_APPEND);
        $this->collada_cylinder( [ 900,900, 100 ],   50, 25, 100, [deg2rad(45 ), deg2rad(245 )], [deg2rad(45 ), deg2rad(245 )], false, true, true );
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Cylinder 6: 45-245 pie' .PHP_EOL, FILE_APPEND);
        $this->collada_cylinder( [ 1000,1000, 100 ], 50, 25, 100, [deg2rad(45 ), deg2rad(245 )], [deg2rad(45 ), deg2rad(245 )], true, true, true );
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function DEBUG_generate_test_spheres(): void
    {
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sphere pie 1: full' .PHP_EOL, FILE_APPEND);
        $this->collada_sphere_pie( [ 0, 0, 100 ], 50 );
//*
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sphere pie 2: 50%' .PHP_EOL, FILE_APPEND);
        $this->collada_sphere_pie( [ -100, -100, 100 ], 50, [ 0, 2 * M_PI ],[ 0, 2 * M_PI ],[ 0, M_PI ]);

        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sphere pie 3:  30-83' .PHP_EOL, FILE_APPEND);
        $this->collada_sphere_pie( [ -200, -200, 100 ], 50, [ 0, 2 * M_PI ],[ 0, 2 * M_PI ],[ deg2rad(30), deg2rad(83) ]);

        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sphere pie 4:  30-110' .PHP_EOL, FILE_APPEND);
        $this->collada_sphere_pie( [ -300, -300, 100 ], 50, [ 0, 2 * M_PI ],[ 0, 2 * M_PI ],[ deg2rad(30), deg2rad(110) ]);

        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sphere pie 5:  30-240' .PHP_EOL, FILE_APPEND);
        //$this->collada_sphere( [ 1100,1100, 100 ], 50 );
        $this->collada_sphere_pie( [ -400, -400, 100 ], 50, [ 0, 2 * M_PI ],[ 0, 2 * M_PI ],[ deg2rad(30), deg2rad(240) ]);

        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sphere not pie 6:  full' .PHP_EOL, FILE_APPEND);
        $this->collada_sphere_not_pie( [ -500, -500, 100 ], 50, [ 0, 2 * M_PI ],[ 0, 2 * M_PI ],[ 0, 2 * M_PI ] );

        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sphere not pie 7:  30-60' .PHP_EOL, FILE_APPEND);
        $this->collada_sphere_not_pie( [ -600, -600, 100 ], 50, [ deg2rad(30), deg2rad(60) ],[ 0, 2 * M_PI ],[ 0, 2 * M_PI ]);

        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sphere not pie 8:  30-160' .PHP_EOL, FILE_APPEND);
        $this->collada_sphere_not_pie( [ -700, -700, 100 ], 50, [ deg2rad(30), deg2rad(160) ],[ 0, 2 * M_PI ],[ 0, 2 * M_PI ]);

        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sphere not pie 9:  30-230' .PHP_EOL, FILE_APPEND);
        $this->collada_sphere_not_pie( [ -800, -800, 100 ], 50, [ deg2rad(30), deg2rad(230) ],[ 0, 2 * M_PI ],[ 0, 2 * M_PI ]);

        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sphere not pie 10:  85-355' .PHP_EOL, FILE_APPEND);
        $this->collada_sphere_not_pie( [ -900, -900, 100 ], 50, [ deg2rad(85), deg2rad(355) ],[ 0, 2 * M_PI ],[ 0, 2 * M_PI ]);

        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sphere not pie 11:  30-160, 20-50' .PHP_EOL, FILE_APPEND);
        $this->collada_sphere_not_pie( [ -1000, -1000, 100 ], 50, [ deg2rad(30), deg2rad(160) ],[ 0, 2 * M_PI ],[ deg2rad(20), deg2rad(50) ]);

        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sphere not pie 12:  30-60, 20-50' .PHP_EOL, FILE_APPEND);
        $this->collada_sphere_not_pie( [ -1100, -1100, 100 ], 50, [ deg2rad(30), deg2rad(60) ],[ 0, 2 * M_PI ],[ deg2rad(20), deg2rad(50) ]);
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Sphere not pie 13:  30-100, 20-250' .PHP_EOL, FILE_APPEND);
        $this->collada_sphere_not_pie( [ -1200, -1200, 100 ], 50, [ deg2rad(30), deg2rad(100) ],[ 0, 2 * M_PI ],[ deg2rad(20), deg2rad(250) ]);

    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function DEBUG_generate_test_blades(): void
    {
        file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Blade type 2:' .PHP_EOL, FILE_APPEND);
        //$this->collada_blade_type_2( [ -1300, -1300, 100 ], $this->adjusted_blade_length_m, $this->blade_root_diameter_m );
        $this->collada_blade_type_2( [ 0, 0, 100 ], $this->adjusted_blade_length_m, $this->blade_root_diameter_m );
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function generateColladaData(
    ) : string
    {
        // First create the tower
        // Next the nacelle
        // Next the axle
        // Finally the blades

        $created = date('Y-m-d\TH:i:s.u');
        //$safeName = $this->xml_escape($this->data[K_SITENAME]);
        $scale = 1;
        $up_axis = $this->z_up ? 'Z_UP' : 'Y_UP';
        $geometry_id = $this->xml_escape( 'wtva-geometry0' );
        $geometry_name = $this->xml_escape( 'wtva-turbine' );
        $tower_height = (float)$this->data[K_TOWERHEIGHT];
        $this->tower_top_diameter_m = (float)$this->data[K_TOWERTOPDIAMETER];
        $this->blade_count = (int)$this->data[K_BLADECOUNT];

        $nacelle_height_m = ($this->data[K_NACELLESHAPE] == V_NACELLE_CYLINDER)
            ? (float)$this->data[K_NACELLEDIAMETER]
            : (float)$this->data[K_NACELLEBOXHEIGHT];
        $blade_length = (float)$this->data[K_BLADELENGTH];
        $this->blade_root_diameter_m = (float)$this->data[K_BLADEROOTDIAMETER];

        // Axle diameter needs to be at least the blade root diameter, as does the length of the cylinder
        // Also axle circumference (thus diameter) needs to be at least number-of-blades times blade root diameter
        $this->axle_diameter_m = 1.1 * max(
            $this->blade_root_diameter_m,
            ( $this->blade_root_diameter_m * $this->blade_count ) / M_PI );
        $this->axle_length_m = $this->blade_root_diameter_m * 1.1;
        $this->adjusted_blade_length_m = $blade_length - ($this->axle_diameter_m / 2.0);


        $adjusted_tower_height_m = $tower_height - ($nacelle_height_m / 2.0);

        $this->blade_depth_m = max( $this->blade2_chord_percent_of_length * $blade_length, $this->min_blade_depth_m );

        $this->nacelle_yoffset_m = max( ( $this->blade_depth_m - $this->axle_diameter_m ), $this->min_blade_offset_from_tower_m );
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' blade_depth_m='.$this->blade_depth_m .' max('. ($this->blade2_chord_percent_of_length * $blade_length).' , '.$this->min_blade_depth_m .' )' .PHP_EOL, FILE_APPEND);
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' macelle_offset_m='.$this->nacelle_yoffset_m .' max('. ( $this->blade_depth_m - $this->axle_diameter_m ) .' , '. $this->min_blade_offset_from_tower_m .' )' .PHP_EOL, FILE_APPEND);

        // The Tower
        //*
        $this->generateTower( $adjusted_tower_height_m );
        //*/

        // The Nacelle
        $this->generateNacelle( $nacelle_height_m, $adjusted_tower_height_m );

        // The Axle
        $this->generateAxle( $nacelle_height_m, $adjusted_tower_height_m );

        // The Blades
        $this->generateBlades( $nacelle_height_m, $adjusted_tower_height_m );

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' ->adjusted_blade_length_m='.$this->adjusted_blade_length_m .' this->blade_root_diameter_m='. $this->blade_root_diameter_m .PHP_EOL, FILE_APPEND);
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' normals: '. print_r( $this->normals, true ) .PHP_EOL, FILE_APPEND);

        $vertices_accessor_count = count( $this->vertices );
        $vertices_count = $vertices_accessor_count * 3;
        $normals_accessor_count = count( $this->normals );
        $normals_count = $normals_accessor_count * 3;
        $triangles_count = count( $this->triangles );

        $formatted_vertices = $this->format_float_array($this->vertices, 0);
        $formatted_normals = $this->format_float_array($this->normals, 0);
        $formatted_triangles = $this->format_int_array($this->triangles, 0);

        $dae = <<<DAE
<COLLADA xmlns="http://www.collada.org/2005/11/COLLADASchema" version="1.4.1">
  <asset>
    <created>$created</created>
    <modified>$created</modified>
    <up_axis>$up_axis</up_axis>
  </asset>
  <library_effects>
    <effect id="effect0" name="effect0">
      <profile_COMMON>
        <technique sid="common">
          <phong>
            <emission>
              <color>0.0 0.0 0.0 1.0</color>
            </emission>
            <ambient>
              <color>0.0 0.0 0.0 1.0</color>
            </ambient>
            <diffuse>
              <color>1 1 1 1.0</color>
            </diffuse>
            <specular>
              <color>0 1 0 1.0</color>
            </specular>
            <shininess>
              <float>0.0</float>
            </shininess>
            <reflective>
              <color>0.0 0.0 0.0 1.0</color>
            </reflective>
            <reflectivity>
              <float>0.0</float>
            </reflectivity>
            <transparent>
              <color>0.0 0.0 0.0 1.0</color>
            </transparent>
            <transparency>
              <float>1.0</float>
            </transparency>
          </phong>
        </technique>
        <extra>
          <technique profile="GOOGLEEARTH">
            <double_sided>0</double_sided>
          </technique>
        </extra>
      </profile_COMMON>
    </effect>
  </library_effects>
  <library_geometries>
    <geometry id="$geometry_id" name="$geometry_name">
        <mesh>
            <source id="vertices-array">
                <float_array count="$vertices_count" id="vertices-array-array">$formatted_vertices</float_array>
                <technique_common>
                    <accessor count="$vertices_accessor_count" source="#vertices-array-array" stride="3">
                        <param type="float" name="X" />
                        <param type="float" name="Y" />
                        <param type="float" name="Z" />
                    </accessor>
                </technique_common>
            </source>
            <source id="normals-array">
                <float_array count="$normals_count" id="normals-array-array">$formatted_normals</float_array>
                <technique_common>
                    <accessor count="$normals_accessor_count" source="#normals-array-array" stride="3">
                        <param type="float" name="X" />
                        <param type="float" name="Y" />
                        <param type="float" name="Z" />
                    </accessor>
                </technique_common>
            </source>
            <vertices id="vertices-array-vertices">
                <input semantic="POSITION" source="#vertices-array" />
            </vertices>
            <triangles count="$triangles_count" material="materialref">
                <input offset="0" semantic="VERTEX" source="#vertices-array-vertices" />
                <input offset="1" semantic="NORMAL" source="#normals-array" />
                <p>$formatted_triangles</p>
            </triangles>
        </mesh>
    </geometry>
  </library_geometries>
  <library_materials>
    <material id="turbinematerial0" name="turbinematerial">
      <instance_effect url="#effect0" />
    </material>
  </library_materials>
  <library_visual_scenes>
    <visual_scene id="turbinescene">
      <node id="node0" name="node0">
        <instance_geometry url="#$geometry_id">
          <bind_material>
            <technique_common>
              <instance_material symbol="materialref" target="#turbinematerial0" />
            </technique_common>
          </bind_material>
        </instance_geometry>
      </node>
    </visual_scene>
  </library_visual_scenes>
  <scene>
    <instance_visual_scene url="#turbinescene" />
  </scene>
</COLLADA>
DAE;

        return $dae . PHP_EOL;
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function generateOrientation(
        int $direction_deg,
        bool $visibile
    ) : string
    {
        $name = $this->xml_escape( sprintf( 'orientation%d', $direction_deg ));
        $directions = [
            'North',
            'North East',
            'East',
            'South East',
            'South',
            'South West',
            'West',
            'North West',
        ];
        $direction_count = count( $directions );
        $deg_per_direction = 360.0 / $direction_count;
        $corrected_dir_deg = ( $direction_deg + ($deg_per_direction / 2.0 ) + 360.0);
        if ($corrected_dir_deg > 360.0)
            $corrected_dir_deg = $corrected_dir_deg - 360.0;
        $k = (int)( $corrected_dir_deg / $deg_per_direction ) % $direction_count;
        $direction_name = $directions[ $k ];
        $visibility = $visibile ? 1 : 0;

        $placemarks = '';
        foreach( $this->locations as $loc_num => $loc)
        {
            /*
            $loc_name = 'Test';
            $loc_lat = 55.9;
            $loc_lon = -3.0;
            //*/
            list( $loc_enabled, $loc_name, $loc_lat_deg, $loc_lon_deg, $loc_lat_rad, $loc_lon_rad, $loc_x, $loc_y, $loc_z ) = $loc;

            if ($loc_enabled)
                $placemarks .= <<<KML
        <Placemark id="$name$loc_num">
            <name>$loc_name</name>
            <visibility>$visibility</visibility>
            <description>$loc_name</description>
            <Model id="$name$loc_num-1">
                <Location>
                    <longitude>$loc_lon_deg</longitude>
                    <latitude>$loc_lat_deg</latitude>
                    <altitude>0</altitude>
                </Location>
                <Orientation>
                    <heading>$direction_deg</heading>
                    <tilt>0</tilt>
                    <roll>0</roll>
                </Orientation>
                <Scale>
                    <x>1</x>
                    <y>1</y>
                    <z>1</z>
                </Scale>
                <Link id="$name$loc_num-2">
                    <href>$this->kmz_subdir/$this->collada_model_filename</href>
                </Link>
                <ResourceMap>
                </ResourceMap>
            </Model>
        </Placemark>

KML;

        }

        $kml = <<<KML
    <Document id="$name">
        <name>$name</name>
        <description>Orientation $direction_deg from North - approx $direction_name</description>
$placemarks
    </Document>
KML;
        return $kml;
    }

    // -----------------------------------------------------------------------------------------------------------------
    protected function generateKMLData(
    ) : string
    {
        $safe_name = $this->xml_escape($this->data[K_SITENAME]);

        $orientations = '';
        $dir_deg1 = $this->data[K_TOWERORIENTATION1];
        for( $o = 0; $o < $this->data[K_TOWERORIENTATIONCOUNT]; $o++)
        {
            $dir_deg = ( $dir_deg1 + $o * 360 / $this->data[K_TOWERORIENTATIONCOUNT] ) % 360;
            $orientations .= $this->generateOrientation( $dir_deg, ($o == 0) );
        }

        $kml = <<<KML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
<Document>
    <name>$safe_name</name>
    <open>1</open>
$orientations
</Document>
</kml>
KML;

        return $kml . PHP_EOL;
    }

    // -----------------------------------------------------------------------------------------------------------------
    public function generateKMZ(
    ) : array
    {
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Data: '. print_r( $this->data, true ) .PHP_EOL, FILE_APPEND);

        $okaySoFar = true;
        $m = [];
        $kmzPath = '';

        $daeContent = $this->generateColladaData();
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Data: '. print_r( $this->data, true ) .PHP_EOL, FILE_APPEND);
        $kmlContent = $this->generateKMLData();
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Data: '. print_r( $this->data, true ) .PHP_EOL, FILE_APPEND);

        //$wtvaPath = tempnam(sys_get_temp_dir(), 'wtva_');
        $wtvaPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wtva_'. date('Ymd_His_') . (string)mt_rand( 1000, 9999) ;
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' wtvaPath: '. print_r( $wtvaPath, true ) .PHP_EOL, FILE_APPEND);
        mkdir( $wtvaPath, 0777, true );
        if (! file_exists( $wtvaPath ) || ! is_dir( $wtvaPath ))
        {
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' Path not made' .PHP_EOL, FILE_APPEND);
            $okaySoFar = false;
            $m = ['Failed to allocate a temporary WTVA directory.'];
        }
        if ($okaySoFar)
        {
            mkdir($wtvaPath . DIRECTORY_SEPARATOR . $this->kmz_subdir, 0777, true);
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' subdir made '. print_r( $wtvaPath. DIRECTORY_SEPARATOR . $this->kmz_subdir, true ) .PHP_EOL, FILE_APPEND);
        }
        if ($okaySoFar
            && ! file_exists( $wtvaPath )
            && ! is_dir( $wtvaPath )
        ) {
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' dir not made ' .PHP_EOL, FILE_APPEND);
            $okaySoFar = false;
            $m = ['Failed to allocate a temporary WTVA subdirectory.'];
        }
        if ($okaySoFar)
        {
            $daePath = $wtvaPath . DIRECTORY_SEPARATOR .
                $this->kmz_subdir .DIRECTORY_SEPARATOR. $this->collada_model_filename;
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' daePath '. $daePath .PHP_EOL, FILE_APPEND);
            file_put_contents($daePath, $daeContent);

            $docPath = $wtvaPath . DIRECTORY_SEPARATOR .
                $this->kmz_subdir .DIRECTORY_SEPARATOR. $this->kml_filename;
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' docPath '. $docPath .PHP_EOL, FILE_APPEND);
            file_put_contents($docPath, $kmlContent);

            $kmzPath = $wtvaPath . DIRECTORY_SEPARATOR . 'wtva.kmz';
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' kmzPath '. $kmzPath .PHP_EOL, FILE_APPEND);
            $zip = new ZipArchive();
            if ($zip->open($kmzPath, ZipArchive::CREATE) === true)
            {
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' zip file opened '. $kmzPath .PHP_EOL, FILE_APPEND);
                $zip->addFile($docPath, $this->kml_filename);
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' added doc file '. $docPath .PHP_EOL, FILE_APPEND);
                $zip->addEmptyDir( $this->kmz_subdir );
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' added empty subdir '. $this->kmz_subdir .PHP_EOL, FILE_APPEND);
                $zip->addFile( $wtvaPath . DIRECTORY_SEPARATOR . $this->kmz_subdir . DIRECTORY_SEPARATOR . $this->collada_model_filename,
                    $this->kmz_subdir .'/'. $this->collada_model_filename
                );
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' added Collada file '. $kmzPath .PHP_EOL, FILE_APPEND);
                $zip->close();
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' zip closed '. $kmzPath .PHP_EOL, FILE_APPEND);

                @unlink( $daePath );
                @unlink( $docPath );
            }
        }

//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' okaySoFar '. print_r( $okaySoFar, true ) .PHP_EOL, FILE_APPEND);
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' m '. print_r( $m, true ) .PHP_EOL, FILE_APPEND);
//file_put_contents($this->DEBUGFILE, 'wtva/'.__LINE__. ' kmzPath '. print_r( $kmzPath, true ) .PHP_EOL, FILE_APPEND);
        return [ $okaySoFar, $m, $kmzPath ];
    }
}
