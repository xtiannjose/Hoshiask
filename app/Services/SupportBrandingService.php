<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SupportBrandingService
{
    /**
     * Older department names still found on some accounts, mapped to the department
     * (by slug) they mean. Keys are lowercased with non-alphanumerics removed.
     */
    private const LEGACY_DEPARTMENT_ALIASES = [
        'hoshishicorporation' => 'hoshishi',
        'administration' => 'hoshishi',
        'it' => 'hoshishi',
        'htown' => 'harbor-town',
    ];

    private const FALLBACK_DEPARTMENT_SLUG = 'others';

    private const FALLBACK_DEPARTMENT_NAME = 'Others';

    private ?Collection $departmentCache = null;

    public static function flushDepartmentCache(): void
    {
        app()->forgetInstance(self::class);
    }

    public function supportDepartment(): string
    {
        return trim((string) config('hoshiask.support_department', 'Hoshishi')) ?: 'Hoshishi';
    }

    public function supportBrandName(): string
    {
        return trim((string) config('hoshiask.support_brand_name', $this->supportDepartment())) ?: $this->supportDepartment();
    }

    public function supportOrganizationName(): string
    {
        return trim((string) config('hoshiask.support_organization_name', 'Hoshishi Corporation')) ?: 'Hoshishi Corporation';
    }

    public function supportTeamName(): string
    {
        return trim((string) config('hoshiask.support_team_name', 'Hoshishi Technical Team')) ?: 'Hoshishi Technical Team';
    }

    public function supportLogoPath(): string
    {
        return trim((string) config('hoshiask.support_logo_path', 'images/Hoshishi Logo.png')) ?: 'images/Hoshishi Logo.png';
    }

    public function supportLogoUrl(): string
    {
        $logoPath = $this->supportLogoPath();

        if (! file_exists(public_path($logoPath))) {
            $logoPath = 'images/Hoshishi Logo.png';
        }

        return asset($logoPath);
    }

    public function departmentBrandKey(?string $department, ?string $role = null): string
    {
        return $this->resolveDepartment($department, $role)->slug ?? self::FALLBACK_DEPARTMENT_SLUG;
    }

    /**
     * @return array{key: string, name: string, logo_path: string, logo_url: string}
     */
    public function departmentBrandAssets(?string $department, ?string $role = null): array
    {
        $resolvedDepartment = $this->resolveDepartment($department, $role)
            ?? $this->departmentsBySlug()->get(self::FALLBACK_DEPARTMENT_SLUG);

        if ($resolvedDepartment instanceof Department) {
            return [
                'key' => (string) $resolvedDepartment->slug,
                'name' => (string) $resolvedDepartment->name,
                'logo_path' => $resolvedDepartment->resolved_logo_path,
                'logo_url' => $resolvedDepartment->logo_url,
            ];
        }

        $fallbackLogoPath = (new Department)->resolved_logo_path;

        return [
            'key' => self::FALLBACK_DEPARTMENT_SLUG,
            'name' => self::FALLBACK_DEPARTMENT_NAME,
            'logo_path' => $fallbackLogoPath,
            'logo_url' => asset($fallbackLogoPath),
        ];
    }

    /**
     * Staff without a department belong to the support department.
     */
    private function resolveDepartment(?string $department, ?string $role): ?Department
    {
        $name = trim((string) $department);
        if ($name === '' && in_array(User::normalizeRole($role), User::TICKET_CONSOLE_ROLES, true)) {
            $name = $this->supportDepartment();
        }

        if ($name === '') {
            return null;
        }

        $slug = Str::slug($name);
        $department = $this->departments()->first(
            fn (Department $candidate): bool => strcasecmp((string) $candidate->name, $name) === 0
                || (string) $candidate->slug === $slug
        );
        if ($department instanceof Department) {
            return $department;
        }

        $aliasSlug = self::LEGACY_DEPARTMENT_ALIASES[preg_replace('/[^a-z0-9]+/', '', strtolower($name))] ?? null;

        return $aliasSlug !== null ? $this->departmentsBySlug()->get($aliasSlug) : null;
    }

    /**
     * @return Collection<int, Department>
     */
    private function departments(): Collection
    {
        return $this->departmentCache ??= Department::query()
            ->orderByRaw('LOWER(name)')
            ->get(['id', 'name', 'slug', 'logo_path']);
    }

    /**
     * @return Collection<string, Department>
     */
    private function departmentsBySlug(): Collection
    {
        return $this->departments()->keyBy('slug');
    }
}
