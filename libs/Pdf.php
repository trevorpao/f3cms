<?php

namespace F3CMS;

require_once 'tcpdf/tcpdf.php';

class Pdf extends Helper
{
    /**
     * create a Pdf by html
     *
     * @param string $title    title
     * @param string $uri      html uri
     * @param string $fileName pdf filename
     *
     * @return string file path
     */
    public static function createPdf($title, $uri, $fileName)
    {
        $pdfPath = '/upload/doc/';
        $font    = 'simfang';
        $lang    = [
            'a_meta_charset'  => 'UTF-8',
            'a_meta_dir'      => 'ltr',
            'a_meta_language' => 'zh',
            'w_page'          => '頁面',
        ];

        if (!file_exists($pdfPath)) {
            mkdir($pdfPath, 770);
        }

        // create new PDF document
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, $lang['a_meta_charset'], false);

        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('YOTTA');
        $pdf->SetTitle($title);
        $pdf->SetSubject($title);
        // $pdf->SetKeywords($keyword);

        // set default header data
        // $pdf->SetHeaderData('logo.png', '10', $title, '');

        // set header and footer fonts
        $pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
        $pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);

        // set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // set auto page breaks
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

        // set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // set some language-dependent strings
        // use /tcpdf/config/lang/zho.php
        $pdf->setLanguageArray($lang);

        // set font
        $pdf->SetFont($font, '', 10);

        // ---------------------------------------------------------

        // add a page
        $pdf->AddPage();

        // get esternal file content
        $html = file_get_contents($uri, false);

        // output the HTML content
        $pdf->writeHTML($html, true, false, true, false, '');

        $pdf->lastPage();

        // ---------------------------------------------------------

        //Close and output PDF document
        $pdf->Output($pdfPath . $file_name . '.pdf', 'FD');

        return $pdfPath . $file_name . '.pdf';
    }
}
