<?php

namespace F3CMS;

/**
 * React any request
 */
class rStats extends Reaction
{
    /**
     * @param $f3
     * @param $args
     */
    public function do_status($f3, $args)
    {
        chkAuth(fStats::PV_R);

        $req = parent::_getReq();

        if (empty($req['query'])) {
            $req['query'] = ',insert_ts:28daysAgo';
        }

        $req['page'] = (isset($req['page'])) ? ($req['page'] - 1) : 0;

        $req['query'] = fStats::_handleQuery($req['query']);

        if (!empty($req['query']['insert_ts[<>]'])) {
            $start     = $req['query']['insert_ts[<>]'][0];
            $end       = $req['query']['insert_ts[<>]'][1];
            $dateLabel = $start . ' ~ ' . $end;
        } else {
            $start     = '28daysAgo'; // '7daysAgo';
            $end       = 'today'; // 'today';
            $dateLabel = '近 28 天';
        }

        $fc  = new FCHelper('stats');
        $idx = 'stats_default_' . $start . '_' . $end;
        $rtn = $fc->get($idx, 60); // 60 mins

        if (empty($rtn)) {
            $ga = new GAHelper(
                f3()->get('gcp_property'),
                f3()->get('configpath') . '/' . f3()->get('gcp_json')
            );

            $data1 = $ga->byDate($start, $end);
            $data2 = $ga->byCountry($start, $end);

            $swappedData = array_map(fn ($i) => ['x' => $i['y'], 'y' => $i['x']], $data2);

            $rtn = [
                'date' => [
                    'subset' => $data1,
                    'label'  => $dateLabel,
                ],
                'country' => [
                    'subset' => $swappedData,
                    'label'  => $dateLabel,
                ],
            ];

            $fc->save($idx, jsonEncode($rtn));
        } else {
            $rtn = json_decode(preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $rtn), true);
        }

        $rtn['presses'] = fPress::limitRows(['m.status' => fPress::ST_DRAFT], 0, 5)['subset'];

        $rtn['property_id'] = f3()->get('gcp_property');

        return parent::_return(1, ['subset' => $rtn]);
    }
}
