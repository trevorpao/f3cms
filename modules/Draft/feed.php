<?php

namespace F3CMS;

/**
 * data feed
 */
class fDraft extends Feed
{
    public const MTB       = 'draft';
    public const MULTILANG = 0;

    public const ST_NEW     = 'New';
    public const ST_WAITING = 'Waiting';
    public const ST_DONE    = 'Done';
    public const ST_INVALID = 'Invalid';
    public const ST_USED    = 'Used';

    public const BE_COLS = 'm.id,m.press_id,m.owner_id,m.status,m.lang,m.method,m.intent,m.insert_ts,m.last_ts,m.last_user,m.insert_user';

    /**
     * @param $req
     *
     * @return mixed
     */
    public static function add($req)
    {
        mh()->insert(self::fmTbl(), array_merge($req, [
            'status'     => self::ST_NEW,
            'insert_ts'  => date('Y-m-d H:i:s'),
            'insert_user'=> fStaff::_current('id'),
        ]));

        return self::chkErr(mh()->id());
    }

    public static function reportError($pid, $error)
    {
        $data = mh()->update(self::fmTbl(), [
            'status'  => self::ST_INVALID,
            'content' => $error,
        ], [
            'id' => $pid,
        ]);

        return parent::chkErr($data->rowCount());
    }

    public static function acceptLang()
    {
        return [
            'tw' => '中文',
            'en' => '英語',
            'ja' => '日本語',
            'ko' => '韓語',
            // 'fr' => '法語',
        ];
    }

    public static function cronImport($limit = 5)
    {
        mh(true)->info();

        $limit = (!empty($limit)) ? max(min($limit * 1, 10), 1) : 5;

        $data = self::limitRows([
            'm.status'        => self::ST_DONE,
            'm.method'        => 'translate',
            'm.request_id[!]' => '',
            'ORDER'           => ['m.id' => 'ASC'],
        ], 0, $limit, ',m.request_id,m.content');

        \__::map($data['subset'], function ($row) {
            $result = self::batchImport($row);

            echo $result;
            usleep(300000); // 0.3s
        });
    }

    public static function cronAnswer($limit = 5)
    {
        mh(true)->info();

        $limit = (!empty($limit)) ? max(min($limit * 1, 10), 1) : 5;

        $data = self::limitRows([
            'm.status'        => self::ST_WAITING,
            'm.request_id[!]' => '',
            'ORDER'           => ['m.id' => 'ASC'],
        ], 0, $limit, ',m.request_id');

        \__::map($data['subset'], function ($row) {
            $result = self::batchAnswer($row);

            echo $result;
            usleep(300000); // 0.3s
        });
    }

    public static function cronjob($limit = 5)
    {
        mh(true)->info();

        $limit = (!empty($limit)) ? max(min($limit * 1, 10), 1) : 5;

        $data = self::limitRows([
            'm.status' => self::ST_NEW,
            'ORDER'    => ['m.id' => 'ASC'],
        ], 0, $limit, ',m.guideline');

        if ($data['count'] > 0) {
            self::saveCol([
                'col' => 'status',
                'val' => self::ST_WAITING,
                'pid' => \__::pluck($data['subset'], 'id'),
            ]);
        }

        \__::map($data['subset'], function ($row) {
            $method   = 'batch' . ucfirst($row['method']);
            $class    = '\F3CMS\fDraft';

            if (method_exists($class, $method)) {
                $result = call_user_func($class . '::' . $method, $row);
            } else {
                $result = '';
            }

            echo $result;

            // TODO: next step in flow

            usleep(300000); // 0.3s
        });
    }

    public static function batchWriting($row)
    {
        $rtn = PHP_EOL . $row['id'] . ') Writing : ';

        $res = kDraft::writing($row['intent'], $row['guideline']);
        usleep(30000); // 0.03s

        $data = mh()->update(self::fmTbl(), [
            'status'     => self::ST_DONE,
            'content'    => $res['data']['reply'],
            'request_id' => $res['request_id'],
        ], [
            'id' => $row['id'],
        ]);

        $rtn .= 'Done';

        return $rtn; // TODO: return json
    }

