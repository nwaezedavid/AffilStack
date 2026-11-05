<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Concerns\WritesMaskedCredentials;
use App\Models\PlatformMailSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The mailbox AffilStack sends its own emails from (password resets,
 * receipts, lifecycle emails, admin alerts). Overrides the .env MAIL_*
 * values when switched on — see PlatformMailSetting::applyToConfig().
 */
class PlatformEmailSettings extends Page
{
    use ScopedToDepartment, WritesMaskedCredentials;

    protected static string $department = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'Platform Email';

    protected static ?string $title = 'Platform Email';

    protected string $view = 'filament.pages.platform-email-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = PlatformMailSetting::current();

        $this->form->fill([
            'is_enabled' => $settings->is_enabled,
            'host' => $settings->credential('host', 'smtp.hostinger.com'),
            'port' => (string) $settings->credential('port', '465'),
            'username' => $settings->credential('username', config('mail.mailers.smtp.username')),
            'password' => null,
            'from_address' => $settings->credential('from_address', config('mail.from.address')),
            'from_name' => $settings->credential('from_name', config('app.name')),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $settings = PlatformMailSetting::current();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Sending mailbox')
                    ->description($this->statusDescription($settings))
                    ->columns(2)
                    ->components([
                        Toggle::make('is_enabled')
                            ->label('Use these settings instead of the server .env')
                            ->helperText('Switch on once the details below are filled in. Use "Send test email" first to check them.')
                            ->columnSpanFull(),
                        TextInput::make('host')
                            ->label('SMTP server')
                            ->helperText('Hostinger Email: smtp.hostinger.com')
                            ->requiredIf('is_enabled', true),
                        Select::make('port')
                            ->label('Port')
                            ->options(['465' => '465 (SSL)', '587' => '587 (STARTTLS)'])
                            ->native(false)
                            ->required(),
                        TextInput::make('username')
                            ->label('Mailbox address (username)')
                            ->email()
                            ->requiredIf('is_enabled', true),
                        TextInput::make('password')
                            ->label('Mailbox password')
                            ->password()
                            ->revealable()
                            ->placeholder($this->maskedPlaceholder(filled($settings->credential('password'))))
                            ->helperText('The mailbox password from hPanel → Emails, not your hPanel password.'),
                        TextInput::make('from_address')
                            ->label('From address')
                            ->email()
                            ->helperText('Keep it the same as the mailbox address unless you created an alias.'),
                        TextInput::make('from_name')
                            ->label('From name'),
                    ]),
            ]);
    }

    protected function statusDescription(PlatformMailSetting $settings): string
    {
        $using = $settings->isUsable() ? 'AffilStack sends with these settings.' : 'AffilStack sends with the server .env settings.';

        if (! $settings->last_tested_at) {
            return $using;
        }

        return $using.' Last test '.$settings->last_tested_at->diffForHumans().': '.($settings->last_test_status === 'success' ? 'sent successfully.' : 'failed — '.$settings->last_test_message);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data): void
    {
        $settings = PlatformMailSetting::current();

        $settings->update([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'credentials' => $this->mergeMaskedCredentials($settings->credentials ?? [], [
                'host' => trim((string) ($data['host'] ?? '')) ?: null,
                'port' => (string) ($data['port'] ?? '465'),
                'username' => trim((string) ($data['username'] ?? '')) ?: null,
                'password' => $data['password'] ?? null,
                'from_address' => trim((string) ($data['from_address'] ?? '')) ?: null,
                'from_name' => trim((string) ($data['from_name'] ?? '')) ?: null,
            ], ['password']),
        ]);

        PlatformMailSetting::applyToConfig();
    }

    public function save(): void
    {
        $this->persist($this->form->getState());

        Notification::make()->title('Platform email settings saved')->success()->send();
    }

    /**
     * Sends one real email with the settings as typed (saved first, without
     * switching them on), so a wrong password shows here instead of as a
     * failed password reset.
     */
    public function sendTest(string $to): void
    {
        $data = $this->form->getState();
        $this->persist($data);
        $settings = PlatformMailSetting::current();

        $config = $settings->replicate()->fill(['is_enabled' => true])->toMailConfig();

        if (! $config) {
            Notification::make()->title('Fill in the server, mailbox address and password first')->warning()->send();

            return;
        }

        $original = collect(array_keys($config))->mapWithKeys(fn (string $key): array => [$key => config($key)])->all();

        try {
            config($config);
            app()->forgetInstance('mail.manager');
            app()->forgetInstance('mailer');

            Mail::raw('This is a test email from '.config('app.name').'. If you can read this, password resets and receipts will be delivered.', function ($message) use ($to): void {
                $message->to($to)->subject(config('app.name').' email test');
            });

            $settings->update(['last_tested_at' => now(), 'last_test_status' => 'success', 'last_test_message' => null]);
            Notification::make()->title("Test email sent to {$to}")->body('Check the inbox (and spam folder). Switch on "Use these settings" and save if it is not on yet.')->success()->send();
        } catch (Throwable $e) {
            $settings->update(['last_tested_at' => now(), 'last_test_status' => 'failed', 'last_test_message' => mb_substr($e->getMessage(), 0, 500)]);
            Notification::make()->title('The test email could not be sent')->body(str_contains($e->getMessage(), '535') ? 'The mail server rejected the mailbox address or password. Reset the mailbox password in hPanel → Emails, paste it here and try again.' : mb_substr($e->getMessage(), 0, 300))->danger()->persistent()->send();
        } finally {
            config($original);
            app()->forgetInstance('mail.manager');
            app()->forgetInstance('mailer');
            PlatformMailSetting::applyToConfig();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Send test email')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->schema([
                    TextInput::make('to')->label('Send to')->email()->required()->default(fn (): ?string => auth()->user()?->email),
                ])
                ->action(fn (array $data) => $this->sendTest($data['to'])),
        ];
    }
}
