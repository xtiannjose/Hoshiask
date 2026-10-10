<?php

namespace App\Services\TicketImport;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Collection;

class ImportEntityLookupCache
{
    /**
     * @var Collection<int, User>|null
     */
    private ?Collection $users = null;

    /**
     * @var Collection<int, Category>|null
     */
    private ?Collection $categories = null;

    /**
     * @var array<int, User>|null
     */
    private ?array $usersById = null;

    /**
     * @var array<string, User>|null
     */
    private ?array $usersByEmail = null;

    /**
     * @var array<string, User>|null
     */
    private ?array $usersByUsername = null;

    /**
     * @var array<string, User>|null
     */
    private ?array $supportUsersByName = null;

    /**
     * @var array<int, Category>|null
     */
    private ?array $categoriesById = null;

    /**
     * @var array<string, Category>|null
     */
    private ?array $categoriesByName = null;

    public function userById(int $id): ?User
    {
        return $this->usersById()[$id] ?? null;
    }

    public function userByEmail(string $email): ?User
    {
        return $this->usersByEmail()[strtolower($email)] ?? null;
    }

    public function userByUsername(string $username): ?User
    {
        return $this->usersByUsername()[strtolower($username)] ?? null;
    }

    public function supportUserByDisplayName(string $displayName): ?User
    {
        return $this->supportUsersByName()[$this->normalizeLookupKey($displayName)] ?? null;
    }

    public function categoryById(int $id): ?Category
    {
        return $this->categoriesById()[$id] ?? null;
    }

    public function categoryByName(string $name): ?Category
    {
        return $this->categoriesByName()[strtolower($name)] ?? null;
    }

    /**
     * @return array<int, User>
     */
    private function usersById(): array
    {
        if ($this->usersById === null) {
            $this->usersById = $this->users()->keyBy('id')->all();
        }

        return $this->usersById;
    }

    /**
     * @return array<string, User>
     */
    private function usersByEmail(): array
    {
        if ($this->usersByEmail === null) {
            $this->usersByEmail = [];

            foreach ($this->users() as $user) {
                $email = strtolower((string) $user->email);
                if ($email !== '') {
                    $this->usersByEmail[$email] = $user;
                }
            }
        }

        return $this->usersByEmail;
    }

    /**
     * @return array<string, User>
     */
    private function usersByUsername(): array
    {
        if ($this->usersByUsername === null) {
            $this->usersByUsername = [];

            foreach ($this->users() as $user) {
                $username = strtolower((string) $user->username);
                if ($username !== '') {
                    $this->usersByUsername[$username] = $user;
                }
            }
        }

        return $this->usersByUsername;
    }

    /**
     * @return array<string, User>
     */
    private function supportUsersByName(): array
    {
        if ($this->supportUsersByName === null) {
            $groupedUsers = $this->users()
                ->whereIn('role', User::TICKET_CONSOLE_ROLES)
                ->groupBy(fn (User $user): string => $this->normalizeLookupKey((string) $user->name));

            $this->supportUsersByName = $groupedUsers
                ->filter(static fn (Collection $users, string $key): bool => $key !== '' && $users->count() === 1)
                ->map(fn (Collection $users): User => $users->first())
                ->all();
        }

        return $this->supportUsersByName;
    }

    /**
     * @return array<int, Category>
     */
    private function categoriesById(): array
    {
        if ($this->categoriesById === null) {
            $this->categoriesById = $this->categories()->keyBy('id')->all();
        }

        return $this->categoriesById;
    }

    /**
     * @return array<string, Category>
     */
    private function categoriesByName(): array
    {
        if ($this->categoriesByName === null) {
            $this->categoriesByName = [];

            foreach ($this->categories() as $category) {
                $this->categoriesByName[strtolower((string) $category->name)] = $category;
            }
        }

        return $this->categoriesByName;
    }

    /**
     * @return Collection<int, User>
     */
    private function users(): Collection
    {
        if ($this->users === null) {
            $this->users = User::query()->get();
        }

        return $this->users;
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories(): Collection
    {
        if ($this->categories === null) {
            $this->categories = Category::query()->get();
        }

        return $this->categories;
    }

    private function normalizeLookupKey(string $value): string
    {
        return strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }
}