    public static function batchGuideline($row)
    {
        $rtn = PHP_EOL . $row['id'] . ') Guideline : ';

        $res = kDraft::guideline($row['intent']);
        usleep(30000); // 0.03s

        $data = mh()->update(self::fmTbl(), [
            'status'     => self::ST_DONE,
            'guideline'  => $res['data']['reply'],
            'request_id' => $res['request_id'],
        ], [
            'id' => $row['id'],
        ]);

        $rtn .= 'Done';

        return $rtn; // TODO: return json
    }

    public static function batchSeohelper($row)
    {
        $rtn = PHP_EOL . $row['id'] . ') Seohelper : ';

        $res = kDraft::seohelper($row['guideline'], $row['press_id']);
        usleep(30000); // 0.03s

        $data = mh()->update(self::fmTbl(), [
            'status'     => self::ST_DONE,
            'content'    => jsonEncode($res['data']['reply']),
            'request_id' => $res['request_id'],
        ], [
            'id' => $row['id'],
        ]);

        $rtn .= 'Done';

        return $rtn; // TODO: return json
    }

    public static function batchImport($row)
    {
        $rtn = PHP_EOL . $row['id'] . ') Import : ';
        $err = '';
        $json = jsonDecode($row['content']);

        if ('Syntax error, malformed JSON' == $json || empty($json['article_title']) || empty($json['article_info']) || empty($json['article_content'])) {
            $err = '格式錯誤';
        }

        if (mb_strlen($json['article_title']) > 255 || mb_strlen($json['article_info']) > 700) {
            $err = '標題或引言過長!';
        }

        $cu = fPress::one($row['press_id']);

        if (empty($cu)) {
            $err = '無對應文章!';
        }

        if (!empty($cu['lang'][$row['lang']]['content'])) {
            $err = '文章中已有內容，請先清空!';
        }

        if ($err == '') {
            $affected = fPress::fromDraft($row['press_id'], $row['lang'], [
                'title'   => $json['article_title'],
                'info'    => $json['article_info'],
                'content' => $json['article_content'],
            ]);

            if ($affected) {
                self::saveCol([
                    'col' => 'status',
                    'val' => self::ST_USED,
                    'pid' => $row['id'],
                ]);

                $err = '0';
            } else {
                $err = '無法寫入!';
            }
        }

        return $rtn . $err;
    }

    public static function batchTranslate($row)
    {
        $rtn = PHP_EOL . $row['id'] . ') Translate : ';

        $res = kDraft::translate($row['lang'], $row['guideline'], $row['press_id']);
        usleep(30000); // 0.03s

        $data = mh()->update(self::fmTbl(), [
            'request_id' => $res['request_id'],
        ], [
            'id' => $row['id'],
        ]);

        $rtn .= 'Done';

        return $rtn; // TODO: return json
    }

    public static function batchAnswer($row)
    {
        $rtn = PHP_EOL . $row['id'] . ') Answer : ';

        $res = kDraft::answer($row['request_id']);
        usleep(30000); // 0.03s

        if (1 == $res['code']) {
            $reply = $res['data']['reply'];

            if (!is_array($reply)) {
                $status = self::ST_INVALID;
                $rtn .= '格式錯誤';
            } elseif (empty($reply['article_title'])
                || empty($reply['article_info'])
                || empty($reply['article_content'])
            ) {
                $status = self::ST_INVALID;
                $rtn .= '未完成';
            } else {
                $status = self::ST_DONE;
                $rtn .= 'Done';
            }

            $data = mh()->update(self::fmTbl(), [
                'status'  => $status,
                'content' => ((!is_array($reply)) ? $reply : jsonEncode($reply)),
            ], [
                'id' => $row['id'],
            ]);
        } else {
            $rtn .= $res['data']['msg'];
        }

        return $rtn;
    }
}
