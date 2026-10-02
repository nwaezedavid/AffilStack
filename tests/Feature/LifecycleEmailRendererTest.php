<?php

namespace Tests\Feature;

use App\Models\LifecycleEmail;
use App\Models\User;
use App\Services\Lifecycle\LifecycleEmailRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LifecycleEmailRendererTest extends TestCase
{
    use RefreshDatabase;

    protected function email(array $attributes): LifecycleEmail
    {
        return new LifecycleEmail($attributes + [
            'type' => LifecycleEmail::TYPE_FESTIVE,
            'name' => 'Test',
            'subject' => 'Hi {first_name}',
            'heading' => 'Hello',
            'body' => 'Body',
            'theme_color' => '#123456',
        ]);
    }

    public function test_placeholders_and_conditional_blocks(): void
    {
        $user = User::factory()->create(['name' => 'Ngozi Adichie', 'credits_balance' => 1500]);
        $email = $this->email(['body' => "Hi {first_name} from {business_name}.\n\n{if:bonus_credits}Gift: {bonus_credits}{/if}{unless:product_name}No product yet.{/unless}\n\nYou have {credits} credits."]);

        $rendered = app(LifecycleEmailRenderer::class)->render($email, $user, ['bonus_credits' => 0]);

        $this->assertSame('Hi Ngozi', $rendered['subject']);
        $this->assertStringContainsString('Hi Ngozi from your business.', $rendered['body_html']);
        $this->assertStringNotContainsString('Gift:', $rendered['body_html']);
        $this->assertStringContainsString('No product yet.', $rendered['body_html']);
        $this->assertStringContainsString('1,500 credits', $rendered['body_html']);
        $this->assertStringContainsString('style="margin:0 0 16px', $rendered['body_html']);
    }

    public function test_user_supplied_values_and_raw_html_are_escaped(): void
    {
        $user = User::factory()->create(['name' => '<script>alert(1)</script> Eve']);
        $user->forceFill(['company_name' => '**Bold** <img src=x onerror=alert(1)>'])->save();
        $email = $this->email(['body' => "{business_name}\n\n<iframe src=\"https://evil.test\"></iframe>\n\n[click](javascript:alert(1))"]);

        $rendered = app(LifecycleEmailRenderer::class)->render($email, $user->fresh());

        $this->assertStringNotContainsString('<script', $rendered['body_html'].$rendered['subject']);
        $this->assertStringNotContainsString('<img', $rendered['body_html']);
        $this->assertStringNotContainsString('<iframe', $rendered['body_html']);
        $this->assertStringNotContainsString('javascript:', $rendered['body_html']);
        $this->assertStringNotContainsString('<strong', $rendered['body_html']);
    }

    public function test_cta_falls_back_to_the_dashboard_for_non_http_links(): void
    {
        $user = User::factory()->create();

        $rendered = app(LifecycleEmailRenderer::class)->render($this->email(['cta_label' => 'Go', 'cta_url' => 'javascript:alert(1)']), $user);
        $this->assertSame(route('dashboard'), $rendered['cta_url']);

        $rendered = app(LifecycleEmailRenderer::class)->render($this->email(['cta_label' => 'Go', 'cta_url' => '{profile_url}']), $user);
        $this->assertSame(route('profile'), $rendered['cta_url']);

        $rendered = app(LifecycleEmailRenderer::class)->render($this->email(['cta_label' => null]), $user);
        $this->assertNull($rendered['cta_url']);
    }

    public function test_every_preset_renders(): void
    {
        $user = User::factory()->create();

        LifecycleEmail::all()->each(function (LifecycleEmail $email) use ($user) {
            $rendered = app(LifecycleEmailRenderer::class)->render($email, $user);
            $this->assertStringNotContainsString('{', $rendered['subject'].$rendered['heading'].$rendered['body_html'], $email->key);
        });
    }
}
