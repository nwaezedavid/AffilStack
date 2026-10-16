<?php

namespace App\Support;

use App\Models\User;

/**
 * The customer dashboard's sidebar, grouped by what the user is trying to
 * do. Kept here rather than inline in layouts/app.blade.php so each kind of
 * account gets exactly the links it can open — a team seat or an
 * affiliate-only account never sees a link that would just 403 (see
 * RestrictAgencySeats / RestrictAffiliateOnlyAccounts).
 *
 * Icons are Heroicons outline names (rendered as <x-heroicon-o-{icon}>).
 */
class DashboardNavigation
{
    /**
     * @return array<int, array{label: string, items: array<int, array{name: string, match: string, label: string, icon: string, params?: array<int, mixed>}>}>
     */
    public static function groupsFor(User $user): array
    {
        if ($user->isAffiliateOnly()) {
            return [
                ['label' => 'Partner programme', 'items' => [
                    ['name' => 'referrals.index', 'match' => 'referrals.index', 'label' => 'Referrals & Earnings', 'icon' => 'banknotes'],
                ]],
            ];
        }

        if ($user->isSeat()) {
            $offerItem = $user->hasSharedTeamAccess()
                ? ['name' => 'offers.index', 'match' => 'offers.*', 'label' => 'Products', 'icon' => 'magnifying-glass-circle']
                : ['name' => 'offers.show', 'params' => [$user->seat_offer_id], 'match' => 'offers.*', 'label' => 'My Product', 'icon' => 'magnifying-glass-circle'];

            return [
                ['label' => 'Workspace', 'items' => [
                    $offerItem,
                    ['name' => 'calendar.index', 'match' => 'calendar.*', 'label' => 'Content Calendar', 'icon' => 'calendar-days'],
                    ['name' => 'swipe-files.index', 'match' => 'swipe-files.*', 'label' => 'Swipe Files', 'icon' => 'rectangle-stack'],
                ]],
                ['label' => 'Connections', 'items' => [
                    ['name' => 'social-connections.index', 'match' => 'social-connections.*', 'label' => 'Connected Accounts', 'icon' => 'link'],
                    ['name' => 'api-access.index', 'match' => 'api-access.*', 'label' => 'API & Integrations', 'icon' => 'code-bracket-square'],
                ]],
            ];
        }

        return [
            ['label' => 'Start here', 'items' => [
                ['name' => 'dashboard', 'match' => 'dashboard', 'label' => 'Get Started', 'icon' => 'rocket-launch'],
                ['name' => 'offers.index', 'match' => 'offers.*', 'label' => 'My Products', 'icon' => 'magnifying-glass-circle'],
                ['name' => 'intelligence-centre.index', 'match' => 'intelligence-centre.*', 'label' => 'Intelligence Centre', 'icon' => 'light-bulb'],
            ]],
            ['label' => 'Find & close buyers', 'items' => [
                ['name' => 'crm.index', 'match' => 'crm.*', 'label' => 'CRM & Campaigns', 'icon' => 'users'],
                // Agent 08: replies from CRM contacts, with AI follow-up drafting.
                ['name' => 'crm.inbox', 'match' => 'crm.inbox*', 'label' => 'Inbox & Replies', 'icon' => 'inbox-arrow-down'],
                ['name' => 'leads.index', 'match' => 'leads.*', 'label' => 'Local Leads', 'icon' => 'map-pin'],
                ['name' => 'extension.index', 'match' => 'extension.*', 'label' => 'Chrome Extension', 'icon' => 'puzzle-piece'],
            ]],
            ['label' => 'Publish & track', 'items' => [
                ['name' => 'calendar.index', 'match' => 'calendar.*', 'label' => 'Content Calendar', 'icon' => 'calendar-days'],
                ['name' => 'swipe-files.index', 'match' => 'swipe-files.*', 'label' => 'Swipe Files', 'icon' => 'rectangle-stack'],
                ['name' => 'links.index', 'match' => 'links.*', 'label' => 'Links & Clicks', 'icon' => 'cursor-arrow-rays'],
                ['name' => 'earnings.index', 'match' => 'earnings.*', 'label' => 'Earnings', 'icon' => 'chart-bar'],
                ['name' => 'templates.index', 'match' => 'templates.*', 'label' => 'Template Library', 'icon' => 'document-duplicate'],
            ]],
            ['label' => 'Connections', 'items' => [
                ['name' => 'marketplace.browse', 'match' => ['marketplace.*', 'addons.*', 'oauth.*'], 'label' => 'Add-on Marketplace', 'icon' => 'puzzle-piece'],
                ['name' => 'email-connections.index', 'match' => 'email-connections.*', 'label' => 'Email Sending', 'icon' => 'envelope'],
                ['name' => 'social-connections.index', 'match' => 'social-connections.*', 'label' => 'Connected Accounts', 'icon' => 'link'],
                ['name' => 'networks.index', 'match' => 'networks.*', 'label' => 'Affiliate Networks', 'icon' => 'building-storefront'],
                ['name' => 'wordpress.index', 'match' => 'wordpress.*', 'label' => 'WordPress Sites', 'icon' => 'globe-alt'],
                ['name' => 'api-access.index', 'match' => 'api-access.*', 'label' => 'API & Integrations', 'icon' => 'code-bracket-square'],
            ]],
            ['label' => 'Account', 'items' => [
                ['name' => 'referrals.index', 'match' => 'referrals.index', 'label' => 'Refer & Earn', 'icon' => 'gift'],
                ['name' => 'team.index', 'match' => 'team.*', 'label' => 'Team', 'icon' => 'user-group'],
                ['name' => 'billing.index', 'match' => 'billing.*', 'label' => 'Billing & Plan', 'icon' => 'credit-card'],
                ['name' => 'credit-topups.index', 'match' => 'credit-topups.*', 'label' => 'Buy Credits', 'icon' => 'bolt'],
                ['name' => 'support.index', 'match' => 'support.*', 'label' => 'Support', 'icon' => 'chat-bubble-left-right'],
                ['name' => 'testimonial.edit', 'match' => 'testimonial.*', 'label' => 'Share a Review', 'icon' => 'star'],
            ]],
        ];
    }
}
