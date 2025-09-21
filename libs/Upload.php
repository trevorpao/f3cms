<?php

namespace F3CMS;

class Upload extends Helper
{
    /**
     * @param       $files
     * @param array $thumbnails
     * @param       $column
     */
    public static function savePhoto($files, $thumbnails = [], $column = 'photo', $acceptable = [])
    {
        $root        = rtrim(f3()->get('abspath') . 'upload/' . f3()->get('upload_dir'), '/');
        $linkPath    = f3()->get('webpath') . 'upload';
        $path        = '/img/' . date('Y/m') . '/';
        $current     = $files[$column];

        $acceptable = (!empty($acceptable)) ? $acceptable : f3()->get('photo_acceptable');

        if (($current['size'] >= f3()->get('maxsize')) || (0 == $current['size'])) {
            Reaction::_return('2002', ['msg' => 'File too large(' . $current['size'] . '). File must be less than ' . f3()->get('maxsize') . '.']);
        }

        if (!in_array($current['type'], $acceptable) && !empty($current['type'])) {
            Reaction::_return('2003', ['msg' => 'Invalid file type. Only JPG, GIF and PNG types are accepted(' . $current['type'] . ').']);
        }

        if (0 != $current['error']) {
            Reaction::_return('2004', ['msg' => 'other error.']);
        }

        if (!FSHelper::mkdir($root . $path) || !is_writable($root . $path)) {
            Reaction::_return('2006', ['msg' => 'failed to mkdir(' . $root . $path . ').']);
        }

        if (!is_link($linkPath) && !symlink($root, $linkPath)) {
            Reaction::_return('2006', ['msg' => 'failed to link(' . $root . ').']);
        }

        if (file_exists($current['tmp_name'])) {
            return FSHelper::genThumbnails($path . substr(md5(uniqid(microtime(), 1)), 0, 15), $current, $thumbnails);
        } else {
            return ['', 0, 0, ''];
        }
    }

    /**
     * @param       $files
     * @param array $acceptable
     *
     * @return mixed
     */
    public static function saveFile($files, $acceptable = [], $column = 'file')
    {
        $root    = f3()->get('ROOT') . f3()->get('BASE');
        $path    = '/upload/doc/' . date('Y/m') . '/';
        $current = $files[$column];

        $acceptable = (!empty($acceptable)) ? $acceptable : f3()->get('file_acceptable');

        if (($current['size'] >= f3()->get('maxsize')) || (0 == $current['size'])) {
            Reaction::_return('2002', ['msg' => 'File too large(' . $current['size'] . '). File must be less than ' . f3()->get('maxsize') . '.']);
        }

        if (!in_array($current['type'], $acceptable) || empty($current['type'])) {
            Reaction::_return('2003', ['msg' => 'Invalid file type.(' . $current['type'] . ')']);
        }

        if (0 != $current['error']) {
            Reaction::_return('2004', ['msg' => 'other error.']);
        }

        if (!FSHelper::mkdir($root . $path) || !is_writable($root . $path)) {
            Reaction::_return('2006', ['msg' => 'failed to mkdir.']);
        }

        $path_parts = pathinfo($current['name']);
        $old_fn     = $path_parts['filename'];
        $ext        = $path_parts['extension'];

        $filename = $path . substr(md5(uniqid(microtime(), 1)), 0, 15);

        if (move_uploaded_file($current['tmp_name'], $root . $filename . '.' . $ext)) {
            $new_fn = $filename . '.' . $ext;
        } else {
            $new_fn = '';
        }

        return $new_fn;
    }

    /**
     * @param $uri
     *
     * @return mixed
     */
    public static function takeScreenshot($uri)
    {
        $root = f3()->get('ROOT') . f3()->get('BASE');
        $path = '/upload/screenshot/' . date('Y/m') . '/';

        if (!FSHelper::mkdir($root . $path) || !is_writable($root . $path)) {
            Reaction::_return('2006', ['msg' => 'failed to mkdir.']);
        }

        $filename = $path . substr(md5(uniqid(microtime(), 1)), 0, 15);
        $fp       = fopen($root . $filename . '.png', 'w+b');

        $params = [
            'key'     => f3()->get('screenshot_key'),
            'size'    => Screenshot::SIZE_F,
            'url'     => $uri,
            'format'  => Screenshot::PNG,
            'timeout' => 1000,
        ];

        $ss  = new Screenshot($params);
        $raw = $ss->saveScreen($fp);
        fclose($fp);

        return $filename . '.png';
    }

    /**
     * adapter for PHPExcel
     *
     * @param string $filename -
     * @param int    $startRow -
     * @param int    $endRow   -
     * @param array  $columns  -
     *
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
