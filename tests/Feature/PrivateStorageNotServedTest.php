<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivateStorageNotServedTest extends TestCase
{
    public function test_the_private_disk_has_no_web_route_so_backups_and_attachments_cannot_be_fetched_by_url(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->map(fn ($route) => $route->uri())->all();

        $this->assertNotContains('storage/{path}', $routes);
    }

    public function test_a_storage_url_is_not_found_rather_than_forbidden(): void
    {
        $this->get('/storage/backups/hoshiask-20260101-000000.tar')->assertNotFound();
    }
}
