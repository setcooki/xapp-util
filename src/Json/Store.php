<?php

defined('XAPP') || require_once(dirname(__FILE__) . '/../../../core/core.php');

xapp_import('xapp.Util.Json.Exception');
xapp_import('xapp.Util.Json');

/**
 * Util json store class
 *
 * @package Util
 * @subpackage Util_Json
 * @class Xapp_Util_Json_Store
 * @error 169
 * @author Frank Mueller <set@cooki.me>
 */
class Xapp_Util_Json_Store extends Xapp_Util_Std_Store
{
    /**
     * json implementation for decoding, see Xapp_Util_Std_Store::decode for more details
     *
     * @error 16902
     * @see Xapp_Util_Std_Store::decode
     * @param mixed $value expects the value to decode
     * @param null|string $algo expects optional algorithm
     * @return mixed
     * @throws Xapp_Util_Std_Exception
     */
    public static function decode($value, $algo = 'JSON')
    {
        return parent::decode($value, $algo);
    }


    /**
     * json implementation for encoding, see Xapp_Util_Std_Store::encode for more details
     *
     * @error 16903
     * @see Xapp_Util_Std_Store::encode
     * @param mixed $value expects value to encode
     * @param null|string $algo expects optional algorithm
     * @return string
     * @throws Xapp_Util_Std_Exception
     */
    public static function encode($value, $algo = 'JSON')
    {
        return parent::encode($value, $algo);
    }


    /**
     * dump/print stores json object to screen
     *
     * @error 16904
     * @return void
     */
    public function dump()
    {
        Xapp_Util_Json::dump($this->_object);
    }


    /**
     * json implementation for importing, see Xapp_Util_Std_Store::import for more details
     *
     * @error 169001
     * @see Xapp_Util_Std_Store import
     * @param mixed $mixed expects import source
     * @param null|mixed $key expects key/password when using decryption
     * @return mixed
     * @throws Xapp_Util_Std_Exception
     */
    public function import($mixed, $key = null)
    {
        if(Xapp_Util_Json::isJson($mixed))
        {
            $mixed = self::decode($mixed, 'JSON');
        }
        return parent::import($mixed, $key);
    }
}