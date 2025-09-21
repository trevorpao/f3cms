<?php

namespace F3CMS;

/**
 * kit lib
 */
class kExcel extends Kit
{
    public static function loadCsv($filename)
    {
        $root    = f3()->get('ROOT') . f3()->get('BASE');
        // load the CSV file
        $csv = array_map('str_getcsv', file($root . $filename));
        // handle each row
        foreach ($csv as $row) {
            // handle each column
            foreach ($row as $idx => $column) {
                // do something with the column
            }
        }

        return $csv;
    }

    /**
     * @param $filename
     * @param $rows
     */
    public static function render($filename, $rows)
    {
        // TODO: record current staff data

        if (!$rows) {
            header('Content-Type:text/html; charset=utf-8');
            echo '無結果';
        } else {
            f3()->set('rows', $rows);

            echo Outfit::_setXls($filename . '_' . date('YmdHis'))
                ->render('xls/' . $filename . '.html', 'application/vnd.ms-excel');
        }
    }

    /**
     * @param $filename
     * @param $rows
     */
    public static function dumpFile($filename, $rows, $path)
    {
        // TODO: record current staff data

        f3()->set('rows', $rows);
        $tp      = \Template::instance();
        $content = $tp->render('xls/' . $filename . '.html');

        FSHelper::dumpFile(f3()->get('abspath') . $path, $content);
    }
}
