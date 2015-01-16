<?php

defined('XAPP') || require_once(dirname(__FILE__) . '/../Core/core.php');

xapp_import('xapp.Util.Std.Exception');

/**
 * Util std query class
 *
 * @package Util
 * @subpackage Util_Std
 * @class Xapp_Util_Std_Query
 * @error 167
 * @author Frank Mueller <set@cooki.me>
 */
class Xapp_Util_Std_Query implements Iterator
{
    /**
     * defines whether a failed query will return the default return value as defined in DEFAULT_VALUE or throw a
     * standard xapp result exception
     *
     * @const THROW_EXCEPTION
     */
    const THROW_EXCEPTION               = 'UTIL_STD_QUERY_THROW_EXCEPTION';

    /**
     * defines the default return value if a query fails and option THROW_EXCEPTION is set to boolean false
     *
     * @const DEFAULT_VALUE
     */
    const DEFAULT_VALUE                 = 'UTIL_STD_DEFAULT_VALUE';

    /**
     * return query results as reference or clone result. if option is set to boolean true will return the result as
     * reference so manipulation result will also manipulate the object registered will class instance. if set to boolean
     * false will return a copy of result only
     *
     * @const RETURN_AS_REFERENCE
     */
    const RETURN_AS_REFERENCE           = 'UTIL_STD_QUERY_RETURN_AS_REFERENCE';

    /**
     * allow callbacks as filter. if this option is enabled by either setting a boolean true value or passing an array
     * with allowed callbacks (function string names or class::method string names). allowed callback must be passed as
     * full qualified name - wildcard callbacks are not supported. callbacks can be used as filter argument, e.g
     * "myclass::callback". if set to false will ignore callbacks
     *
     * @const ALLOW_CALLBACKS
     */
    const ALLOW_CALLBACKS               = 'UTIL_STD_QUERY_ALLOW_CALLBACKS';

    /**
     * defines the enclosure string for callback parameters that will be used to parse the parameter chain with php´s
     * native str_getcsv() function. defaults to single quote
     *
     * @const PARAMETER_ENCLOSURE
     */
    const PARAMETER_ENCLOSURE           = 'UTIL_STD_QUERY_PARAMETER_ENCLOSURE';

    /**
     * when using the filter options/commands to retrieve values at path with indices or filter ranges/commands that are
     * expected to return multiple values as array but fail due to out of range errors or simply not finding any values
     * in range the default behaviour is to either return a boolean false value of throw an exception (depending on class
     * option THROW_EXCEPTION). to override this behaviour change the FILTER_ALWAYS_RETURNS_ARRAY to boolean true which
     * will result in always returning an array even if nothing was found. also std object values will be converted to
     * array! by using this option iteration is supported without having to deal with exceptions and other return values
     *
     * @const FILTER_ALWAYS_RETURNS_ARRAY
     */
    const FILTER_ALWAYS_RETURNS_ARRAY   = 'UTIL_STD_QUERY_FILTER_ALWAYS_RETURNS_ARRAY';

    /**
     * in default mode (boolean false) the target objects keys are treated case insensitive. in case of insensitive query
     * mode a query like "key=1" and "KEY=1" results in the same regardless of the actual case of the object key. to use
     * case sensitive queries set class option to boolean true
     *
     * @const CASE_SENSITIVE_QUERIES
     */
    const CASE_SENSITIVE_QUERIES      = 'UTIL_STD_QUERY_CASE_SENSITIVE_QUERIES';


    /**
     * internal iteration position pointer
     *
     * @var int
     */
    private $position = 0;

    /**
     * contains the std object to be queried when class is used with instance
     *
     * @var null|object
     */
    protected $_object = null;

    /**
     * internal init flag to detect whether query result has been fetched thus enabling a new query on the original object
     * or still in query mode
     *
     * @var bool
     */
    private $_init = true;

    /**
     * contains the search result when querying non-static via query() method
     *
     * @var null|mixed
     */
    private $_result = null;

    /**
     * list of allowed query operators when using query commands on paths results. see assert) method for further details
     *
     * @var array
     */
    private static $_operators = array
    (
        '!%',
        '%',
        '>%',
        '<%',
        '!->',
        '->',
        '!<>',
        '<>',
        '*',
        '==',
        '!==',
        '!=',
        '>=',
        '<=',
        '=',
        '>',
        '<'
    );

    /**
     * options dictionary for this class containing all data type values
     *
     * @var array
     */
    public static $optionsDict = array
    (
        self::THROW_EXCEPTION               => XAPP_TYPE_BOOL,
        self::DEFAULT_VALUE                 => XAPP_TYPE_MIXED,
        self::RETURN_AS_REFERENCE           => XAPP_TYPE_BOOL,
        self::ALLOW_CALLBACKS               => array(XAPP_TYPE_BOOL, XAPP_TYPE_ARRAY),
        self::PARAMETER_ENCLOSURE           => XAPP_TYPE_STRING,
        self::FILTER_ALWAYS_RETURNS_ARRAY   => XAPP_TYPE_BOOL,
        self::CASE_SENSITIVE_QUERIES        => XAPP_TYPE_BOOL
    );

    /**
     * options mandatory map for this class contains all mandatory values
     *
     * @var array
     */
    public static $optionsRule = array
    (
        self::THROW_EXCEPTION               => 1,
        self::DEFAULT_VALUE                 => 0,
        self::RETURN_AS_REFERENCE           => 0,
        self::ALLOW_CALLBACKS               => 0,
        self::PARAMETER_ENCLOSURE           => 1,
        self::FILTER_ALWAYS_RETURNS_ARRAY   => 0,
        self::CASE_SENSITIVE_QUERIES        => 0
    );

    /**
     * options default value array containing all class option default values
     *
     * @var array
     */
    public static $options = array
    (
        self::THROW_EXCEPTION               => true,
        self::DEFAULT_VALUE                 => false,
        self::RETURN_AS_REFERENCE           => true,
        self::ALLOW_CALLBACKS               => false,
        self::PARAMETER_ENCLOSURE           => '\'',
        self::FILTER_ALWAYS_RETURNS_ARRAY   => false,
        self::CASE_SENSITIVE_QUERIES        => false
    );



    /**
     * class constructor sets the object to be queried with optional static class options
     *
     * @error 16701
     * @param object|array $object expects the object to be searched
     * @param null|mixed $options expects optional options as array or object
     * @throws Xapp_Util_Std_Exception
     */
    public function __construct(&$object, $options = null)
    {
        $class = get_class();

        xapp_init_options($options, $class);
        if(is_array($object) || (is_object($object) && get_class($object) === 'stdClass'))
        {
            if(is_array($object))
            {
                $object = xapp_array_to_object($object);
            }
            $this->_object =& $object;
        }else{
            throw new Xapp_Util_Std_Exception(_("value passed as first argument must be of type std object or array"), 1670101);
        }
    }


