<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * QA 11-21 #13: Mark as Read and Mark All as Read. Read status is per user, stays
 * after reload, read notifications stay listed and still open, and nobody can
 * change another user's notification.
 */
class NotificationReadStatusTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, array $data = []): string
    {
        $id = (string) Str::uuid();
        $user->notifications()->create([
            'id' => $id,
            'type' => \App\Notifications\RequestStatusChanged::class,
            'data' => array_merge(['status' => 'venue_approved', 'message' => 'Venue approved'], $data),
        ]);

        return $id;
    }

    public function test_the_page_shows_unread_and_read_with_the_actions(): void
    {
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $unread = $this->notify($user);
        $read = $this->notify($user);
        $user->notifications()->where('id', $read)->update(['read_at' => now()]);

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('1 unread')
            ->assertSee('Mark All as Read')
            ->assertSee(route('notifications.read', $unread), false)
            ->assertDontSee(route('notifications.read', $read), false)
            ->assertSee(route('notifications.open', $read), false)
            ->assertSee('data-state="unread"', false)
            ->assertSee('data-state="read"', false);
    }

    public function test_mark_all_is_disabled_when_nothing_is_unread(): void
    {
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $id = $this->notify($user);
        $user->notifications()->where('id', $id)->update(['read_at' => now()]);

        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-mark-all-button\s+disabled/', $html);
        $this->assertStringContainsString('No unread notifications', $html);
    }

    public function test_mark_as_read_persists_and_is_safe_to_repeat(): void
    {
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $id = $this->notify($user);
        $this->notify($user);

        $this->actingAs($user)->postJson(route('notifications.read', $id))
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 'read', 'unread_count' => 1, 'message' => 'Notification marked as read.']);
        $firstReadAt = $user->notifications()->find($id)->read_at;
        $this->assertNotNull($firstReadAt);

        $this->travel(5)->minutes();
        $this->actingAs($user)->postJson(route('notifications.read', $id))
            ->assertOk()->assertJson(['success' => true, 'unread_count' => 1]);
        $this->assertTrue($firstReadAt->equalTo($user->notifications()->find($id)->read_at));

        // Still listed after reload, and still opens.
        $this->actingAs($user)->get(route('notifications.index'))->assertSee(route('notifications.open', $id), false);
    }

    public function test_mark_as_read_without_javascript_returns_to_the_list(): void
    {
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $id = $this->notify($user);

        $this->actingAs($user)->from(route('notifications.index'))->post(route('notifications.read', $id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success', 'Notification marked as read.');
        $this->assertNotNull($user->notifications()->find($id)->read_at);
    }

    public function test_another_users_notification_cannot_be_changed(): void
    {
        $owner = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $stranger = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $id = $this->notify($owner);
        $this->notify($stranger);

        $this->actingAs($stranger)->postJson(route('notifications.read', $id))
            ->assertNotFound()
            ->assertJson(['success' => false]);
        $this->assertNull($owner->notifications()->find($id)->read_at);

        $this->actingAs($stranger)->postJson(route('notifications.read-all'))
            ->assertOk()->assertJson(['marked' => 1, 'unread_count' => 0]);
        $this->assertNull($owner->notifications()->find($id)->read_at);
        $this->assertSame(1, $owner->unreadNotifications()->count());
    }

    public function test_mark_all_as_read_marks_only_unread_and_repeats_safely(): void
    {
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->notify($user);
        $this->notify($user);
        $this->notify($user);

        $this->actingAs($user)->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJson(['success' => true, 'marked' => 3, 'unread_count' => 0, 'message' => '3 notifications marked as read.']);
        $this->actingAs($user)->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJson(['success' => true, 'marked' => 0, 'unread_count' => 0, 'message' => 'All notifications are already read.']);
        $this->assertSame(3, $user->notifications()->count());
    }

    public function test_guests_cannot_mark_notifications(): void
    {
        $this->postJson(route('notifications.read-all'))->assertUnauthorized();
    }

    public function test_the_unread_tab_lists_only_unread(): void
    {
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $unread = $this->notify($user);
        $read = $this->notify($user);
        $user->notifications()->where('id', $read)->update(['read_at' => now()]);

        $this->actingAs($user)->get(route('notifications.index', ['filter' => 'unread']))
            ->assertOk()
            ->assertSee(route('notifications.open', $unread), false)
            ->assertDontSee(route('notifications.open', $read), false)
            ->assertSee('Today');

        $user->unreadNotifications()->update(['read_at' => now()]);
        $this->actingAs($user)->get(route('notifications.index', ['filter' => 'unread']))
            ->assertSee("You're all caught up", false);
    }
}
