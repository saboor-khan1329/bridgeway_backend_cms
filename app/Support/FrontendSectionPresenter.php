<?php

namespace App\Support;

use App\Models\ContentBlock;

/** API-only derived values; the frontend never applies business formulas. */
class FrontendSectionPresenter
{
    public static function data(ContentBlock $block): array
    {
        $data = $block->cmsData();
        if ($block->type === 'revenueCalculate') {
            foreach ($data['sliderOptions'] ?? [] as $index => $option) {
                if (! isset($option['revenue']) && isset($option['investment'], $data['revenueMultiplier'])) {
                    $data['sliderOptions'][$index]['revenue'] = round(
                        (float) $option['investment'] * (float) $data['revenueMultiplier'], 2
                    );
                }
            }
        }
        return $data;
    }
}
