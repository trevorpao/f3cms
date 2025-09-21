<?php

namespace F3CMS;

// The Module class serves as a base class for other modules in the application.
// It provides utility methods for escaping data, handling requests, and managing languages.

class Module
{
    /**
     * Escapes special characters in an array or string to prevent SQL injection.
     *
     * @param mixed $array The data to escape (can be an array or string).
     * @param bool  $quote Whether to add quotes around the escaped value (default: true).
     * @return mixed The escaped data.
     */
    protected static function _escape($array, $quote = true)
    {
        if (is_array($array)) {
            foreach ($array as $k => $v) {
                if (is_string($v)) {
                    if ($quote) {
                        $array[$k] = mh()->quote(self::protectedXss2($v));
                    } else {
                        $array[$k] = ('content' != $k) ? self::_mres(self::protectedXss2($v)) : self::_mres(PFHelper::getInstance()->purify(html_entity_decode($v)), false);
                    }
                } elseif (is_array($v)) {
                    $array[$k] = self::_escape($v, $quote);
                }
            }
        } else {
            if ($quote) {
                $array = mh()->quote(self::protectedXss2($array));
            } else {
                $array = self::_mres(self::protectedXss2($array));
            }
        }

        return $array;
    }

    /**
     * Escapes special characters for MSSQL or other databases.
     *
     * @param mixed $param          The data to escape.
     * @param bool  $quote          Whether to add quotes around the escaped value (default: true).
     * @param bool  $only_for_mssql Whether to apply escaping only for MSSQL (default: false).
     * @return mixed The escaped data.
     */
    static protected function _mres($param, $quote = true, $only_for_mssql = false)
    {
        // if(is_array($param))
        //     return array_map(__METHOD__, $param);

        if ($only_for_mssql && !empty($param) && is_string($param)) {
            if ($quote) {
                return str_replace(['\\', "\0", "'", '"', "\x1a"], ['\\\\', '\\0', "\\'", '\\"', '\\Z'], $param);
            } else {
                return str_replace(['\\', "\0", "\x1a"], ['\\\\', '\\0', '\\Z'], $param);
            }
        }

        return $param;
    }

