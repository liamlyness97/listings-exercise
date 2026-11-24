<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The README says to build user-scoped work against $request->user() or
 * auth()->user(). Both must resolve the demo user, so the auth middleware and
 * policies behave as they would with real authentication.
 */
class ActAsDemoUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_test/current-user', fn (Request $request) => [
            'request_user' => $request->user()?->id,
            'auth_user' => auth()->user()?->id,
            'authenticated' => auth()->check(),
        ]);
    }

    public function test_every_way_of_reading_the_user_resolves_the_demo_user(): void
    {
        $demo = User::factory()->create();
        User::factory()->create();

        $this->get('/_test/current-user')->assertExactJson([
            'request_user' => $demo->id,
            'auth_user' => $demo->id,
            'authenticated' => true,
        ]);
    }

    /**
     * Laravel sorts the `auth` middleware ahead of ordinary web-group
     * middleware, so the stub must be prioritised before it or `auth` would
     * run first and see a guest.
     */
    public function test_routes_behind_the_auth_middleware_see_the_demo_user(): void
    {
        Route::middleware(['web', 'auth'])->get('/_test/members-only', fn () => 'ok');
        User::factory()->create();

        $this->get('/_test/members-only')->assertOk();
    }

    /**
     * The app instance can outlive a request (several requests in one test,
     * or a long-running server). A user set on the guard by an earlier request
     * must not leak into one that finds no user.
     */
    public function test_a_user_from_an_earlier_request_does_not_leak_into_a_guest_request(): void
    {
        $demo = User::factory()->create();
        $this->get('/_test/current-user')->assertJsonPath('authenticated', true);

        $demo->delete();

        $this->get('/_test/current-user')->assertJsonPath('authenticated', false);
    }

    public function test_an_unseeded_database_behaves_like_a_guest(): void
    {
        $this->get('/_test/current-user')->assertExactJson([
            'request_user' => null,
            'auth_user' => null,
            'authenticated' => false,
        ]);
    }
}
