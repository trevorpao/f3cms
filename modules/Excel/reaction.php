<?php
namespace F3CMS;

class rExcel extends Reaction
{

    function do_upload_file($f3, $args)
    {
        if (!rStaff::_isLogin()) {
            return parent::_return(8001);
        }

        $filename = Upload::saveFile(
            f3()->get('FILES'),
            [
                "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                "application/octet-stream",
                "application/vnd.ms-excel"
            ]
        );

        return $this->load_xls($filename);
    }

    function load_xls($filename)
    {

        $sheetData = Upload::readExcel($filename, 2, 2000, ['B', 'D', 'E', 'F', 'G']);

        $rtn = ['new_programs' => [], 'new_folders' => [], 'schedules' => []];

        foreach ($sheetData as $idx => & $row) {
            $row = array_map('trim', array_filter($row));
            if (!empty($row['E'])) {
                $row['E'] = strtolower($row['E']);
            }
            if (!empty($row['B'])) {
                $next = array_filter($sheetData[$idx + 1]);
                $row['B'] = date('Y-m-d', XlsReadFilter::number2Ts($row['B']));
                $rtn['schedules'][] = ['d' => $row['B'], 's' => $row['D'], 'e' => $next['D'], 'c' => $row['E']];

                if (!isset($rtn['new_programs'][$row['E']])) {
                    $rtn['new_programs'][$row['E']] = '';
                }
            }
            if (!empty($row['F'])) {
                $rtn['new_programs'][$row['E']] = $row['F'];
            }
            if (!empty($row['G'])) {
                $rtn['new_folders'][$row['E']] = $row['G'];
            }
        }

        $tmpProg = [];

        foreach ($rtn['new_programs'] as $key => $value) {
            $program = Program::get_program_by_codename($key);
            if (!empty($program)) {
                unset($rtn['new_programs'][$key]);
                continue;
            }
            $tmpProg[] = ['codename' => $key, 'title' => $value];
        }

        $rtn['new_programs'] = $tmpProg;

        $tmpProg = [];

        foreach ($rtn['new_folders'] as $key => $value) {
            $program = Program::get_program_by_codename($key);
            if (!empty($program)) {
                unset($rtn['new_folders'][$key]);
                continue;
            }
            $tmpProg[] = ['codename' => $key, 'folder' => $value];
        }

        $rtn['new_folders'] = $tmpProg;

        f3()->set('SESSION.uploadPrograms', json_encode($rtn));

        return parent::_return(1, $rtn);
    }

    function do_save_new_schedules($f3, $args)
    {
        $allData = json_decode(f3()->get('SESSION.uploadPrograms') , true);

        db()->begin();

        foreach ($allData['schedules'] as $prog) {
            $program = Program::get_program_by_codename($prog['c']);
            if (empty($program)) {
                $program['title'] = $prog['c'];
                $program['uri'] = '';
                $program['id'] = 0;
            }

            db()->exec("INSERT INTO `" . tpf() . "schedules`(`title`, `uri`, `program_id`, `start_date`, `end_date`, `status`, `last_ts`, " . "`last_user`, `insert_user`, `insert_ts`) VALUES ('" . $program['title'] . "', '" . $program['uri'] . "', '" . $program['id'] . "', '" . $prog['d'] . " " . $prog['s'] . ":00', '" . $prog['d'] . " " . $prog['e'] . ":00', 'Yes', '" . date('Y-m-d H:i:s') . "', '" . rStaff::_CStaff('id') . "', '" . rStaff::_CStaff('id') . "', '" . date('Y-m-d H:i:s') . "')");
        }

        db()->commit();

        return parent::_return(1, $allData['schedules']);
    }
}
