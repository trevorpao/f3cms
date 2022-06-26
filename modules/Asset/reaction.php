<?php

namespace F3CMS;

/**
 * React any request
 */
class rAsset extends Reaction
{
    public static function myImg($txt = '')
    {
        $result = fAsset::byUser('Image', f3()->get('event.userID'), f3()->get('event.boardID'));

        if ($result) {
            $more = '';
            if (count($result) > 11) {
                $result = array_slice($result, 0, 11);
                $more   = 'https://liff.line.me/' . f3()->get('line_liff') . '?path=asset/images&boardID=' . f3()->get('event.boardID');
            }

            return LineMsgBuilder::mircoFlex($result, '先前存入的圖片', $more);
        } else {
            return '查無記錄';
        }
    }

    public static function myFile($txt = '')
    {
        $result = fAsset::byUser('File', f3()->get('event.userID'), f3()->get('event.boardID'));
        // $result = fAsset::byUser('Image', f3()->get('event.userID'), f3()->get('event.boardID'));

        if ($result) {
            $more = '';
            if (count($result) > 11) {
                $result = array_slice($result, 0, 11);
                $more   = 'https://liff.line.me/' . f3()->get('line_liff') . '?path=asset/files&boardID=' . f3()->get('event.boardID');
            }
            // $result = array_slice($result, 0, 2);
            return LineMsgBuilder::mircoFlex($result, '先前存入的檔案', $more);
        } else {
            return '查無記錄';
        }
    }

    public static function myVideo($txt = '')
    {
        $result = fAsset::byUser('Video', f3()->get('event.userID'), f3()->get('event.boardID'));

        if ($result) {
            $more = '';
            if (count($result) > 11) {
                $result = array_slice($result, 0, 11);
                $more   = 'https://liff.line.me/' . f3()->get('line_liff') . '?path=asset/videos&boardID=' . f3()->get('event.boardID');
            }

            return LineMsgBuilder::mircoFlex($result, '先前存入的影片', $more);
        } else {
            return '查無記錄';
        }
    }

    public static function myAudio($txt = '')
    {
        $result = fAsset::byUser('Audio', f3()->get('event.userID'), f3()->get('event.boardID'));

        if ($result) {
            $more = '';
            if (count($result) > 11) {
                $result = array_slice($result, 0, 11);
                $more   = 'https://liff.line.me/' . f3()->get('line_liff') . '?path=asset/audios&boardID=' . f3()->get('event.boardID');
            }

            return LineMsgBuilder::mircoFlex($result, '先前存入的音檔', $more);
        } else {
            return '查無記錄';
        }
    }
}
