<?php

namespace F3CMS\LineEventHandler\MessageHandler\Flex;

use LINE\LINEBot\Constant\Flex\ComponentButtonStyle;
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
use LINE\LINEBot\TemplateActionBuilder\Uri\AltUriBuilder;
use LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class FlexSampleShopping
{
    private static $items = [
        '111' => [
            'photo' => 'https://example.com/photo1.png',
            'name'  => 'Arm Chair, White',
            'price' => 49.99,
            'stock' => true,
        ],
        '112' => [
            'photo' => 'https://example.com/photo2.png',
            'name'  => 'Metal Desk Lamp',
            'price' => 11.99,
            'stock' => false,
        ],
    ];

    /**
     * Create sample shopping flex message
     *
     * @return \LINE\LINEBot\MessageBuilder\FlexMessageBuilder
     */
    public static function get()
    {
        return FlexMessageBuilder::builder()
            ->setAltText('Shopping')
            ->setContents(new CarouselContainerBuilder([
                self::createItemBubble(111),
                self::createItemBubble(112),
                self::createMoreBubble(),
            ]));
    }

    private static function createItemBubble($itemId)
    {
        $item = self::$items[$itemId];

        return BubbleContainerBuilder::builder()
            ->setHero(self::createItemHeroBlock($item))
            ->setBody(self::createItemBodyBlock($item))
            ->setFooter(self::createItemFooterBlock($item));
    }

    private static function createItemHeroBlock($item)
    {
        return ImageComponentBuilder::builder()
            ->setUrl($item['photo'])
            ->setSize(ComponentImageSize::FULL)
            ->setAspectRatio(ComponentImageAspectRatio::R20TO13)
            ->setAspectMode(ComponentImageAspectMode::COVER);
    }

    private static function createItemBodyBlock($item)
    {
        $components   = [];
        $components[] = TextComponentBuilder::builder()
            ->setText($item['name'])
            ->setWrap(true)
            ->setWeight(ComponentFontWeight::BOLD)
            ->setSize(ComponentFontSize::XL);

        $price        = explode('.', number_format($item['price'], 2));
        $components[] = BoxComponentBuilder::builder()
            ->setLayout(ComponentLayout::BASELINE)
            ->setContents([
                TextComponentBuilder::builder()
                    ->setText('$' . $price[0])
                    ->setWrap(true)
                    ->setWeight(ComponentFontWeight::BOLD)
                    ->setSize(ComponentFontSize::XL)
                    ->setFlex(0),
                TextComponentBuilder::builder()
                    ->setText('.' . $price[1])
                    ->setWrap(true)
                    ->setWeight(ComponentFontWeight::BOLD)
                    ->setSize(ComponentFontSize::SM)
                    ->setFlex(0),
            ]);

        if (!$item['stock']) {
            $components[] = TextComponentBuilder::builder()
                ->setText('Temporarily out of stock')
                ->setWrap(true)
                ->setSize(ComponentFontSize::XXS)
                ->setMargin(ComponentMargin::MD)
                ->setColor('#ff5551')
                ->setFlex(0);
        }

        return BoxComponentBuilder::builder()
            ->setLayout(ComponentLayout::VERTICAL)
            ->setSpacing(ComponentSpacing::SM)
            ->setContents($components);
    }

    private static function createItemFooterBlock($item)
    {
        $color      = $item['stock'] ? null : '#aaaaaa';
        $cartButton = ButtonComponentBuilder::builder()
            ->setStyle(ComponentButtonStyle::PRIMARY)
            ->setColor($color)
            ->setAction(
                new UriTemplateActionBuilder(
                    'Add to Cart',
                    'https://example.com',
                    new AltUriBuilder('https://example.com#desktop')
                )
            );

        $wishButton = ButtonComponentBuilder::builder()
            ->setAction(
                new UriTemplateActionBuilder(
                    'Add to wishlist',
                    'https://example.com',
                    new AltUriBuilder('https://example.com#desktop')
                )
            );

        return BoxComponentBuilder::builder()
            ->setLayout(ComponentLayout::VERTICAL)
            ->setSpacing(ComponentSpacing::SM)
            ->setContents([$cartButton, $wishButton]);
    }

    private static function createMoreBubble()
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
                                    'https://example.com',
                                    new AltUriBuilder('https://example.com#desktop')
                                )
                            ),
                    ])
            );
    }
}
