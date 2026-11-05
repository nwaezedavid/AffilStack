<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Concerns\AssignsComplimentaryPlan;
use App\Filament\Resources\Users\UserResource;
use App\Models\CustomerWallet;
use App\Models\User;
use App\Services\CustomerWallet\CustomerWalletManager;
use App\Services\CustomerWallet\InsufficientCustomerWalletBalanceException;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use AssignsComplimentaryPlan;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->adjustWalletAction(),
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        /** @var User $user */
        $user = $this->record;

        $this->guardComplimentaryPlan($user);
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->record;

        $this->assignComplimentaryPlan($user);
    }

    /**
     * Manual wallet credit or refund, always with a note. Every adjustment
     * is a new wallet history row; nothing is ever edited.
     */
    protected function adjustWalletAction(): Action
    {
        return Action::make('adjustWallet')
            ->label('Adjust wallet')
            ->icon('heroicon-o-wallet')
            ->visible(fn () => auth()->user()?->canAccessDepartment('billing') ?? false)
            ->modalDescription(fn (): string => 'Current balance: '.$this->walletBalanceLabel().'. Customers can\'t withdraw wallet money.')
            ->schema([
                Radio::make('type')
                    ->label('Adjustment')
                    ->options([
                        'admin_credit' => 'Manual credit (adds money)',
                        'admin_refund' => 'Refund to wallet (adds money)',
                        'admin_deduction' => 'Correction (removes money)',
                    ])
                    ->default('admin_credit')
                    ->required(),
                TextInput::make('amount')
                    ->label(fn (): string => 'Amount ('.$this->walletCurrency().')')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->required(),
                Textarea::make('note')
                    ->label('Note')
                    ->helperText('Required — shown in the customer\'s wallet history.')
                    ->required()
                    ->maxLength(500),
            ])
            ->action(function (array $data, CustomerWalletManager $wallets): void {
                /** @var User $user */
                $user = $this->record;
                $amountMinor = (int) round(((float) $data['amount']) * 100);
                $descriptions = [
                    'admin_credit' => 'Credit from AffilStack',
                    'admin_refund' => 'Refund to wallet',
                    'admin_deduction' => 'Correction by AffilStack',
                ];
                $type = array_key_exists($data['type'], $descriptions) ? $data['type'] : 'admin_credit';

                try {
                    $type === 'admin_deduction'
                        ? $wallets->debit($user, $amountMinor, $type, $descriptions[$type], null, $data['note'], auth()->user())
                        : $wallets->credit($user, $amountMinor, $type, $descriptions[$type], null, $data['note'], auth()->user());
                } catch (InsufficientCustomerWalletBalanceException) {
                    Notification::make()->title('The wallet doesn\'t have that much to remove')->danger()->send();

                    return;
                }

                Notification::make()->title('Wallet updated — balance '.$this->walletBalanceLabel())->success()->send();
            });
    }

    protected function walletCurrency(): string
    {
        /** @var User $user */
        $user = $this->record;

        return $user->customerWallet?->currency ?? strtoupper((string) config('customer_wallet.default_currency', 'USD'));
    }

    protected function walletBalanceLabel(): string
    {
        /** @var User $user */
        $user = $this->record;
        $wallet = $user->customerWallet()->first();

        return $wallet ? $wallet->formattedBalance() : CustomerWallet::format(0, $this->walletCurrency());
    }
}
