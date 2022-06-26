<?php

namespace F3CMS;

use LINE\LINEBot\Constant\Flex\BubleContainerSize;
use LINE\LINEBot\Constant\Flex\ComponentFontSize;
use LINE\LINEBot\Constant\Flex\ComponentFontWeight;
use LINE\LINEBot\Constant\Flex\ComponentGravity;
use LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
use LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
use LINE\LINEBot\Constant\Flex\ComponentImageSize;
use LINE\LINEBot\Constant\Flex\ComponentLayout;
use LINE\LINEBot\Constant\Flex\ComponentMargin;
use LINE\LINEBot\Constant\Flex\ComponentSpacing;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ImageComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\CarouselContainerBuilder;
use LINE\LINEBot\MessageBuilder\FlexMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder;
use LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder;
use LINE\LINEBot\TemplateActionBuilder\DatetimePickerTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\LocationTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\Uri\AltUriBuilder;
use LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;

class LineMsgBuilder
{
    /**
     * @param $items
     * @param $alt
     */
    public static function carousel($items, $alt = '項目清單')
    {
        /**
         * 'title' => 'foo1', 'info' => 'bar1', 'cover' => '/static/buttons/1040.jpg'
         */
        $baseUri = f3()->get('uri');
        $rows    = [];
        foreach ($items as $item) {
            $rows[] = new CarouselColumnTemplateBuilder($item['title'], $item['info'], $baseUri . $item['cover'], self::_button($item));
        }

        $carouselTemplateBuilder = new CarouselTemplateBuilder($rows);

        return new TemplateMessageBuilder($alt, $carouselTemplateBuilder);
    }

    /**
     * @param $items
     * @param $alt
     */
    public static function mircoFlex($items, $alt = '項目清單', $more = '')
    {
        /**
         * 'uri' => 'https://example.com/photo1.png',
         * 'title' => 'Arm Chair, White',
         * 'insert_ts' => 49.99
         */
        $rows = [];
        foreach ($items as $item) {
            $rows[] = self::_createItemBubble($item);
        }

        if ('' != $more) {
            $rows[] = self::_createMoreBubble($more, BubleContainerSize::MICRO);
        }

        return FlexMessageBuilder::builder()
            ->setAltText($alt)
            ->setContents(new CarouselContainerBuilder($rows));
    }

    /**
     * @param $items
     * @param $alt
     */
    public static function quickMenu($alt = '工作選單(限用手機)')
    {
        /**
         * 'cover' => 'https://example.com/photo1.png',
         * 'title' => 'Arm Chair, White',
         * 'price' => 49.99,
         * 'stock' => true
         */
        $iconUri = f3()->get('iconCDN');
        $liffUri = 'https://liff.line.me/' . f3()->get('line_liff');

        $dtBtn = new DatetimePickerTemplateActionBuilder(
            '挑選日期',
            'postback=pickedDate',
            'datetime',
            date('Y-m-d') . 't00:00',
            date('Y-m-d', strtotime('+6 month')) . 't23:59', // max
            date('Y-m-d', strtotime('-1 month')) . 't00:00'  // min
        );

        $locBtn = new LocationTemplateActionBuilder('加註地點');

        $doneBtn  = new PostbackTemplateActionBuilder('設定完成', 'postback=Done');
        $closeBtn = new PostbackTemplateActionBuilder('關閉', 'postback=closeQuickMenu');

        $newBtn     = new UriTemplateActionBuilder('開新票', $liffUri . '?path=ticket/new&boardID=' . f3()->get('event.boardID'));
        $commentBtn = new UriTemplateActionBuilder('加上註解', $liffUri . '?path=comment/new&boardID=' . f3()->get('event.boardID'));

        // Not Working:: https://line.me/R/ (2021/0222)
        // Deprecated:: line://

        $quickReply = new QuickReplyMessageBuilder([
            new QuickReplyButtonBuilder($doneBtn, $iconUri . 'success.png'),
            new QuickReplyButtonBuilder($commentBtn, $iconUri . 'comment.png'),
            new QuickReplyButtonBuilder($newBtn, $iconUri . 'add1.png'),

            new QuickReplyButtonBuilder($dtBtn, $iconUri . 'calenda.png'),
            new QuickReplyButtonBuilder($locBtn, $iconUri . 'globe.png'),

            new QuickReplyButtonBuilder($closeBtn, $iconUri . 'delete1.png'),
        ]);

        return new TextMessageBuilder($alt, $quickReply);
    }

    public static function findMenu($alt = '請點選要尋找的項目(限用手機)')
    {
        // $location = new PostbackTemplateActionBuilder('地點', 'postback=searchLocation', '找之前存放的地點');
        $issue    = new PostbackTemplateActionBuilder('工單', 'postback=searchIssue', '找之前存放的工單');
        $date     = new PostbackTemplateActionBuilder('日期', 'postback=searchDate', '找之前存放的日期');
        $img      = new PostbackTemplateActionBuilder('圖片', 'postback=searchImg', '找之前存放的圖片');
        $file     = new PostbackTemplateActionBuilder('檔案', 'postback=searchFile', '找之前存放的檔案');
        $video    = new PostbackTemplateActionBuilder('影片', 'postback=searchVideo', '找之前存放的影片');
        $audio    = new PostbackTemplateActionBuilder('音檔', 'postback=searchAudio', '找之前存放的音檔');
        // $goolge   = new PostbackTemplateActionBuilder('連結', 'postback=searchGoolge', 'Google it');

        $quickReply = new QuickReplyMessageBuilder([
            new QuickReplyButtonBuilder($issue),
            // new QuickReplyButtonBuilder($location),
            new QuickReplyButtonBuilder($date),
            new QuickReplyButtonBuilder($img),
            new QuickReplyButtonBuilder($file),
            new QuickReplyButtonBuilder($video),
            new QuickReplyButtonBuilder($audio),
        ]);

        return new TextMessageBuilder($alt, $quickReply);
    }

