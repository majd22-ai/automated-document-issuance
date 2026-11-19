<?php

namespace Tests\Feature;

use App\Services\ResidenceLocations;
use App\Services\ResidencePhones;
use Tests\TestCase;

class ResidencePhonesTest extends TestCase
{
    public function test_yemen_accepts_nine_local_digits_with_or_without_prefix_and_rejects_ten(): void
    {
        $phones = app(ResidencePhones::class);
        foreach (['777123456', '+967777123456', '00967777123456'] as $phone) {
            $this->assertSame([], $phones->errors(['residence_country' => 'اليمن', 'phone' => $phone]));
        }
        foreach (['7771234567', '+9677771234567', '009677771234567', '+966777123456'] as $phone) {
            $this->assertArrayHasKey('data.phone', $phones->errors(['residence_country' => 'اليمن', 'phone' => $phone]));
        }
    }

    public function test_optional_phone_and_witnesses_use_same_limit_and_other_countries_allow_longer_numbers(): void
    {
        $phones = app(ResidencePhones::class);
        $this->assertSame([], $phones->errors(['residence_country' => 'اليمن', 'alternate_phone' => '']));
        $errors = $phones->errors(['residence_country' => 'اليمن', 'alternate_phone' => '7771234567'], [['phone' => '+9677771234567']]);
        $this->assertArrayHasKey('data.alternate_phone', $errors);
        $this->assertArrayHasKey('witnesses.0.phone', $errors);
        $this->assertSame([], $phones->errors(['residence_country' => 'السعودية', 'phone' => '+966501234567']));
        $this->assertFalse(config('identity_card.fields.3.residence_village.required'));
    }

    public function test_all_configured_middle_east_countries_exist_in_directory_with_calling_codes(): void
    {
        $countries = array_column(app(ResidenceLocations::class)->countries(), 'code');
        $this->assertCount(17, config('residence_phones'));
        foreach (config('residence_phones') as $country => $code) {
            $this->assertContains($country, $countries);
            $this->assertMatchesRegularExpression('/^[1-9][0-9]{0,2}$/', $code);
        }
    }
}
