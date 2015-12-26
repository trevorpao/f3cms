<?php
namespace F3CMS;

class BaseModule
{

    protected $_db;

    //! Instantiate class
    function __construct() {
        $this->_db = \Base::instance()->get('DB');
    }

    /**
     * _escape
     * @param mixed $array - obj need to escape
     * @return mixed
     */
    static protected function _escape($array, $quote = true)
    {
        $f3 = \Base::instance();
        if (is_array($array)) {
            while (list($k,$v) = each($array)) {
                if (is_string($v)) {
                    if ($quote) {
                        $array[$k] =  $f3->get('DB')->quote(htmlspecialchars(trim($v)));
                    }
                    else {
                        $array[$k] =  htmlspecialchars(trim($v));
                    }
                }
                else if (is_array($v)) {
                    $array[$k] = $this->_escape($v, $quote);
                }
            }
        }
        else {
            if ($quote) {
                $array = $f3->get('DB')->quote(htmlspecialchars(trim($array)));
            }
            else {
                $array = htmlspecialchars(trim($array));
            }
        }

        return $array;
    }

    /**
     * handle angular post data
     * @return array - post data
     */
    static function _getReq()
    {
        $f3 = \Base::instance();
        return json_decode($f3->get('BODY'), true);
    }

    /**
     * set a no [0] array
     * @param  array $ary - target array
     * @return array      - fixed array
     */
    static function _fixAry(array $ary)
    {
        array_unshift($ary, "");
        unset($ary[0]);
        return $ary;
    }

    /**
     * new return mode
     * @param mixed $code - whether sucess or error code
     * @param array $data - the data need to return
     * @return array
     */
    static function _return($code = 1, $data = array())
    {
        $return = array('code' => (string)$code);
        if (!empty($data)) {
            $return['data'] = $data;
        }

        header('Content-Type: application/json');
        die(json_encode($return));
    }

    /**
     * set excel header
     * @param string $filename - file name to user
     */
    static function _setXls($filename)
    {
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
        header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
        header("Pragma: no-cache"); // HTTP/1.0
        header("Content-Disposition:filename=". $filename .".xls");
        header("Content-type:application/vnd.ms-excel; charset=UTF-8");
        header("Content-Language:content=zh-tw");
        echo "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; CHARSET=UTF-8\">";
    }

    /** get class const */
    static function _getMainTbl()
    {
        $that = get_called_class();
        return $that::MTB;
    }

    static public function _slugify($text)
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
        // trim
        $text = trim($text, '-');

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // lowercase
        $text = strtolower($text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }

}
