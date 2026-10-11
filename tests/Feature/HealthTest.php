<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_checks_the_database_connection(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'database' => 'connected']);
    }

    public function test_database_failure_returns_a_safe_error(): void
    {
        DB::shouldReceive('select')->once()->with('SELECT 1')
            ->andThrow(new RuntimeException('Database credentials must stay private'));

        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'unavailable',
                'database' => 'unavailable',
                'message' => 'The service is temporarily unavailable.',
            ]);
    }

    public function test_frontend_origin_is_allowed(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:4210'])
            ->getJson('/api/health')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:4210');
    }

    public function test_other_origins_are_not_allowed(): void
    {
        $this->withHeaders(['Origin' => 'https://unrelated.example'])
            ->getJson('/api/health')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
