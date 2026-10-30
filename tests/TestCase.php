<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Each actingAs() is a fresh sign-in, like logging out and in again. The
     * auth.session middleware remembers the signed-in account's password hash
     * in the session; without clearing it, switching to another account in
     * the same test would be treated as a changed password and signed out (401).
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        $guard ??= config('auth.defaults.guard');

        if ($this->app->bound('session')) {
            $this->app['session']->forget('password_hash_' . $guard);
        }

        return parent::actingAs($user, $guard);
    }
}
