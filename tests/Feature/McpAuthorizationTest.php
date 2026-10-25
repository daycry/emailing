<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ContactList;
use App\Models\Membership;
use App\Models\User;
use App\Services\AccountContext;
use App\Services\AccountKeyService;
use App\Services\ContactService;
use App\Services\Mcp\DelegatedContext;
use App\Services\Mcp\GrantService;
use App\Services\TeamService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\McpFixtures;
use Tests\TestCase;

class McpAuthorizationTest extends TestCase
{
    use DatabaseMigrations, McpFixtures;

    private function denied(callable $action, int $status): void
    {
        try {
            $action();
            $this->fail('Unauthorized delegated action succeeded.');
        } catch (HttpException $error) {
            $this->assertSame($status, $error->getStatusCode());
        }
    }

    public function test_explicit_context_has_no_session_or_auth_impersonation_and_never_changes_account(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [$other, $foreign] = $this->mcpAccount();
        [$token] = $this->mcpCredential($user, $account);
        $foreignList = ContactList::create(['account_id' => $foreign->id, 'name' => 'Foreign list', 'description' => '']);
        $service = new ContactService(new AccountContext(DelegatedContext::verified($token)));
        $this->assertNull(Auth::id());
        $list = $service->saveList(null, ['name' => 'Delegated list', 'description' => '']);
        $this->assertSame($account->id, $list->account_id);
        $this->assertNull(Auth::id());
        $this->assertSame([$list->id], $service->lists()->pluck('id')->all());
        try {
            $service->deleteList($foreignList->id);
            $this->fail('Foreign list deleted.');
        } catch (ModelNotFoundException) {
            $this->assertNotNull($foreignList->fresh());
        }
        $this->assertSame($user->id, AuditEvent::where('action', 'contact_list.created')->sole()->actor_id);
        $this->assertSame('mcp_grant', AuditEvent::where('action', 'contact_list.created')->sole()->metadata['kind']);
        $this->actingAs($other)->withSession(['account_id' => $foreign->id]);
        $service->saveList(null, ['name' => 'Still delegated', 'description' => '']);
        $this->assertSame($other->id, Auth::id());
        $this->assertSame($foreign->id, session('account_id'));
        $this->assertSame(2, ContactList::where('account_id', $account->id)->count());
        $this->denied(fn () => (new AccountContext(DelegatedContext::verified($token)))->authorizeAdmin(), 403);
    }

    public function test_grant_token_membership_and_operation_intersection_is_reloaded(): void
    {
        $this->mcpConfiguration();
        [$user, $account, $member] = $this->mcpAccount();
        [$token] = $this->mcpCredential($user, $account, ['contacts.read', 'contacts.write'], ['contacts.read']);
        $context = DelegatedContext::verified($token);
        $context->authorize(['contacts.read']);
        $this->denied(fn () => $context->authorize(['contacts.write']), 403);
        $this->denied(fn () => $context->authorize(['templates.read']), 403);
        $member->update(['role' => 'read-only']);
        $context->authorize(['contacts.read']);
        $member->update(['role' => 'restricted', 'scopes' => ['templates.read']]);
        $this->denied(fn () => $context->authorize(['contacts.read']), 403);
        $member->update(['role' => 'admin']);
        $this->assertSame(['contacts.read'], $context->effectiveScopes());
        $member->update(['role' => 'unknown']);
        $this->denied(fn () => $context->authorize(), 401);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_all_credential_bindings_expiry_and_revocation_are_checked_again_at_mutation(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        foreach (['purpose' => 'api', 'issuer' => 'https://other.example.test', 'resource' => 'https://other.example.test/mcp', 'tokenable_type' => 'Other', 'expires_at' => now()->subSecond(), 'revoked_at' => now(), 'abilities' => ['settings.write']] as $field => $value) {
            [$token] = $this->mcpCredential($user, $account);
            $context = DelegatedContext::verified($token);
            $token->forceFill([$field => $value])->save();
            $this->denied(fn () => (new ContactService(new AccountContext($context)))->saveList(null, ['name' => 'Denied', 'description' => '']), 401);
        }
        [$token, $grant, , $client] = $this->mcpCredential($user, $account);
        $context = DelegatedContext::verified($token);
        $client->update(['revoked_at' => now()]);
        $this->denied(fn () => $context->authorize(), 401);
        [$token, $grant] = $this->mcpCredential($user, $account);
        $context = DelegatedContext::verified($token);
        app(GrantService::class)->revoke($grant->id, $user->id);
        $this->denied(fn () => (new ContactService(new AccountContext($context)))->saveList(null, ['name' => 'Denied', 'description' => '']), 401);
        $this->assertDatabaseCount('contact_lists', 0);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_membership_removal_is_terminal_for_personal_grants_but_not_account_keys(): void
    {
        $this->mcpConfiguration();
        [$owner, $account] = $this->mcpAccount();
        $actor = User::factory()->create(['email' => 'delegate@example.test']);
        $member = Membership::create(['user_id' => $actor->id, 'account_id' => $account->id, 'role' => 'admin', 'scopes' => []]);
        [$token, $grant] = $this->mcpCredential($actor, $account);
        $context = DelegatedContext::verified($token);
        $key = app(AccountKeyService::class)->create($account->id, ['name' => 'Synthetic account key', 'scopes' => ['mail.send']], ['mail.send'], actor: $actor->id);
        $this->actingAs($owner)->withSession(['account_id' => $account->id]);
        app(TeamService::class)->removeMember($member->id);
        $this->denied(fn () => $context->authorize(), 401);
        $this->assertNotNull($grant->fresh()->revoked_at);
        Membership::create(['user_id' => $actor->id, 'account_id' => $account->id, 'role' => 'admin', 'scopes' => []]);
        $this->denied(fn () => $context->authorize(), 401);
        $request = Request::create('/', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$key['secret']]);
        $candidate = app(AccountKeyService::class)->candidate($request);
        $this->assertSame($account->id, app(AccountKeyService::class)->operation($candidate, ['mail.send'], fn ($key) => $key->account_id));
    }
}
