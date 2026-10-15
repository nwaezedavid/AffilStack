<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Models\CrmList;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Blog\BlogFeed;
use App\Services\Blog\BlogKit;
use App\Services\Blog\WordPressPublisher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * Website → Blog: connects the WordPress blog (blog.affilstack.com) to
 * AffilStack — the address, the login used to publish posts from Admin →
 * Website → Blog posts, the connection key the AffilStack theme uses for
 * newsletter sign-ups, and the end-of-post call to action and newsletter
 * box the theme shows. The theme's header and footer follow the main
 * website automatically (see BlogKit).
 */
class BlogSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'content';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Blog settings';

    protected static ?string $title = 'Blog (blog.affilstack.com)';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.feature-demo-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'seo_blog_url' => SiteSetting::get('seo_blog_url') ?: SiteSetting::get('blog_feed_url'),
            'blog_wp_username' => SiteSetting::get('blog_wp_username'),
            'blog_wp_app_password' => null,
            'blog_cta_enabled' => SiteSetting::flag('blog_cta_enabled', true),
            'blog_cta_heading' => SiteSetting::get('blog_cta_heading'),
            'blog_cta_text' => SiteSetting::get('blog_cta_text'),
            'blog_cta_button_label' => SiteSetting::get('blog_cta_button_label'),
            'blog_cta_button_url' => SiteSetting::get('blog_cta_button_url'),
            'blog_newsletter_enabled' => SiteSetting::flag('blog_newsletter_enabled', false),
            'blog_newsletter_list_id' => SiteSetting::get('blog_newsletter_list_id'),
            'blog_newsletter_heading' => SiteSetting::get('blog_newsletter_heading'),
            'blog_newsletter_text' => SiteSetting::get('blog_newsletter_text'),
            'blog_newsletter_button_label' => SiteSetting::get('blog_newsletter_button_label'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Callout::make('How the blog is connected')
                ->description('The blog runs WordPress with the AffilStack theme. Its header, footer, logo and colours follow this website automatically. Posts can be written here (Website → Blog posts) and sent to WordPress, and readers who join the newsletter are added to a CRM list after confirming their email.')
                ->info()
                ->footerActions([
                    Action::make('posts')->label('Blog posts')->url(BlogPostResource::getUrl())->color('gray'),
                ]),

            Section::make('1. Blog address & WordPress login')
                ->description('The WordPress login lets AffilStack send posts to the blog. In WordPress go to Users → Profile → Application Passwords, type "AffilStack" and click "Add New Application Password", then paste the password it shows here. It is stored encrypted.')
                ->columns(2)
                ->components([
                    TextInput::make('seo_blog_url')
                        ->label('Blog address')
                        ->url()
                        ->placeholder('https://blog.affilstack.com')
                        ->helperText('Also used for "Blog" in the menu and for the latest posts on the homepage.')
                        ->columnSpanFull(),
                    TextInput::make('blog_wp_username')
                        ->label('WordPress username')
                        ->maxLength(120),
                    TextInput::make('blog_wp_app_password')
                        ->label('Application password')
                        ->password()
                        ->revealable()
                        ->placeholder(WordPressPublisher::applicationPassword() !== null ? '•••••••• saved — leave blank to keep' : 'xxxx xxxx xxxx xxxx xxxx xxxx'),
                    Actions::make([
                        Action::make('testConnection')
                            ->label('Save & test the connection')
                            ->icon(Heroicon::OutlinedSignal)
                            ->action(fn () => $this->testConnection()),
                    ])->columnSpanFull(),
                ]),

            Section::make('2. Connection key for the AffilStack theme')
                ->description('In WordPress go to Appearance → AffilStack and paste this key, so newsletter sign-ups on the blog reach your CRM. Generate a new key if it ever leaks; the old one stops working at once.')
                ->components([
                    Text::make(fn (): string => BlogKit::connectionKey())->copyable()->fontFamily('mono'),
                    Actions::make([
                        Action::make('rotateKey')
                            ->label('Generate a new key')
                            ->icon(Heroicon::OutlinedArrowPath)
                            ->color('gray')
                            ->requiresConfirmation()
                            ->modalDescription('The blog\'s newsletter form stops working until you paste the new key into WordPress → Appearance → AffilStack.')
                            ->action(function (): void {
                                BlogKit::rotateConnectionKey();
                                Notification::make()->title('New key generated — paste it into WordPress')->success()->send();
                            }),
                    ]),
                ]),

            Section::make('3. Call to action at the end of every post')
                ->columns(2)
                ->components([
                    Toggle::make('blog_cta_enabled')->label('Show the call-to-action box')->columnSpanFull(),
                    TextInput::make('blog_cta_heading')->label('Heading')->placeholder(BlogKit::DEFAULT_CTA_HEADING)->maxLength(120)->columnSpanFull(),
                    Textarea::make('blog_cta_text')->label('Text')->placeholder(BlogKit::DEFAULT_CTA_TEXT)->rows(2)->maxLength(300)->columnSpanFull(),
                    TextInput::make('blog_cta_button_label')->label('Button text')->placeholder(BlogKit::DEFAULT_CTA_BUTTON)->maxLength(40),
                    TextInput::make('blog_cta_button_url')->label('Button link')->url()->placeholder(route('registration.pricing'))->helperText('Leave blank for the Pricing page.'),
                ]),

            Section::make('4. Newsletter sign-up')
                ->description('A sign-up box after every post. Readers get a confirmation email first (double opt-in), then join the CRM list below — and any autopilot campaign on that list starts for them.')
                ->columns(2)
                ->components([
                    Toggle::make('blog_newsletter_enabled')->label('Show the newsletter box')->columnSpanFull(),
                    Select::make('blog_newsletter_list_id')
                        ->label('Add confirmed subscribers to this CRM list')
                        ->options(fn (): array => $this->listOptions())
                        ->searchable()
                        ->helperText('Lists in admin accounts\' CRMs. Create one in the app under CRM → Lists first.')
                        ->columnSpanFull(),
                    TextInput::make('blog_newsletter_heading')->label('Heading')->placeholder(BlogKit::DEFAULT_NEWSLETTER_HEADING)->maxLength(120)->columnSpanFull(),
                    Textarea::make('blog_newsletter_text')->label('Text')->placeholder(BlogKit::DEFAULT_NEWSLETTER_TEXT)->rows(2)->maxLength(300)->columnSpanFull(),
                    TextInput::make('blog_newsletter_button_label')->label('Button text')->placeholder(BlogKit::DEFAULT_NEWSLETTER_BUTTON)->maxLength(40),
                ]),
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function listOptions(): array
    {
        $adminIds = User::role(['admin', 'super-admin'])->pluck('id');

        return CrmList::query()
            ->whereIn('user_id', $adminIds)
            ->with('user:id,email')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (CrmList $list): array => [$list->id => $list->name.' ('.$list->user?->email.')'])
            ->all();
    }

    public function save(): void
    {
        $this->persist();

        Notification::make()->title('Saved')->body('The blog picks up the changes within five minutes.')->success()->send();
    }

    protected function persist(): void
    {
        $data = $this->form->getState();

        $url = BlogFeed::normalizeBaseUrl($data['seo_blog_url'] ?? null);
        SiteSetting::set('seo_blog_url', $url);

        foreach (['blog_wp_username', 'blog_cta_heading', 'blog_cta_text', 'blog_cta_button_label', 'blog_cta_button_url',
            'blog_newsletter_heading', 'blog_newsletter_text', 'blog_newsletter_button_label'] as $key) {
            SiteSetting::set($key, filled($data[$key] ?? null) ? trim((string) $data[$key]) : null);
        }

        $listId = (int) ($data['blog_newsletter_list_id'] ?? 0);
        SiteSetting::set('blog_newsletter_list_id', array_key_exists($listId, $this->listOptions()) ? (string) $listId : null);

        SiteSetting::setFlag('blog_cta_enabled', (bool) ($data['blog_cta_enabled'] ?? false));
        SiteSetting::setFlag('blog_newsletter_enabled', (bool) ($data['blog_newsletter_enabled'] ?? false));

        WordPressPublisher::storeApplicationPassword($data['blog_wp_app_password'] ?? null);
        $this->data['blog_wp_app_password'] = null;

        BlogKit::flush();
        BlogFeed::flush();
    }

    public function testConnection(): void
    {
        $this->persist();

        try {
            $message = app(WordPressPublisher::class)->test();
            Notification::make()->title('Connected')->body($message)->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Couldn\'t connect to WordPress')->body($e->getMessage())->danger()->persistent()->send();
        }
    }

    public function publicUrl(): string
    {
        return BlogFeed::baseUrl() ?? route('home');
    }
}
