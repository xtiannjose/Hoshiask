<?php

namespace Tests\Unit;

use App\Support\PhilippineLocations;
use Tests\TestCase;

class PhilippineLocationsTest extends TestCase
{
    public function test_recognizes_official_province_names_case_insensitively(): void
    {
        $this->assertTrue(PhilippineLocations::isValidProvince('Cebu'));
        $this->assertTrue(PhilippineLocations::isValidProvince('cebu'));
        $this->assertTrue(PhilippineLocations::isValidProvince('NCR'));
        $this->assertFalse(PhilippineLocations::isValidProvince('Atlantis'));
        $this->assertFalse(PhilippineLocations::isValidProvince(''));
    }

    public function test_recognizes_common_province_aliases(): void
    {
        $this->assertTrue(PhilippineLocations::isValidProvince('Metro Manila'));
        $this->assertTrue(PhilippineLocations::isValidProvince('metro manila'));
        $this->assertTrue(PhilippineLocations::isValidProvince('National Capital Region'));
    }

    public function test_validates_a_municipality_belongs_to_its_province(): void
    {
        $this->assertTrue(PhilippineLocations::isValidPair('NCR', 'Pasig'));
        $this->assertTrue(PhilippineLocations::isValidPair('Metro Manila', 'Pasig'));
        $this->assertTrue(PhilippineLocations::isValidPair('Cebu', 'Mandaue'));
        $this->assertFalse(PhilippineLocations::isValidPair('Cebu', 'Pasig'));
        $this->assertFalse(PhilippineLocations::isValidPair('Atlantis', 'Pasig'));
    }

    public function test_tolerates_a_redundant_or_missing_city_suffix(): void
    {
        $this->assertTrue(PhilippineLocations::isValidPair('NCR', 'Pasig City'));
        $this->assertTrue(PhilippineLocations::isValidPair('NCR', 'pasig city'));
        $this->assertTrue(PhilippineLocations::isValidPair('Cebu', 'Mandaue City'));
        $this->assertTrue(PhilippineLocations::isValidPair('Davao del Sur', 'Davao'));
    }

    public function test_provinces_list_covers_all_81_official_provinces_plus_ncr(): void
    {
        $this->assertCount(82, PhilippineLocations::provinces());
    }
}
