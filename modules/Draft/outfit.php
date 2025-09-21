<?php

namespace F3CMS;

/**
 * for render page
 */
class oDraft extends Outfit
{
    /**
     * @param $f3
     * @param $args
     *
     * /draft/1
     */
    public function do_preview($f3, $args)
    {
        // TODO: use One-time passport for invited translators

        if (!kStaff::_isLogin()) {
            f3()->error(404);
        }

        $row = fDraft::one($args['slug'], 'id');

        if (null == $row) {
            f3()->error(404);
        }

        f3()->set('page', [
            'desc'    => '',
            'img'     => '',
            'keyword' => '',
        ]);

        if (!empty($row['content'])) {
            $json = jsonDecode($row['content']);

            if ('Syntax error, malformed JSON' != $json) {
                $row['content'] = $json;
            }

            if (!empty($row['content']['article_content'])) {
                $row['content']['article_content'] = kDraft::toMarkdown($row['content']['article_content']);
            } else {
                $row['content'] = [
                    'article_content' => $row['content'],
                ];
            }

            if (empty($row['content']['article_title'])) {
                $row['content']['article_title'] = $row['method'];
            }

            if (empty($row['content']['article_info'])) {
                $row['content']['article_info'] = $row['intent'];
            }
        }

        _dzv('cu', $row);

        self::render('draft.twig', $row['intent'], '/draft/' . $row['id']);
    }
}
