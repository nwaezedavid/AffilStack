<?php

namespace App\Filament\Resources\Users\Concerns;

use App\Models\Plan;
use App\Models\User;
use App\Services\Payments\AdminPlanAssigner;
use Filament\Notifications\Notification;
use InvalidArgumentException;

/**
 * The Users screen's "Subscription plan" field (create and edit): picking a
 * plan gives the customer one complimentary month — see AdminPlanAssigner.
 */
trait AssignsComplimentaryPlan
{
    protected function selectedComplimentaryPlan(): ?Plan
    {
        $planId = $this->data['assign_plan_id'] ?? null;

        return filled($planId) ? Plan::find($planId) : null;
    }

    /**
     * Stops the save when the customer already pays for a plan.
     */
    protected function guardComplimentaryPlan(User $user): void
    {
        if (! $this->selectedComplimentaryPlan()) {
            return;
        }

        if ($reason = app(AdminPlanAssigner::class)->blockingReason($user)) {
            Notification::make()->title('Plan not assigned')->body($reason)->danger()->send();
            $this->halt();
        }
    }

    protected function assignComplimentaryPlan(User $user): void
    {
        $plan = $this->selectedComplimentaryPlan();

        if (! $plan) {
            return;
        }

        $cycle = ($this->data['assign_billing_cycle'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';

        try {
            $subscription = app(AdminPlanAssigner::class)->assign($user, $plan, $cycle, auth()->user());
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Plan not assigned')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->data['assign_plan_id'] = null;

        Notification::make()
            ->title("{$plan->name} assigned — free until {$subscription->current_period_end->format('M j, Y')}")
            ->success()
            ->send();
    }
}
