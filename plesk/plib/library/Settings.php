<?php
/**
 * Extension settings, stored with pm_Settings (secrets encrypted with pm_Settings::setEncrypted).
 */
class Modules_Paknic_Settings
{
    /** Registrant fields the API requires; all must be set before domains are registered. */
    const REQUIRED_REGISTRANT = ['first_name', 'last_name', 'address1', 'city', 'state', 'postal_code', 'country_code', 'phone'];

    const REGISTRANT_FIELDS = ['first_name', 'last_name', 'organization', 'address1', 'address2', 'city', 'state', 'postal_code', 'country_code', 'email', 'phone'];

    public static function get($name, $default = '')
    {
        return pm_Settings::get($name, $default);
    }

    public static function apiSecret()
    {
        return (string)pm_Settings::getDecrypted('api_secret');
    }

    public static function flag($name)
    {
        return pm_Settings::get($name, '0') === '1';
    }

    public static function registrant()
    {
        $contact = [];
        foreach (self::REGISTRANT_FIELDS as $field) {
            $contact[$field] = (string)pm_Settings::get('registrant_' . $field, '');
        }
        return $contact;
    }

    public static function nameservers()
    {
        $list = preg_split('/[\s,]+/', (string)pm_Settings::get('nameservers', ''), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_map('strtolower', $list));
    }

    public static function onlyTlds()
    {
        $list = preg_split('/[\s,]+/', (string)pm_Settings::get('only_tlds', ''), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_map(function ($tld) { return '.' . ltrim(strtolower($tld), '.'); }, $list));
    }
}
