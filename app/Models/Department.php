<?php

namespace App\Models;

use App\Models\Concerns\GeneratesUniqueSlug;
use App\Services\SupportBrandingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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

    /**
     * Why this department cannot be deleted, or null when it can. Accounts and tickets keep their department as
     * text and the filters list only the departments that exist, so deleting one that is still used would leave
     * them with a name that no list offers. Pass the counts when the caller already has them.
     */
    public function deletionBlockedReason(?int $userCount = null, ?int $ticketCount = null): ?string
    {
        if (strcasecmp($this->name, User::supportDepartment()) === 0) {
            return "{$this->name} is the support department and cannot be deleted.";
        }

        $userCount ??= User::query()->where('department', $this->name)->count();
        $ticketCount ??= DB::table('tickets')->where('department', $this->name)->count();

        $uses = [];
        if ($userCount > 0) {
            $uses[] = $userCount.' '.Str::plural('account', $userCount);
        }
        if ($ticketCount > 0) {
            $uses[] = $ticketCount.' '.Str::plural('ticket', $ticketCount);
        }

        if ($uses === []) {
            return null;
        }

        return 'Still used by '.implode(' and ', $uses).'. Only a department that nothing uses can be deleted; rename it instead.';
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
