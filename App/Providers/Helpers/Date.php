<?php

namespace FSPoster\App\Providers\Helpers;

use DateTime;
use DateTimeZone;

class Date
{
    private static $time_zone;

    public static function getTimeZone ()
    {
        if ( is_null( self::$time_zone ) )
        {
			$tz_string = get_option( 'timezone_string' );
			$tz_offset = get_option( 'gmt_offset', 0 );

			if ( ! empty( $tz_string ) )
			{
				$timezone = $tz_string;
			}
			else
			{
				if ( ! empty( $tz_offset ) )
				{
					$hours   = abs( (int) $tz_offset );
					$minutes = ( abs( $tz_offset ) - $hours ) * 60;

					$timezone = ( $tz_offset > 0 ? '+' : '-' ) . sprintf( '%02d:%02d', $hours, $minutes );
				}
				else
				{
					$timezone = 'UTC';
				}
			}

			self::$time_zone = new DateTimeZone( $timezone );
        }

        return self::$time_zone;
    }

    public static function getZone() {
        $timezone_string = get_option( 'timezone_string' );
    
        return $timezone_string;

    }

    public static function getUTC(){
        $offset  = (float) get_option( 'gmt_offset' );
        $hours   = (int) $offset;
        $minutes = ( $offset - $hours );
    
        $sign      = ( $offset < 0 ) ? '-' : '+';
        $abs_hour  = abs( $hours );
        $abs_mins  = abs( $minutes * 60 );
        $tz_offset = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );
    
