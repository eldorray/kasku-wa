<?php

use App\Models\Household;
use App\Models\User;

if (! function_exists('currentHousehold')) {
    /**
     * Resolve the active household for the request. Falls back to the auth
     * user's resolveHousehold() if not bound (e.g. during console).
     */
    function currentHousehold(): ?Household
    {
        if (app()->bound('current_household')) {
            return app('current_household');
        }
        $user = auth()->user();

        return $user instanceof User ? $user->resolveHousehold() : null;
    }
}
