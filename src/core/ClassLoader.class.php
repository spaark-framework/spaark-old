<?php namespace Spaark\Core;

use \Spaark\Core\Model\Config;

// {{{ Exceptions

    /*
    class MissingClassException extends \Exception {}
    class InvalidClassException extends MissingClassException {}
    */

    /**
     * Thrown when a class isn't defined inside its filename
     *
     * Eg:
     * <code>
     *   // myclass.class.php
     *   class NotMyClass
     *   {
     *       //
     *   }
     * </code>
     */
    class NoClassInFileException extends \Exception
    {
        public function __construct($class)
        {
            parent::__construct
            (
                $class . ' not declared in its file'
            );

            $this->line = 'ClassLoader';
            $this->file = '{Spaark}';
        }
    }

    /**
     * Throw when a class has been declared as deprecated
     *
     * Eg:
     * <code>
     *   class MyDeprecatedClass
     *   {
     *       const DEPRECATED = true;
     *   }
     * </code>
     */
    class DeprecatedClassException extends \Exception
    {
        public function __construct($class)
        {
            parent::__construct
            (
                  $class . ', or one of its parents, has been marked '
                . 'as deprecated'
            );
        }
    }

    // }}}

        ////////////////////////////////////////////////////////

class ClassLoader
{
    /**
     * Mapping of namespaces starts to file paths
     */
    private static $starts = array( );

    private static $models = array( );

    /**
     * Initialises the ClassLoader
     */
    public static function init()
    {
        self::$starts['spaark'] = SPAARK_PATH;
        self::$models[]         = 'Spaark\Core\Model\\';

        spl_autoload_register('Spaark\Core\ClassLoader::autoload');
    }

    /**
     * Initialise the ClassLoader again once the config has been loaded
     *
     * This method exists because the ClassLoader is initialised very
     * early on in the bootstrap process. Once the config classes have
     * completed loading, they may contain directives which change the
     * ClassLoader's default behaviour.
     */
    public static function appInit()
    {
        $ns                = strtolower(trim
        (
            Config::getConf('namespace'), '\\'
        ));
        self::$starts[$ns] = ROOT;
        self::$models[]    = $ns . '\Model\\';
    }

    /**
     * This is the autoload function. DO NOT CALL THIS. Call load()
     * instead!
     *
     * @param string $class    The class to load
     * @return bool            Whether the class was loaded or not
     * @see load()
     * @see _load()
     */
    public static function autoload($class)
    {
        return self::_load($class);
    }

    /**
     * Internal function to load a class
     *
     * @param string $class    The class to load
     * @return bool            Whether the class was loaded or not
     */
    private static function _load($class)
    {
        if (self::exists($class)) return true;

        $class  = ltrim($class, '\\');
        $parts  = explode('\\', $class);
        $first  = strtolower($parts[0]);

        if (isset(self::$starts[$first]))
        {
            $path =
                  self::$starts[$first] . '/'
                . strtolower(implode
                  (
                     DIRECTORY_SEPARATOR,
                     array_slice($parts, 1)
                  )) 
                . '.class.php';
        }
        else
        {
            $path =
                ROOT . '/'
              . strtolower(implode(DIRECTORY_SEPARATOR, $parts))
              . '.class.php';
        }

        if (self::getFile($path, $class))
        {
            return $class;
        }

        $newClass = implode('\\', array_slice($parts, 0, -1));
        $newPath  = dirname($path) . '.class.php';

        if (self::getFile($newPath, $newClass) && self::exists($class))
        {
            return $class;
        }

        return false;
    }

    /**
     * Loads a class safely (if it is already loaded, it won't load it
     * again)
     *
     * @param string $name     The class to load
     * @param bool   $tryModel If true, it will try different namespaces
     * @return bool            Whether the class was loaded or not
     */
    public static function load($name, $tryModel = true)
    {
        return
            class_exists($name, false) || interface_exists($name, false)
            ?: self::autoLoad($name, $tryModel);
    }

    /**
     * Attempts to load a model from different namespaces.
     *
     * It tries (in order):
     *   + The local namespace (if provided)
     *   + The app's model namespace
     *   + Spaark's model namespace
     *
     * @param string $name The model to load
     * @param string $localScope The local namespace to try
     * @param mixed If successful, the full name of the loaded class.
     *     False otherwise
     */
    public static function loadModel($name, $localScope = NULL)
    {
        //Local Scope
        if ($localScope)
        {
            $fullName = $localScope. '\\' . $name;

            if (self::_load($fullName))
            {
                return $fullName;
            }
        }

        foreach (self::$models as $model)
        {
            //Spaark Model Scope
            $fullName = $model . $name;
            if (self::_load($fullName))
            {
                return $fullName;
            }
        }

        return false;
    }

    /**
     * Attempts to load a class from the given file
     *
     * @param string $file  The filename to load
     * @param string $class The class / interface to check for
     * @return bool         If the file was successfully loaded
     * @throws NoClassInFileException If the file existed, but the class
     *     was not specified inside it
     * @throws DeprecatedClassException If the class loaded successfully
     *     but has been declared deprecated
     */
    private static function getFile($file, $class)
    {
        if (!file_exists($file)) return false;

        require_once($file);

        if (!self::exists($class))
        {
            throw new NoClassInFileException($class);
        }
        elseif (defined($class . '::DEPRECATED'))
        {
            throw new DeprecatedClassException($class);
        }

        $name =
            substr($class, strrpos($class, '\\') + 1) . '_onload';

        if (method_exists($class, $name))
        {
            $class::$name();
        }

        return true;
    }

    /**
     * Checks if a class or interface exists without attempting to load
     * it
     *
     * @param string $class The name of the class / interface
     * @return bool True if the class or interface exists
     */
    public static function exists($class)
    {
        return
            class_exists($class, false) ||
            interface_exists($class, false);
    }
}
