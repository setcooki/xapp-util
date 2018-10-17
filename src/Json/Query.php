<?php

defined('XAPP') || require_once(dirname(__FILE__) . '/../../../core/core.php');

xapp_import('xapp.Util.Json.Exception');

/**
 * Util json query class
 *
 * @package Util
 * @subpackage Util_Json
 * @class Xapp_Util_Json_Query
 * @error 168
 * @author Frank Mueller <set@cooki.me>
 */
class Xapp_Util_Json_Query extends Xapp_Util_Std_Query
{
    /**
     * class constructor checks if passed object is a json string and decodes then first, than
     * calls parent constructor
     *
     * @error 16801
     * @param array|object|string $object expects the json object or json string
     * @param null|mixed $options expects optional options
     */
    public function __construct($object, $options = null)
    {
        if(Xapp_Util_Json::isJson($object))
        {
            $object = Xapp_Util_Json::decode($object);
        }
        parent::__construct($object, $options);
    }


    /**
     * create instance from json file, json string or json object with this static method
     *
     * @error 16802
     * @param string $mixed expects any of the above
     * @param null|mixed $options expects optional options
     * @return Xapp_Util_Json_Query
     * @throws Xapp_Util_Json_Exception
     */
    public static function createFrom($mixed, $options = null)
    {
        if(is_file($mixed))
        {
            return new self(file_get_contents($mixed), $options);
        }else if(is_string($mixed) || is_object($mixed)){
            return new self($mixed, $options);
        }else{
            throw new Xapp_Util_Json_Exception(__("first argument is not a valid json string or object"), 1680201);
        }
    }
}