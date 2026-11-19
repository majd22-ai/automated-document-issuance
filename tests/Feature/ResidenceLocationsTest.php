<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ResidenceLocations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResidenceLocationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('In-memory database required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_directory_contains_countries_and_all_yemen_districts_from_existing_directory(): void
    {
        $locations = app(ResidenceLocations::class);
        $this->assertGreaterThan(200, count($locations->countries()));
        $this->assertSame('YE', $locations->countryCode('اليمن'));
        $this->assertSame('SA', $locations->countryCode('السعودية'));
        $yemen = $locations->governorates('YE');
        $this->assertSame(22, count($yemen));
        $this->assertGreaterThanOrEqual(333, collect($yemen)->sum(fn ($row) => count($row['districts'])));
    }

    public function test_foreign_country_does_not_receive_yemen_governorates_and_endpoint_requires_login(): void
    {
        $this->getJson(route('locations.residence', 'SA'))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson(route('locations.residence', 'SA'))
            ->assertOk()->assertJsonPath('country', 'SA')->assertJsonCount(13, 'governorates');
        $regions = app(ResidenceLocations::class)->governorates('SA');
        $this->assertNotContains('تعز', array_column($regions, 'name'));
        foreach ($regions as $region) {
            $this->assertStringStartsWith('SA.', $region['id']);
            foreach ($region['districts'] as $district) {
                $this->assertStringStartsWith($region['id'].'.', $district['id']);
            }
        }
    }

    public function test_cross_country_and_cross_governorate_values_are_rejected(): void
    {
        $locations = app(ResidenceLocations::class);
        $this->assertArrayHasKey('data.residence_governorate', $locations->errors(['residence_country' => 'السعودية', 'residence_governorate' => 'تعز', 'residence_district' => 'المعافر']));
        $regions = collect($locations->governorates('YE'))->filter(fn ($row) => count($row['districts']) > 0)->values();
        $data = ['residence_country' => 'اليمن', 'residence_governorate' => $regions[0]['name'], 'residence_district' => $regions[1]['districts'][0]['name']];
        $this->assertArrayHasKey('data.residence_district', $locations->errors($data));
        $data['residence_district'] = $regions[0]['districts'][0]['name'];
        $this->assertSame([], $locations->errors($data));
    }

    public function test_country_code_cannot_be_used_to_read_arbitrary_files(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/locations/residence/ZZ')->assertNotFound();
        $this->getJson('/locations/residence/countryInfo.txt')->assertNotFound();
    }

    public function test_every_country_has_a_well_formed_local_directory(): void
    {
        $locations = app(ResidenceLocations::class);
        foreach ($locations->countries() as $country) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $country['code']);
            $this->assertNotEmpty($country['name']);
            $rows = $locations->governorates($country['code']);
            $this->assertIsArray($rows);
            foreach ($rows as $row) {
                $this->assertNotEmpty($row['name']);
                $this->assertIsArray($row['districts']);
            }
        }
    }
}
