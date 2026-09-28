<?php

namespace Tests\Unit;

use App\Support\ImageField;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ImageFieldTest extends TestCase
{
    public function test_single_rules_includes_width_and_height_metadata_fields(): void
    {
        $rules = ImageField::singleRules('featured_image');

        $this->assertArrayHasKey('featured_image.path', $rules);
        $this->assertArrayHasKey('featured_image.alt', $rules);
        $this->assertArrayHasKey('featured_image.title', $rules);
        $this->assertArrayHasKey('featured_image.caption', $rules);
        $this->assertArrayHasKey('featured_image.width', $rules);
        $this->assertArrayHasKey('featured_image.height', $rules);

        $validator = Validator::make([
            'featured_image' => [
                'path' => 'uploads/image.png',
                'alt' => 'Alt text',
                'title' => 'Title attribute',
                'caption' => 'A photo caption',
                'width' => 1200,
                'height' => 800,
            ],
        ], $rules);

        $this->assertFalse($validator->fails());
    }

    public function test_single_rules_rejects_negative_or_oversized_dimensions(): void
    {
        $rules = ImageField::singleRules('banner');

        $validator = Validator::make([
            'banner' => [
                'width' => -10,
                'height' => 99999,
            ],
        ], $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('banner.width', $validator->errors()->toArray());
        $this->assertArrayHasKey('banner.height', $validator->errors()->toArray());
    }

    public function test_remap_validation_exception_maps_width_and_height_keys(): void
    {
        $innerException = ValidationException::withMessages([
            'width' => ['Must be a valid integer.'],
            'height' => ['Height cannot exceed 10000.'],
        ]);

        $remapped = ImageField::remapValidationException('gallery', $innerException);

        $this->assertArrayHasKey('gallery.width', $remapped->errors());
        $this->assertArrayHasKey('gallery.height', $remapped->errors());
    }
}
