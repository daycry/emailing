<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Livewire\Team;
use App\Models\Account;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\TeamInvitation;
use App\Services\AccountContext;
use App\Services\Permissions;
use App\Services\TeamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TeammateTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = app(CreateNewUser::class)->create(['name' => 'Propietario', 'account_name' => 'Equipo A', 'email' => 'owner@example.test', 'password' => 'Correct-password-123', 'password_confirmation' => 'Correct-password-123']);
        $this->actingAs($owner);

        return $owner;
    }

    private function member(Account $account, string $role = 'read-only', array $scopes = []): Membership
    {
        return Membership::create(['account_id' => $account->id, 'user_id' => User::factory()->create()->id, 'role' => $role, 'scopes' => $scopes]);
    }

    private function invitation(Account $account, User $recipient, array $overrides = []): array
    {
        $token = Str::random(64);
        $invitation = Invitation::create(array_merge(['account_id' => $account->id, 'invited_by' => $account->owner_id, 'email' => $recipient->email, 'role' => 'read-only', 'scopes' => [], 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)], $overrides));

        return [$invitation, $token];
    }

    public function test_invitation_is_hashed_and_mailpit_link_can_be_used_only_once_by_recipient(): void
    {
        $this->owner();
        $recipient = User::factory()->create(['email' => 'invite-'.Str::uuid().'@example.test']);
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => env('MAIL_HOST', '127.0.0.1'), 'mail.mailers.smtp.port' => (int) env('MAIL_PORT', 11025), 'mail.mailers.smtp.scheme' => null]);
        Livewire::test(Team::class)->set('email', $recipient->email)->set('role', 'restricted')->set('scopes', ['contacts.read'])->call('invite')->assertHasNoErrors()->assertSee('Invitación enviada');
        $invitation = Invitation::sole();
        $this->assertSame(64, strlen($invitation->token_hash));
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $invitation->expires_at->timestamp, 5);
        $messages = Http::timeout(5)->get('http://127.0.0.1:18025/api/v1/search', ['query' => 'to:'.$recipient->email])->throw()->json('messages');
        $this->assertCount(1, $messages);
        $message = Http::timeout(5)->get('http://127.0.0.1:18025/api/v1/message/'.$messages[0]['ID'])->throw()->json();
        $this->assertSame(1, preg_match('~/invitations/([A-Za-z0-9]{64})~', html_entity_decode(($message['HTML'] ?? '').($message['Text'] ?? '')), $matches));
        $token = $matches[1];
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertNotContains($token, $invitation->toArray());
        $this->actingAs(User::factory()->create())->post('/invitations/'.$token)->assertForbidden();
        $this->assertNull($invitation->fresh()->accepted_at);
        $this->actingAs($recipient)->post('/invitations/'.$token)->assertRedirect('/team');
        $membership = $recipient->memberships()->sole();
        $this->assertSame(['contacts.read'], $membership->scopes);
        $this->post('/invitations/'.$token)->assertStatus(410);
        $this->assertDatabaseCount('memberships', 2);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $recipient->id, 'action' => 'invitation.accepted']);
    }

    public function test_expired_revoked_and_replaced_links_are_rejected(): void
    {
        $owner = $this->owner();
        $account = Account::sole();
        $recipient = User::factory()->create();
        Notification::fake();
        foreach (['expires_at' => now()->subMinute(), 'revoked_at' => now(), 'accepted_at' => now()] as $field => $value) {
            [, $token] = $this->invitation($account, $recipient, [$field => $value]);
            $this->actingAs($recipient)->post('/invitations/'.$token)->assertStatus(410);
        }
        [$invitation, $token] = $this->invitation($account, $recipient);
        $this->actingAs($owner);
        app(TeamService::class)->resend($invitation->id);
        $this->actingAs($recipient)->post('/invitations/'.$token)->assertNotFound();
        $this->post('/invitations/'.Str::random(64))->assertNotFound();
        $this->assertDatabaseCount('memberships', 1);
        $this->actingAs($owner);
        app(TeamService::class)->revokeInvitation($invitation->id);
        $this->assertNotNull($invitation->fresh()->revoked_at);
    }

    public function test_read_only_and_restricted_cannot_manage_people_even_with_forged_state(): void
    {
        $this->owner();
        $account = Account::sole();
        $target = $this->member($account);
        foreach (['read-only', 'restricted'] as $role) {
            $member = $this->member($account, $role, array_keys(Permissions::SCOPES));
            $this->actingAs($member->user);
            Livewire::test(Team::class)->set('email', 'attacker@example.test')->set('role', 'admin')->call('invite')->assertForbidden();
            Livewire::test(Team::class)->set('editing', $target->id)->set('memberRole', 'admin')->call('saveMember')->assertForbidden();
            Livewire::test(Team::class)->call('remove', $account->memberships()->where('user_id', $account->owner_id)->value('id'))->assertForbidden();
        }
        $this->assertSame('read-only', $target->fresh()->role);
        $this->assertDatabaseCount('invitations', 0);
    }

    public function test_unknown_scopes_are_rejected_and_presets_have_no_admin_privileges(): void
    {
        $this->owner();
        Livewire::test(Team::class)->set('email', 'person@example.test')->set('role', 'restricted')->set('scopes', ['team.admin'])->call('invite')->assertHasErrors('scopes.0');
        foreach (Permissions::PRESETS as $preset => $scopes) {
            Livewire::test(Team::class)->set('preset', $preset)->assertSet('role', 'restricted')->assertSet('scopes', $scopes);
        }
        $readonly = $this->member(Account::sole());
        $this->assertTrue(Permissions::allows($readonly, 'contacts.read'));
        $this->assertFalse(Permissions::allows($readonly, 'mail.send'));
        $this->assertFalse(Permissions::allows($readonly, 'unknown.read'));
    }

    public function test_owner_cannot_be_removed_or_demoted_and_transfer_requires_current_password(): void
    {
        $owner = $this->owner();
        $account = Account::sole();
        $ownerMembership = $owner->memberships()->sole();
        $target = $this->member($account);
        Livewire::test(Team::class)->call('remove', $ownerMembership->id)->assertHasErrors('member');
        Livewire::test(Team::class)->set('editing', $ownerMembership->id)->set('memberRole', 'read-only')->call('saveMember')->assertHasErrors('member');
        $component = Livewire::test(Team::class)->set('transferMember', $target->id)->set('transferPassword', 'wrong')->call('transfer')->assertHasErrors('transferPassword')->assertSet('transferPassword', '');
        $this->assertSame($owner->id, $account->fresh()->owner_id);
        $component->set('transferPassword', 'Correct-password-123')->call('transfer')->assertHasNoErrors()->assertSet('transferPassword', '')->assertSet('transferMember', null)->assertSee('Propiedad transferida.');
        $this->assertSame($target->user_id, $account->fresh()->owner_id);
        $this->assertSame('admin', $target->fresh()->role);
        $this->assertSame('admin', $ownerMembership->fresh()->role);
        $this->assertDatabaseHas('audit_events', ['action' => 'account.ownership_transferred', 'actor_id' => $owner->id]);
    }

    public function test_successful_invitation_clears_errors_from_the_previous_attempt(): void
    {
        Notification::fake();
        $this->owner();
        $component = Livewire::test(Team::class)->set('email', 'invalid-email')->call('invite')->assertHasErrors('email');
        $this->assertDatabaseCount('invitations', 0);

        $component->set('email', 'corrected@example.test')->call('invite')->assertHasNoErrors()->assertSet('email', '')->assertSee('Invitación enviada.');

        $this->assertDatabaseHas('invitations', ['account_id' => Account::sole()->id, 'email' => 'corrected@example.test', 'role' => 'read-only']);
        $this->assertDatabaseHas('audit_events', ['action' => 'teammate.invited']);
        Notification::assertSentOnDemand(TeamInvitation::class);
    }

    public function test_successful_permission_change_clears_errors_from_the_previous_attempt(): void
    {
        $this->owner();
        $target = $this->member(Account::sole());
        $component = Livewire::test(Team::class)->set('editing', $target->id)->set('memberRole', 'restricted')->set('memberScopes', ['unknown.scope'])->call('saveMember')->assertHasErrors('scopes.0');
        $this->assertSame('read-only', $target->fresh()->role);

        $component->set('memberScopes', ['contacts.read'])->call('saveMember')->assertHasNoErrors()->assertSet('editing', null)->assertSee('Permisos actualizados.');

        $this->assertSame('restricted', $target->fresh()->role);
        $this->assertSame(['contacts.read'], $target->fresh()->scopes);
        $this->assertDatabaseHas('audit_events', ['action' => 'teammate.permissions_changed']);
    }

    public function test_successful_removal_clears_errors_from_the_previous_attempt(): void
    {
        $owner = $this->owner();
        $target = $this->member(Account::sole());
        $component = Livewire::test(Team::class)->call('remove', $owner->memberships()->sole()->id)->assertHasErrors('member');
        $this->assertModelExists($target);

        $component->call('remove', $target->id)->assertHasNoErrors()->assertSee('Acceso retirado.');

        $this->assertModelMissing($target);
        $this->assertDatabaseHas('memberships', ['user_id' => $owner->id, 'role' => 'admin']);
        $this->assertDatabaseHas('audit_events', ['action' => 'teammate.removed']);
    }

    public function test_permission_changes_and_revocation_take_effect_on_next_action_and_preserve_account(): void
    {
        $owner = $this->owner();
        $account = Account::sole();
        $member = $this->member($account, 'admin');
        $this->actingAs($member->user);
        $component = Livewire::test(Team::class);
        $this->actingAs($owner);
        app(TeamService::class)->updateMember($member->id, 'restricted', ['contacts.read']);
        $this->actingAs($member->user);
        $component->set('email', 'person@example.test')->call('invite')->assertForbidden();
        $this->assertTrue(Permissions::allows(app(AccountContext::class)->membership(), 'contacts.read'));
        $this->assertFalse(Permissions::allows(app(AccountContext::class)->membership(), 'mail.send'));
        $this->actingAs($owner);
        app(TeamService::class)->removeMember($member->id);
        $this->actingAs($member->user)->get('/dashboard')->assertForbidden();
        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
        $this->assertDatabaseHas('audit_events', ['account_id' => $account->id, 'actor_id' => $owner->id, 'action' => 'teammate.removed']);
    }

    public function test_membership_and_invitation_identifiers_from_other_accounts_are_not_accepted(): void
    {
        $this->owner();
        $account = Account::sole();
        $other = User::factory()->create();
        $foreign = Account::create(['name' => 'Equipo B', 'owner_id' => $other->id]);
        $member = $this->member($foreign);
        [$invitation] = $this->invitation($foreign, $member->user);
        Livewire::test(Team::class)->call('edit', $member->id)->assertNotFound();
        Livewire::test(Team::class)->set('editing', $member->id)->set('memberRole', 'admin')->call('saveMember')->assertNotFound();
        Livewire::test(Team::class)->call('revoke', $invitation->id)->assertNotFound();
        Livewire::test(Team::class)->call('resend', $invitation->id)->assertNotFound();
        Livewire::test(Team::class)->set('transferMember', $member->id)->set('transferPassword', 'Correct-password-123')->call('transfer')->assertNotFound();
        $this->assertSame($account->owner_id, $account->fresh()->owner_id);
        $this->assertSame('read-only', $member->fresh()->role);
    }
}
