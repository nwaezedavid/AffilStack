<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * AffilStack only pays out its own affiliate program; affiliate networks pay
 * their commissions directly. Replaces the old "payouts" claims in saved
 * About page and homepage text, only where the original wording is unchanged.
 */
return new class extends Migration
{
    /**
     * @return array<string, string>
     */
    protected function replacements(): array
    {
        return [
            'A single platform for research, content, and payouts' => 'A single platform for research, content, and sales tracking',
            'Pay affiliates on time, every time' => 'See exactly what earns',
            'A payout workflow built in from day one, not bolted on as an afterthought.' => 'Track every click and sale across your affiliate networks, so you know which link, product and post made the money.',
            'Payments & payouts' => 'Sales & earnings tracking',
            'Multi-gateway checkout for your customers, and a real payout workflow for your own affiliates.' => 'Your sales and commissions from every affiliate network in one place. The networks pay you directly; AffilStack only pays out its own affiliate program.',
            'You publish, track, and get paid' => 'You publish and track every sale',
            "Every click and sale is tracked automatically, and payouts — yours and your affiliates' — are handled inside the platform." => 'Every click and sale is tracked automatically, so you can see how each link performs on each network. Your commissions are paid by the networks themselves.',
            'From your first offer to your first payout' => 'From your first offer to your first tracked sale',
        ];
    }

    public function up(): void
    {
        $keys = ['about_hero_subheadline', 'about_goals', 'about_what_we_do', 'about_how_we_works', 'home_features_subheading'];

        foreach (DB::table('site_settings')->whereIn('key', $keys)->get() as $row) {
            $value = (string) $row->value;
            $new = $value;

            foreach ($this->replacements() as $old => $replacement) {
                $new = str_replace([$old, json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), json_encode($old)],
                    [$replacement, json_encode($replacement, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), json_encode($replacement)], $new);
            }

            if ($new !== $value) {
                DB::table('site_settings')->where('key', $row->key)->update(['value' => $new]);
                Cache::forget('site_setting:'.$row->key);
            }
        }
    }

    public function down(): void
    {
        // Wording change only; nothing to undo.
    }
};
