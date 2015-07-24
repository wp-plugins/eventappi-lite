<?php namespace EventAppi\Helpers;

/**
 * Class Format
 *
 * @package EventAppi\Helpers
 */
class Format
{

    /**
     * @param $dateFormatString
     *
     * @return mixed
     */
    public static function getJSCompatibleDateFormatString($dateFormatString)
    {
        return str_replace('S', '', $dateFormatString);
    }

    public static function getJSDateFormatString($dateFormatString)
    {
        // Native PHP
        $pattern = array(
            //day
            'd',  // day of the month
            'j',  // 3 letter name of the day
            'l',  // full name of the day
            'z',  // day of the year
            'S',  // st, nd, rd or th - as in 1st, 2nd, 3rd or 10th - not supported natively in JavaScript

            //month
            'F',  // Month name full
            'M',  // Month name short
            'n',  // numeric month no leading zeros
            'm',  // numeric month leading zeros

            //year
            'Y',  // full numeric year
            'y'   // numeric year: 2 digit
        );

        // JavaScript equivalents
        $replace = array(
            // Day
            'dd', // day of the month
            'd',  // 3 letter name of the day
            'DD', // full name of the day
            'o',  // day of the year
            '',   // st, nd, rd or th - as in 1st, 2nd, 3rd or 10th - not supported natively in JavaScript

            // Month
            'MM', // Month name full
            'M',  // Month name short
            'm',  // numeric month no leading zeros
            'mm', // numeric month leading zeros

            // Year
            'yy', // full numeric year
            'y'   // numeric year: 2 digit
        );

        foreach ($pattern as &$p) {
            $p = '/' . $p . '/';
        }

        return preg_replace($pattern, $replace, $dateFormatString);
    }
}
