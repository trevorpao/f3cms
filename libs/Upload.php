<?php
namespace F3CMS;

class Upload extends Helper
{

    static function savePhoto($files, $thumbnail = [], $column = 'photo')
    {
        $f3 = f3();
        $root = $f3->get('ROOT') . $f3->get('BASE');
        $path = "/upload/img/".date("Y/m")."/";

        if (($files[$column]['size'] >= $f3->get('maxsize')) || ($files[$column]['size'] == 0)) {
            Reaction::_return("2002", "File too large. File must be less than 2 megabytes.");
        }

        if (!in_array($files[$column]['type'], $f3->get('photo_acceptable')) && !empty($files["photo"]["type"])) {
            Reaction::_return("2003", 'Invalid file type. Only JPG, GIF and PNG types are accepted.');
        }

        if ($files[$column]['error'] != 0) {
            Reaction::_return("2004", 'other error.');
        }

        if (!file_exists($root . $path)) {
            mkdir($root . $path, 0777, true);
        }

        $path_parts = pathinfo($files[$column]['name']);
        $old_fn = $path_parts['filename'];
        $ext = $path_parts['extension'];

        $filename = $path . substr(md5(uniqid(microtime(), 1)), 0, 15);

        if (move_uploaded_file($files[$column]['tmp_name'], $root . $filename .".". $ext)) {
            list($width, $height, $type, $attr) = getimagesize($root . $filename .".". $ext);

            $img = new \Image($filename .".". $ext, false, $root);

            foreach ($thumbnail as $ns) {
                $img->resize($ns[0], $ns[1], true);
                file_put_contents($root . $filename ."_".$ns[0]."x".$ns[1].".". $ext, $img->dump());
            }

            $new_fn = $filename .".". $ext;
        }
        else {
            $new_fn = '';
            $width = 0;
            $height = 0;
        }

        return array($new_fn, $width, $height, $old_fn);
    }

    static function saveFile($files, $acceptable = [])
    {
        $f3 = f3();
        $root = $f3->get('ROOT') . $f3->get('BASE');
        $path = "/upload/doc/".date("Y/m")."/";

        $acceptable = (!empty($acceptable)) ? $acceptable : array(
            "application/pdf",
        );

        if (($files['file']['size'] >= $f3->get('maxsize')) || ($files['file']['size'] == 0)) {
            Reaction::_return("2002", "File too large. File must be less than 2 megabytes.");
        }

        if (!in_array($files['file']['type'], $acceptable) && !empty($files["file"]["type"])) {
            Reaction::_return("2003", 'Invalid file type.('. $files['file']['type'] .')');
        }

        if ($files['file']['error'] != 0) {
            Reaction::_return("2004", 'other error.');
        }

        if (!file_exists($root . $path)) {
            mkdir($root . $path, 0777, true);
        }

        $path_parts = pathinfo($files['file']['name']);
        $old_fn = $path_parts['filename'];
        $ext = $path_parts['extension'];

        $filename = $path . substr(md5(uniqid(microtime(), 1)), 0, 15);

        if (move_uploaded_file($files['file']['tmp_name'], $root . $filename .".". $ext)) {
            $new_fn = $filename .".". $ext;
        }
        else {
            $new_fn = '';
        }

        return $new_fn;
    }

    static function takeScreenshot($uri)
    {
        $f3 = f3();
        $root = $f3->get('ROOT') . $f3->get('BASE');
        $path = "/upload/screenshot/".date("Y/m")."/";

        if (!file_exists($root . $path)) {
            mkdir($root . $path, 0777, true);
        }

        $filename = $path . substr(md5(uniqid(microtime(), 1)), 0, 15);
        $fp = fopen($root . $filename . ".png", 'w+b');

        $params = [
            'key'     => $f3->get('screenshot_key'),
            'size'    => Screenshot::SIZE_F,
            'url'     => $uri,
            'format'  => Screenshot::PNG,
            'timeout' => 1000
        ];

        $ss = new Screenshot($params);
        $raw = $ss->saveScreen($fp);
        fclose($fp);

        return $filename . ".png";
    }

    /**
     * adapter for PHPExcel
     *
     * @param  string  $filename -
     * @param  integer $startRow -
     * @param  integer $endRow   -
     * @param  array   $columns  -
     *
     * @return array
     */
    static function readExcel($filename, $startRow, $endRow, $columns)
    {
        $f3 = f3();
        $root = $f3->get('ROOT') . $f3->get('BASE');

        // include_once $root . $f3->get('vendors') . 'PHPExcel/IOFactory.php';

        $inputFileName = $root .''. $filename;

        $filterSubset = new XlsReadFilter($startRow, $endRow, $columns);

        try {
            $inputFileType = PHPExcel_IOFactory::identify($inputFileName);

            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
            $objReader->setReadDataOnly(true);
            $objReader->setReadFilter($filterSubset);
            $objPHPExcel = $objReader->load($inputFileName);

            return $objPHPExcel->getActiveSheet()->toArray(null,true,true,true);
        } catch(PHPExcel_Reader_Exception $e) {
            Reaction::_return("2005", str_replace($root, '', $e->getMessage()));
        }
    }

    /**
     * scan folder
     *
     * @param  string  $dir
     * @param  integer $only_dir -only folder type or all
     * @param  string  $target   -target column name or all
     *
     * @return array
     */
    static function scan($dir = '', $only_dir = 0, $target = 'all')
    {
        $f3 = f3();
        $root = $f3->get('ROOT') . $f3->get('BASE');

        $files = array();

        // Is there actually such a folder/file?
        if(file_exists($root . $dir)){
            foreach(scandir($root . $dir) as $f) {
                if(!$f || $f[0] == '.') {
                    continue; // Ignore hidden files
                }

                if(is_dir($root . $dir . '/' . $f)) {
                    if ($only_dir == 1 || $only_dir == 0) {
                        // The path is a folder
                        $files[] = array(
                            "name" => $f,
                            "type" => "folder",
                            "path" => $dir . '/' . $f,
                            "items" => self::scan($dir . '/' . $f, $only_dir) // Recursively get the contents of the folder
                        );
                    }
                }
                else {
                    // It is a file
                    if ($only_dir == 2 || $only_dir == 0) {
                        $tmp = array(
                            "name" => $f,
                            "type" => "file",
                            "path" => $dir . '/' . $f,
                            "size" => filesize($root . $dir . '/' . $f) // Gets the size of this file
                        );

                        if ($target == 'all') {
                            $files[] = $tmp;
                        }
                        else {
                            $files[] = $tmp[$target];
                        }
                    }
                }
            }
        }

        return $files;
    }
}
