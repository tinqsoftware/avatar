<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_admin_login_page_renders(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('Avatar IA');
    }
}
