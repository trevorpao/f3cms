<?php

namespace F3CMS\LineEventHandler\MessageHandler\Flex;

use LINE\LINEBot\Constant\Flex\BubleContainerSize;
use LINE\LINEBot\Constant\Flex\ComponentButtonHeight;
use LINE\LINEBot\Constant\Flex\ComponentButtonStyle;
use LINE\LINEBot\Constant\Flex\ComponentFontSize;
use LINE\LINEBot\Constant\Flex\ComponentFontWeight;
use LINE\LINEBot\Constant\Flex\ComponentIconSize;
use LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
use LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
use LINE\LINEBot\Constant\Flex\ComponentImageSize;
use LINE\LINEBot\Constant\Flex\ComponentLayout;
use LINE\LINEBot\Constant\Flex\ComponentMargin;
use LINE\LINEBot\Constant\Flex\ComponentSpaceSize;
use LINE\LINEBot\Constant\Flex\ComponentSpacing;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\IconComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ImageComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\SeparatorComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\SpanComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder;
use LINE\LINEBot\MessageBuilder\FlexMessageBuilder;
use LINE\LINEBot\TemplateActionBuilder\Uri\AltUriBuilder;
use LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class FlexSampleRestaurant
{
    /**
     * Create sample restaurant flex message
     *
     * @return \LINE\LINEBot\MessageBuilder\FlexMessageBuilder
     */
    public static function get()
    {
        return FlexMessageBuilder::builder()
            ->setAltText('Restaurant')
            ->setContents(
                BubbleContainerBuilder::builder()
                    ->setHero(self::createHeroBlock())
                    ->setBody(self::createBodyBlock())
                    ->setFooter(self::createFooterBlock())
                    ->setSize(BubleContainerSize::GIGA)
            );
    }

    private static function createHeroBlock()
    {
        return ImageComponentBuilder::builder()
            ->setUrl('https://example.com/cafe.png')
            ->setSize(ComponentImageSize::FULL)
            ->setAspectRatio(ComponentImageAspectRatio::R20TO13)
            ->setAspectMode(ComponentImageAspectMode::COVER)
            ->setAction(
                new UriTemplateActionBuilder(
                    null,
                    'https://example.com',
                    new AltUriBuilder('https://example.com#desktop')
                )
            );
    }

    private static function createBodyBlock()
    {
        $title = TextComponentBuilder::builder()
            ->setText('Brown Cafe')
            ->setWeight(ComponentFontWeight::BOLD)
            ->setSize(ComponentFontSize::XL);

        $goldStar = IconComponentBuilder::builder()
            ->setUrl('https://example.com/gold_star.png')
            ->setSize(ComponentIconSize::SM);
        $grayStar = IconComponentBuilder::builder()
            ->setUrl('https://example.com/gray_star.png')
            ->setSize(ComponentIconSize::SM);
        $point = TextComponentBuilder::builder()
            ->setText('4.0')
            ->setSize(ComponentFontSize::SM)
            ->setColor('#999999')
            ->setMargin(ComponentMargin::MD)
            ->setFlex(0);
        $review = BoxComponentBuilder::builder()
            ->setLayout(ComponentLayout::BASELINE)
            ->setMargin(ComponentMargin::MD)
            ->setContents([$goldStar, $goldStar, $goldStar, $goldStar, $grayStar, $point]);

        $place = BoxComponentBuilder::builder()
            ->setLayout(ComponentLayout::BASELINE)
            ->setSpacing(ComponentSpacing::SM)
            ->setContents([
                TextComponentBuilder::builder()
                    ->setText('Place')
                    ->setColor('#aaaaaa')
                    ->setSize(ComponentFontSize::SM)
                    ->setFlex(1),
                TextComponentBuilder::builder()
                    ->setText('Miraina Tower, 4-1-6 Shinjuku, Tokyo')
                    ->setWrap(true)
                    ->setColor('#666666')
                    ->setSize(ComponentFontSize::SM)
                    ->setFlex(5),
            ]);
        $time = BoxComponentBuilder::builder()
            ->setLayout(ComponentLayout::BASELINE)
            ->setSpacing(ComponentSpacing::SM)
            ->setContents([
                TextComponentBuilder::builder()
                    ->setText('Time')
                    ->setColor('#aaaaaa')
                    ->setSize(ComponentFontSize::SM)
                    ->setFlex(1),
                TextComponentBuilder::builder()
                    ->setText('10:00 - 23:00')
                    ->setWrap(true)
                    ->setColor('#666666')
                    ->setSize(ComponentFontSize::SM)
                    ->setFlex(5)
                    ->setContents([
                        SpanComponentBuilder::builder()
                            ->setText('10:00'),
                        SpanComponentBuilder::builder()
                            ->setText('-')
                            ->setColor('#a0a0a0')
                            ->setSize(ComponentFontSize::XS),
                        SpanComponentBuilder::builder()
                            ->setText('23:00'),
                    ]),
            ]);
        $info = BoxComponentBuilder::builder()
            ->setLayout(ComponentLayout::VERTICAL)
            ->setMargin(ComponentMargin::LG)
            ->setSpacing(ComponentSpacing::SM)
            ->setContents([$place, $time]);

        return BoxComponentBuilder::builder()
            ->setLayout(ComponentLayout::VERTICAL)
            ->setBackgroundColor('#fafafa')
            ->setPaddingAll('8%')
            ->setContents([$title, $review, $info]);
    }

    private static function createFooterBlock()
    {
        $callButton = ButtonComponentBuilder::builder()
            ->setStyle(ComponentButtonStyle::LINK)
            ->setHeight(ComponentButtonHeight::SM)
            ->setAction(
                new UriTemplateActionBuilder(
                    'CALL',
                    'https://example.com',
                    new AltUriBuilder('https://example.com#desktop')
                )
            );
        $websiteButton = ButtonComponentBuilder::builder()
            ->setStyle(ComponentButtonStyle::LINK)
            ->setHeight(ComponentButtonHeight::SM)
            ->setAction(
                new UriTemplateActionBuilder(
                    'WEBSITE',
                    'https://example.com',
                    new AltUriBuilder('https://example.com#desktop')
                )
            );
        $spacer = new SeparatorComponentBuilder(ComponentSpaceSize::SM);

        return BoxComponentBuilder::builder()
            ->setLayout(ComponentLayout::VERTICAL)
            ->setSpacing(ComponentSpacing::SM)
            ->setFlex(0)
            ->setBackgroundColor('#fafafa')
            ->setBorderColor('#e0e0e0')
            ->setBorderWidth('1px')
            ->setContents([$callButton, $websiteButton, $spacer]);
    }
}
