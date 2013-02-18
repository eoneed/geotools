<?php

/**
 * This file is part of the Geotools library.
 *
 * (c) Antoine Corcy <contact@sbin.dk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Geotools\Convert;

use Geotools\Coordinate\CoordinateInterface;
use Geotools\AbstractGeotools;

/**
 * Convert class
 *
 * @author Antoine Corcy <contact@sbin.dk>
 */
class Convert extends AbstractGeotools implements ConvertInterface
{
    /**
     * The coordinate to convert.
     *
     * @var CoordinateInterface
     */
    protected $coordinates;


    /**
     * Set the coordinate to convert.
     *
     * @param CoordinateInterface $coordinates The coordinate to convert.
     */
    public function __construct(CoordinateInterface $coordinates)
    {
        $this->coordinates = $coordinates;
    }

    /**
     * Parse decimal degrees coordinate to degrees minutes seconds and decimal minutes coordinate.
     *
     * @param string $coordinate The coordinate to parse.
     *
     * @return array The replace pairs values.
     */
    private function parseCoordinate($coordinate)
    {
        list($degrees) = explode('.', abs($coordinate));
        list($minutes) = explode('.', (abs($coordinate) - $degrees) * 60);

        return array(
            'positive'       => $coordinate >= 0,
            'degrees'        => (string) $degrees,
            'decimalMinutes' => (string) round((abs($coordinate) - $degrees) * 60,
                ConvertInterface::DECIMAL_MINUTES_PRECISION,
                ConvertInterface::DECIMAL_MINUTES_MODE),
            'minutes'        => (string) $minutes,
            'seconds'        => (string) round(((abs($coordinate) - $degrees) * 60 - $minutes) * 60),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toDegreesMinutesSeconds($format = ConvertInterface::DEFAULT_DMS_FORMAT)
    {
        $latitude  = $this->parseCoordinate($this->coordinates->getLatitude());
        $longitude = $this->parseCoordinate($this->coordinates->getLongitude());

        return strtr($format, array(
            ConvertInterface::LATITUDE_SIGN       => $latitude['positive'] ? '' : '-',
            ConvertInterface::LATITUDE_DIRECTION  => $latitude['positive'] ? 'N' : 'S',
            ConvertInterface::LATITUDE_DEGREES    => $latitude['degrees'],
            ConvertInterface::LATITUDE_MINUTES    => $latitude['minutes'],
            ConvertInterface::LATITUDE_SECONDS    => $latitude['seconds'],
            ConvertInterface::LONGITUDE_SIGN      => $longitude['positive'] ? '' : '-',
            ConvertInterface::LONGITUDE_DIRECTION => $longitude['positive'] ? 'E' : 'W',
            ConvertInterface::LONGITUDE_DEGREES   => $longitude['degrees'],
            ConvertInterface::LONGITUDE_MINUTES   => $longitude['minutes'],
            ConvertInterface::LONGITUDE_SECONDS   => $longitude['seconds'],
        ));
    }

    /**
     * Alias of toDegreesMinutesSeconds function.
     *
     * @param string $format The way to format the DMS coordinate.
     *
     * @return string Converted and formatted string.
     */
    public function toDMS($format = ConvertInterface::DEFAULT_DMS_FORMAT)
    {
        return $this->toDegreesMinutesSeconds($format);
    }

    /**
     * {@inheritDoc}
     */
    public function toDecimalMinutes($format = ConvertInterface::DEFAULT_DM_FORMAT)
    {
        $latitude  = $this->parseCoordinate($this->coordinates->getLatitude());
        $longitude = $this->parseCoordinate($this->coordinates->getLongitude());

        return strtr($format, array(
            ConvertInterface::LATITUDE_SIGN             => $latitude['positive'] ? '' : '-',
            ConvertInterface::LATITUDE_DIRECTION        => $latitude['positive'] ? 'N' : 'S',
            ConvertInterface::LATITUDE_DEGREES          => $latitude['degrees'],
            ConvertInterface::LATITUDE_DECIMAL_MINUTES  => $latitude['decimalMinutes'],
            ConvertInterface::LONGITUDE_SIGN            => $longitude['positive'] ? '' : '-',
            ConvertInterface::LONGITUDE_DIRECTION       => $longitude['positive'] ? 'E' : 'W',
            ConvertInterface::LONGITUDE_DEGREES         => $longitude['degrees'],
            ConvertInterface::LONGITUDE_DECIMAL_MINUTES => $longitude['decimalMinutes'],
        ));
    }

    /**
     * Alias of toDecimalMinutes function.
     *
     * @param string $format The way to format the DMS coordinate.
     *
     * @return string Converted and formatted string.
     */
    public function toDM($format = ConvertInterface::DEFAULT_DM_FORMAT)
    {
        return $this->toDecimalMinutes($format);
    }

    /**
     * {@inheritDoc}
     */
    public function toUniversalTransverseMercator()
    {
        // Convert decimal degrees coordinates to radian.
        $phi    = deg2rad($this->coordinates->getLatitude());
        $lambda = deg2rad($this->coordinates->getLongitude());

        // Compute the zone UTM zone.
        $zone = (int) (($this->coordinates->getLongitude() + 180.0) / 6) + 1;

        // Determines the central meridian for the given UTM zone.
        $lambda0 = deg2rad(-183.0 + ($zone * 6.0));

        $ep2 = (pow(AbstractGeotools::EARTH_RADIUS_MAJOR, 2.0) - pow(AbstractGeotools::EARTH_RADIUS_MINOR, 2.0)) / pow(AbstractGeotools::EARTH_RADIUS_MINOR, 2.0);
        $nu2 = $ep2 * pow(cos($phi), 2.0);
        $nN   = pow(AbstractGeotools::EARTH_RADIUS_MAJOR, 2.0) / (AbstractGeotools::EARTH_RADIUS_MINOR * sqrt(1 + $nu2));
        $t   = tan($phi);
        $t2  = $t * $t;
        $tmp = ($t2 * $t2 * $t2) - pow($t, 6.0);
        $l   = $lambda - $lambda0;

        $l3coef = 1.0 - $t2 + $nu2;
        $l4coef = 5.0 - $t2 + 9 * $nu2 + 4.0 * ($nu2 * $nu2);
        $l5coef = 5.0 - 18.0 * $t2 + ($t2 * $t2) + 14.0 * $nu2 - 58.0 * $t2 * $nu2;
        $l6coef = 61.0 - 58.0 * $t2 + ($t2 * $t2) + 270.0 * $nu2 - 330.0 * $t2 * $nu2;
        $l7coef = 61.0 - 479.0 * $t2 + 179.0 * ($t2 * $t2) - ($t2 * $t2 * $t2);
        $l8coef = 1385.0 - 3111.0 * $t2 + 543.0 * ($t2 * $t2) - ($t2 * $t2 * $t2);

        // Calculate easting.
        $easting = $nN * cos($phi) * $l
            + ($nN / 6.0 * pow(cos($phi), 3.0) * $l3coef * pow($l, 3.0))
            + ($nN / 120.0 * pow(cos($phi), 5.0) * $l5coef * pow($l, 5.0))
            + ($nN / 5040.0 * pow(cos($phi), 7.0) * $l7coef * pow($l, 7.0));

        // Calculate northing.
        $n = (AbstractGeotools::EARTH_RADIUS_MAJOR - AbstractGeotools::EARTH_RADIUS_MINOR) / (AbstractGeotools::EARTH_RADIUS_MAJOR + AbstractGeotools::EARTH_RADIUS_MINOR);
        $alpha = ((AbstractGeotools::EARTH_RADIUS_MAJOR + AbstractGeotools::EARTH_RADIUS_MINOR) / 2.0) * (1.0 + (pow($n, 2.0) / 4.0) + (pow($n, 4.0) / 64.0));
        $beta = (-3.0 * $n / 2.0) + (9.0 * pow($n, 3.0) / 16.0) + (-3.0 * pow($n, 5.0) / 32.0);
        $gamma = (15.0 * pow($n, 2.0) / 16.0) + (-15.0 * pow($n, 4.0) / 32.0);
        $delta = (-35.0 * pow($n, 3.0) / 48.0) + (105.0 * pow($n, 5.0) / 256.0);
        $epsilon = (315.0 * pow($n, 4.0) / 512.0);
        $northing = $alpha
            * ($phi + ($beta * sin(2.0 * $phi))
            + ($gamma * sin(4.0 * $phi))
            + ($delta * sin(6.0 * $phi))
            + ($epsilon * sin(8.0 * $phi)))
            + ($t / 2.0 * $nN * pow(cos($phi), 2.0) * pow($l, 2.0))
            + ($t / 24.0 * $nN * pow(cos($phi), 4.0) * $l4coef * pow($l, 4.0))
            + ($t / 720.0 * $nN * pow(cos($phi), 6.0) * $l6coef * pow($l, 6.0))
            + ($t / 40320.0 * $nN * pow(cos($phi), 8.0) * $l8coef * pow($l, 8.0));

        // Adjust easting and northing for UTM system.
        $easting = $easting * AbstractGeotools::UTM_SCALE_FACTOR + 500000.0;
        $northing = $northing * AbstractGeotools::UTM_SCALE_FACTOR;
        if ($northing < 0.0) {
            $northing += 10000000.0;
        }

        return sprintf('%d%s %d %d',
            $zone, $this->latitudeBands[(int) ($this->coordinates->getLatitude() + 80) / 8], $easting, $northing
        );
    }

    /**
     * Alias of toUniversalTransverseMercator function.
     *
     * @return string The converted UTM coordinate in meters
     */
    public function toUTM()
    {
        return $this->toUniversalTransverseMercator();
    }
}