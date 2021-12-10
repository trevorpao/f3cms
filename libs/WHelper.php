<?php

namespace F3CMS;

use PhpOffice\PhpWord\IOFactory as IOFactory;
use PhpOffice\PhpWord\PhpWord as PhpWord;
use PhpOffice\PhpWord\Shared\Converter as Converter;

class WHelper extends PhpWord
{
    /**
     * @var mixed
     */
    private static $_instance = false;

    public static function init()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * @return mixed
     */
    public function newPage()
    {
        $margin = Converter::cmToTwip(1);

        $page = self::$_instance->addSection([
            'orientation'  => 'portrait', // [portrait|landscape]
            'marginTop'    => $margin,
            'marginLeft'   => $margin,
            'marginRight'  => $margin,
            'marginBottom' => $margin,
            'pageSizeW'    => 11906,
            'pageSizeH'    => 16838,
        ]);

        return $page;
    }

    /**
     * @param $company
     *
     * @return mixed
     */
    public function newCert($company, $year)
    {
        // header('Content-type: image/png');

        // $year = date('Y') - 1911;
        $root     = f3()->get('ROOT') . f3()->get('BASE');
        $path     = '/upload/cert/' . $year . '/';
        $filename = date('mdHis') . '.png';

        if (!file_exists($root . $path)) {
            mkdir($root . $path, 0775, true);
        }

        if (!file_exists($root . $path) || !is_writable($root . $path)) {
            Reaction::_return('2006', 'failed to mkdir.');
        }

        $imgPath = $root . '/upload/img/bg.png';
        $image   = imagecreatefrompng($imgPath);
        $color   = imagecolorallocate($image, 0, 0, 0);
        $font    = $root . '/font/msjh.ttf';

        imagettftext($image, 22, 0, 245, 630, $color, $font, $company);
        imagettftext($image, 18, 0, 370, 710, $color, $font, $year);
        imagettftext($image, 12, 0, 505, 1268, $color, $font, $year);
        imagettftext($image, 12, 0, 577, 1268, $color, $font, '12');

        imagepng($image, $root . $path . $filename);

        return $root . $path . $filename;
    }

    /**
     * @param $filename
     * @param $type       [ODText|Word2007]
     */
    public function done($filename, $type = 'ODText')
    {
        $root = f3()->get('ROOT') . f3()->get('BASE');
        $path = '/upload/doc/' . date('Y/m') . '/';

        if (!file_exists($root . $path)) {
            mkdir($root . $path, 0775, true);
        }

        if (!file_exists($root . $path) || !is_writable($root . $path)) {
            Reaction::_return('2006', 'failed to mkdir.');
        }

        $objWriter = IOFactory::createWriter(self::$_instance, $type);
        $objWriter->save($root . $path . $filename);
    }

    /**
     * @param $filename
     * @param $type       [ODText|Word2007]
     */
    public function output($filename, $type = 'ODText')
    {
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');

        $objWriter = IOFactory::createWriter(self::$_instance, $type);
        $objWriter->save('php://output');
    }
}
