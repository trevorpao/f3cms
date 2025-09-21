<?php

namespace F3CMS;

/**
 * React any request
 */
class rDraft extends Reaction
{
    /**
     * @param array $row
     *
     * @return mixed
     */
    public static function handleRow($row = [])
    {
        $row['press_id'] = ($row['press_id'] > 0) ? [fPress::oneOpt($row['press_id'])] : [[]];
        $row['owner_id'] = ($row['owner_id'] > 0) ? [fStaff::oneOpt($row['owner_id'])] : [[]];

        if (!in_array($row['method'], ['guideline', 'writing']) && !empty($row['content'])) {
            $row['meta'] = jsonDecode($row['content']);
        }

        return $row;
    }

    public function do_import($f3, $args)
    {
        chkAuth(fDraft::PV_U);
        $req = parent::_getReq();

        if (mb_strlen($req['title']) > 255 || mb_strlen($req['info']) > 600) {
            return self::_return(8106, ['msg' => '標題或引言過長!']);
        }

        $cu = fPress::one($req['press_id']);

        if (empty($cu)) {
            return self::_return(8106, ['msg' => '無內容']);
        }

        if (!empty($cu['lang'][$req['lang']]['content'])) {
            return self::_return(8106, ['msg' => '文章中已有內容，請先清空!!']);
        }

        $old = fDraft::one($req['id'], 'id', [
            'status' => fDraft::ST_DONE,
            'lang'   => $req['lang'],
        ]);

        if (empty($old) || empty($old['content'])) {
            return self::_return(8106, ['msg' => '請檢查草稿狀態及內容']);
        }

        $affected = fPress::fromDraft($req['press_id'], $req['lang'], [
            'title'   => $req['title'],
            'info'    => $req['info'],
            'content' => $req['content'],
        ]);

        if ($affected) {
            fDraft::saveCol([
                'col' => 'status',
                'val' => fDraft::ST_USED,
                'pid' => $old['id'],
            ]);
        }

        return self::_return(1);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_genSeo($f3, $args)
    {
        chkAuth(fDraft::PV_U);
        $req = parent::_getReq();

        $acceptLang = fDraft::acceptLang();

        $original = (isset($req['original'])) ? $req['original'] : 'tw';
        if (!array_key_exists($original, $acceptLang)) {
            return self::_return(8106, ['msg' => '無來源語系參數']);
        }
        $cu         = fPress::one($req['pid']);
        $oriContent = $cu['lang'][$original];

        if (empty($cu) || empty($oriContent['content'])) {
            return self::_return(8106, ['msg' => '原文無內容']);
        }

        if (kDraft::strWidth($oriContent['content']) < 100) {
            return self::_return(8106, ['msg' => '文章內文不足 100 字']);
        }

        $content = trim($oriContent['content']);
        if (empty($content)) {
            return self::_return(8106, ['msg' => '無法取得原文內容']);
        }

        $old = fDraft::one($cu['id'], 'press_id', [
            'status[!]' => fDraft::ST_INVALID,
            'method'    => 'seohelper',
            'lang'      => $original,
        ]);

        if (!empty($old)) {
            return self::_return(8106, ['msg' => '已有相同的草稿']);
        }

        $pid = fDraft::add([
            'lang'      => $original,
            'press_id'  => $cu['id'],
            'intent'    => '產生文章 (' . $cu['id'] . ') SEO 相關資料',
            'guideline' => $content,
            'method'    => 'seohelper',
        ]);

        return self::_return(1, ['msg' => '已建立 SEO 草稿，稍後由排程執行，可至草稿區查看結果。']);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_nextstep($f3, $args)
    {
        chkAuth(fDraft::PV_U);
        $req = parent::_getReq();

        $old = fDraft::one($req['pid'], 'id', [
            'status[!]' => fDraft::ST_NEW,
        ]);

        if (empty($old)) {
            return self::_return(8106, ['msg' => '無來源資料']);
        }

        if (empty($old['intent'])) {
            return self::_return(8106, ['msg' => '來源意圖為空']);
        }

        if ('importIntro' == $req['method']) {
            if (empty($old['press_id'])) {
                return self::_return(8106, ['msg' => '無來源文章']);
            }

            if (empty($old['content'])) {
                return self::_return(8106, ['msg' => '無引言']);
            }

            $tmp = jsonDecode($old['content']);

            $affected = fPress::fromDraft($old['press_id'], 'tw', [
                'info'    => $tmp['introduction'],
            ]);

            if ($affected) {
                return self::_return(1);
            } else {
                return self::_return(8106, ['msg' => '匯入失敗']);
            }
        }

        if ('guideline' == $old['method']) {
            if (empty($old['guideline'])) {
                return self::_return(8106, ['msg' => '來源無指引內容']);
            }

            $method = 'writing';
            $tip    = '已建立文章試稿';

            $pid = fDraft::add([
                'lang'      => $old['lang'],
                'press_id'  => $old['press_id'],
                'intent'    => $old['intent'],
                'guideline' => $old['guideline'],
                'method'    => $method,
            ]);
        }

        if ('writing' == $old['method']) {
            if (empty($old['content'])) {
                return self::_return(8106, ['msg' => '來源無試稿']);
            }

            $method = 'seohelper';
            $tip    = '已建立 SEO 文案';

            $pid = fDraft::add([
                'lang'      => $old['lang'],
                'press_id'  => $old['press_id'],
                'intent'    => '產生文章試稿 SEO 相關資料',
                'guideline' => $old['content'],
                'method'    => $method,
            ]);
        }

        return self::_return(1, ['msg' => $tip . '，稍後由排程執行工作，可至草稿區查看結果。']);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_quicktrans($f3, $args)
    {
        chkAuth(fDraft::PV_U);
        $req = parent::_getReq();

        $acceptLang = fDraft::acceptLang();

        $original = (isset($req['from'])) ? $req['from'] : 'tw';
        if (!array_key_exists($original, $acceptLang)) {
            return self::_return(8106, ['msg' => '無來源語系參數']);
        }

        $target = (isset($req['target'])) ? $req['target'] : 'en';
        if (!array_key_exists($target, $acceptLang)) {
            return self::_return(8106, ['msg' => '無目標語系參數']);
        }

        $content = trim($req['str']);
        if (empty($content)) {
            return self::_return(8106, ['msg' => '無法取得原文內容']);
        }

        $targetLang = $acceptLang[$target];

        $pid = fDraft::add([
            'status'    => fDraft::ST_WAITING,
            'lang'      => $target,
            'press_id'  => 0,
            'intent'    => '翻譯文案為 ' . $targetLang,
            'guideline' => $content,
            'method'    => 'quicktrans',
        ]);

        $res = kDraft::quicktrans($target, $content);

        if (1 != $res['code']) {
            return self::_return($res['code'], $res['data']);
        }

        fDraft::saveCol([
            'col' => 'request_id',
            'val' => $res['request_id'],
            'pid' => $pid,
        ]);

        $reply = $res['data']['reply'];

        if (0 === stripos($reply, '再問我一次')) {
            return self::_return(8106, ['msg' => 'wrong ai result']);
        }

        if (preg_match('/```json/', $reply)) {
            $reply = str_replace(['```json', '```'], '', $reply);
        }

        fDraft::saveCol([
            'col' => 'content',
            'val' => $reply,
            'pid' => $pid,
        ]);

        $json = jsonDecode($reply);
        if (!is_array($json) || empty($json['article'])) {
            fDraft::saveCol([
                'col' => 'status',
                'val' => fDraft::ST_INVALID,
                'pid' => $pid,
            ]);

            return self::_return(8106, ['msg' => 'wrong ai json', 'data' => $json]);
        } else {
            fDraft::saveCol([
                'col' => 'status',
                'val' => fDraft::ST_DONE,
                'pid' => $pid,
            ]);
        }

        return self::_return(1, ['msg' => '已翻譯為' . $targetLang . '。', 'article' => $json['article']]);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_resetI18n($f3, $args)
    {
        chkAuth(fDraft::PV_U);
        $req = parent::_getReq();

        $acceptLang = fDraft::acceptLang();

        $original = (isset($req['original'])) ? $req['original'] : 'tw';
        $cu       = fPress::one($req['pid']);

        if (empty($cu)) {
            return self::_return(8106, ['msg' => '原文無內容']);
        }

        foreach ($acceptLang as $idx => $label) {
            if ($idx == $original) {
                continue;
            }

            $targetLang = $acceptLang[$idx];

            if (!empty($cu['lang'][$idx]) && !empty($cu['lang'][$idx]['content'])) {
                fPress::emptyI18nContent($cu['id'], $idx);
            }

            $old = fDraft::one($cu['id'], 'press_id', [
                'status[!]' => fDraft::ST_INVALID,
                'method'    => 'translate',
                'lang'      => $idx,
            ]);

            if (!empty($old)) {
                fDraft::changeStatus($old['id'], fDraft::ST_INVALID);
            }
        }

        self::_return(1, ['msg' => '已重設多語系資料!!']);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_translate($f3, $args)
    {
        chkAuth(fDraft::PV_U);
        $req = parent::_getReq();

        $acceptLang = fDraft::acceptLang();

        $original = 'tw'; // ($req['target'] == 'en') ? 'tw' : 'en';
        if (!array_key_exists($req['target'], $acceptLang)) {
            return self::_return(8106);
        }

        $targetLang = $acceptLang[$req['target']];
        $cu         = fPress::one($req['press_id']);
        $oriContent = $cu['lang'][$original];

        if (empty($cu) || empty($oriContent['content'])) {
            return self::_return(8106, ['msg' => '無內容']);
        }

        if (!empty($cu['lang'][$req['target']]) && !empty($cu['lang'][$req['target']]['content'])) {
            return self::_return(8106, ['msg' => '已有' . $targetLang . '的內容']);
        }

        $old = fDraft::one($cu['id'], 'press_id', [
            'status[!]' => fDraft::ST_INVALID,
            'method'    => 'translate',
            'lang'      => $req['target'],
        ]);

        if (!empty($old)) {
            return self::_return(8106, ['msg' => '已有相同的草稿']);
        }

        $content = trim($oriContent['content']);
        if (empty($content)) {
            return self::_return(8106, ['msg' => '無法取得原文內容']);
        }

        $tmp = [
            'article_title' => $oriContent['title'],
            'article_info' => $oriContent['info'],
            'article_content' => $content,
        ];

        $guideline = jsonEncode($tmp);

        // $guideline = '-- 標題開始 --' . PHP_EOL . $oriContent['title'] . PHP_EOL . '-- 標題結束 --' . PHP_EOL .
        //     '-- 引言開始 --' . PHP_EOL . $oriContent['info'] . PHP_EOL . '-- 引言結束 --' . PHP_EOL .
        //     '-- 文本開始 --' . PHP_EOL . $content . PHP_EOL . '-- 文本結束 --';

        $pid = fDraft::add([
            'lang'      => $req['target'],
            'press_id'  => $req['press_id'],
            'intent'    => '翻譯文章 (' . $req['press_id'] . ') 為 ' . $targetLang,
            'guideline' => $guideline,
            'method'    => 'translate',
        ]);

        if (empty($pid)) {
            return self::_return(8106);
        }

        return self::_return(1, ['msg' => '已建立翻譯任務，稍後由排程執行，可至草稿區查看結果。']);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_genI18n($f3, $args)
    {
        chkAuth(fDraft::PV_U);
        $req = parent::_getReq();

        $acceptLang = fDraft::acceptLang();

        $original = (isset($req['original'])) ? $req['original'] : 'tw';
        if (!array_key_exists($original, $acceptLang)) {
            return self::_return(8106, ['msg' => '無來源語系參數']);
        }
        $cu = fPress::one($req['pid']);
        $oriContent = $cu['lang'][$original];

        if (empty($cu) || empty($oriContent['title']) || empty($oriContent['info']) || empty($oriContent['content'])) {
            return self::_return(8106, ['msg' => '原文中應有標題、引言、內文等資訊']);
        }

        $content = trim($oriContent['content']);
        if (empty($content)) {
            return self::_return(8106, ['msg' => '無法取得原文內容']);
        }

        $tmp = [
            'article_title' => $oriContent['title'],
            'article_info' => $oriContent['info'],
            'article_content' => $content,
        ];

        $guideline = jsonEncode($tmp);

        // $guideline = '-- 標題開始 --'. PHP_EOL . $oriContent['title'] . PHP_EOL .'-- 標題結束 --'. PHP_EOL .
        //     '-- 引言開始 --'. PHP_EOL . $oriContent['info'] . PHP_EOL .'-- 引言結束 --'. PHP_EOL .
        //     '-- 文本開始 --'. PHP_EOL . $content . PHP_EOL .'-- 文本結束 --';

        $error = '';

        foreach ($acceptLang as $idx => $label) {
            if ($idx == $original) {
                continue;
            }
            $tmp = '';

            $targetLang = $acceptLang[$idx];

            if (!empty($cu['lang'][$idx]) && !empty($cu['lang'][$idx]['content'])) {
                $tmp .= '已有'. $targetLang .'的內容';
            }

            $old = fDraft::one($cu['id'], 'press_id', [
                'status[!]' => fDraft::ST_INVALID,
                'method' => 'translate',
                'lang' => $idx,
            ]);

            if (!empty($old)) {
                $tmp .= '已有相同的'. $targetLang .'草稿';
            }

            if ($tmp == '') {
                $pid = fDraft::add([
                    'lang' => $idx,
                    'press_id' => $cu['id'],
                    'intent' => '翻譯文章 (' . $cu['id'] . ') 為 ' . $targetLang,
                    'guideline' => $guideline,
                    'method' => 'translate'
                ]);
            }

            $error .= $tmp;
        }

        if ($error == '') {
            return self::_return(1, ['msg' => '已建立多語系翻譯任務，稍後由排程執行翻譯，可至草稿區查看結果。']);
        } else {
            return self::_return(1, ['msg' => $error]);
        }
    }
}
