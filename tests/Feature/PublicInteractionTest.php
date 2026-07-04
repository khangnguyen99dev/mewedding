<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInteractionTest extends TestCase
{
    use RefreshDatabase;

    private function publishedInvitation(): Invitation
    {
        $user = User::factory()->create();
        $tpl = Template::create(['key' => 'nobel', 'name' => 'Nobel', 'version' => '1.0.0', 'status' => 'active', 'manifest' => []]);

        return Invitation::create([
            'user_id' => $user->id, 'template_id' => $tpl->id, 'slug' => 'demo',
            'title' => 'Demo', 'status' => 'published', 'published_at' => now(), 'settings' => [],
        ]);
    }

    public function test_guest_can_submit_rsvp(): void
    {
        $inv = $this->publishedInvitation();

        $this->postJson("/api/public/{$inv->slug}/rsvp", [
            'name' => 'Nguyễn Văn A', 'guest_count' => 2, 'attendance' => 'yes',
        ])->assertCreated()->assertJsonPath('stats.attending_guests', 2);

        $this->assertDatabaseHas('rsvps', ['invitation_id' => $inv->id, 'name' => 'Nguyễn Văn A', 'guest_count' => 2]);
    }

    public function test_rsvp_requires_name(): void
    {
        $inv = $this->publishedInvitation();
        $this->postJson("/api/public/{$inv->slug}/rsvp", ['guest_count' => 2])->assertStatus(422);
    }

    public function test_guest_can_post_and_list_guestbook(): void
    {
        $inv = $this->publishedInvitation();

        $this->postJson("/api/public/{$inv->slug}/guestbook", [
            'name' => 'Khách', 'message' => 'Chúc mừng hạnh phúc!', 'emoji' => '❤️',
        ])->assertCreated()->assertJsonPath('approved', true);

        $this->getJson("/api/public/{$inv->slug}/guestbook")
            ->assertOk()
            ->assertJsonPath('data.0.message', 'Chúc mừng hạnh phúc!');
    }

    public function test_moderated_guestbook_message_is_hidden_until_approved(): void
    {
        $inv = $this->publishedInvitation();
        $inv->update(['settings' => ['guestbook' => ['moderate' => true]]]);

        $this->postJson("/api/public/{$inv->slug}/guestbook", ['name' => 'K', 'message' => 'Pending wish'])
            ->assertCreated()->assertJsonPath('approved', false);

        // Not visible in the public (approved-only) list.
        $this->getJson("/api/public/{$inv->slug}/guestbook")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_cannot_interact_with_unpublished_invitation(): void
    {
        $inv = $this->publishedInvitation();
        $inv->update(['status' => 'draft']);

        $this->postJson("/api/public/{$inv->slug}/rsvp", ['name' => 'A'])->assertNotFound();
    }
}
