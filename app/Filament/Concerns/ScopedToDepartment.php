<?php

namespace App\Filament\Concerns;

/**
 * Gates a Filament Resource or Page to one admin department (see
 * config('admin.departments')) — used by the admin sub-accounts feature.
 * Full admins ('admin'/'super-admin') always bypass this via
 * User::isFullAdmin(); an 'admin_sub' account only sees it once granted
 * that department's permission. Overriding canAccess() covers both
 * navigation visibility and direct URL access — see
 * Filament\Resources\Resource\Concerns\HasNavigation and
 * Filament\Pages\Concerns\CanAuthorizeAccess.
 *
 * Using classes must declare: protected static string $department = '...';
 */
trait ScopedToDepartment
{
    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessDepartment(static::$department) ?? false;
    }
}