        return $tz_offset;
    }

    public static function dateTime ( $date = 'now', $modify = false )
    {
        if ( is_numeric( $date ) )
        {
            $datetime = new DateTime( 'now', self::getTimeZone() );
            $datetime->setTimestamp( $date );
        } else
        {
            $datetime = new DateTime( $date, self::getTimeZone() );
        }

        if ( !empty( $modify ) )
        {
            $datetime->modify( $modify );
        }

        return $datetime->format( self::formatDateTime() );
    }

    public static function datee ( $date = 'now', $modify = false )
    {
        if ( is_numeric( $date ) )
        {
            $datetime = new DateTime( 'now', self::getTimeZone() );
            $datetime->setTimestamp( $date );
        } else
        {
            $datetime = new DateTime( $date, self::getTimeZone() );
        }

        if ( !empty( $modify ) )
        {
            $datetime->modify( $modify );
        }

        return $datetime->format( self::formatDate() );
    }

    public static function time ( $date = 'now', $modify = false )
    {
        if ( is_numeric( $date ) )
        {
            $datetime = new DateTime( 'now', self::getTimeZone() );
            $datetime->setTimestamp( $date );
        } else
        {
            $datetime = new DateTime( $date, self::getTimeZone() );
        }

        if ( !empty( $modify ) )
        {
            $datetime->modify( $modify );
        }

        return $datetime->format( self::formatTime() );
    }

    public static function dateTimeSQL ( $date = 'now', $modify = false )
    {
        if ( is_numeric( $date ) )
        {
            $datetime = new DateTime( 'now', self::getTimeZone() );
            $datetime->setTimestamp( $date );
        } else
        {
            $datetime = new DateTime( $date, self::getTimeZone() );
        }

        if ( !empty( $modify ) )
        {
            $datetime->modify( $modify );
        }

        return $datetime->format( self::formatDateTime( true ) );
    }

    public static function dateSQL ( $date = 'now', $modify = false )
    {
        if ( is_numeric( $date ) )
        {
            $datetime = new DateTime( 'now', self::getTimeZone() );
            $datetime->setTimestamp( $date );
        } else
        {
            $datetime = new DateTime( $date, self::getTimeZone() );
        }

        if ( !empty( $modify ) )
        {
            $datetime->modify( $modify );
        }

        return $datetime->format( self::formatDate( true ) );
    }

    public static function format ( $format, $date = 'now', $modify = false )
    {
        if ( is_numeric( $date ) )
        {
            $datetime = new DateTime( 'now', self::getTimeZone() );
            $datetime->setTimestamp( $date );
        } else
        {
            $datetime = new DateTime( $date, self::getTimeZone() );
        }

        if ( !empty( $modify ) )
        {
            $datetime->modify( $modify );
        }

        return $datetime->format( $format );
    }

    public static function timeSQL ( $date = 'now', $modify = false )
    {
        if ( is_numeric( $date ) )
        {
            $datetime = new DateTime( 'now', self::getTimeZone() );
            $datetime->setTimestamp( $date );
        } else
        {
            $datetime = new DateTime( $date, self::getTimeZone() );
        }

        if ( !empty( $modify ) )
        {
            $datetime->modify( $modify );
        }

        return $datetime->format( self::formatTime( true ) );
    }

    public static function epoch ( $date = 'now', $modify = false )
    {
        $datetime = new DateTime( $date, self::getTimeZone() );

        if ( !empty( $modify ) )
        {
            $datetime->modify( $modify );
        }

        return $datetime->getTimestamp();
    }

    public static function formatDate ( $forSQL = false )
    {
        if ( $forSQL )
        {
            return 'Y-m-d';
        } else
        {
            return 'Y-m-d';
        }
    }

    public static function formatTime ( $forSQL = false )
    {
        if ( $forSQL )
        {
            return 'H:i:s';
        } else
        {
            return 'H:i';
        }
    }

    public static function formatDateTime ( $forSQL = false )
    {
        return self::formatDate( $forSQL ) . ' ' . self::formatTime( $forSQL );
    }

    public static function year ( $date = 'now', $modify = false )
    {
        if ( is_numeric( $date ) )
        {
            $datetime = new DateTime( 'now', self::getTimeZone() );
            $datetime->setTimestamp( $date );
        } else
        {
            $datetime = new DateTime( $date, self::getTimeZone() );
        }

        if ( !empty( $modify ) )
        {
            $datetime->modify( $modify );
        }

        return $datetime->format( 'Y' );
    }

    public static function month ( $date = 'now', $modify = false )
    {
        if ( is_numeric( $date ) )
        {
            $datetime = new DateTime( 'now', self::getTimeZone() );
            $datetime->setTimestamp( $date );
        } else
        {
            $datetime = new DateTime( $date, self::getTimeZone() );
        }

        if ( !empty( $modify ) )
        {
            $datetime->modify( $modify );
        }

        return $datetime->format( 'm' );
    }

    public static function week ( $date = 'now', $modify = false )
    {
        if ( is_numeric( $date ) )
        {
            $datetime = new DateTime( 'now', self::getTimeZone() );
            $datetime->setTimestamp( $date );
        } else
        {
            $datetime = new DateTime( $date, self::getTimeZone() );
        }

        if ( !empty( $modify ) )
        {
            $datetime->modify( $modify );
        }

        return (int)$datetime->format( 'w' );
    }

    public static function day ( $date = 'now', $modify = false )
    {
        if ( is_numeric( $date ) )
        {
            $datetime = new DateTime( 'now', self::getTimeZone() );
            $datetime->setTimestamp( $date );
        } else
        {
            $datetime = new DateTime( $date, self::getTimeZone() );
        }

        if ( !empty( $modify ) )
        {
            $datetime->modify( $modify );
        }

        return $datetime->format( 'd' );
    }

    public static function lastDateOfMonth ( $year, $month )
    {
        $datetime = new DateTime( "{$year}-{$month}-01", self::getTimeZone() );

        return $datetime->format( 'Y-m-t' );
    }

    /** @return array{value: int, unit: string} */
    public static function convertFromSecondsToUnit ( int $seconds ): array
    {
        if ( $seconds >= 86400 )
        {
            $unit  = 'day';
            $value = intval( $seconds / 86400 );
        }
        else if ( $seconds >= 3600 )
        {
            $unit  = 'hour';
            $value = intval( $seconds / 3600 );
        }
        else if ( $seconds >= 60 )
        {
            $unit  = 'minute';
            $value = intval( $seconds / 60 );
        }
		else
		{
			$unit  = 'second';
			$value = $seconds;
		}

        return [
            'value' => $value,
            'unit'  => $unit,
        ];
    }

    public static function convertFromUnitToSeconds ( int $value, string $unit ): int
    {
        switch ( $unit )
        {
            case 'day':
                $seconds = $value * 86400;
                break;
            case 'hour':
                $seconds = $value * 3600;
                break;
            case 'minute':
                $seconds = $value * 60;
                break;
            default:
                $seconds = $value;
        }

        return $seconds;
    }

	/**
	 * Check the time if is between two times
	 *
	 * @param $time  int time to check
	 * @param $start int start time to compare
	 * @param $end   int end time to compare
	 *
	 * @return bool if given time is between two dates, then true, otherwise false
	 */
	public static function isBetweenDates ( int $time, int $start, int $end ): bool
	{
		if ( $start < $end )
			return $time >= $start && $time <= $end;

		return $time <= $end || $time >= $start;
	}

}