    /**
     * Protects a string from XSS attacks by encoding special characters.
     *
     * @param string $str The input string.
     * @return string The XSS-protected string.
     */
    final static function protectedXss($str)
    {
        return htmlentities($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Protects a string from XSS attacks using raw URL decoding and sanitization.
     *
     * @param string $str The input string.
     * @return string The XSS-protected string.
     */
    final static function protectedXss2($str)
    {
        $str = rawurldecode($str);

        return filter_var($str, FILTER_SANITIZE_SPECIAL_CHARS);
    }

    /**
     * Shifts a class name to a different namespace or module.
     *
     * @param string $name   The original class name.
     * @param string $target The target module or namespace.
     * @return string The shifted class name.
     */
    public static function _shift($name, $target)
    {
        $name = str_replace(['F3CMS\\', '\\'], ['', ''], $name);

        [$type, $className] = preg_split('/(?<=[fork])(?=[A-Z])/', $name);

        return '\\F3CMS\\' . \F3CMS_Autoloader::getPrefix()[$target] . $className;
    }

    /**
     * Handles AngularJS POST data and returns it as an array.
     *
     * @return array The processed POST data.
     */
    public static function _getReq()
    {
        $rtn = [];

        if (1 == f3()->get('isCORS')) {
            $str = f3()->get('BODY');
            if (empty($str)) {
                $str = file_get_contents('php://input');
            }

            $rtn = json_decode($str, true);
            if (!(JSON_ERROR_NONE == json_last_error())) {
                parse_str($str, $rtn);
            }
        }

        if (empty($rtn)) {
            $rtn = f3()->get('POST');
        }

        if (empty($rtn)) {
            $rtn = f3()->get('GET');
        }

        if (empty($rtn)) {
            $rtn = f3()->get('REQUEST');
        }

        return self::_escape($rtn, false);
    }

    /**
     * Retrieves the current language based on the request or default settings.
     *
     * @param array $args Additional arguments for language selection (optional).
     * @return string The selected language.
     */
    public static function _lang($args = [])
    {
        $acceptLang = f3()->get('acceptLang');
        if (1 == count($acceptLang)) {
            return f3()->get('defaultLang');
        } elseif (isset($_SERVER['HTTP_X_BACKEND_LANG'])) {
            $lang = $_SERVER['HTTP_X_BACKEND_LANG'];
            if (!in_array($lang, $acceptLang)) {
                $lang = f3()->get('defaultLang');
            }

            return $lang;
        } else {
            if (!f3()->exists('lang') || !empty($args)) {
                $lang = f3()->get('defaultLang');

                if (f3()->exists('COOKIE.user_lang')) {
                    $lang = f3()->get('COOKIE.user_lang');
                }

                if (isset($_SERVER['HTTP_CUSTOMER_LANG'])) {
                    $lang = $_SERVER['HTTP_CUSTOMER_LANG'];
                }

                if (!empty($args) && !empty($args['lang'])) {
                    $lang = $args['lang'];
                }

                if (f3()->exists('REQUEST.lang') && !empty(f3()->get('REQUEST.lang'))) {
                    $lang = f3()->get('REQUEST.lang');
                }

                if (!in_array($lang, $acceptLang)) {
                    $lang = f3()->get('defaultLang');
                }

                f3()->set('lang', $lang);
            }
        }

        return f3()->get('lang');
    }

    /**
     * Detects the user agent of a mobile device.
     *
     * @return string The detected device type.
     */
    public static function _mobile_user_agent()
    {
        if (!f3()->exists('device')) {
            $device = 'unknown';

            if (stristr($_SERVER['HTTP_USER_AGENT'], 'ipad')) {
                $device = 'ipad';
            } elseif (stristr($_SERVER['HTTP_USER_AGENT'], 'iphone') || strstr($_SERVER['HTTP_USER_AGENT'], 'iphone')) {
                $device = 'iphone';
            } elseif (stristr($_SERVER['HTTP_USER_AGENT'], 'blackberry')) {
                $device = 'blackberry';
            } elseif (stristr($_SERVER['HTTP_USER_AGENT'], 'android')) {
                $device = 'android';
            }

            f3()->set('device', $device);
        } else {
            $device = f3()->get('device');
        }

        return $device;
    }

    /**
     * Converts a string into a URL-friendly slug.
     *
     * @param string $text The input string.
     * @return string The slugified string.
     */
    public static function _slugify($text)
    {
        $text = str_replace('//', '/', $text);
        $text = str_replace(' ', '-', $text);

        // Replace non-letter or digit characters with '-'.
        $text = preg_replace('~[^\\pL\d%/-]+~u', '-', $text);
        $text = preg_replace('~[^\\pL\\d%/-]+~u', '-', $text);

        // Trim and convert to lowercase.
        $text = trim($text, '-');
        $text = strtolower($text);

        // URL encode the slug.
        $text = urlencode($text);

        return $text;
    }

    /**
     * Checks if a class file exists based on its name.
     *
     * @param string $pClassName The name of the class to check.
     * @return bool True if the class file exists, false otherwise.
     */
    public static function _exists($pClassName)
    {
        $className = ltrim($pClassName, '\\');
        $fileName  = '';
        $namespace = '';

        if ($lastNsPos = strrpos($className, '\\')) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }

        [$type, $moduleName] = preg_split('/(?<=[fork])(?=[A-Z])/', $className);

        if (null !== $moduleName) {
            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $moduleName) . DIRECTORY_SEPARATOR . \F3CMS_Autoloader::getType()[$type] . '.php';

            $fileName = str_replace('libs', 'modules', __DIR__) . str_replace('F3CMS', '', $fileName);

            if (false === file_exists($fileName)) {
                $fileName = str_replace('/f3cms', '', $fileName);
            }
        } else {
            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $type) . '.php';
            $fileName = __DIR__ . str_replace('F3CMS', '', $fileName);
        }

        if ((false === file_exists($fileName)) || (false === is_readable($fileName))) {
            return false;
        }

        return true;
    }
}