    /**
     * static method to create instance of class
     *
     * @error 16702
     * @param object $object expects the object to be searched
     * @param null|mixed $options expects optional options as array or object
     * @return Xapp_Util_Std_Query
     */
    public static function create($object, $options = null)
    {
        return new self($object, $options);
    }


    /**
     * set/get static options for all instances of query class. setting options will overwrite all previous set options.
     * if first argument is null will return all options
     *
     * @error 16703
     * @param null|mixed $options expects optional options as array or object
     * @return array|mixed|null
     */
    public static function options($options = null)
    {
        $class = get_class();

        if($options !== null)
        {
            return xapp_set_options($options, $class);
        }else{
            return xapp_get_options($class);
        }
    }


    /**
     * set/get object as query target/object. allowed are only stdClass objects and arrays. if first argument is not set
     * will return the object registered with instance either as reference of cloned without reference. see RETURN_AS_REFERENCE
     * class option for more
     *
     * @error 16719
     * @param null|mixed|array $object expects the optional new object
     * @return null|object
     * @throws Xapp_Util_Std_Exception
     */
    public function object(&$object = null)
    {
        $return = null;
        $class = get_class();

        if($object !== null)
        {
            $this->init();

            if(is_array($object) || (is_object($object) && get_class($object) === 'stdClass'))
            {
                if(is_array($object))
                {
                    $object = xapp_array_to_object($object);
                }
                $this->_object =& $object;
            }else{
                throw new Xapp_Util_Std_Exception(_("value passed as first argument must be of type std object or array"), 1671901);
            }
        }
        if(!xapp_get_option(self::RETURN_AS_REFERENCE, $class))
        {
            $object = clone $this->_object;
            return $object;
        }else{
            return $this->_object;
        }
    }


    /**
     * query object with path and optional query and filter arguments. the first parameter must always be set and default
     * to "/" which means from root. the second argument can by a optional string or array of query statements - see
     * Xapp_Util_Std_Query::find for syntax and usage. the third argument can be a optional string or array filter values
     * - see Xapp_Util_Std_Query::filter for more details. this method does not return the query result but reference to
     * $this so multiple queries can be stacked if needed. to get the actual query result use Xapp_Util_Std_Query::get or
     * any other method that will use the Xapp_Util_Std_Query::get method to return results. e.g.
     * <code>
     *  $query
     *      ->query('/path')
     *      ->query('/key')
     *      ->get();
     * </code>
     * see Xapp_Util_Std_Query::find or class description for further details
     *
     * @error 16704
     * @see Xapp_Util_Std_Query::find
     * @param string $path expects the query path
     * @param null|string|array $query expects the optional query command
     * @param null|int|string $filter expects optional filter command
     * @return $this
     */
    public function query($path, $query = null, $filter = null)
    {
        if($this->_init === true)
        {
            $this->_result =& self::find($this->_object, $path, $query, $filter);
            $this->_init = false;
        }else{
            $this->_result =& self::find($this->_result, $path, $query, $filter);
        }
        return $this;
    }


    /**
     * get the result with optional index if result is expected to be an array. use the get() method every time to exit
     * query mode and to get the query result. with out calling get() or init() a second query will be performed on the
     * result instead of the original object! the second argument expects either a integer index value or any other filter
     * compatible command like "1,2,3" or "1..5". see class description or Xapp_Util_Std_Query::filter function for more
     * details. NOTE: use the "&" reference operator to return full reference to value since single values will not have
     * a reference unless called like:
     * <code>
     *      $result =& $query
     *          ->query('/path')
     *          ->query('/key')
     *          ->get();
     * </code>
     * this seems to be a strange behaviour in PHP 5+ if you want to always return a reference regardless of the returned
     * value use the "&" reference operator and set class option RETURN_AS_REFERENCE to "true"
     *
     * @error 16705
     * @param null|int|string $index expects optional index or filter command
     * @return mixed
     */
    public function &get($index = null)
    {
        if($index !== null)
        {
            $this->_result =& $this->at($index);
        }
        return $this->init();
    }


    /**
     * exit query mode, init for new query and return result. preferably use Xapp_Util_Std_Query::get instead! without
     * calling init() or get() on your query no result will be returned and query mode will not be exited! use init()
     * over get() only for legacy reasons since init() is supposed to be used only internally to reset to use for new
     * query
     *
     * @error 16720
     * @return mixed
     */
    public function &init()
    {
        $result = null;
        $class = get_class();

        $this->_init = true;

        if(!xapp_get_option(self::RETURN_AS_REFERENCE, $class) && is_object($this->_result))
        {
            $result = clone $this->_result;
            return $result;
        }else{
            return $this->_result;
        }
    }


    /**
     * shortcut function to get first value out of result regardless of the type, object or array, of the result. throws
     * either exception if used in wrong context or nothing found at index or returns default return value
     *
     * @error 16706
     * @see Xapp_Util_Std_Query::get
     * @return mixed
     * @throws Xapp_Util_Std_Exception
     */
    public function &first()
    {
        if($this->_result !== null)
        {
            return $this->get(0);
        }else{
            throw new Xapp_Util_Std_Exception(xapp_sprintf(_("method: %s used in wrong context"), __FUNCTION__), 1670601);
        }
    }


    /**
     * shortcut function to get last value out of result regardless of the type, object or array, of the result. throws
     * either exception if used in wrong context or nothing found at index or returns default return value
     *
     * @error 16707
     * @see Xapp_Util_Std_Query::get
     * @return mixed
     * @throws Xapp_Util_Std_Exception
     */
    public function &last()
    {
        if($this->_result !== null)
        {
            return $this->get(-1);
        }else{
            throw new Xapp_Util_Std_Exception(xapp_sprintf(_("method: %s used in wrong context"), __FUNCTION__), 1670701);
        }
    }


    /**
     * shortcut function and legacy function for Xapp_Util_Std_Query::get used with second argument index. it is advised
     * to use the get() function instead! this function expects a filter index or filter command and returns query result.
     * throws either exception if used in wrong context or nothing found at index or returns default return value
     *
     * @error 16708
     * @see Xapp_Util_Std_Query::get
     * @param int|string $index expects the index as int or filter string command
     * @return mixed
     * @throws Xapp_Util_Std_Exception
     */
    public function &at($index)
    {
        if($this->_result !== null)
        {
            return self::filter($this->init(), $index);
        }else{
            throw new Xapp_Util_Std_Exception(xapp_sprintf(_("method: %s used in wrong context"), __FUNCTION__), 1670801);
        }
    }


