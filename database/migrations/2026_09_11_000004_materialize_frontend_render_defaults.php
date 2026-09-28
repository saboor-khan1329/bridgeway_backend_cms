<?php

use App\Models\ContentBlock;
use App\Models\SiteSetting;
use App\Support\ContentFieldStore;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'serviceHero' => [
                'form' => ['label' => 'Enter your details', 'placeholder' => 'Enter your details', 'submitText' => 'Get Your Free Quote'],
                'testimonial' => [
                    'decoration' => ['src' => '/images/service/middle-box-top.svg', 'width' => 143, 'height' => 28, 'alt' => ''],
                    'quote' => 'Bridgeway has been a game-changer for our web strategy.',
                    'sourceLogo' => ['src' => '/images/service/service-clutch-icon.svg', 'width' => 27, 'height' => 30, 'alt' => 'Clutch'],
                    'company' => 'U.S. Healthcare Company', 'source' => 'Review from Clutch.co',
                    'ratingImage' => ['src' => '/images/service/rating-star.svg', 'width' => 66, 'height' => 11, 'alt' => 'Review rating'],
                    'ratings' => [['label' => 'Rated 4.9/5 stars on G2'], ['label' => 'Rated 4.9/5 stars on Clutch']],
                    'averageLabel' => 'Industry average',
                ],
            ],
            'serLogo' => [
                'title' => 'Trusted by startups and Fortune 500 companies', 'highlight' => '500',
                'logos' => [
                    ['ImgSrc' => '/images/service/ser-logo-1.svg', 'alt' => 'Client logo'],
                    ['ImgSrc' => '/images/service/ser-logo-2.webp', 'alt' => 'Client logo'],
                    ['ImgSrc' => '/images/service/ser-logo-3.webp', 'alt' => 'Client logo'],
                    ['ImgSrc' => '/images/service/ser-logo-4.webp', 'alt' => 'Client logo'],
                    ['ImgSrc' => '/images/service/ser-logo-5.webp', 'alt' => 'Client logo'],
                    ['ImgSrc' => '/images/service/ser-logo-6.webp', 'alt' => 'Client logo'],
                ],
            ],
            'ctaOne' => [
                'logo' => ['src' => '/images/white-logo.svg', 'width' => 200, 'height' => 200, 'alt' => 'BridgeWay Digital'],
                'button' => ['href' => '/get-a-free-quote', 'text' => 'GET STARTED ➜'],
            ],
            'serPrice' => ['ui' => [
                'icon' => ['src' => '/images/service/ser-price-img-1.svg', 'alt' => 'Custom development'],
                'startingAt' => 'Starting at', 'priceSuffix' => '/ project', 'includedTitle' => 'What’s Included:',
                'button' => ['href' => '/get-a-free-quote', 'text' => 'GET MY CUSTOM QUOTE ➜'],
                'background' => ['src' => '/images/service/SerPrice-bg.webp', 'alt' => ''],
            ]],
            'featureComparison' => ['icon' => ['src' => '/images/service/Sub_Service-1.svg', 'alt' => 'Code']],
            'serWhyBusiness' => ['heading' => [
                'beforeIcon' => 'Here’s why Businesses',
                'icon' => ['src' => '/images/home/headt-img.webp', 'width' => 50, 'height' => 50, 'alt' => ''],
                'afterIcon' => 'BridgeWay Digital',
                'ratingImage' => ['src' => '/images/home/why-businesses-star.webp', 'width' => 180, 'height' => 24, 'alt' => 'Five-star rating'],
            ]],
            'serCta' => [
                'title' => 'Bridgeway Digital has earned hundreds of reviews praising our efficiency, expertise, and client-focused approach.',
                'button' => ['href' => '#', 'text' => 'Client Testimonials ➜'],
                'image' => ['src' => '/images/service/ser-cta-img.webp', 'width' => 258, 'height' => 255, 'alt' => 'Client Testimonials'],
                'rating' => [
                    'label' => 'BRIDGEWAY DIGITAL AGENCY RATING',
                    'stars' => ['src' => '/images/service/ser-star.svg', 'width' => 114, 'height' => 18, 'alt' => 'star'],
                    'value' => '4.9/5', 'detail' => 'BASED ON OVER 500 THIRD-PARTY REVIEWS',
                ],
            ],
            'serContactForm' => [
                'eyebrow' => 'Engage with us on your terms.',
                'steps' => ['Fill in our quick form', 'Our consultant will contact you to discuss your needs', 'Receive your website delivered securely before the deadline'],
                'formTitle' => 'Book a Consultation',
                'formNotice' => 'No Payment or Credit Card Required. Initially',
                'formDescription' => "Complete the form below and we'll contact you with further instructions and details about your project.",
                'fields' => [
                    'name' => ['label' => 'Name', 'placeholder' => 'Name:'],
                    'email' => ['label' => 'Email', 'placeholder' => 'Email:'],
                    'phone' => ['label' => 'Phone Number', 'placeholder' => 'Phone Number:'],
                    'projectDetails' => ['label' => 'Define Your Project', 'placeholder' => 'Define Your Project (Optional)'],
                ],
                'submitText' => 'Submit',
            ],
            'serTable' => ['tableData' => ['title' => 'Features'], 'scrollHint' => '👉 Swipe to view more'],
            'developmentCost' => ['button' => ['href' => '/get-a-free-quote', 'text' => 'GET YOUR FREE AUDIT →']],
            'sertHero' => [
                'logos' => [
                    ['src' => '/images/service/hero-logo-1.webp', 'alt' => 'Partner logo', 'width' => 150, 'height' => 80],
                    ['src' => '/images/service/hero-logo-2.webp', 'alt' => 'Partner logo', 'width' => 215, 'height' => 80],
                    ['src' => '/images/service/hero-logo-3.webp', 'alt' => 'Partner logo', 'width' => 100, 'height' => 80],
                ],
                'formContent' => [
                    'title' => 'Get a Free Amazon Store Audit!',
                    'fields' => [
                        'fullName' => ['label' => 'Full Name', 'placeholder' => 'Full Name'],
                        'email' => ['label' => 'Email Address', 'placeholder' => 'Your Email'],
                        'phone' => ['label' => 'Phone Number', 'placeholder' => '(201) 555-0123'],
                        'products' => ['label' => 'Number of Products Listed', 'placeholder' => 'Please Select', 'options' => [
                            ['value' => '1-10', 'label' => '1-10'], ['value' => '11-50', 'label' => '11-50'],
                            ['value' => '51-100', 'label' => '51-100'], ['value' => '100+', 'label' => '100+'],
                        ]],
                        'country' => ['label' => 'Country'],
                        'storeUrl' => ['label' => 'Website URL/Amazon Store URL', 'placeholder' => 'Website URL/Amazon Store URL'],
                        'budget' => ['label' => 'Estimated Budget', 'placeholder' => 'Select Price', 'options' => [
                            ['value' => '$0 - $500', 'label' => '$0 - $500'], ['value' => '$500 - $1000', 'label' => '$500 - $1000'],
                            ['value' => '$1000 - $5000', 'label' => '$1000 - $5000'], ['value' => '$5000+', 'label' => '$5000+'],
                        ]],
                        'expectations' => ['label' => 'What are your expectations from us?', 'placeholder' => "Let's break the ice. Write in as much detail as you want."],
                    ],
                    'complimentaryTitle' => 'Complimentary Audit Includes:',
                    'complimentaryItems' => ['Keyword Research', 'Growth Potential', 'Expert Recommendations'],
                    'ratings' => [
                        ['label' => 'Rated 5/5 stars', 'icon' => '/images/service/clutch-review-icon.webp', 'stars' => '/images/service/clutch-review-star.webp'],
                        ['label' => 'Rated 5/5 stars', 'icon' => '/images/service/clutch-review-icon.webp', 'stars' => '/images/service/clutch-review-star.webp'],
                    ],
                    'submitText' => 'SUBMIT',
                ],
            ],
        ];

        ContentBlock::query()->whereIn('type', array_keys($defaults))->with('fields')
            ->each(function (ContentBlock $block) use ($defaults): void {
                ContentFieldStore::replace($block, $this->mergeMissing($defaults[$block->type], $block->cmsData()));
            });

        SiteSetting::bulkPut([
            'blog_default_image' => '/images/blogs/blog-1-img.webp',
            'blog_default_image_alt' => 'Blog image',
            'blog_default_author_image' => '/images/blogs/author-icon.webp',
            'blog_default_author_image_alt' => 'Blog author',
            'blog_default_author_name' => 'BWD Admin',
        ], 'branding');
    }

    public function down(): void
    {
        SiteSetting::query()->whereIn('key', ['blog_default_image', 'blog_default_image_alt', 'blog_default_author_image', 'blog_default_author_image_alt', 'blog_default_author_name'])->delete();
    }

    private function mergeMissing(array $defaults, array $content): array
    {
        foreach ($defaults as $key => $default) {
            if (! array_key_exists($key, $content) || $content[$key] === null || $content[$key] === '') {
                $content[$key] = $default;
            } elseif (is_array($default) && ! array_is_list($default) && is_array($content[$key])) {
                $content[$key] = $this->mergeMissing($default, $content[$key]);
            }
        }

        return $content;
    }
};
