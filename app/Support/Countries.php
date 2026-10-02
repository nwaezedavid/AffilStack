<?php

namespace App\Support;

use DateTimeZone;
use Illuminate\Support\Str;

/**
 * The full ISO 3166-1 alpha-2 country list (names in English), each with
 * its international dialling code and "main" IANA timezone — used by the
 * Profile page (country, phone country code, timezone preselect) and by
 * the lifecycle emails, which fall back to a user's country's main
 * timezone when they haven't picked one. Static on purpose: no runtime
 * dependency on the intl extension or a third-party package.
 */
class Countries
{
    /**
     * @var array<string, array{0: string, 1: string|null, 2: string|null}>
     */
    protected const COUNTRIES = [
        'AF' => ['Afghanistan', '+93', 'Asia/Kabul'],
        'AL' => ['Albania', '+355', 'Europe/Tirane'],
        'DZ' => ['Algeria', '+213', 'Africa/Algiers'],
        'AS' => ['American Samoa', '+1', 'Pacific/Pago_Pago'],
        'AD' => ['Andorra', '+376', 'Europe/Andorra'],
        'AO' => ['Angola', '+244', 'Africa/Luanda'],
        'AI' => ['Anguilla', '+1', 'America/Anguilla'],
        'AQ' => ['Antarctica', null, 'UTC'],
        'AG' => ['Antigua & Barbuda', '+1', 'America/Antigua'],
        'AR' => ['Argentina', '+54', 'America/Argentina/Buenos_Aires'],
        'AM' => ['Armenia', '+374', 'Asia/Yerevan'],
        'AW' => ['Aruba', '+297', 'America/Aruba'],
        'AU' => ['Australia', '+61', 'Australia/Sydney'],
        'AT' => ['Austria', '+43', 'Europe/Vienna'],
        'AZ' => ['Azerbaijan', '+994', 'Asia/Baku'],
        'BS' => ['Bahamas', '+1', 'America/Nassau'],
        'BH' => ['Bahrain', '+973', 'Asia/Bahrain'],
        'BD' => ['Bangladesh', '+880', 'Asia/Dhaka'],
        'BB' => ['Barbados', '+1', 'America/Barbados'],
        'BY' => ['Belarus', '+375', 'Europe/Minsk'],
        'BE' => ['Belgium', '+32', 'Europe/Brussels'],
        'BZ' => ['Belize', '+501', 'America/Belize'],
        'BJ' => ['Benin', '+229', 'Africa/Porto-Novo'],
        'BM' => ['Bermuda', '+1', 'Atlantic/Bermuda'],
        'BT' => ['Bhutan', '+975', 'Asia/Thimphu'],
        'BO' => ['Bolivia', '+591', 'America/La_Paz'],
        'BA' => ['Bosnia & Herzegovina', '+387', 'Europe/Sarajevo'],
        'BW' => ['Botswana', '+267', 'Africa/Gaborone'],
        'BV' => ['Bouvet Island', '+47', 'Europe/Oslo'],
        'BR' => ['Brazil', '+55', 'America/Sao_Paulo'],
        'IO' => ['British Indian Ocean Territory', '+246', 'Indian/Chagos'],
        'VG' => ['British Virgin Islands', '+1', 'America/Tortola'],
        'BN' => ['Brunei', '+673', 'Asia/Brunei'],
        'BG' => ['Bulgaria', '+359', 'Europe/Sofia'],
        'BF' => ['Burkina Faso', '+226', 'Africa/Ouagadougou'],
        'BI' => ['Burundi', '+257', 'Africa/Bujumbura'],
        'KH' => ['Cambodia', '+855', 'Asia/Phnom_Penh'],
        'CM' => ['Cameroon', '+237', 'Africa/Douala'],
        'CA' => ['Canada', '+1', 'America/Toronto'],
        'CV' => ['Cape Verde', '+238', 'Atlantic/Cape_Verde'],
        'BQ' => ['Caribbean Netherlands', '+599', 'America/Kralendijk'],
        'KY' => ['Cayman Islands', '+1', 'America/Cayman'],
        'CF' => ['Central African Republic', '+236', 'Africa/Bangui'],
        'TD' => ['Chad', '+235', 'Africa/Ndjamena'],
        'CL' => ['Chile', '+56', 'America/Santiago'],
        'CN' => ['China', '+86', 'Asia/Shanghai'],
        'CX' => ['Christmas Island', '+61', 'Indian/Christmas'],
        'CC' => ['Cocos (Keeling) Islands', '+61', 'Indian/Cocos'],
        'CO' => ['Colombia', '+57', 'America/Bogota'],
        'KM' => ['Comoros', '+269', 'Indian/Comoro'],
        'CG' => ['Congo - Brazzaville', '+242', 'Africa/Brazzaville'],
        'CD' => ['Congo - Kinshasa', '+243', 'Africa/Kinshasa'],
        'CK' => ['Cook Islands', '+682', 'Pacific/Rarotonga'],
        'CR' => ['Costa Rica', '+506', 'America/Costa_Rica'],
        'HR' => ['Croatia', '+385', 'Europe/Zagreb'],
        'CU' => ['Cuba', '+53', 'America/Havana'],
        'CW' => ['Curaçao', '+599', 'America/Curacao'],
        'CY' => ['Cyprus', '+357', 'Asia/Nicosia'],
        'CZ' => ['Czechia', '+420', 'Europe/Prague'],
        'CI' => ['Côte d’Ivoire', '+225', 'Africa/Abidjan'],
        'DK' => ['Denmark', '+45', 'Europe/Copenhagen'],
        'DJ' => ['Djibouti', '+253', 'Africa/Djibouti'],
        'DM' => ['Dominica', '+1', 'America/Dominica'],
        'DO' => ['Dominican Republic', '+1', 'America/Santo_Domingo'],
        'EC' => ['Ecuador', '+593', 'America/Guayaquil'],
        'EG' => ['Egypt', '+20', 'Africa/Cairo'],
        'SV' => ['El Salvador', '+503', 'America/El_Salvador'],
        'GQ' => ['Equatorial Guinea', '+240', 'Africa/Malabo'],
        'ER' => ['Eritrea', '+291', 'Africa/Asmara'],
        'EE' => ['Estonia', '+372', 'Europe/Tallinn'],
        'SZ' => ['Eswatini', '+268', 'Africa/Mbabane'],
        'ET' => ['Ethiopia', '+251', 'Africa/Addis_Ababa'],
        'FK' => ['Falkland Islands', '+500', 'Atlantic/Stanley'],
        'FO' => ['Faroe Islands', '+298', 'Atlantic/Faroe'],
        'FJ' => ['Fiji', '+679', 'Pacific/Fiji'],
        'FI' => ['Finland', '+358', 'Europe/Helsinki'],
        'FR' => ['France', '+33', 'Europe/Paris'],
        'GF' => ['French Guiana', '+594', 'America/Cayenne'],
        'PF' => ['French Polynesia', '+689', 'Pacific/Tahiti'],
        'TF' => ['French Southern Territories', '+262', 'Indian/Kerguelen'],
        'GA' => ['Gabon', '+241', 'Africa/Libreville'],
        'GM' => ['Gambia', '+220', 'Africa/Banjul'],
        'GE' => ['Georgia', '+995', 'Asia/Tbilisi'],
        'DE' => ['Germany', '+49', 'Europe/Berlin'],
        'GH' => ['Ghana', '+233', 'Africa/Accra'],
        'GI' => ['Gibraltar', '+350', 'Europe/Gibraltar'],
        'GR' => ['Greece', '+30', 'Europe/Athens'],
        'GL' => ['Greenland', '+299', 'America/Nuuk'],
        'GD' => ['Grenada', '+1', 'America/Grenada'],
        'GP' => ['Guadeloupe', '+590', 'America/Guadeloupe'],
        'GU' => ['Guam', '+1', 'Pacific/Guam'],
        'GT' => ['Guatemala', '+502', 'America/Guatemala'],
        'GG' => ['Guernsey', '+44', 'Europe/Guernsey'],
        'GN' => ['Guinea', '+224', 'Africa/Conakry'],
        'GW' => ['Guinea-Bissau', '+245', 'Africa/Bissau'],
        'GY' => ['Guyana', '+592', 'America/Guyana'],
        'HT' => ['Haiti', '+509', 'America/Port-au-Prince'],
        'HM' => ['Heard & McDonald Islands', null, 'UTC'],
        'HN' => ['Honduras', '+504', 'America/Tegucigalpa'],
        'HK' => ['Hong Kong', '+852', 'Asia/Hong_Kong'],
        'HU' => ['Hungary', '+36', 'Europe/Budapest'],
        'IS' => ['Iceland', '+354', 'Atlantic/Reykjavik'],
        'IN' => ['India', '+91', 'Asia/Kolkata'],
        'ID' => ['Indonesia', '+62', 'Asia/Jakarta'],
        'IR' => ['Iran', '+98', 'Asia/Tehran'],
        'IQ' => ['Iraq', '+964', 'Asia/Baghdad'],
        'IE' => ['Ireland', '+353', 'Europe/Dublin'],
        'IM' => ['Isle of Man', '+44', 'Europe/Isle_of_Man'],
        'IL' => ['Israel', '+972', 'Asia/Jerusalem'],
        'IT' => ['Italy', '+39', 'Europe/Rome'],
        'JM' => ['Jamaica', '+1', 'America/Jamaica'],
        'JP' => ['Japan', '+81', 'Asia/Tokyo'],
        'JE' => ['Jersey', '+44', 'Europe/Jersey'],
        'JO' => ['Jordan', '+962', 'Asia/Amman'],
        'KZ' => ['Kazakhstan', '+7', 'Asia/Almaty'],
        'KE' => ['Kenya', '+254', 'Africa/Nairobi'],
        'KI' => ['Kiribati', '+686', 'Pacific/Tarawa'],
        'XK' => ['Kosovo', '+383', 'Europe/Belgrade'],
        'KW' => ['Kuwait', '+965', 'Asia/Kuwait'],
        'KG' => ['Kyrgyzstan', '+996', 'Asia/Bishkek'],
        'LA' => ['Laos', '+856', 'Asia/Vientiane'],
        'LV' => ['Latvia', '+371', 'Europe/Riga'],
        'LB' => ['Lebanon', '+961', 'Asia/Beirut'],
        'LS' => ['Lesotho', '+266', 'Africa/Maseru'],
        'LR' => ['Liberia', '+231', 'Africa/Monrovia'],
        'LY' => ['Libya', '+218', 'Africa/Tripoli'],
        'LI' => ['Liechtenstein', '+423', 'Europe/Vaduz'],
        'LT' => ['Lithuania', '+370', 'Europe/Vilnius'],
        'LU' => ['Luxembourg', '+352', 'Europe/Luxembourg'],
        'MO' => ['Macao', '+853', 'Asia/Macau'],
        'MG' => ['Madagascar', '+261', 'Indian/Antananarivo'],
        'MW' => ['Malawi', '+265', 'Africa/Blantyre'],
        'MY' => ['Malaysia', '+60', 'Asia/Kuala_Lumpur'],
        'MV' => ['Maldives', '+960', 'Indian/Maldives'],
        'ML' => ['Mali', '+223', 'Africa/Bamako'],
        'MT' => ['Malta', '+356', 'Europe/Malta'],
        'MH' => ['Marshall Islands', '+692', 'Pacific/Majuro'],
        'MQ' => ['Martinique', '+596', 'America/Martinique'],
        'MR' => ['Mauritania', '+222', 'Africa/Nouakchott'],
        'MU' => ['Mauritius', '+230', 'Indian/Mauritius'],
        'YT' => ['Mayotte', '+262', 'Indian/Mayotte'],
        'MX' => ['Mexico', '+52', 'America/Mexico_City'],
        'FM' => ['Micronesia', '+691', 'Pacific/Pohnpei'],
        'MD' => ['Moldova', '+373', 'Europe/Chisinau'],
        'MC' => ['Monaco', '+377', 'Europe/Monaco'],
        'MN' => ['Mongolia', '+976', 'Asia/Ulaanbaatar'],
        'ME' => ['Montenegro', '+382', 'Europe/Podgorica'],
        'MS' => ['Montserrat', '+1', 'America/Montserrat'],
        'MA' => ['Morocco', '+212', 'Africa/Casablanca'],
        'MZ' => ['Mozambique', '+258', 'Africa/Maputo'],
        'MM' => ['Myanmar (Burma)', '+95', 'Asia/Yangon'],
        'NA' => ['Namibia', '+264', 'Africa/Windhoek'],
        'NR' => ['Nauru', '+674', 'Pacific/Nauru'],
        'NP' => ['Nepal', '+977', 'Asia/Kathmandu'],
        'NL' => ['Netherlands', '+31', 'Europe/Amsterdam'],
        'NC' => ['New Caledonia', '+687', 'Pacific/Noumea'],
        'NZ' => ['New Zealand', '+64', 'Pacific/Auckland'],
        'NI' => ['Nicaragua', '+505', 'America/Managua'],
        'NE' => ['Niger', '+227', 'Africa/Niamey'],
        'NG' => ['Nigeria', '+234', 'Africa/Lagos'],
        'NU' => ['Niue', '+683', 'Pacific/Niue'],
        'NF' => ['Norfolk Island', '+672', 'Pacific/Norfolk'],
        'KP' => ['North Korea', '+850', 'Asia/Pyongyang'],
        'MK' => ['North Macedonia', '+389', 'Europe/Skopje'],
        'MP' => ['Northern Mariana Islands', '+1', 'Pacific/Saipan'],
        'NO' => ['Norway', '+47', 'Europe/Oslo'],
        'OM' => ['Oman', '+968', 'Asia/Muscat'],
        'PK' => ['Pakistan', '+92', 'Asia/Karachi'],
        'PW' => ['Palau', '+680', 'Pacific/Palau'],
        'PS' => ['Palestinian Territories', '+970', 'Asia/Gaza'],
        'PA' => ['Panama', '+507', 'America/Panama'],
        'PG' => ['Papua New Guinea', '+675', 'Pacific/Port_Moresby'],
        'PY' => ['Paraguay', '+595', 'America/Asuncion'],
        'PE' => ['Peru', '+51', 'America/Lima'],
        'PH' => ['Philippines', '+63', 'Asia/Manila'],
        'PN' => ['Pitcairn Islands', '+64', 'Pacific/Pitcairn'],
        'PL' => ['Poland', '+48', 'Europe/Warsaw'],
        'PT' => ['Portugal', '+351', 'Europe/Lisbon'],
        'PR' => ['Puerto Rico', '+1', 'America/Puerto_Rico'],
        'QA' => ['Qatar', '+974', 'Asia/Qatar'],
        'RO' => ['Romania', '+40', 'Europe/Bucharest'],
        'RU' => ['Russia', '+7', 'Europe/Moscow'],
        'RW' => ['Rwanda', '+250', 'Africa/Kigali'],
        'RE' => ['Réunion', '+262', 'Indian/Reunion'],
        'WS' => ['Samoa', '+685', 'Pacific/Apia'],
        'SM' => ['San Marino', '+378', 'Europe/San_Marino'],
        'SA' => ['Saudi Arabia', '+966', 'Asia/Riyadh'],
        'SN' => ['Senegal', '+221', 'Africa/Dakar'],
        'RS' => ['Serbia', '+381', 'Europe/Belgrade'],
        'SC' => ['Seychelles', '+248', 'Indian/Mahe'],
        'SL' => ['Sierra Leone', '+232', 'Africa/Freetown'],
        'SG' => ['Singapore', '+65', 'Asia/Singapore'],
        'SX' => ['Sint Maarten', '+1', 'America/Lower_Princes'],
        'SK' => ['Slovakia', '+421', 'Europe/Bratislava'],
        'SI' => ['Slovenia', '+386', 'Europe/Ljubljana'],
        'SB' => ['Solomon Islands', '+677', 'Pacific/Guadalcanal'],
        'SO' => ['Somalia', '+252', 'Africa/Mogadishu'],
        'ZA' => ['South Africa', '+27', 'Africa/Johannesburg'],
        'GS' => ['South Georgia & South Sandwich Islands', '+500', 'Atlantic/South_Georgia'],
        'KR' => ['South Korea', '+82', 'Asia/Seoul'],
        'SS' => ['South Sudan', '+211', 'Africa/Juba'],
        'ES' => ['Spain', '+34', 'Europe/Madrid'],
        'LK' => ['Sri Lanka', '+94', 'Asia/Colombo'],
        'BL' => ['St. Barthélemy', '+590', 'America/St_Barthelemy'],
        'SH' => ['St. Helena', '+290', 'Atlantic/St_Helena'],
        'KN' => ['St. Kitts & Nevis', '+1', 'America/St_Kitts'],
        'LC' => ['St. Lucia', '+1', 'America/St_Lucia'],
        'MF' => ['St. Martin', '+590', 'America/Marigot'],
        'PM' => ['St. Pierre & Miquelon', '+508', 'America/Miquelon'],
        'VC' => ['St. Vincent & Grenadines', '+1', 'America/St_Vincent'],
        'SD' => ['Sudan', '+249', 'Africa/Khartoum'],
        'SR' => ['Suriname', '+597', 'America/Paramaribo'],
        'SJ' => ['Svalbard & Jan Mayen', '+4779', 'Arctic/Longyearbyen'],
        'SE' => ['Sweden', '+46', 'Europe/Stockholm'],
        'CH' => ['Switzerland', '+41', 'Europe/Zurich'],
        'SY' => ['Syria', '+963', 'Asia/Damascus'],
        'ST' => ['São Tomé & Príncipe', '+239', 'Africa/Sao_Tome'],
        'TW' => ['Taiwan', '+886', 'Asia/Taipei'],
        'TJ' => ['Tajikistan', '+992', 'Asia/Dushanbe'],
        'TZ' => ['Tanzania', '+255', 'Africa/Dar_es_Salaam'],
        'TH' => ['Thailand', '+66', 'Asia/Bangkok'],
        'TL' => ['Timor-Leste', '+670', 'Asia/Dili'],
        'TG' => ['Togo', '+228', 'Africa/Lome'],
        'TK' => ['Tokelau', '+690', 'Pacific/Fakaofo'],
        'TO' => ['Tonga', '+676', 'Pacific/Tongatapu'],
        'TT' => ['Trinidad & Tobago', '+1', 'America/Port_of_Spain'],
        'TN' => ['Tunisia', '+216', 'Africa/Tunis'],
        'TM' => ['Turkmenistan', '+993', 'Asia/Ashgabat'],
        'TC' => ['Turks & Caicos Islands', '+1', 'America/Grand_Turk'],
        'TV' => ['Tuvalu', '+688', 'Pacific/Funafuti'],
        'TR' => ['Türkiye', '+90', 'Europe/Istanbul'],
        'UM' => ['U.S. Outlying Islands', '+268', 'Pacific/Wake'],
        'VI' => ['U.S. Virgin Islands', '+1', 'America/St_Thomas'],
        'UG' => ['Uganda', '+256', 'Africa/Kampala'],
        'UA' => ['Ukraine', '+380', 'Europe/Kyiv'],
        'AE' => ['United Arab Emirates', '+971', 'Asia/Dubai'],
        'GB' => ['United Kingdom', '+44', 'Europe/London'],
        'US' => ['United States', '+1', 'America/New_York'],
        'UY' => ['Uruguay', '+598', 'America/Montevideo'],
        'UZ' => ['Uzbekistan', '+998', 'Asia/Tashkent'],
        'VU' => ['Vanuatu', '+678', 'Pacific/Efate'],
        'VA' => ['Vatican City', '+39', 'Europe/Vatican'],
        'VE' => ['Venezuela', '+58', 'America/Caracas'],
        'VN' => ['Vietnam', '+84', 'Asia/Ho_Chi_Minh'],
        'WF' => ['Wallis & Futuna', '+681', 'Pacific/Wallis'],
        'EH' => ['Western Sahara', '+2125288', 'Africa/El_Aaiun'],
        'YE' => ['Yemen', '+967', 'Asia/Aden'],
        'ZM' => ['Zambia', '+260', 'Africa/Lusaka'],
        'ZW' => ['Zimbabwe', '+263', 'Africa/Harare'],
        'AX' => ['Åland Islands', '+35818', 'Europe/Mariehamn'],
    ];

