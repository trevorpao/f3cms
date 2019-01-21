<?php
namespace F3CMS;

use Intervention\Image\ImageManagerStatic as Image;

class Upload extends Helper
{
    /**
     * @param $files
     * @param array $thumbnail
     * @param $column
     */
    public static function savePhoto($files, $thumbnail = array(), $column = 'photo')
    {
        $root = f3()->get('ROOT') . f3()->get('BASE');
        $path = '/upload/img/' . date('Y/m') . '/';

        if (($files[$column]['size'] >= f3()->get('maxsize')) || ($files[$column]['size'] == 0)) {
            Reaction::_return('2002', 'File too large. File must be less than 2 megabytes.');
        }

        if (!in_array($files[$column]['type'], f3()->get('photo_acceptable')) && !empty($files['photo']['type'])) {
            Reaction::_return('2003', 'Invalid file type. Only JPG, GIF and PNG types are accepted.');
        }

        if ($files[$column]['error'] != 0) {
            Reaction::_return('2004', 'other error.');
        }

        if (!file_exists($root . $path)) {
            mkdir($root . $path, 0775, true);
        }

        if (!file_exists($root . $path) || !is_writable($root . $path)) {
            Reaction::_return('2006', 'failed to mkdir.');
        }

        $path_parts = pathinfo($files[$column]['name']);
        $old_fn = $path_parts['filename'];
        $ext = $path_parts['extension'];

        $filename = $path . substr(md5(uniqid(microtime(), 1)), 0, 15);

        if (file_exists($files[$column]['tmp_name'])) {
            Image::configure(array('driver' => 'imagick')); // imagick|gd

            $im = Image::make($files[$column]['tmp_name']);
            $im->interlace();

            $width = $im->width();
            $height = $im->height();

            if ($width > 1440) {
                $im->save($root . $filename . '_ori.' . $ext); // save original img

                // resizing to default size
                $im->resize(1440, null, function ($constraint) {
                    $constraint->aspectRatio(); // constraint the current aspect-ratio
                    $constraint->upsize(); // Keep image from being upsized.
                });
            }

            // TODO: watermark
            // $im->insert('public/watermark.png', 'bottom-right', 10, 10);

            $im->save($root . $filename . '.' . $ext);

            foreach ($thumbnail as $ns) {
                // cropping and resizing
                $im->fit($ns[0], $ns[1], function ($constraint) {
                    $constraint->upsize(); // Keep image from being upsized.
                }, 'center'); // top-left, top, center

                $im->save($root . $filename . '_' . $ns[0] . 'x' . $ns[1] . '.' . $ext);
            }

            $new_fn = $filename . '.' . $ext;
        }
        else {
            $new_fn = '';
            $width = 0;
            $height = 0;
        }

        return array($new_fn, $width, $height, $old_fn);
    }

    /**
     * @param $files
     * @param array $acceptable
     * @return mixed
     */
    public static function saveFile($files, $acceptable = array())
    {
        $root = f3()->get('ROOT') . f3()->get('BASE');
        $path = '/upload/doc/' . date('Y/m') . '/';

        $acceptable = (!empty($acceptable)) ? $acceptable : array(
            'application/pdf'
        );

        if (($files['file']['size'] >= f3()->get('maxsize')) || ($files['file']['size'] == 0)) {
            Reaction::_return('2002', array('msg' => 'File too large. File must be less than 2 megabytes.'));
        }

        if (!in_array($files['file']['type'], $acceptable) && !empty($files['file']['type'])) {
            Reaction::_return('2003', array('msg' => 'Invalid file type.(' . $files['file']['type'] . ')'));
        }

        if ($files['file']['error'] != 0) {
            Reaction::_return('2004', array('msg' => 'other error.'));
        }

        if (!file_exists($root . $path)) {
            mkdir($root . $path, 0777, true);
        }

        $path_parts = pathinfo($files['file']['name']);
        $old_fn = $path_parts['filename'];
        $ext = $path_parts['extension'];

        $filename = $path . substr(md5(uniqid(microtime(), 1)), 0, 15);

        if (move_uploaded_file($files['file']['tmp_name'], $root . $filename . '.' . $ext)) {
            $new_fn = $filename . '.' . $ext;
        } else {
            $new_fn = '';
        }

        return $new_fn;
    }

