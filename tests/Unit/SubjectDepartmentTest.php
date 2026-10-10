<?php

namespace Tests\Unit;

use App\Support\SubjectDepartment;
use PHPUnit\Framework\TestCase;

class SubjectDepartmentTest extends TestCase
{
    private const DEPARTMENTS = ['Hoshishi', 'Alder', 'Birch', 'Cedar', 'Ember', 'Orb', 'Orbit', 'Fjord', 'Glade', 'Juniper', 'Harbor Town', 'Harbor Camp', 'Others'];

    public function test_it_reads_the_department_from_the_start_of_the_subject(): void
    {
        $expectations = [
            'Alder - Printer jammed' => 'Alder',
            'Juniper ANTIQUE - Starlink offline' => 'Juniper',
            'Fjord BARMM ELECTION - Router request' => 'Fjord',
            'fjord - lower case still counts' => 'Fjord',
            'Philippine Ember - Data allocation request' => 'Ember',
            'Cedar Region 3 - Laptops not charging' => 'Cedar',
            'Birch FO 5 Sir Rodel - Activation' => 'Birch',
            'Hoshishi 3rd Floor - Door access not working' => 'Hoshishi',
            'Glade GHQ â€“ en dash separator' => 'Glade',
            'Glade GHQ â€” em dash separator' => 'Glade',
            'Harbor Town - Whole phrase' => 'Harbor Town',
        ];

        foreach ($expectations as $subject => $department) {
            $this->assertSame($department, SubjectDepartment::match($subject, self::DEPARTMENTS), $subject);
        }
    }

    public function test_a_name_only_matches_as_a_whole_word(): void
    {
        $this->assertSame('Orbit', SubjectDepartment::match('Orbit 5 - Survey', self::DEPARTMENTS));
        $this->assertSame('Orb', SubjectDepartment::match('Orb Region 6 - Survey', self::DEPARTMENTS));
        $this->assertNull(SubjectDepartment::match('DARWIN CITY HALL - Survey', ['Orb', 'Orbit']));
        $this->assertSame('Juniper', SubjectDepartment::match('PARTNER_Juniper R1-2 - Starlink', self::DEPARTMENTS));
        $this->assertSame('Juniper', SubjectDepartment::match('Juniper-R6 - Relocation', self::DEPARTMENTS));
    }

    public function test_the_earliest_mention_wins_and_ties_go_to_the_longer_name(): void
    {
        $this->assertSame('Alder', SubjectDepartment::match('Alder Juniper - Shared site', self::DEPARTMENTS));
        $this->assertSame('Juniper', SubjectDepartment::match('Juniper Alder - Shared site', self::DEPARTMENTS));
        $this->assertSame('Harbor Town', SubjectDepartment::match('Harbor Town - Camera', ['Harbor', 'Harbor Town']));
    }

    public function test_only_the_prefix_counts(): void
    {
        $this->assertNull(SubjectDepartment::match('Printer jammed - Alder office', self::DEPARTMENTS));
        $this->assertNull(SubjectDepartment::match('Alder printer jammed', self::DEPARTMENTS), 'no separator, so no prefix');
        $this->assertNull(SubjectDepartment::match('', self::DEPARTMENTS));
        $this->assertNull(SubjectDepartment::match(' - Alder', self::DEPARTMENTS));
    }

    public function test_an_organisation_that_is_not_a_department_matches_nothing(): void
    {
        $this->assertNull(SubjectDepartment::match('HARBOR BAYVIEW RIDGE - No signal', self::DEPARTMENTS));
        $this->assertNull(SubjectDepartment::match('PPA - MICT - No Active Subscription', self::DEPARTMENTS));
        $this->assertNull(SubjectDepartment::match('Jyra Wong - Shipping of 1 Starlink', self::DEPARTMENTS));
    }

    public function test_names_with_punctuation_are_matched_literally(): void
    {
        $this->assertSame('St. Mary (Main)', SubjectDepartment::match('St. Mary (Main) - Wifi down', ['St. Mary (Main)', 'St']));
        $this->assertNull(SubjectDepartment::match('Stx Mary - Wifi down', ['St. Mary']));
    }

    public function test_the_prefix_is_the_text_before_the_first_spaced_dash(): void
    {
        $this->assertSame('Juniper-R6', SubjectDepartment::prefix('Juniper-R6 - SIS - Iloilo'));
        $this->assertNull(SubjectDepartment::prefix('No separator here'));
        $this->assertNull(SubjectDepartment::prefix('Hyphen-without-spaces'));
    }

    public function test_text_that_is_not_valid_utf8_has_no_prefix_instead_of_failing(): void
    {
        $this->assertNull(SubjectDepartment::prefix('±1 - Alder'));
        $this->assertNull(SubjectDepartment::match('±1 - Alder', self::DEPARTMENTS));
    }
}
