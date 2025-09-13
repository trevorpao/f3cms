<?php
/**
 * F3CMS_Autoloader 類負責自動加載 F3CMS 框架中的類文件。
 * 它提供了註冊自動加載器、獲取類型與前綴對應關係，以及根據類名加載文件的功能。
 */
class F3CMS_Autoloader
{
    /**
     * 註冊自動加載器
     *
     * @return bool 成功返回 true，失敗返回 false
     */
    public static function Register()
    {
        if (function_exists('__autoload')) {
            spl_autoload_register('__autoload');
        }
        if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
            return spl_autoload_register(['F3CMS_Autoloader', 'Load'], true, true);
        } else {
            return spl_autoload_register(['F3CMS_Autoloader', 'Load']);
        }
    }

    /**
     * 獲取前綴對應的類型
     *
     * @return array 前綴對應的類型
     */
    public static function getType()
    {
        return [
            'f' => 'feed',
            'o' => 'outfit',
            'r' => 'reaction',
            'k' => 'kit',
        ];
    }

    /**
     * 獲取類型對應的前綴
     *
     * @return array 類型對應的前綴
     */
    public static function getPrefix()
    {
        return [
            'feed'     => 'f',
            'outfit'   => 'o',
            'reaction' => 'r',
            'kit'      => 'k',
        ];
    }

    /**
     * Load 方法根據提供的類名自動加載對應的類。它首先使用 detect 方法來找到類文件的路徑，
     * 如果找到，則包含該文件以加載類。
     *
     * @param string $pClassName 要加載的類名
     */
    public static function Load($pClassName)
    {
        $fileName = self::detect($pClassName);
        if (false !== $fileName) {
            require $fileName;
        }
    }

    /**
     * detect 方法負責檢測類文件是否存在，並返回文件路徑。
     *
     * @param string $pClassName 要檢測的類名
     * @return string|false 如果找到文件，返回文件路徑；否則返回 false。
     */
    public static function detect($pClassName)
    {
        if ((class_exists($pClassName, false)) || (0 !== strpos($pClassName, 'F3CMS'))) {
            return false;
        }

        $className = ltrim($pClassName, '\\');
        $fileName  = '';
        $namespace = '';
        if ($lastNsPos = strrpos($className, '\\')) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }

        if (preg_match('/(^[fork])+([A-Z]\S*)/', $className, $match)) {
            $type = $match[1];
            $moduleName = $match[2];

            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $moduleName) . DIRECTORY_SEPARATOR . self::getType()[$type] . '.php';

            $fileName = str_replace('libs', 'modules', __DIR__) . str_replace('F3CMS', '', $fileName);

            if (false === file_exists($fileName)) {
                $fileName = str_replace('/f3cms', '', $fileName);
            }
        } else {
            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
            $fileName = __DIR__ . str_replace('F3CMS', '', $fileName);
        }

        if ((false === file_exists($fileName)) || (false === is_readable($fileName))) {
            return false;
        }

        return $fileName;
    }
}

// 註冊自動加載器
F3CMS_Autoloader::Register();
