<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Notifications\ContactMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The redesigned public Contact page (task #162): admin-uploadable image,
 * three contact emails (hello/info/support), social links reused from the
 * SEO Settings "Social profiles" fields, and the existing contact form —
 * see resources/views/marketing/contact.blade.php.
 */
class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_shows_the_three_contact_emails(): void
    {
        SiteSetting::set('contact_email_hello', 'hello@example.com');
        SiteSetting::set('contact_email_info', 'info@example.com');
        SiteSetting::set('support_email', 'support@example.com');

        $response = $this->get('/contact');

        $response->assertOk();
        $response->assertSee('hello@example.com');
        $response->assertSee('info@example.com');
        $response->assertSee('support@example.com');
    }

    public function test_contact_page_shows_social_links_only_when_configured(): void
    {
        $this->get('/contact')->assertDontSee('aria-label="LinkedIn"', false);

        SiteSetting::set('seo_social_linkedin', 'https://linkedin.com/company/example');

        $this->get('/contact')->assertSee('https://linkedin.com/company/example');
    }

    public function test_contact_page_shows_the_admin_uploaded_image(): void
    {
        SiteSetting::set('contact_image_path', 'contact/team.webp');

        $response = $this->get('/contact');

        $response->assertOk();
        $response->assertSee('contact/team.webp');
    }

    public function test_submitting_the_contact_form_notifies_support_with_a_reply_to_the_submitter(): void
    {
        Notification::fake();

        SiteSetting::set('support_email', 'support@example.com');

        $this->post('/contact', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'A question',
            'message' => 'Hello there, I have a question.',
        ])->assertRedirect();

        $this->assertDatabaseHas('contact_messages', ['email' => 'jane@example.com']);

        Notification::assertSentOnDemand(ContactMessageReceived::class, function (ContactMessageReceived $notification, array $channels, object $notifiable) {
            $mail = $notification->toMail($notifiable);

            return $mail->replyTo[0][0] === 'jane@example.com';
        });
    }
}
