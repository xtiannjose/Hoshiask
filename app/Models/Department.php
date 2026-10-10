<?php

namespace App\Models;

use App\Models\Concerns\GeneratesUniqueSlug;
use App\Services\SupportBrandingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Department extends Model
{
    use GeneratesUniqueSlug;
    use HasFactory;

    private const MANAGED_LOGO_PREFIXES = [
        'storage/department-logos/',
        'images/departments/',
    ];

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
    ];

    protected static function booted(): void
    {
        static::saved(function (): void {
            User::flushDepartmentCaches();
            SupportBrandingService::flushDepartmentCache();
        });

        static::deleted(function (): void {
            User::flushDepartmentCaches();
            SupportBrandingService::flushDepartmentCache();
        });
    }

    public function getResolvedLogoPathAttribute(): string
    {
        $logoPath = trim((string) $this->logo_path);
        if ($logoPath !== '' && file_exists(public_path($logoPath))) {
            return $logoPath;
        }

        return 'images/Others Logo.png';
    }

    public function getLogoUrlAttribute(): string
    {
        return asset($this->resolved_logo_path);
    }

    public function usesManagedLogo(): bool
    {
        foreach (self::MANAGED_LOGO_PREFIXES as $prefix) {
            if (str_starts_with((string) $this->logo_path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function managedLogoStoragePath(): ?string
    {
        if (! $this->usesManagedLogo()) {
            return null;
        }

        foreach (self::MANAGED_LOGO_PREFIXES as $prefix) {
            if (str_starts_with((string) $this->logo_path, $prefix)) {
                return Str::after((string) $this->logo_path, $prefix);
            }
        }

        return null;
    }

    public function deleteManagedLogoIfPresent(): void
    {
        $storagePath = $this->managedLogoStoragePath();
        if ($storagePath === null) {
            return;
        }

        Storage::disk('department-logos')->delete($storagePath);
    }

    public static function generateAvailableSlug(string $name, ?int $ignoreDepartmentId = null): string
    {
        return self::uniqueSlugFor($name, 'department', $ignoreDepartmentId);
    }
}
