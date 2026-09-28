<?php

namespace App\Support;

/**
 * Explicit component field structures. These are schema definitions only:
 * authored content is stored exclusively in CMS records, never in this registry.
 * Empty scalars declare text fields; booleans/numbers declare typed controls;
 * associative arrays declare field groups and lists declare repeatable rows.
 */
class FrontendSectionSchema
{
    public const FIELDS = [
        'floatingCtas' => [
            'callText' => '', 'offerText' => '', 'title' => '', 'defaultMarketing' => '', 'submitText' => '',
            'fields' => [
                'fullName' => ['label' => '', 'placeholder' => ''],
                'country' => ['label' => '', 'placeholder' => ''],
                'phone' => ['label' => '', 'placeholder' => ''],
                'email' => ['label' => '', 'placeholder' => ''],
                'marketing' => ['label' => '', 'options' => [['value' => '', 'label' => '']]],
            ],
        ],
        'aboutCta' => [
            'image' => [
                'src' => '',
                'alt' => '',
                'width' => 0,
                'height' => 0,
            ],
            'badge' => [
                'icon' => '',
                'iconAlt' => '',
                'value' => '',
                'suffix' => '',
                'label' => '',
            ],
            'title' => '',
            'description' => '',
            'button' => [
                'href' => '',
                'text' => '',
            ],
        ],
        'aboutHero' => [
            'title' => '',
            'description' => '',
            'buttons' => [
                [
                    'href' => '',
                    'text' => '',
                    'className' => '',
                ],
            ],
        ],
        'aboutLogosSlider' => [
            'heading' => '',
            'logos' => [
                '',
            ],
        ],
        'aboutOurValue' => [
            'tag' => '',
            'title' => '',
            'description' => '',
            'values' => [
                [
                    'imgSrc' => '',
                    'title' => '',
                    'description' => '',
                ],
            ],
        ],
        'aboutSolution' => [
            'heading' => '',
            'image' => [
                'src' => '',
                'alt' => '',
                'width' => 0,
                'height' => 0,
            ],
            'nav' => [
                'prevLabel' => '',
                'nextLabel' => '',
            ],
            'slides' => [
                [
                    'img' => '',
                    'heading' => '',
                    'details' => '',
                    'linkIcon' => '',
                    'link' => '',
                ],
            ],
        ],
        'aboutWhyChoose' => [
            'heading' => '',
            'initialTab' => '',
            'tabs' => [
                [
                    'id' => '',
                    'label' => '',
                ],
            ],
            'tabContent' => [
                'about_quality' => [
                    'imgSrc' => '',
                    'title' => '',
                    'desc' => '',
                ],
                'about_Team' => [
                    'imgSrc' => '',
                    'title' => '',
                    'desc' => '',
                ],
                'about_Delivery' => [
                    'imgSrc' => '',
                    'title' => '',
                    'desc' => '',
                ],
                'about_Support' => [
                    'imgSrc' => '',
                    'title' => '',
                    'desc' => '',
                ],
                'about_Approach' => [
                    'imgSrc' => '',
                    'title' => '',
                    'desc' => '',
                ],
            ],
        ],
        'award' => [
            'groups' => [
                [
                    'title' => '',
                    'logos' => [
                        '',
                    ],
                ],
            ],
        ],
        'benefits' => [
            'title' => '',
            'cards' => [
                [
                    'image' => [
                        'src' => '',
                        'width' => 0,
                        'height' => 0,
                        'alt' => '',
                    ],
                    'list' => [
                        '',
                    ],
                    'title' => '',
                ],
            ],
        ],
        'blogsHero' => [
            'title' => '',
            'description' => '',
            'popularTitle' => '',
            'recentTitle' => '',
            'bgImg' => [
                'src' => '',
                'alt' => '',
            ],
        ],
        'businessGrowth' => [
            'heading' => '',
            'description' => '',
            'tabcontent' => [
                [
                    'icon' => '',
                    'title' => '',
                    'desc' => '',
                ],
            ],
            'button' => [
                'url' => '',
                'text' => '',
            ],
            'bgImg' => [
                'src' => '',
                'alt' => '',
            ],
            'secondary' => false,
        ],
        'contactHero' => [
            'title' => '',
            'details' => [
                [
                    'heading' => '',
                    'description' => '',
                ],
            ],
            'formTitle' => '',
            'defaultValues' => [
                'fullName' => '',
                'email' => '',
                'phone' => '',
                'country' => '',
                'websiteUrl' => '',
                'service' => '',
                'brandName' => '',
                'currency' => '',
                'budget' => '',
                'message' => '',
                'subscriptions' => [],
            ],
            'fields' => [
                'fullName' => [
                    'label' => '',
                    'placeholder' => '',
                ],
                'email' => [
                    'label' => '',
                    'placeholder' => '',
                ],
                'phone' => [
                    'label' => '',
                    'placeholder' => '',
                ],
                'country' => [
                    'label' => '',
                ],
                'websiteUrl' => [
                    'label' => '',
                    'placeholder' => '',
                ],
                'service' => [
                    'label' => '',
                    'options' => [
                        [
                            'value' => '',
                            'label' => '',
                        ],
                    ],
                ],
                'brandName' => [
                    'label' => '',
                    'placeholder' => '',
                ],
                'currency' => [
                    'label' => '',
                    'options' => [
                        [
                            'value' => '',
                            'label' => '',
                        ],
                    ],
                ],
                'budget' => [
                    'label' => '',
                    'rangeLabel' => '',
                    'options' => [
                        [
                            'value' => '',
                            'label' => '',
                        ],
                    ],
                ],
                'message' => [
                    'label' => '',
                    'placeholder' => '',
                ],
            ],
            'subscriptionsNote' => '',
            'subscriptions' => [
                [
                    'id' => '',
                    'value' => '',
                    'label' => '',
                ],
            ],
            'privacyNote' => '',
            'submitText' => '',
        ],
        'cta' => [
            'tag' => '',
            'title' => '',
            'button' => [
                'href' => '',
                'text' => '',
            ],
        ],
        'ctaOne' => [
            'left' => [
                'heading' => '',
                'description' => '',
            ],
            'right' => [
                'heading' => '',
                'description' => '',
                'list' => [
                    '',
                ],
            ],
            'logo' => [
                'src' => '',
                'width' => 0,
                'height' => 0,
                'alt' => '',
            ],
            'button' => [
                'href' => '',
                'text' => '',
            ],
        ],
        'developmentCost' => [
            'MainTitle' => '',
            'desc' => '',
            'tabs' => [
                [
                    'label' => '',
                ],
            ],
            'tabContent' => [
                [
                    'ImgSrc' => '',
                    'title' => '',
                    'para' => '',
                ],
            ],
            'button' => [
                'href' => '',
                'text' => '',
            ],
            'btnLink' => '',
            'buttonLink' => '',
        ],
        'experience' => [
            'heading' => '',
            'metrics' => [
                [
                    'value' => '',
                    'label' => '',
                ],
            ],
            'links' => [
                [
                    'href' => '',
                    'text' => '',
                ],
            ],
            'image' => [
                'src' => '',
                'alt' => '',
            ],
        ],
        'expertTeam' => [
            'MainTitle' => '',
            'desc' => '',
            'imgSrc' => '',
            'style' => [
                'height' => '',
            ],
            'sliderData' => [
                [
                    'title' => '',
                    'organization' => '',
                    'challenge' => '',
                    'initiativeTextOne' => '',
                    'initiativeTextTwo' => '',
                    'initiativeTextThree' => '',
                    'percentage' => '',
                    'increase' => '',
                ],
            ],
            'labels' => [
                'organization' => '',
                'challenge' => '',
                'initiatives' => '',
                'result' => '',
            ],
            'imgAlt' => '',
            'imgWidth' => 0,
            'imgHeight' => 0,
            'sectionId' => '',
        ],
        'featureComparison' => [
            'heading' => '',
            'comparisonTableData' => [
                'headings' => [
                    'left' => '',
                    'right' => '',
                ],
                'rows' => [
                    [
                        'left' => '',
                        'right' => '',
                    ],
                ],
            ],
            'rightData' => [
                'comment' => '',
                'customerName' => '',
                'achivements' => [
                    [
                        'value' => '',
                        'detail' => '',
                    ],
                ],
            ],
            'icon' => [
                'src' => '',
                'alt' => '',
            ],
        ],
        'goals' => [
            'MainTitle' => '',
            'desc' => '',
            'cards' => [
                '',
            ],
            'bottomCards' => [
                '',
            ],
        ],
        'heroSection' => [
            'title' => '',
            'description' => '',
            'emailPlaceholder' => '',
            'submitText' => '',
            'image' => [
                'desktop' => '',
                'mobile' => '',
                'alt' => '',
            ],
            'stats' => [
                [
                    'value' => '',
                    'label' => '',
                    'average' => '',
                ],
            ],
        ],
        'homeBlog' => [
            'heading' => '',
            'viewMore' => [
                'href' => '',
                'text' => '',
            ],
            'posts' => [
                [
                    'title' => '',
                    'readMin' => '',
                    'imgSrc' => '',
                    'link' => '',
                ],
            ],
        ],
        'madeTheChoice' => [
            'MainTitle' => '',
            'headingclass' => '',
            'MadeTheChoiceData' => [
                [
                    'icon' => '',
                    'textone' => '',
                    'texttwo' => '',
                ],
            ],
        ],
        'marketing' => [
            'arrowIcon' => '',
            'items' => [
                [
                    'title' => '',
                    'description' => '',
                    'link' => '',
                    'imgSrc' => '',
                ],
            ],
        ],
        'ourHistory' => [
            'heading' => '',
            'paragraphs' => [
                '',
            ],
            'desktopImage' => [
                'src' => '',
                'alt' => '',
                'width' => 0,
                'height' => 0,
            ],
            'mobileImages' => [
                [
                    'src' => '',
                    'alt' => '',
                    'width' => 0,
                    'height' => 0,
                ],
            ],
        ],
        'partnerLogo' => [
            'heading' => '',
            'logos' => [
                [
                    'imgSrc' => '',
                    'imgalt' => '',
                ],
            ],
        ],
        'performCard' => [
            'heading' => '',
            'detail' => '',
            'cardContent' => [
                [
                    'icon' => '',
                    'title' => '',
                    'desc' => '',
                ],
            ],
        ],
        'policyContent' => [
            'title' => '',
            'content' => '',
        ],
        'portHero' => [
            'title' => '',
            'description' => '',
            'button' => [
                'href' => '',
                'text' => '',
            ],
        ],
        'portTabs' => [
            'heading' => '',
            'description' => '',
            'tabs' => [
                [
                    'id' => '',
                    'label' => '',
                    'items' => [
                        [
                            'type' => '',
                            'imgSrc' => '',
                            'previewSrc' => '',
                            'videoSrc' => '',
                            'thumbnail' => '',
                            'title' => '',
                            'category' => '',
                        ],
                    ],
                ],
            ],
        ],
        'realWorldTicker' => [
            'title' => '',
            'items' => [
                [
                    'count' => '',
                    'title' => '',
                    'description' => '',
                ],
            ],
        ],
        'revenueCalculate' => [
            'titleBefore' => '',
            'titleHighlight' => '',
            'description' => '',
            'descriptionHighlight' => '',
            'list' => [
                '',
            ],
            'cardTitle' => '',
            'currency' => '',
            'revenueMultiplier' => 0,
            'sliderIcon' => '',
            'buttonText' => '',
            'sliderOptions' => [
                [
                    'investment' => 0,
                    'revenue' => 0,
                ],
            ],
            'btn' => [
                'text' => '',
                'href' => '',
            ],
        ],
        'serCall' => [
            'contactOptions' => [
                [
                    'icon' => '',
                    'eyebrow' => '',
                    'label' => '',
                    'href' => '',
                    'iconAlt' => '',
                ],
            ],
            'heading' => '',
            'description' => '',
        ],
        'serContactForm' => [
            'uploadLabel' => '',
            'uploadAccessibleLabel' => '',
            'heading' => '',
            'eyebrow' => '',
            'steps' => [
                '',
            ],
            'formTitle' => '',
            'formNotice' => '',
            'formDescription' => '',
            'fields' => [
                'name' => [
                    'label' => '',
                    'placeholder' => '',
                ],
                'email' => [
                    'label' => '',
                    'placeholder' => '',
                ],
                'phone' => [
                    'label' => '',
                    'placeholder' => '',
                ],
                'projectDetails' => [
                    'label' => '',
                    'placeholder' => '',
                ],
            ],
            'submitText' => '',
        ],
        'serCta' => [
            'title' => '',
            'button' => [
                'href' => '',
                'text' => '',
            ],
            'image' => [
                'src' => '',
                'width' => 0,
                'height' => 0,
                'alt' => '',
            ],
            'rating' => [
                'label' => '',
                'stars' => [
                    'src' => '',
                    'width' => 0,
                    'height' => 0,
                    'alt' => '',
                ],
                'value' => '',
                'detail' => '',
            ],
        ],
        'serCtaThree' => [
            'title' => '',
            'button' => [
                'text' => '',
                'href' => '',
            ],
            'image' => [
                'src' => '',
                'width' => 0,
                'height' => 0,
                'alt' => '',
            ],
            'stats' => [
                'value' => '',
                'label' => '',
            ],
        ],
        'serCtaTwo' => [
            'title' => '',
            'button' => [
                'text' => '',
                'href' => '',
            ],
            'description' => '',
            'highlightText' => '',
            'image' => [
                'src' => '',
                'alt' => '',
                'width' => 0,
                'height' => 0,
            ],
        ],
        'serFaq' => [
            'MainTitle' => '',
            'faqData' => [
                [
                    'question' => '',
                    'answer' => '',
                ],
            ],
        ],
        'serLogo' => [
            'title' => '',
            'highlight' => '',
            'logos' => [
                [
                    'ImgSrc' => '',
                    'alt' => '',
                ],
            ],
        ],
        'serPrice' => [
            'heading' => '',
            'description' => '',
            'leftData' => [
                'heading' => '',
                'value' => '',
                'offerings' => [
                    [
                        'imgSrc' => '',
                        'title' => '',
                        'desc' => '',
                    ],
                ],
            ],
            'rightData' => [
                'heading' => '',
                'points' => [
                    [
                        'point' => '',
                    ],
                ],
            ],
            'ui' => [
                'icon' => [
                    'src' => '',
                    'alt' => '',
                ],
                'startingAt' => '',
                'priceSuffix' => '',
                'includedTitle' => '',
                'button' => [
                    'href' => '',
                    'text' => '',
                ],
                'background' => [
                    'src' => '',
                    'alt' => '',
                ],
            ],
        ],
        'serTable' => [
            'heading' => '',
            'description' => '',
            'tableData' => [
                'columns' => [
                    '',
                ],
                'features' => [
                    [
                        'title' => '',
                        'values' => [
                            '',
                        ],
                    ],
                ],
                'title' => '',
            ],
            'staticRowData' => [
                'title' => '',
                'phone' => [
                    'text' => '',
                    'link' => '',
                ],
                'values' => [
                    [
                        'link' => '',
                        'text' => '',
                    ],
                ],
            ],
            'scrollHint' => '',
        ],
        'serWhyBusiness' => [
            'tabs' => [
                [
                    'id' => '',
                    'label' => '',
                ],
            ],
            'tabContent' => [
                'TechWave' => [
                    'title' => '',
                    'para' => '',
                    'asideValue' => '',
                    'asideText' => '',
                ],
                'GreenLine' => [
                    'bg' => '',
                    'title' => '',
                    'para' => '',
                    'asideValue' => '',
                    'asideText' => '',
                ],
                'Pioneer' => [
                    'bg' => '',
                    'title' => '',
                    'para' => '',
                    'asideValue' => '',
                    'asideText' => '',
                ],
                'Quantum' => [
                    'bg' => '',
                    'title' => '',
                    'para' => '',
                    'asideValue' => '',
                    'asideText' => '',
                ],
            ],
            'heading' => [
                'beforeIcon' => '',
                'icon' => [
                    'src' => '',
                    'width' => 0,
                    'height' => 0,
                    'alt' => '',
                ],
                'afterIcon' => '',
                'ratingImage' => [
                    'src' => '',
                    'width' => 0,
                    'height' => 0,
                    'alt' => '',
                ],
            ],
            'initialTab' => '',
        ],
        'sertCard' => [
            'title' => '',
            'cards' => [
                [
                    'image' => [
                        'src' => '',
                        'width' => 0,
                        'height' => 0,
                        'alt' => '',
                    ],
                    'title' => '',
                    'description' => '',
                ],
            ],
            'highlight' => '',
        ],
        'sertContent' => [
            'heading' => '',
            'secContent' => [
                [
                    'title' => '',
                    'textOne' => '',
                    'color' => '',
                    'textTwo' => '',
                    'imgSrc' => '',
                ],
            ],
            'description' => '',
        ],
        'sertCta' => [
            'beforeFirstTag' => '',
            'firstTag' => [
                'text' => '',
                'href' => '',
            ],
            'betweenFirstSecondTag' => '',
            'secondTag' => [
                'text' => '',
                'href' => '',
            ],
            'betweenSecondThirdTag' => '',
            'thirdTag' => [
                'text' => '',
                'href' => '',
            ],
            'icons' => [
                'arrowIcon' => '',
                'starIcon' => '',
                'diamondIcon' => '',
                'heartIcon' => '',
            ],
        ],
        'sertCtaThree' => [
            'title' => '',
            'textBefore' => '',
            'textHighlight' => '',
            'textAfter' => '',
            'button' => [
                'text' => '',
                'href' => '',
            ],
        ],
        'sertCtaTwo' => [
            'title' => '',
            'button' => [
                'url' => '',
                'text' => '',
            ],
            'bgImg' => [
                'src' => '',
                'alt' => '',
            ],
            'description' => '',
            'secondary' => false,
        ],
        'sertHero' => [
            'title' => '',
            'del' => '',
            'detail' => '',
            'furtherDetail' => '',
            'logos' => [
                [
                    'src' => '',
                    'alt' => '',
                    'width' => 0,
                    'height' => 0,
                ],
            ],
            'formContent' => [
                'title' => '',
                'fields' => [
                    'fullName' => [
                        'label' => '',
                        'placeholder' => '',
                    ],
                    'email' => [
                        'label' => '',
                        'placeholder' => '',
                    ],
                    'phone' => [
                        'label' => '',
                        'placeholder' => '',
                    ],
                    'products' => [
                        'label' => '',
                        'placeholder' => '',
                        'options' => [
                            [
                                'value' => '',
                                'label' => '',
                            ],
                        ],
                    ],
                    'country' => [
                        'label' => '',
                    ],
                    'storeUrl' => [
                        'label' => '',
                        'placeholder' => '',
                    ],
                    'budget' => [
                        'label' => '',
                        'placeholder' => '',
                        'options' => [
                            [
                                'value' => '',
                                'label' => '',
                            ],
                        ],
                    ],
                    'expectations' => [
                        'label' => '',
                        'placeholder' => '',
                    ],
                ],
                'complimentaryTitle' => '',
                'complimentaryItems' => [
                    '',
                ],
                'ratings' => [
                    [
                        'label' => '',
                        'icon' => '',
                        'stars' => '',
                    ],
                ],
                'submitText' => '',
            ],
        ],
        'sertPackages' => [
            'title' => '',
            'packages' => [
                [
                    'title' => '',
                    'price' => '',
                    'priceDuration' => '',
                    'oldPrice' => '',
                    'discountText' => '',
                    'listTitle' => '',
                    'features' => [
                        '',
                    ],
                    'buttons' => [
                        [
                            'text' => '',
                            'href' => '',
                        ],
                    ],
                    'img' => '',
                    'description' => '',
                ],
            ],
            'description' => '',
        ],
        'sertPpcAccordion' => [
            'heading' => '',
            'data' => [
                [
                    'id' => 0,
                    'title' => '',
                    'icon' => '',
                    'contentTitle' => '',
                    'contentDesc' => '',
                    'image' => '',
                ],
            ],
            'description' => '',
        ],
        'sertSlider' => [
            'sliderSettings' => [
                'delay' => 0,
            ],
            'slides' => [
                [
                    'alignCenter' => false,
                    'items' => [
                        [
                            'type' => '',
                            'value' => '',
                            'label' => '',
                            'src' => '',
                            'width' => 0,
                            'height' => 0,
                            'alt' => '',
                        ],
                    ],
                ],
            ],
        ],
        'sertSolutions' => [
            'heading' => '',
            'cards' => [
                [
                    'title' => '',
                    'detail' => '',
                    'icon' => [
                        'src' => '',
                        'alt' => '',
                    ],
                ],
            ],
        ],
        'sertTicker' => [
            'heading' => '',
            'description' => '',
            'tabContent' => [
                [
                    'title' => 0,
                    'sign' => '',
                    'unit' => '',
                    'text' => '',
                ],
            ],
        ],
        'sertTwoColumn' => [
            'MainTitle' => '',
            'twColData' => [
                'title' => '',
                'tags' => '',
                'description' => '',
                'image' => [
                    'url' => '',
                    'alt' => '',
                ],
            ],
            'reverse' => false,
        ],
        'serviceHero' => [
            'heading' => '',
            'description' => '',
            'btn' => [
                'text' => '',
            ],
            'services' => [
                [
                    'value' => '',
                    'label' => '',
                    'average' => '',
                ],
            ],
            'form' => [
                'label' => '',
                'placeholder' => '',
                'submitText' => '',
            ],
            'testimonial' => [
                'decoration' => [
                    'src' => '',
                    'width' => 0,
                    'height' => 0,
                    'alt' => '',
                ],
                'quote' => '',
                'sourceLogo' => [
                    'src' => '',
                    'width' => 0,
                    'height' => 0,
                    'alt' => '',
                ],
                'company' => '',
                'source' => '',
                'ratingImage' => [
                    'src' => '',
                    'width' => 0,
                    'height' => 0,
                    'alt' => '',
                ],
                'ratings' => [
                    [
                        'label' => '',
                    ],
                ],
                'averageLabel' => '',
            ],
        ],
        'subService' => [
            'MainTitle' => '',
            'desc' => '',
            'btnText' => '',
            'SubServiceData' => [
                [
                    'ImgSrc' => '',
                    'title' => '',
                    'desc' => '',
                ],
            ],
            'btnHref' => '',
        ],
        'successGraph' => [
            'heading' => '',
            'cta' => [
                'href' => '',
                'text' => '',
            ],
            'mobileImage' => '',
            'tabs' => [
                [
                    'id' => '',
                    'label' => '',
                ],
            ],
            'tabContent' => [
                'organic' => [
                    'bg' => '',
                    'title' => '',
                    'para' => '',
                    'asideValue' => '',
                    'asideText' => '',
                    'asideDesc' => '',
                ],
                'revenue' => [
                    'bg' => '',
                    'title' => '',
                    'para' => '',
                    'asideValue' => '',
                    'asideText' => '',
                    'asideDesc' => '',
                ],
                'roi' => [
                    'bg' => '',
                    'title' => '',
                    'para' => '',
                    'asideValue' => '',
                    'asideText' => '',
                    'asideDesc' => '',
                ],
                'ctr' => [
                    'bg' => '',
                    'title' => '',
                    'para' => '',
                    'asideValue' => '',
                    'asideText' => '',
                    'asideDesc' => '',
                ],
                'market' => [
                    'bg' => '',
                    'title' => '',
                    'para' => '',
                    'asideValue' => '',
                    'asideText' => '',
                    'asideDesc' => '',
                ],
            ],
        ],
        'tableOfContent' => [
            'content' => [
                [
                    'className' => '',
                    'text' => '',
                    'beforePhone' => '',
                    'phone' => '',
                    'phoneHref' => '',
                    'betweenText' => '',
                    'email' => '',
                    'emailHref' => '',
                    'afterText' => '',
                    'type' => '',
                ],
            ],
            'toc' => [
                'title' => '',
                'arrowIcon' => '',
                'links' => [
                    [
                        'text' => '',
                        'href' => '',
                    ],
                ],
                'button' => [
                    'text' => '',
                    'href' => '',
                ],
            ],
            'defaultOpen' => false,
        ],
        'technologies' => [
            'heading' => '',
            'stats' => [
                [
                    'key' => '',
                    'label' => '',
                    'icon' => '',
                    'iconAlt' => '',
                ],
            ],
            'groups' => [
                [
                    'category' => '',
                    'items' => [
                        [
                            'title' => '',
                            'icon' => '',
                            'experience' => '',
                            'projects' => '',
                            'workforce' => '',
                            'description' => '',
                        ],
                    ],
                ],
            ],
        ],
        'termsContent' => [
            'title' => '',
            'content' => '',
        ],
        'vaCard' => [
            'title' => '',
            'cardContent' => [
                [
                    'icon' => '',
                    'title' => '',
                    'detail' => '',
                ],
            ],
        ],
        'vaHero' => [
            'rollingWords' => [
                '',
            ],
            'titleSuffix' => '',
            'titleAccent' => '',
            'reelIcon' => '',
            'reelText' => '',
            'title' => '',
            'buttons' => [
                [
                    'text' => '',
                    'href' => '',
                ],
            ],
            'bgReel' => [
                'src' => '',
            ],
        ],
        'vaMedia' => [
            'title' => '',
            'description' => '',
            'video' => [
                'src' => '',
                'autoplay' => false,
                'loop' => false,
                'muted' => false,
                'type' => '',
                'bg' => '',
            ],
            'button' => [
                'url' => '',
                'text' => '',
            ],
            'img' => [
                'src' => '',
                'alt' => '',
            ],
        ],
        'vaProcess' => [
            'title' => '',
            'description' => '',
            'steps' => [
                [
                    'step' => '',
                    'title' => '',
                    'desc' => '',
                    'icon' => '',
                ],
            ],
        ],
        'vaWork' => [
            'title' => '',
            'description' => '',
            'content' => [
                [
                    'thumbnail' => '',
                    'src' => '',
                ],
            ],
        ],
        'weBelieve' => [
            'heading' => '',
            'description' => '',
            'initialTab' => '',
            'tabs' => [
                [
                    'id' => '',
                    'label' => '',
                ],
            ],
            'tabContent' => [
                'vision' => [
                    'title' => '',
                    'para' => '',
                ],
                'mission' => [
                    'title' => '',
                    'para' => '',
                ],
                'quality' => [
                    'title' => '',
                    'para' => '',
                ],
            ],
        ],
        'whyBusinesses' => [
            'sectionId' => '',
            'heading' => '',
            'headingIcon' => '',
            'headingSuffix' => '',
            'tabs' => [
                [
                    'id' => '',
                    'label' => '',
                ],
            ],
            'slidesData' => [
                'tab-web-dev' => [
                    [
                        'text1' => '',
                        'text2' => '',
                        'text3' => '',
                        'text4' => '',
                        'starImg' => '',
                    ],
                ],
                'tab-seo' => [
                    [
                        'text1' => '',
                        'text2' => '',
                        'text3' => '',
                        'text4' => '',
                        'starImg' => '',
                    ],
                ],
                'tab-digital-marketing' => [
                    [
                        'text1' => '',
                        'text2' => '',
                        'text3' => '',
                        'text4' => '',
                        'starImg' => '',
                    ],
                ],
                'tab-video-animation' => [
                    [
                        'text1' => '',
                        'text2' => '',
                        'text3' => '',
                        'text4' => '',
                        'starImg' => '',
                    ],
                ],
                'tab-performance-marketing' => [
                    [
                        'text1' => '',
                        'text2' => '',
                        'text3' => '',
                        'text4' => '',
                        'starImg' => '',
                    ],
                ],
                'tab-amazon-marketing' => [
                    [
                        'text1' => '',
                        'text2' => '',
                        'text3' => '',
                        'text4' => '',
                        'starImg' => '',
                    ],
                ],
            ],
        ],
        'whyTrust' => [
            'tag' => '',
            'heading' => '',
            'description' => '',
            'cardContent' => [
                [
                    'imgSrc' => '',
                    'title' => '',
                    'desc' => '',
                ],
            ],
        ],
        'yourTeam' => [
            'heading' => '',
            'button' => [
                'href' => '',
                'text' => '',
            ],
            'images' => [
                [
                    'imgsrc' => '',
                    'title' => '',
                ],
            ],
        ],
    ];

    public static function prototype(string $type): array
    {
        return array_replace_recursive(match ($type) {
            'marketing', 'aboutSolution' => ['linkText' => ''],
            'expertTeam' => ['labels' => ['organization' => '', 'challenge' => '', 'initiatives' => '', 'result' => ''], 'imgAlt' => ''],
            default => [],
        }, self::FIELDS[$type] ?? []);
    }
}
