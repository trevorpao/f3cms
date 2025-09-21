<?php

// This file is responsible for autoloading classes and managing dependencies.
// It ensures that required classes are loaded dynamically when needed.

class F3CMS_Autoloader
{
    // Registers the autoloader function to PHP's SPL autoload stack.
    public static function Register()
    {
        // If a legacy __autoload function exists, register it with SPL.
        if (function_exists('__autoload')) {
            spl_autoload_register('__autoload');
        }

        // Register the custom autoloader function based on PHP version.
        if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
            return spl_autoload_register(['F3CMS_Autoloader', 'Load'], true, true);
        } else {
            return spl_autoload_register(['F3CMS_Autoloader', 'Load']);
        }
    }

    // Returns a mapping of type prefixes to their corresponding module names.
    public static function getType()
    {
        return [
            'f' => 'feed',
            'o' => 'outfit',
            'r' => 'reaction',
            'k' => 'kit',
        ];
    }

    // Returns a mapping of module names to their corresponding type prefixes.
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
     * Autoload a class identified by name.
     *
     * @param string $pClassName Name of the object to load.
     */
    public static function Load($pClassName)
    {
        // Detect the file path for the given class name.
        $fileName = self::detect($pClassName);

        // If a valid file path is found, include the file.
        if (false !== $fileName) {
            require $fileName;
        }
    }

    /**
     * Detects the file path of a class file based on its name.
     *
     * @param string $pClassName Name of the object to load.
     * @return string|false The file path if found, or false if not.
     */
    public static function detect($pClassName)
    {
        // Skip detection if the class already exists or does not belong to the F3CMS or PCMS namespace.
        if (class_exists($pClassName, false) || ((0 !== strpos($pClassName, 'F3CMS')) && (0 !== strpos($pClassName, 'PCMS')))) {
            return false;
        }

        // Normalize the class name and initialize variables for namespace and file path.
        $className = ltrim($pClassName, '\\');
        $fileName  = '';
        $namespace = '';

        // Extract the namespace and class name if a namespace exists.
        if ($lastNsPos = strrpos($className, '\\')) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }

        // Handle module-specific class names with type prefixes.
        if (preg_match('/(^[fork])+([A-Z]\S*)/', $className, $match)) {
            $type       = $match[1];
            $moduleName = $match[2];

            // Construct the file path based on the module type and name.
            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $moduleName) . DIRECTORY_SEPARATOR . self::getType()[$type] . '.php';

            // Check for the file in two possible locations.
            $fileName = str_replace('libs', 'modules', __DIR__) . str_replace('F3CMS', '', $fileName);

            // Use the first valid file path found.
            $fileName = $fileName;
        } else {
            // Handle standard class names without type prefixes.
            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
            $fileName = __DIR__ . str_replace('F3CMS', '', $fileName);
        }

        // Return false if the file does not exist or is not readable.
        if ((false === file_exists($fileName)) || (false === is_readable($fileName))) {
            return false;
        }

        return $fileName;
    }
}

// Register the autoloader when this file is included.
F3CMS_Autoloader::Register();
