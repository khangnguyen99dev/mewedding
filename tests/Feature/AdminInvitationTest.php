<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use App\Services\TemplateRegistry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function template(string $key = 'nobel'): Template
    {
        $manifest = app(TemplateRegistry::class)->find($key) ?? ['name' => ucfirst($key), 'version' => '1.0.0'];

        return Template::create([
            'key' => $key,
            'name' => $manifest['name'],
            'version' => $manifest['version'] ?? '1.0.0',
            'status' => 'active',
            'manifest' => $manifest,
        ]);
    }

    public function test_guest_cannot_list_invitations(): void
    {
        $this->getJson('/api/invitations')->assertUnauthorized();
    }

    public function test_admin_can_create_invitation_from_template(): void
    {
        $admin = $this->admin();
        // A folder-less template: createFromTemplate works, no demo media to upload.
        $this->template('minimal');
        Sanctum::actingAs($admin);

        $res = $this->postJson('/api/invitations', ['template_key' => 'minimal', 'title' => 'Test Wedding']);

        $res->assertCreated()->assertJsonPath('data.title', 'Test Wedding')->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseHas('invitations', ['title' => 'Test Wedding', 'user_id' => $admin->id]);
    }

    public function test_creating_with_unknown_template_fails_validation(): void
    {
        Sanctum::actingAs($this->admin());
        $this->postJson('/api/invitations', ['template_key' => 'does-not-exist'])->assertStatus(422);
    }

    public function test_reserved_slug_is_rejected(): void
    {
        Sanctum::actingAs($this->admin());
        $this->template('nobel');
        $this->postJson('/api/invitations', ['template_key' => 'nobel', 'slug' => 'admin'])->assertStatus(422);
    }

    public function test_published_invitation_renders_publicly_and_draft_404s(): void
    {
        $admin = $this->admin();
        $tpl = $this->template('nobel');

        $published = Invitation::create([
            'user_id' => $admin->id, 'template_id' => $tpl->id, 'slug' => 'anh-em',
            'title' => 'Anh & Em', 'status' => 'published', 'published_at' => now(),
            'settings' => ['hero' => ['groom_name' => 'Anh', 'bride_name' => 'Em']],
        ]);

        $this->get('/'.$published->slug)->assertOk()->assertSee('Anh', false);

        $draft = Invitation::create([
            'user_id' => $admin->id, 'template_id' => $tpl->id, 'slug' => 'draft-one',
            'title' => 'Draft', 'status' => 'draft', 'settings' => [],
        ]);
        $this->get('/'.$draft->slug)->assertNotFound();
    }

    public function test_update_publish_and_unpublish(): void
    {
        $admin = $this->admin();
        $tpl = $this->template('nobel');
        Sanctum::actingAs($admin);

        $inv = Invitation::create([
            'user_id' => $admin->id, 'template_id' => $tpl->id, 'slug' => 'x', 'title' => 'X', 'status' => 'draft', 'settings' => [],
        ]);

        $this->putJson("/api/invitations/{$inv->id}", ['title' => 'Updated', 'settings' => ['hero' => ['groom_name' => 'G']]])
            ->assertOk()->assertJsonPath('data.title', 'Updated');

        $this->postJson("/api/invitations/{$inv->id}/publish")->assertOk()->assertJsonPath('data.status', 'published');
        $this->postJson("/api/invitations/{$inv->id}/unpublish")->assertOk()->assertJsonPath('data.status', 'draft');
    }

    public function test_editor_cannot_view_another_users_invitation(): void
    {
        $owner = $this->admin();
        $tpl = $this->template('nobel');
        $inv = Invitation::create([
            'user_id' => $owner->id, 'template_id' => $tpl->id, 'slug' => 'owned', 'title' => 'Owned', 'status' => 'draft', 'settings' => [],
        ]);

        $editor = User::factory()->create();
        $editor->assignRole('editor');
        Sanctum::actingAs($editor);

        $this->getJson("/api/invitations/{$inv->id}")->assertForbidden();
    }
}
