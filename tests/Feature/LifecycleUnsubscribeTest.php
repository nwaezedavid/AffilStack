<?php

namespace Tests\Feature;

use App\Mail\LifecycleMail;
use App\Models\LifecycleEmail;
use App\Models\User;
use App\Services\Lifecycle\LifecycleEmailRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class LifecycleUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_signed_link_unsubscribes_in_one_click_without_signing_in(): void
    {
        $user = User::factory()->create();

        $this->get(app(LifecycleEmailRenderer::class)->unsubscribeUrl($user))
            ->assertOk()
            ->assertSee('You\'re unsubscribed', false)
            ->assertSee('Resubscribe');

        $this->assertFalse($user->fresh()->marketingEmailsEnabled());
    }

    public function test_an_unsigned_or_tampered_link_is_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->get(route('lifecycle.unsubscribe', $user))->assertForbidden();
        $this->get(str_replace('/'.$user->id.'?', '/'.$other->id.'?', app(LifecycleEmailRenderer::class)->unsubscribeUrl($user)))->assertForbidden();

        $this->assertTrue($user->fresh()->marketingEmailsEnabled());
        $this->assertTrue($other->fresh()->marketingEmailsEnabled());
    }

    public function test_the_rfc_8058_one_click_post_works_without_a_csrf_token(): void
    {
        $user = User::factory()->create();
        $url = URL::signedRoute('lifecycle.unsubscribe.one-click', ['user' => $user->id]);

        $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk();

        $this->assertFalse($user->fresh()->marketingEmailsEnabled());
    }

    public function test_the_list_unsubscribe_header_url_also_accepts_the_one_click_post(): void
    {
        $user = User::factory()->create();
        $headerUrl = app(LifecycleEmailRenderer::class)->unsubscribeUrl($user);

        $this->post($headerUrl)->assertOk();

        $this->assertFalse($user->fresh()->marketingEmailsEnabled());
    }

    public function test_resubscribing_turns_emails_back_on(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['marketing_emails_opt_in' => false])->save();

        $this->post(URL::signedRoute('lifecycle.resubscribe', ['user' => $user->id]))->assertOk()->assertSee('You\'re back on the list', false);

        $this->assertTrue($user->fresh()->marketingEmailsEnabled());
    }

    public function test_every_lifecycle_email_footer_has_the_unsubscribe_link(): void
    {
        $user = User::factory()->create();
        $html = (new LifecycleMail(LifecycleEmail::where('key', 'birthday')->first(), $user))->render();

        $this->assertStringContainsString('Unsubscribe in one click', $html);
        $this->assertStringContainsString(e(app(LifecycleEmailRenderer::class)->unsubscribeUrl($user)), $html);
    }
}