    /**
     * implemented from iterator interface returns object or array at current index
     *
     * @error 16709
     * @return object|array
     */
    public function current()
    {
        return $this->_result[$this->position];
    }


    /**
     * implemented from iterator interface returns the current key or index
     *
     * @error 16710
     * @return int|mixed
     */
    public function key()
    {
        return $this->position;
    }


    /**
     * implemented from iterator interface moves the iterator position one up
     *
     * @error 16711
     * @return void
     */
    public function next()
    {
        $this->init();
        $this->position++;
    }


    /**
     * implemented from iterator interface rewinds the iterator position to initial state
     *
     * @error 16712
     * @return void
     */
    public function rewind()
    {

        $this->position = 0;
    }


    /**
     * implemented from iterator interface checks if the current iterator position is valid
     *
     * @error 16713
     * @return bool
     */
    public function valid()
    {
        return isset($this->_result[$this->position]);
    }


    /**
     * static find by path interface for Xapp_Util_Std_Query::query. this method is the preferred way to write complex
     * queries on transient or temporary objects or repeat queries on those without the need to create a class instance
     * or reuse the options set with first class instance since all options are static as well. use Xapp_Util_Std_Query:.retrieve
     * the same way with only the path and without optional query and/or filter conditions
     *
     * the first argument expects the object to query
     *
     * the second argument expects a xpath like query path the should always start with "/" = root. path identifier. the
     * path syntax is as followed:
     *
     * - /          = returns the whole object
     * - .          = current position of path
     * - /*         = all values of next child as numeric indexed array
     * - /path      = returns the value of property $object->path
     * - /path/0    = returns the array at index 0 of property $object->path provided the property value is an array
     * - /../       = returns the first child property and its values without knowing the name
     * - /../../    = returns the first child of the first child and so on
     * - /book//price = returns all the prices of all books as array
     * - /..//type  = returns all the property values of property type of the first child as array
     *
     * NOTE! that the "*" syntax will only work on first child depth at path and should be best used to reduce array with
     * objects to value collections or to transform objects to numeric indexed arrays
     *
     * the second arguments expects optional query conditions either as string or array. the query must be either a property
     * name to get objects at path that have a property with that name or a query condition with [name][operator][value]
     * with the following operator options:
     *
     * !%   = sql not like wildcard "%value%" (expecting word to match)
     * %    = sql like with wildcard "%value%" (expecting word to match)
     * >%   = sql like with right wildcard "value%" (expecting word to match)
     * <%   = sql like with left wildcard "%value"
     * !->  = not in (expecting comma separated value list)
     * ->   = in (expecting comma separated value list)
     * !<>  = not between (expecting two values, min and max comma separated)
     * <>   = between (expecting two values, min and max comma separated)
     * *    = any value that is not null or empty string
     * ==   = equal and of same data type
     * !==  = not equal and of same data type
     * !=   = not equal
     * >=   = greater than equal
     * <=   = lesser than equal
     * =    = equal
     * >    = greater
     * <    = lesser
     *
     * regex patterns as query condition values are supported and need to be passed as valid PHP regex patterns like:
     *
     * -    = "name=/^key/i"
     *
     * queries can also be performed with a reference to a value of other property in the same object like:
     *
     * -    = "name={property}" where property is another property name (works only in same depth!)
     *
     * to get objects at path only by property name pass the property name as second argument. to query objects at path
     * for more than one property name pass a comma separated property name list, e.g. "price,name" to return all objects
     * that contain a property by those names.
     *
     * to create complex queries multiple queries can be connected with SQL like AND/OR logical operators. either connect
     * multiple queries as string separated by "&&" or "||" operators or pass array with multiple queries like:
     *
     * - "category=shop&&price>10" (get items with category "shop" AND a price greater than "10")
     * - array("category=shop", "price>10") (same as above)
     * - "category=shop||category=post" (get items with category "shop" or "post")
     * - array("category=shop", "||", "category=post") (same as above)
     *
     * NOTE! that the default connector if not used when multiple queries are passed as array defaults to "&&"
     *
     * the fourth optional argument expects a filter condition. NOTE! callback functions can not be internal php functions
     * see Xapp_Util_Std_Query::filter for filter syntax
     *
     * @error 16715
     * @param object|array $object expects the object to search
     * @param string $path expects the query path
     * @param null|string|array $query expect optional query conditions with or without logical connectors
     * @param null|string|int|array $filter expects optional result filter flags
     * @param null|mixed $parent internally stores and passes parent element as reference if needed
     * @return mixed
     * @throws Xapp_Result_Exception
     * @throws Xapp_Util_Std_Exception
     */
    public static function &find(&$object, $path, $query = null, $filter = null, &$parent = null)
    {
        $tmp        = null;
        $class      = get_class();
        $return     = null;
        $result     = array();
        $default    = xapp_get_option(self::DEFAULT_VALUE, $class);
        $operators  = array();

        if($query === null)
        {
            $object =& self::retrieve($object, $path);
            if($filter !== null)
            {
                $object =& self::filter($object, $filter);
            }
            return $object;
        }else{
            if(($object =& self::retrieve($object, $path)) !== false)
            {
                $query = (array)$query;
                //query is string with conditional operators
                if(sizeof($query) === 1 && stripos($query[0], '||') !== false || stripos($query[0], '&&') !== false)
                {
                    $query = preg_split('/(\&\&|\|\|)/i', $query[0], -1, PREG_SPLIT_DELIM_CAPTURE);
                }
                //multiple queries with logical operator
                if(sizeof($query) > 1)
                {
                    $parent = &$object;
                    //normalize
                    foreach($query as $k => $v)
                    {
                        if($k === 0 && array_key_exists(1, $query) && in_array($query[1], array('||', '&&'))) $operators[0] = $query[1];
                        if(in_array(trim($v), array('||', '&&')))
                        {
                            $operators[($k + 1)] = trim($v);
                            unset($query[$k]);
                        }
                    }

                    //execute queries
                    foreach($query as $k => $v)
                    {
                        $tmp = array();
                        if(array_key_exists($k, $operators) && $operators[$k] === '||')
                        {
                            $object =& $parent;
                        }
                        $return =& self::execute($object, $v);
                        foreach($return as &$r)
                        {
                            $tmp[((is_object($r)) ? spl_object_hash($r) : md5(serialize($r)))] = $r;
                        }
                        //or condition
                        if(array_key_exists($k, $operators) && $operators[$k] === '||')
                        {
                            $result = array_filter(array_merge($result, $tmp));
                        //and condition
                        }else{
                            $result = $tmp;
                            $object =& $result;
                        }
                    }

                    $return = null;
                    $result = array_values($result);

                    if(is_array($result) && sizeof($result) === 0)
                    {
                        if(xapp_get_option(self::THROW_EXCEPTION, $class))
                        {
                            throw new Xapp_Result_Exception(xapp_sprintf(_("no result found for query: %s"), $path), 1671501);
                        }else{
                            return xapp_default($default);
                        }
                    }else{
                        if($filter !== null)
                        {
                            return self::filter($result, $filter);
                        }else{
                            return $result;
                        }
                    }
                //single query
                }else{
                    self::execute($object, $query[0], $result);
                    if(!empty($result))
                    {
                        if($filter !== null)
                        {
                            return self::filter($result, $filter);
                        }else{
                            return $result;
                        }
                    }else{
                        if(xapp_get_option(self::THROW_EXCEPTION, $class))
                        {
                            throw new Xapp_Result_Exception(xapp_sprintf(_("no result found for query: %s"), $path), 1671501);
                        }else{
                            return xapp_default($default);
                        }
                    }
                }
            }
        }
        if(xapp_get_option(self::THROW_EXCEPTION, $class))
        {
            throw new Xapp_Result_Exception(xapp_sprintf(_("no result found for query: %s"), $path), 1671501);
        }else{
            return xapp_default($default);
        }
    }


