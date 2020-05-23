<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ValidatePhoneController extends Controller
{
    public static function channel(string $msisdn) : string
    {
        $clean_msisdn = self::clean($msisdn);

        if (in_array(substr($clean_msisdn, 0, 6), self::SAFARICOM_PREFIXES)) {
            return strval('SAFARICOM');
        }

        if (in_array(substr($clean_msisdn, 0, 6), self::AIRTEL_PREFIXES)) {
            return strval('AIRTEL');
        }

        if (in_array(substr($clean_msisdn, 0, 6), self::TELKOM_PREFIXES)) {
            return strval('TELKOM');
        }

        if (in_array(substr($clean_msisdn, 0, 6), self::EQUITEL_PREFIXES)) {
            return strval('EQUITEL');
        }
        if (in_array(substr($clean_msisdn, 0, 6), self::FAIBA_PREFIXES)) {
            return strval('FAIBA');
        }

        return strval('UNDEFINED');
    }

    public static function clean(string $msisdn) : int
    {
        if (substr($msisdn, 0, 1) == '0') {
            $msisdn = '254' . ltrim($msisdn, '0');
        }
        if (substr($msisdn, 0, 1) == '7') {
            $msisdn = '254' . $msisdn;
        }
        return self::checkMSISDNLength(intval($msisdn));
    }

    private static function checkMSISDNLength(string $msisdn) : int
    {
        return (int)(strlen($msisdn) == 12 && is_numeric($msisdn)) ? $msisdn : -1;
    }

    const SAFARICOM_PREFIXES = [
        "25411","254701","254702","254703","254704","254705","254706","254707",
        "254708","254709","254710","254711","254712","254713","254714","254715",
        "254716","254717","254718","254719","254720", "254721", "254722",
        "254723", "254724", "254725","254726","254727","254728","254729",
        "254740","254741","254742","254743","254745","254746","254748",
        "254757","254758","254759",
        "254768","254769",
        "254790", "254791","254792","254793","254794","254795","254796","254797","254798","254799"];

    const AIRTEL_PREFIXES = ["25410",
        "254730","254731","254732","254733","254734","254735","254736", "254737","254738","254739",
        "254750","254751","254752","254753","254754","254755","254756",
        "254762",
        "254780","254781","254782","254783","254784","254785","254786","254787","254788","254789"];

    const TELKOM_PREFIXES = ["254770","254771","254772","254773","254774","254775","254776","254777","254778","254779"];

    const EQUITEL_PREFIXES = ["254763","254764","254765"];

    const FAIBA_PREFIXES = ["254747"];

}