    /**
     * @param $uri
     * @return mixed
     */
    public static function takeScreenshot($uri)
    {
        $root = f3()->get('ROOT') . f3()->get('BASE');
        $path = '/upload/screenshot/' . date('Y/m') . '/';

        if (!file_exists($root . $path)) {
            mkdir($root . $path, 0777, true);
        }

        $filename = $path . substr(md5(uniqid(microtime(), 1)), 0, 15);
        $fp = fopen($root . $filename . '.png', 'w+b');

        $params = array(
            'key'     => f3()->get('screenshot_key'),
            'size'    => Screenshot::SIZE_F,
            'url'     => $uri,
            'format'  => Screenshot::PNG,
            'timeout' => 1000
        );

        $ss = new Screenshot($params);
        $raw = $ss->saveScreen($fp);
        fclose($fp);

        return $filename . '.png';
    }

    /**
     * adapter for PHPExcel
     *
     * @param  string  $filename -
     * @param  integer $startRow -
     * @param  integer $endRow   -
     * @param  array   $columns  -
     * @return array
     */
    public static function readExcel($filename, $startRow, $endRow, $columns)
    {
        $root = f3()->get('ROOT') . f3()->get('BASE');

        // include_once $root . f3()->get('vendors') . 'PHPExcel/IOFactory.php';

        $inputFileName = $root . '' . $filename;

        $filterSubset = new XlsReadFilter($startRow, $endRow, $columns);

        try {
            $inputFileType = PHPExcel_IOFactory::identify($inputFileName);

            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
            $objReader->setReadDataOnly(true);
            $objReader->setReadFilter($filterSubset);
            $objPHPExcel = $objReader->load($inputFileName);

            return $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
        } catch (PHPExcel_Reader_Exception $e) {
            Reaction::_return('2005', str_replace($root, '', $e->getMessage()));
        }
    }

    /**
     * scan folder
     *
     * @param  string  $dir
     * @param  integer $only_dir -only folder type or all
     * @param  string  $target   -target column name or all
     * @return array
     */
    public static function scan($dir = '', $only_dir = 0, $target = 'all')
    {
        $root = f3()->get('ROOT') . f3()->get('BASE');

        $files = array();

        // Is there actually such a folder/file?
        if (file_exists($root . $dir)) {
            foreach (scandir($root . $dir) as $f) {
                if (!$f || $f[0] == '.') {
                    continue; // Ignore hidden files
                }

                if (is_dir($root . $dir . '/' . $f)) {
                    if ($only_dir == 1 || $only_dir == 0) {
                        // The path is a folder
                        $files[] = array(
                            'name'  => $f,
                            'type'  => 'folder',
                            'path'  => $dir . '/' . $f,
                            'items' => self::scan($dir . '/' . $f, $only_dir) // Recursively get the contents of the folder
                        );
                    }
                } else {
                    // It is a file
                    if ($only_dir == 2 || $only_dir == 0) {
                        $tmp = array(
                            'name' => $f,
                            'type' => 'file',
                            'path' => $dir . '/' . $f,
                            'size' => filesize($root . $dir . '/' . $f) // Gets the size of this file
                        );

                        if ($target == 'all') {
                            $files[] = $tmp;
                        } else {
                            $files[] = $tmp[$target];
                        }
                    }
                }
            }
        }

        return $files;
    }

    /**
     * @param $source
     * @param $dist
     * @param $width
     * @param $height
     */
    public static function resizGif($source, $dist, $width = 600, $height = 600)
    {
        $imagick = new Imagick($source);

        $imagick = $imagick->coalesceImages();
        do {
            $imagick->resizeImage($width, $height, Imagick::FILTER_BOX, 1);
        } while ($imagick->nextImage());

        $imagick = $imagick->deconstructImages();

        $imagick->writeImages($dist, true);

        $imagick->clear();
        $imagick->destroy();
    }
}
