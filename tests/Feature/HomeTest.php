<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomeTest extends TestCase
{
    public function test_the_public_home_page_is_available_in_portuguese(): void
    {
        $response = $this->withoutVite()->get('/');

        $response->assertOk()
            ->assertSee('SaldoUP')
            ->assertSee('lang="pt-BR"', false);
    }

    public function test_the_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