    /**
     * @param $alt
     */
    public static function hrMenu($alt = '請點選要進行的動作(限用手機)')
    {
        $liffUri = 'https://liff.line.me/' . f3()->get('line_liff');

        $addBtn  = new UriTemplateActionBuilder('填假單', $liffUri . '?path=leave/new&boardID=' . f3()->get('event.boardID'));
        $listBtn = new UriTemplateActionBuilder('打卡記錄', $liffUri . '?path=checkin/list&boardID=' . f3()->get('event.boardID'));

        $quickReply = new QuickReplyMessageBuilder([
            new QuickReplyButtonBuilder($addBtn),
            new QuickReplyButtonBuilder($listBtn),
        ]);

        return new TextMessageBuilder($alt, $quickReply);
    }

    /**
     * @param $item
     */
    public static function _button($label, $path, $token)
    {
        // return [
        //     new UriTemplateActionBuilder('Go to line.me', 'https://line.me'),
        //     new PostbackTemplateActionBuilder('Buy', 'action=buy&itemid=123'),
        //     new MessageTemplateActionBuilder('Say message', 'hello hello')
        // ];
        $liffUri = 'https://liff.line.me/' . f3()->get('line_liff');

        return ButtonComponentBuilder::builder()
            ->setAction(
                new UriTemplateActionBuilder(
                    $label,
                    $liffUri . '?path=' . $path . '&token=' . $token,
                    new AltUriBuilder(f3()->get('uri') . '/portal?path=' . $path . '&token=' . $token)
                )
            );
    }

    /**
     * @param $item
     */
    private static function _createItemBubble($item)
    {
        if ('Image' == $item['type']) {
            return BubbleContainerBuilder::builder()
                ->setHero(self::_createItemHeroBlock($item))
                ->setBody(self::_createItemBodyBlock($item))
                ->setFooter(self::_createItemFooterBlock($item))
                ->setSize(BubleContainerSize::MICRO);
        } else {
            return BubbleContainerBuilder::builder()
                ->setBody(self::_createItemBodyBlock($item))
                ->setFooter(self::_createItemFooterBlock($item))
                ->setSize(BubleContainerSize::MICRO);
        }
    }

    /**
     * @param $item
     */
    private static function _createItemHeroBlock($item)
    {
        return ImageComponentBuilder::builder()
            ->setUrl(f3()->get('uri') . $item['uri'])
            ->setSize(ComponentImageSize::FULL)
            ->setAspectRatio(ComponentImageAspectRatio::R20TO13)
            ->setAspectMode(ComponentImageAspectMode::COVER);
    }

    /**
     * @param $item
     */
    private static function _createItemBodyBlock($item)
    {
        $components = [];
        if ($item['title']) {
            $components[] = TextComponentBuilder::builder()
                ->setText($item['title'])
                ->setWrap(true)
                ->setWeight(ComponentFontWeight::BOLD)
                ->setSize(ComponentFontSize::XL);
        }

        // if ($item['uri']) {
        //     $components[] = TextComponentBuilder::builder()
        //         ->setText($item['uri'])
        //         ->setWrap(true)
        //         ->setSize(ComponentFontSize::XXS)
        //         ->setMargin(ComponentMargin::MD)
        //     // ->setColor('#ff5551')
        //         ->setFlex(0);
        // }

        return BoxComponentBuilder::builder()
            ->setLayout(ComponentLayout::VERTICAL)
            ->setSpacing(ComponentSpacing::SM)
            ->setContents($components);
    }

    /**
     * @param $item
     */
    private static function _createItemFooterBlock($item)
    {
        $viewButton    = self::_button('View', 'asset/view', $item['token']);
        $refreshButton = self::_button('Refresh Token', 'asset/refresh', $item['token']);

        return BoxComponentBuilder::builder()
            ->setLayout(ComponentLayout::VERTICAL)
            ->setSpacing(ComponentSpacing::SM)
            ->setContents([$viewButton, $refreshButton]);
    }

    /**
     * @param $size
     */
    private static function _createMoreBubble($uri, $size)
    {
        return BubbleContainerBuilder::builder()
            ->setBody(
                BoxComponentBuilder::builder()
                    ->setLayout(ComponentLayout::VERTICAL)
                    ->setSpacing(ComponentSpacing::SM)
                    ->setContents([
                        ButtonComponentBuilder::builder()
                            ->setFlex(1)
                            ->setGravity(ComponentGravity::CENTER)
                            ->setAction(
                                new UriTemplateActionBuilder(
                                    'See more',
                                    $uri,
                                    new AltUriBuilder($uri)
                                )
                            ),
                    ])
            )
            ->setSize($size);
    }
}
