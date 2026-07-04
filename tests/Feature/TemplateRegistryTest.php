<?php

namespace Tests\Feature;

use App\Services\TemplateRegistry;
use Tests\TestCase;

class TemplateRegistryTest extends TestCase
{
    public function test_registry_discovers_filesystem_templates(): void
    {
        $registry = app(TemplateRegistry::class);
        $all = $registry->all();

        $this->assertArrayHasKey('nobel', $all);
        $this->assertArrayHasKey('flowers', $all);
    }

    public function test_registry_exposes_section_schema(): void
    {
        $schema = app(TemplateRegistry::class)->schema('nobel');

        $this->assertArrayHasKey('hero', $schema);
        $this->assertArrayHasKey('groom_name', $schema['hero']['fields']);
    }

    public function test_preview_content_is_available(): void
    {
        $preview = app(TemplateRegistry::class)->preview('flowers');

        $this->assertArrayHasKey('settings', $preview);
        $this->assertEquals('Anh Tú', $preview['settings']['hero']['groom_name']);
    }
}
