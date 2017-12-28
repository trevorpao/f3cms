<?php
//autoload.php
//
class F3CMS_Autoloader
{

    public static function Register()
    {
        if (function_exists('__autoload')) {
            spl_autoload_register('__autoload');
        }
        if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
            return spl_autoload_register(array('F3CMS_Autoloader', 'Load'), true, true);
        } else {
            return spl_autoload_register(array('F3CMS_Autoloader', 'Load'));
        }
    }

    static function getType()
    {
        return array(
            'r' => 'reaction',
            'f' => 'feed',
            'o' => 'outfit',
        );
    }

    static function getPrefix()
    {
        return array(
            'reaction' => 'r',
            'feed' => 'f',
            'outfit' => 'o',
        );
    }

    /**
     * Autoload a class identified by name
     *
     * @param    string    $pClassName        Name of the object to load
     */
    public static function Load($pClassName)
    {
        if ((class_exists($pClassName,FALSE)) || (strpos($pClassName, 'F3CMS') !== 0)) {
            return FALSE;
        }

        $className = ltrim($pClassName, '\\');
        $fileName  = '';
        $namespace = '';
        if ($lastNsPos = strrpos($className, '\\')) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }

        list($type, $moduleName) = preg_split("/(?<=[rfo])(?=[A-Z])/", $className);

        if ($moduleName !== null) {
            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $moduleName) . DIRECTORY_SEPARATOR . self::getType()[$type] . '.php';

            $fileName  = str_replace('libs', 'modules', __DIR__) . str_replace('F3CMS', '', $fileName);
        }
        else {
            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $type) . '.php';
            $fileName  = __DIR__ . str_replace('F3CMS', '', $fileName);
        }

        if ((file_exists($fileName) === FALSE) || (is_readable($fileName) === FALSE)) {
            return FALSE;
        }

        require($fileName);
    }
}

F3CMS_Autoloader::Register();