    /**
     * @return array<string, string> ISO code => English name, alphabetical
     */
    public static function options(): array
    {
        return array_map(fn (array $row) => $row[0], self::COUNTRIES);
    }

    public static function exists(?string $code): bool
    {
        return $code !== null && isset(self::COUNTRIES[strtoupper($code)]);
    }

    public static function name(?string $code): ?string
    {
        return self::COUNTRIES[strtoupper((string) $code)][0] ?? null;
    }

    public static function dialCode(?string $code): ?string
    {
        return self::COUNTRIES[strtoupper((string) $code)][1] ?? null;
    }

    /**
     * @return array<string, string> ISO code => "Nigeria (+234)", only countries with a dialling code
     */
    public static function dialCodeOptions(): array
    {
        $options = [];

        foreach (self::COUNTRIES as $code => [$name, $dial]) {
            if ($dial !== null) {
                $options[$code] = "{$name} ({$dial})";
            }
        }

        return $options;
    }

    /**
     * The timezone most of a country's population lives in — the
     * fallback when a user hasn't chosen their own.
     */
    public static function mainTimezone(?string $code): ?string
    {
        return self::COUNTRIES[strtoupper((string) $code)][2] ?? null;
    }

    /**
     * Normalises whatever is stored in users.country (an ISO code, or a
     * free-text name typed before the Profile page used a dropdown) to an
     * ISO code, or null when it can't be recognised.
     */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (strlen($value) === 2 && self::exists($value)) {
            return strtoupper($value);
        }

        $needle = Str::lower($value);
        $aliases = ['usa' => 'US', 'united states of america' => 'US', 'uk' => 'GB', 'great britain' => 'GB', 'england' => 'GB', 'uae' => 'AE'];

        if (isset($aliases[$needle])) {
            return $aliases[$needle];
        }

        foreach (self::COUNTRIES as $code => [$name]) {
            if (Str::lower($name) === $needle) {
                return $code;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> every valid IANA timezone, keyed by identifier, labelled "Africa/Lagos (UTC+01:00)"
     */
    public static function timezoneOptions(): array
    {
        $now = now();
        $options = [];

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            $options[$identifier] = str_replace('_', ' ', $identifier).' (UTC'.$now->copy()->setTimezone($identifier)->format('P').')';
        }

        return $options;
    }

    public static function isValidTimezone(?string $timezone): bool
    {
        return $timezone !== null && in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }
}
