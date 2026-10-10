<?php

namespace App\Enums;

/**
 * Structured root-cause taxonomy captured when a ticket is resolved or closed,
 * so Root Cause Analysis reports can group issues instead of just listing free text.
 */
enum RootCauseCategory: string
{
    case Hardware = 'hardware';
    case Network = 'network';
    case SoftwareBug = 'software_bug';
    case UserError = 'user_error';
    case ExternalVendor = 'external_vendor';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Hardware => 'Hardware',
            self::Network => 'Network',
            self::SoftwareBug => 'Software Bug',
            self::UserError => 'User Error',
            self::ExternalVendor => 'External / Vendor',
            self::Other => 'Other',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
