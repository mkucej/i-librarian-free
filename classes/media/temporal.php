<?php

namespace Librarian\Media;

use DateTime;
use DateTimeZone;
use Exception;
use IntlDateFormatter;
use Librarian\AppSettings;

final class Temporal {

    /**
     * @var AppSettings
     */
    private $app_settings;

    /**
     * @var Language
     */
    private $lang;

    public function __construct(AppSettings $app_settings, Language $lang) {

        $this->app_settings = $app_settings;
        $this->lang         = $lang;
    }

    /**
     * Locale-aware datetime formatting and conversion to user time zone.
     *
     * @param integer $time Can be timestamp, or ISO date.
     * @return string
     * @throws Exception
     */
    public function toUserTime($time = null): string {

        if (isset($time) === false) {

            $time = gmdate('c');
        }

        $datetime = is_numeric($time) === true ? gmdate('c', $time) : $time;
        $date_obj = new DateTime($datetime, new DateTimeZone('UTC'));

        $timezone = $this->app_settings->getUser('timezone');
        $tz_obj = new DateTimeZone($timezone);

        if (extension_loaded('intl') === false) {

            $date_obj->setTimezone($tz_obj);
            return $date_obj->format('M j, Y g:i A');
        }

        $date = new IntlDateFormatter(
            $this->lang->getLanguage(),
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::SHORT,
            $tz_obj
        );

        return $date->format($date_obj);
    }

    /**
     * Locale-aware date (no time) formatting and conversion to user time zone.
     *
     * @param integer $time Can be timestamp, or ISO date.
     * @return string
     * @throws Exception
     */
    public function toUserDate($time = null): string {

        if (isset($time) === false) {

            $time = gmdate('c');
        }

        $datetime = is_numeric($time) === true ? gmdate('c', $time) : $time;
        $date_obj = new DateTime($datetime, new DateTimeZone('UTC'));

        $timezone = $this->app_settings->getUser('timezone');
        $tz_obj = new DateTimeZone($timezone);

        if (extension_loaded('intl') === false) {

            $date_obj->setTimezone($tz_obj);
            return $date_obj->format('M j, Y');
        }

        $date = new IntlDateFormatter(
            $this->lang->getLanguage(),
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::NONE,
            $tz_obj
        );

        return $date->format($date_obj);
    }

    /**
     * Locale-aware date formatting. No timezone correction. Used mostly for publication dates.
     *
     * @param string $time Can be timestamp, or ISO date.
     * @return string
     * @throws Exception
     */
    public function toLocalDate($time): string {

        if (isset($time) === false) {

            $time = gmdate('c');
        }

        $datetime = is_numeric($time) === true ? gmdate('c', $time) : $time;
        $date_obj = new DateTime($datetime, new DateTimeZone('UTC'));

        if (extension_loaded('intl') === false) {

            return $date_obj->format('M j, Y');
        }

        $fmt = new IntlDateFormatter(
            $this->lang->getLanguage(),
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::NONE,
            new DateTimeZone('UTC')
        );

        return $fmt->format($date_obj);
    }

    /**
     * Get a difference in days of a datetime from today.
     *
     * @param int|string $time Can be timestamp, or ISO date.
     * @return string
     * @throws Exception
     */
    public function diff($time) {

        // Get Timezone objects for later use.
        $timezone = $this->app_settings->getUser('timezone');
        $tz_user = new DateTimeZone($timezone);
        $tz_utc = new DateTimeZone('UTC');

        // Incoming date time is in UTC.
        $datetime1 = new DateTime($time, $tz_utc);
        $datetime2 = new DateTime('now', $tz_utc);

        // Convert datetime to the user timezone.
        $datetime1->setTimezone($tz_user);
        $datetime2->setTimezone($tz_user);

        // Create date objects in user timezone. Ignore time part.
        $date1 = new DateTime($datetime1->format('Y-m-d'), $tz_user);
        $date2 = new DateTime($datetime2->format('Y-m-d'), $tz_user);

        // Get diff in days.
        $interval = $date1->diff($date2, true);
        $diff = $interval->format('%a');

        if ($diff === '0') {

            return 'today';

        } elseif ($diff === '1') {

            return 'yesterday';

        } else {

            return $diff . ' days ago';
        }
    }
}
