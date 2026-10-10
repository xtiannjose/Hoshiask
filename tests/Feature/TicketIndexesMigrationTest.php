<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TicketIndexesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_polling_and_reply_digest_queries_have_an_index_to_lead_with(): void
    {
        $this->assertTrue(Schema::hasIndex('tickets', ['updated_at']));
        $this->assertTrue(Schema::hasIndex('ticket_replies', ['created_at']));
    }
}
