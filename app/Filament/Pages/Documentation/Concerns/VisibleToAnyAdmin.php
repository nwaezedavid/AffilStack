<?php

namespace App\Filament\Pages\Documentation\Concerns;

use Filament\Facades\Filament;

/**
 * Every documentation sub-page is reference material, not something that
 * changes anything, so — unlike most pages in this panel (see
 * ScopedToDepartment) — it's visible to any admin who can reach this panel
 * at all, regardless of which department(s) they're scoped to. An
 * admin_sub account benefits most from reading about a department it
 * *doesn't* have full access to.
 */
trait VisibleToAnyAdmin
{
    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessPanel(Filament::getPanel('admin')) === true;
    }
}
