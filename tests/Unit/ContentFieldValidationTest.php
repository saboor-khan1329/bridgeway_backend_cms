<?php

namespace Tests\Unit;

use App\Support\ContentFieldStore;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContentFieldValidationTest extends TestCase
{
    public function test_numeric_and_boolean_values_keep_their_types(): void
    {
        $this->assertSame(['amount' => 42, 'ratio' => 2.5, 'enabled' => false],
            ContentFieldStore::normalize(['amount' => '42', 'ratio' => '2.5', 'enabled' => '0'],
                ['amount' => 0, 'ratio' => 0.0, 'enabled' => false]));
    }

    public function test_invalid_numeric_input_is_rejected_instead_of_becoming_zero(): void
    {
        $this->expectException(ValidationException::class);
        ContentFieldStore::normalize('not a price', 0);
    }

    public function test_invalid_checkbox_input_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        ContentFieldStore::normalize('maybe', false);
    }
}