    /**
     * retrieve value from object passed in first argument at path passed as second argument. see Xapp_Util_Std_Query::find
     * for more details of path syntax. this function can also be used as static function without needing to instantiate
     * query class prior. just pass the object as first argument and a path in second to retrieve values. the default return
     * value if path returns nothing is boolean false unless class option THROW_EXCEPTION is enabled. if you want to return
     * a different default return value do so by passing the desired value as third argument.
     *
     * @error 16714
     * @param object|array $object expects the object to be searched
     * @param string $path expects the search path
     * @param bool|mixed $default expects default return value
     * @param null|mixed $parent stores optional parent element as reference
     * @see Xapp_Util_Std_Query::find
     * @return bool|mixed
     * @throws Xapp_Result_Exception
     * @throws Exception
     */
    public static function &retrieve(&$object, $path, $default = false, &$parent = null)
    {
        $class = get_class();

        if($default === false && xapp_get_option(self::DEFAULT_VALUE, $class) !== false)
        {
            $default = xapp_get_option(self::DEFAULT_VALUE, $class);
        }
        if(is_object($object) || is_array($object))
        {
            if(empty($path))
            {
                return $object;
            }else{
                $path = stripslashes((string)$path);
                $path = rtrim(trim($path), '/');
                $path = str_replace(array("'/'", '"/"'), "\\", $path);

                //assuming // child syntax
                if(stripos($path, '//') !== false)
                {
                    $path = preg_replace('=[\/]{2,}=i', '//', $path);
                    $path = str_replace('\/', '\\', $path);
                    $path = preg_split('=(\/\/(?:[^/]+|$))=i', $path, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
                    foreach($path as $p)
                    {
                        if(substr($p, 0, 2) === '//')
                        {
                            $object =& self::retrieveChilds($object, $p);
                        }else{
                            $object =& self::retrieveObject($object, $p, $parent);
                        }
                    }
                //normal path
                }else{
                    $object =& self::retrieveObject($object, $path, $parent);
                }
            }
        }
        if($object instanceof Exception)
        {
            throw $object;
        }else if($object === '__FALSE__'){
            if($default === false && xapp_get_option(self::THROW_EXCEPTION, $class))
            {
                throw new Xapp_Result_Exception(xapp_sprintf(_("no result found for path: %s"), $path), 1671401);
            }else{
                return xapp_default($default);
            }
        }else{
            return $object;
        }
    }


    /**
     * default internal function to retrieve objects/values at path. see Xapp_Util_Std_Query::find for path syntax
     *
     * @error 16732
     * @param object|array $object expects the object to be searched
     * @param string $path expects the path to apply on object
     * @param null|mixed $parent stores optional parent element as reference
     * @see Xapp_Util_Std_Query::find
     * @return mixed
     */
    protected static function &retrieveObject(&$object, $path, &$parent = null)
    {
        $return = '__FALSE__';

        if(in_array($path, array('', '/')))
        {
            return $object;
        }
        foreach(explode('/', trim($path, ' /')) as $p)
        {
            $p = str_replace('\\', '/', trim($p));
            $parent = $object;

            //get all childs at path
            if($p === '*'){
                $object =& self::reduceChilds($object);
            //get value at path
            }else if($p === '.'){
                // . means current path so do nothing!
            //get first child at path
            }else if($p === '..'){
                $object =& self::firstChild($object);
            //get value at index assuming value is an array
            }else if(is_numeric($p) && (intval($p) == floatval($p))){
                if(!is_array($object) || !array_key_exists($p, $object))
                {
                    return $return;
                }else{
                    $object =& $object[$p];
                }
            //get at path (default)
            }else{
                if(!isset($object->$p))
                {
                    return $return;
                }else{
                    $object =& $object->$p;
                }
            }
        }

        return $object;
    }


    /**
     * internal function to get first child of an object
     *
     * @error 16731
     * @param object|array $object expects the object to be searched
     * @return mixed
     */
    protected static function &firstChild(&$object)
    {
        if(is_object($object) || is_array($object))
        {
            foreach($object as &$obj)
            {
                return $object =& $obj;
            }
        }
        return '__FALSE__';
    }


    /**
     * internal function to reduce child object at path which is invoked when the query path ends with * = asterisk. see
     * Xapp_Util_Std_Query::find for example. when reducing all childs looses reference since childs will be forced into
     * array.
     *
     * @error 16730
     * @param object|array $object expects the object to be searched
     * @see Xapp_Util_Std_Query::find
     * @return array|string
     */
    protected static function &reduceChilds(&$object)
    {
        $tmp = array();

        if(is_object($object))
        {
            $tmp = get_object_vars($object);
            if(!empty($tmp))
            {
                $tmp = xapp_array_to_object(array_values($tmp));
                return $tmp;
            }else{
                return '__FALSE__';
            }
        }else if(is_array($object)){
            foreach($object as &$val)
            {
                $val = (is_array($val)) ? array($val) : $val;
                foreach($val as &$v)
                {
                    $tmp[] = $v;
                }
            }
            if($tmp !== $object)
            {
                $tmp = xapp_array_to_object($tmp);
                return $tmp;
            }else{
                return '__FALSE__';
            }
        }else{
            return $object;
        }
    }


    /**
     * internal function to retrieve values for a key of all childs at path. e.g. suppose a book object contains several
     * books and you want to only know the price property values of all books use '/books//price' which will return all
     * price values as array. this function is recursive but never will overwrite already found values for key.
     *
     * @error 16729
     * @param object|array $object expects the object to be searched
     * @param string|mixed $key expects the key to look up
     * @param array $array expects array as internal temporary recursion storage
     * @param string $path expects path for internal temporary recursion storage
     * @return array|mixed
     */
    protected static function &retrieveChilds(&$object, $key, Array &$array = array(), $path = '')
    {
        $key = str_replace('\\', '/', trim($key, '/'));
        $key = (is_numeric($key) && ctype_digit($key)) ? (int)$key : $key;

        if(is_object($object) || is_array($object))
        {
            foreach($object as $k => &$v)
            {
                if($key === '*')
                {
                    $array[] = $v;
                }else{
                    if($k === $key)
                    {
                        $skip = false;
                        if(!empty($array) && preg_match('=^'.preg_quote(key($array)).'.*=i', $path))
                        {
                            $skip = true;
                        }
                        if(!$skip)
                        {
                            $array["$path.$k"] = $v;
                            end($array);
                        }
                    }
                    if(is_object($v) || is_array($v))
                    {
                        self::retrieveChilds($v, $key, $array, "$path.$k");
                    }
                }
            }
            $array = array_values($array);
            return $array;
        }else{
            return $object;
        }
    }


    /**
     * execute the query part of a query which is the sql like query see in Xapp_Util_Std_Query::find. this function will
     * iterate through object and apply query on children and return all positive asserted values. to avoid duplicates
     * stores hash in result storage
     *
     * @error 16716
     * @param mixed $object expects object or array for iteration
     * @param string $query expects the query filter string to match
     * @param array $result expects optional result reference
     * @param string $hash expects internal hash
     * @see Xapp_Util_Std_Query::find
     * @return mixed
     */
    final private static function &execute(&$object, $query, &$result = array(), $hash = null)
    {
        if(is_object($object) || is_array($object))
        {
            foreach($object as $k => &$v)
            {
                if(is_object($v))
                {
                    self::execute($v, $query, $result, spl_object_hash($v));
                }else if(is_array($v)){
                    self::execute($v, $query, $result, md5(serialize($v)));
                }else{
                    if(self::assert($k, $v, $query, $object) && !array_key_exists($hash, $result))
                    {
                        $result[$hash] = &$object;
                    }
                }
            }
            $result = array_values($result);
        }
        return $result;
    }


    /**
     * internal filter function to be used to filter the query result by using filter commands or passing a callable.
     * using filter commands with the following syntax:
     *
     * using positive or negative numeric values will return the value(s) at index if using a negative value will use
     * index starting from end of array. -1 will return the last value(s)
     *
     * using a string command like:
     *
     * 'first':
     * will return value(s) at index 0
     * 'last':
     * will return value(s) at last index -1
     * '' or '*':
     * returns the object unfiltered
     * '0..1':
     * returns items between range of start index x and end index y (e.g. 0 to 1)
     * '0.8':
     * returns the items at index which is the middle of start index x and end index y (e.g. 4)
     * '0,1,2':
     * returns the items of index list separated by comma (e.g. returns index at 0, 1 and 3)
     * '!0,1':
     * returns the items which are not in index list separated by comma (e.g. returns all items but items at index 0 and 1)
     * '1+2':
     * returns y items starting from index x (e.g. 1+2 returns items at index 1, 2 and 3)
     * '>1':
     * returns all items starting from or greater then index x (e.g. >1 returns items from 1 until the end of item list)
     * '<3':
     * returns all items lesser then index x (e.g. <3 returns items at index 0, 1 and 2)
     * '%1':
     * returns odd or even items using modulus (e.g. %1 will get all items at odd index)
     *
     * using a callable as filter value. callable´s can be passed like:
     *
     * 'my_func': string function name
     * 'my_func|1': string function name piped with additional arguments
     * 'my_class::method': string class::method
     * 'my_class::method|1' string class::method piped with additional arguments
     * array('my_func', 1,'foo'): array with function name at first index and additional arguments
     * array('my_class::method', 1,'foo'): array with class::method
     * array(array('my_class', 'method')): array with static callable at first index
     * array(array($my_class, 'method')): array with instance callable at first index
     * array(array($my_class, 'method'), 1,'foo')): array with static|instance callable at first index and additional
     * arguments
     *
     * the callable will receive the object as first argument followed by additional arguments if used.
     *
     * if a not supported filter command or not found callable is passed as filter argument will throw exception. the
     * filter function will throw Xapp_Result_Exception or default return value according to class option THROW_EXCEPTION
     * and DEFAULT_VALUE if filtering produces no results. this behaviour can be overwritten by using class option
     * FILTER_ALWAYS_RETURNS_ARRAY. settings this option to boolean true will always return filtered result as array even
     * if the result is empty = empty array
     *
     * @error 16721
     * @param object|array $object expects the object to be searched
     * @param int|string|array $filter expects filter argument
     * @return mixed
     * @throws Xapp_Result_Exception
     * @throws Xapp_Util_Std_Exception
     */
    protected static function &filter(&$object, $filter)
    {
        $tmp        = array();
        $class      = get_class();
        $result     = '__NULL__';
        $default    = xapp_get_option(self::DEFAULT_VALUE, $class);
        $callable   = false;

        $keys = array_keys((array)$object);

        //normalize filter value
        if(is_string($filter))
        {
            $filter = trim($filter);
            if(strcasecmp($filter, 'first') === 0){
                $filter = 0;
            }else if(strcasecmp($filter, 'last') === 0){
                $filter = -1;
            }else if(ctype_digit($filter)){
                $filter = (int)$filter;
            }
        }

        //filter by numeric positive or negative index
        if(filter_var($filter, FILTER_VALIDATE_INT) !== false)
        {
            if($filter >= 0)
            {
                $index = $filter;
            }else{
                $index = sizeof($keys) + $filter;
            }
            if(array_key_exists($index, $keys))
            {
                if(is_array($object))
                {
                    $result = $object[$index];
                }else{
                    $result = $object->{$keys[$index]};
                }
            }
        }

        //filter by callback or filter string commands
        if($result === '__NULL__')
        {
            //try all other string commands first
            if(filter_var($filter, FILTER_VALIDATE_INT) === false && is_string($filter))
            {
                switch($filter)
                {
                    //get object without any filtering
                    case ''
                    :
                        return $object;
                    //get object without any filtering
                    case '*'
                    :
                        return $object;
                    //get items in a range between x to y
                    case ((stripos($filter, '..')) !== false)
                    :
                        $filter = explode('..', trim($filter, '.'));
                        $filter = range((int)$filter[0], (int)$filter[1]);
                        break;
                    //get item in between x and y by its middle value
                    case ((stripos($filter, '.')) !== false):
                        //out of range error ? kick this filter ?
                        $filter = explode('.', trim($filter, '.'));
                        $filter = ceil((int)$filter[0] + (((int)$filter[1] - (int)$filter[0]) / 2));
                        break;
                    //get list of items not in x
                    case ((stripos($filter, '!')) !== false):
                        $filter = explode(',', trim(str_replace('!', '', $filter), ','));
                        for($i = 0; $i < sizeof($object); $i++)
                        {
                            if(!in_array($i, $filter)) { $tmp[] = $i; }
                        }
                        $filter = $tmp;
                        break;
                    //get a list of items
                    case ((stripos($filter, ',')) !== false):
                        $filter = explode(',', trim($filter, ', '));
                        break;
                    //get y items starting by x
                    case ((stripos($filter, '+')) !== false):
                        $filter = explode('+', trim($filter, '+'));
                        $filter = range((int)$filter[0], (int)$filter[0] + (int)$filter[1]);
                        break;
                    //get items > x
                    case ((stripos($filter, '>')) !== false):
                        $filter = (int)str_replace('>', '', $filter);
                        $filter = range(($filter + 1), sizeof($object));
                        break;
                    //get items < x
                    case ((stripos($filter, '<')) !== false):
                        $filter = (int)str_replace('<', '', $filter);
                        $filter = range(0, ($filter - 1));
                        break;
                    //get odd or even items at index using modulus
                    case ((stripos($filter, '%')) !== false):
                        $filter = (int)str_replace('%', '', $filter);
                        if($filter === 0){
                            $filter = range(0, sizeof($object));
                        }else if($filter === 1){
                            $filter = range(1, sizeof($object), 2);
                        }else{
                            $filter = range(0, sizeof($object), $filter);
                        }
                        break;
                    //default
                    default:
                        $callable = true;
                }

                //continue and execute complex filter arguments
                if(!$callable)
                {
                    if(is_object($object))
                    {
                        $result = new stdClass();
                        foreach($keys as $k => $v)
                        {
                            if(in_array($k, (array)$filter))
                            {
                                $result->{$v} = &$object->{$v};
                            }
                        }
                    }else{
                        $result = array();
                        foreach($object as $k => &$v)
                        {
                            if(in_array($k, (array)$filter))
                            {
                                $result[] = $v;
                            }
                        }
                    }
                }
            }

            //try callback
            if($result === '__NULL__' || $callable === true)
            {
                if(($callable = self::isCallable($filter, $object)) !== false)
                {
                    $result = self::invoke($callable[0], $callable[1]);
                }else{
                    if(is_string($filter))
                    {
                        throw new Xapp_Util_Std_Exception(xapp_sprintf(_("passed filter: %s is not a recognized filter or callable"), $filter), 1672103);
                    }else{
                        throw new Xapp_Util_Std_Exception(_("passed filter callable is not a recognized filter or callable"), 1672102);
                    }
                }
            }
        }

        //return filter results
        if($result !== '__NULL__')
        {
            if((is_array($result) && sizeof($result) > 0) || (is_object($result) && sizeof(get_object_vars($result)) > 0))
            {
                if(xapp_get_option(self::FILTER_ALWAYS_RETURNS_ARRAY, $class) && is_object($result))
                {
                    $result = array($result);
                }
                return $result;
            }else{
                if(xapp_get_option(self::FILTER_ALWAYS_RETURNS_ARRAY, $class))
                {
                    $result = (array)$result;
                }
                return $result;
            }
        }else{
            if(xapp_get_option(self::FILTER_ALWAYS_RETURNS_ARRAY, $class))
            {
                $result = array();
                return $result;
            }else{
                if(xapp_get_option(self::THROW_EXCEPTION, $class))
                {
                    throw new Xapp_Result_Exception(_("no object(s) found for filter"), 1672101);
                }else{
                    return xapp_default($default);
                }
            }
        }
    }


    /**
     * assert filter against object or current result of find or query call. the assert function will check if key, operator
     * and value which constitute a valid filter argument, e.g key=value will return a match. the key must match an object
     * item at current path for the filter to apply. there are no wildcard keys allowed. filtering for a object key must be
     * done on full qualified name. e.g. you can query like "category=prod*" but not "cat*=prod*"! see the proper syntax
     * and possible filter operators in Xapp_Util_Std_Query::find
     *
     * @error 16717
     * @param string $key expects the objects item key to look up similar like the column part of a mysql where clause
     * @param mixed $value expects the objects item value to assert similar like the value part of a mysql where clause
     * @param string $query expects the full query filter string to assert
     * @param null|object|array $object expects optional object for reference purpose
     * @see Xapp_Util_Std_Query::find
     * @return bool
     */
    protected static function assert($key, $value, $query, $object = null)
    {
        $class = get_class();

        if(($m = self::isQuery($query)) !== false && self::isFilter($query) === false)
        {
            //query has operator
            if(sizeof($m) >= 2)
            {
                if(self::isEqual($key, $m[0]))
                {
                    if(array_key_exists(2, $m))
                    {
                        $m[2] = self::typify(trim($m[2]));
                    }else{
                        $m[2] = null;
                    }
                    switch((string)$m[1])
                    {
                        case '=':
                            if($m[2] === '*')
                            {
                                return (!is_null($value) && $value !== '') ? true : false;
                            }else if(substr_count($m[2], '/') === 2){
                                return (preg_match($m[2], $value)) ? true : false;
                            }else if(substr_count($m[2], '{') === 1 && substr_count($m[2], '}') === 1){
                                return (!is_null($object) && isset($object->{trim($m[2], ' {}')}) && $value == $object->{trim($m[2], ' {}')}) ? true : false;
                            }else{
                                return ($value == $m[2]) ? true : false;
                            }
                        case '==':
                            return ($value === $m[2]) ? true : false;
                        case '!=':
                            return ($value != $m[2]) ? true : false;
                        case '!==':
                            return ($value !== $m[2]) ? true : false;
                        case '>':
                            return ($value > $m[2]) ? true : false;
                        case '>=':
                            return ($value >= $m[2]) ? true : false;
                        case '<':
                            return ($value < $m[2]) ? true : false;
                        case '<=':
                            return ($value <= $m[2]) ? true : false;
                        case '%':
                            return (stripos($value, $m[2]) !== false) ? true : false;
                        case '!%':
                            return (stripos($value, $m[2]) === false) ? true : false;
                        case '>%':
                            return self::isLike($value, trim($m[2], ' %') . '%');
                        case '<%':
                            return self::isLike($value, '%' . trim($m[2], ' %'));
                        case '<>':
                            $m[2] = explode(',', trim($m[2], ' ,'));
                            if(sizeof($m[2]) >= 2)
                            {
                                return (is_numeric($value) && in_array($value, range((int)$m[2][0], (int)$m[2][1]))) ? true : false;
                            }else{
                                return false;
                            }
                        case '!<>':
                            $m[2] = explode(',', trim($m[2], ' ,'));
                            if(sizeof($m[2]) >= 2)
                            {
                                return (is_numeric($value) && !in_array($value, range((int)$m[2][0], (int)$m[2][1]))) ? true : false;
                            }else{
                                return false;
                            }
                        case '*':
                            return true;
                        case '->':
                            return (in_array($value, explode(',', trim($m[2], ' ,')))) ? true : false;
                        case '!->':
                            return (!in_array($value, explode(',', trim($m[2], ' ,')))) ? true : false;
                        default:
                            return false;
                    }
                }
            //query has no operator assuming query for properties (keys) only
            }else{
                if(xapp_get_option(self::CASE_SENSITIVE_QUERIES, $class))
                {
                    if(in_array($key, str_getcsv($query, ',', '\'')))
                    {
                        return true;
                    }
                }else{
                    if(in_array(strtolower((string)$key), array_map('strtolower', str_getcsv($query, ',', '\''))))
                    {
                        return true;
                    }
                }
            }
        }
        return false;
    }


    /**
     * check if passed value is a valid query part or not. query and filter parts share a similar syntax and argument patterns
     * and operators. if the value passed in first argument is recognized as query part will return the query part. if not
     * returns boolean false
     *
     * @error 16722
     * @param string $query expects the query to test
     * @return bool|string
     */
    public static function isQuery($query)
    {
        $functions = get_defined_functions();

        if(is_string($query))
        {
            //check if query is an integer as string which is a filter argument
            if(ctype_digit($query)){
                return false;
            //string "last" or "first" are legacy filter commands
            }else if(strcasecmp($query, 'last') === 0 || strcasecmp($query, 'first') === 0){
                return false;
            //callbacks that are not internal function are most likely filter arguments
            }else if(is_callable($query) && !in_array($query, $functions['internal'])){
                return false;
            //test query conditions or callback as string
            }else{
                $query = preg_split("=(".addcslashes(implode('|', self::$_operators), implode('', self::$_operators)).")=i", $query, 2, PREG_SPLIT_DELIM_CAPTURE);
                if(sizeof($query) >= 1 && (stripos($query[0], '::') === false && !(bool)preg_match('=^([a-z0-9\_\:]+)\|=i', $query[0])))
                {
                    return $query;
                }
            }
        }
        return false;
    }


    /**
     * check if passed value in first argument is a valid filter part or not. query and filter parts share a similar syntax
     * and argument patterns and operators. if the value passed in first argument is recognized as filter part will return
     * the query part. if not returns boolean false
     *
     * @error 16723
     * @param mixed $filter
     * @return bool|mixed
     */
    public static function isFilter($filter)
    {
        $functions = get_defined_functions();

        if(is_array($filter))
        {
            //if filter is array which is a callback or holds callback as first index
            if(is_callable($filter) || isset($filter[0]) && is_callable($filter[0]))
            {
                return $filter;
            }
        }else if(is_string($filter)){
            //if filter is a index value as string
            if(ctype_digit($filter)){
                return (int)$filter;
            //if filter is legacy "last" or "first" string
            }else if(strcasecmp($filter, 'last') === 0 || strcasecmp($filter, 'first') === 0) {
                return $filter;
            //if filter is callable as string which is not a internal function
            }else if(is_callable($filter) && !in_array($filter, $functions['internal'])) {
                return $filter;
            //if filter is a callable with parameters
            }else if(preg_match('=^([a-z0-9\_\:]+)\|=i', $filter)){
                return $filter;
            //if filter complies to filter rules regex
            }else if((bool)preg_match('/^[\-0-9\,\.\%\&\>\<\!\*]+$/i', $filter)){
                return $filter;
            }
        }else if(is_int($filter)){
            return $filter;
        }
        return false;
    }


    /**
     * mysql style wildcard like function tests if passed value in first argument asserts against pattern. this function
     * expects the match pattern with wildcard parameter, e.g. %value%, or %value, or _value - see mysql implementation
     * of pattern matching since logic is copied from mysql logic. will return boolean true. true if pattern matches -
     * false if not
     *
     * @error 16724
     * @param string|mixed $value expects the value to test
     * @param string $pattern expects the pattern to match
     * @return bool
     */
    public static function isLike($value, $pattern)
    {
        $escape = '\\';
        $expr = '/((?:'.preg_quote($escape, '/').')?(?:'.preg_quote($escape, '/').'|%|_))/';
        $part = preg_split($expr, $pattern, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $expr = '/^';
        $last = false;

        foreach($part as $p)
        {
            switch($p)
            {
                case $escape.$escape:
                    $expr .= preg_quote($escape, '/');
                    break;
                case $escape.'%':
                    $expr .= '%';
                    break;
                case $escape.'_':
                    $expr .= '_';
                    break;
                case '%':
                    if(!$last)
                    {
                        $expr .= '.*?';
                    }
                    break;
                case '_':
                    $expr .= '.';
                    break;
                default:
                    $expr .= preg_quote($p, '/');
            }
            $last = $p == '%';
        }
        $expr .= '$/i';

        return (bool)preg_match($expr, $value);
    }


    /**
     * test if a string or array is a valid callback as defined by php´s is_callable() function. the first argument containing
     * the callback can either by a function name as string with additional parameters according to filter/callback syntax
     * , see Xapp_Util_Std_Query::filter for allowed callback arguments, or can be an array with class instances/string name
     * and method and additional arguments. will return boolean false if passed callable argument fails against php´s
     * is_callable() function or returns array with callable and additional parameters
     *
     * @error 16725
     * @param string|array $callable expects the callable string/array argument
     * @param null|object $object expects optional object for reference purpose
     * @see Xapp_Util_Std_Query::filter
     * @return bool|array
     * @throws Xapp_Util_Std_Exception
     */
    protected static function isCallable($callable, &$object)
    {
        $class      = get_class();
        $params     = array(&$object);
        $callback   = null;
        $callbacks  = xapp_get_option(self::ALLOW_CALLBACKS, $class);

        //callable is a string either function or class::method
        if(is_string($callable) && (bool)preg_match('=^(([a-z0-9\_]+)(?:(?:\:\:)([a-z0-9\_]+))?)(?:\|(.+)?)?$=is', $callable, $m))
        {
            if(array_key_exists(3, $m) && !empty($m[3])){
                $callable = array(trim($m[2]), trim($m[3]));
            }else{
                $callable = trim($m[2]);
            }
            if(array_key_exists(4, $m))
            {
                $params = str_getcsv(trim($m[4], xapp_get_option(self::PARAMETER_ENCLOSURE, $class) . ' '), ',', xapp_get_option(self::PARAMETER_ENCLOSURE, $class));
                array_walk($params, function (&$v)
                {
                    $v = Xapp_Util_Std_Query::typify(stripslashes($v));
                });
                $params = array_merge(array(&$object), $params);
            }else{
                $params = array(&$object);
            }
        //callable is array
        }else if(is_array($callable) && array_key_exists(0, $callable) && is_callable($callable[0])){
            if(array_key_exists(1, $callable)){
                $params = array_merge(array(&$object), array_slice($callable, 1));
            }else{
                $params = array(&$object);
            }
            $callable = $callable[0];
        }

        //test callback and check if callback is allowed
        if(is_callable($callable))
        {
            if($callbacks === false)
            {
                throw new Xapp_Util_Std_Exception(xapp_sprintf(_("no callbacks allowed as filter argument"), $callback), 1672502);
            }
            if(is_array($callbacks))
            {
                //is function
                if(is_string($callable) && !in_array($callable, $callbacks))
                {
                    $callback = $callable;
                //is class with class instance or static class name
                }else if(is_array($callable)){
                    if(is_object($callable[0]) && !in_array(get_class($callable[0]), $callbacks) && !in_array(get_class($callable[0]) . '::' . $callable[1], $callbacks))
                    {
                        $callback = get_class($callable[0]) . '::' . $callable[1];
                    }else if(!in_array($callable[0], $callbacks) && !in_array($callable[0] . '::' . $callable[1], $callbacks)){
                        $callback = $callable[0] . '::' . $callable[1];
                    }
                }
                if(!is_null($callback))
                {
                    throw new Xapp_Util_Std_Exception(xapp_sprintf(_("callback: %s is a valid but not allowed callback"), $callback), 1672501);
                }
            }
            unset($callback);
            unset($callbacks);
            return array($callable, $params);
        }
        return false;
    }


    /**
     * invoke php´s valid callback passed as string or array with or without additional parameters passed in second argument
     * if the callback is valid and accessible which in case of class and methods fail due to calling abstract/non-public
     * methods. in this case invoking will raise an error. if not will invoke the callback and return result
     *
     * @error 16726
     * @param callable $callable expects a valid php´s conform callback value
     * @param null|mixed $params expects optional parameters to pass to callback
     * @return mixed
     * @throws Xapp_Util_Std_Exception
     */
    public static function invoke($callable, $params = null)
    {
        if(is_callable($callable))
        {
            if(is_string($callable))
            {
                return call_user_func_array($callable, (array)$params);
            }else{
                try
                {
                    $class  = new ReflectionClass($callable[0]);
                    if($class->isAbstract())
                    {
                        throw new Xapp_Util_Std_Exception(xapp_sprintf(_("unable to invoke class since class: %s is abstract"), $class->name), 1672605);
                    }
                    $method = $class->getMethod($callable[1]);
                    if(!$method->isPublic())
                    {
                        throw new Xapp_Util_Std_Exception(xapp_sprintf(_("unable to invoke class method since method: %s is not public"), $method->name), 1672604);
                    }
                    if($method->isAbstract())
                    {
                        throw new Xapp_Util_Std_Exception(xapp_sprintf(_("unable to invoke class method since method: %s is abstract"), $method->name), 1672603);
                    }
                    if($method->isStatic())
                    {
                        return call_user_func_array($callable, (array)$params);
                    }else{
                        if(is_object($callable[0]))
                        {
                            return call_user_func_array(array($callable[0], $callable[1]), (array)$params);
                        }else{
                            return call_user_func_array(array($class->newInstance(), $callable[1]), (array)$params);
                        }
                    }
                }
                catch(ReflectionException $e)
                {
                    throw new Xapp_Util_Std_Exception(xapp_sprintf(_("unable to call callback: %s due to reflection error: %s"), ((is_object($callable[0])) ? get_class($callable[0]) : $callable[0]) . '::' . $callable[1], $e->getMessage()), 1710602);
                }
            }
        }else{
            throw new Xapp_Util_Std_Exception(_("passed callable is not a callable"), 1672601);
        }
    }


    /**
     * typify a string value to its expected native php data type
     *
     * @error 16718
     * @param string $value expects the value to typify
     * @return bool|float|int|null|string
     */
    public static function typify($value)
    {
        if(is_numeric($value) && (int)$value <= PHP_INT_MAX)
        {
            if((int)$value != $value){

                return (float)$value;
            }else if(filter_var($value, FILTER_VALIDATE_INT) !== false){
                return (int)$value;
            }else{
                return strval($value);
            }
        }else{
            if($value === 'true' || $value === 'TRUE')
            {
                return true;
            }else if($value === 'false' || $value === 'false'){
                return false;
            }else if($value === 'null' || $value === 'NULL'){
                return null;
            }else{
                return strval($value);
            }
        }
    }


    /**
     * test if the two values passed in the first two arguments are equal depending on class option CASE_SENSITIVE_QUERIES
     * which will test case sensitive or insensitive. returns boolean true or false
     *
     * @error 16727
     * @param mixed $value1 expects first value
     * @param mixed $value2 expects second value
     * @return bool
     */
    protected static function isEqual($value1, $value2)
    {
        $class = get_class();

        if(xapp_get_option(self::CASE_SENSITIVE_QUERIES, $class))
        {
            return ($value1 === $value2) ? true : false;
        }else{
            return (strcasecmp((string)$value1, (string)$value2) === 0) ? true : false;
        }
    }


    /**
     * when cloning query instance clone object an break all references and reset the result variable so query can be used
     * as if it were instantiated fresh
     *
     * @error 16728
     * @return void
     */
    public function __clone()
    {
        $this->_object = clone $this->_object;
        $this->_result = null;
    }
}