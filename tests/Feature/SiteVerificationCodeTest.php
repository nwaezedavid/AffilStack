<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Support\Seo\SiteVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteVerificationCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_whole_meta_tag_is_reduced_to_its_code(): void
    {
        $this->assertSame('-3dH9WIVUsEBJVKO1', SiteVerificationCode::normalize('<meta name="google-site-verification" content="-3dH9WIVUsEBJVKO1" />'));
        $this->assertSame('ABC123', SiteVerificationCode::normalize("<meta name='msvalidate.01' content='ABC123'>"));
        $this->assertSame('-3dH9WIVUsEBJVKO1', SiteVerificationCode::normalize('&lt;meta name=&quot;google-site-verification&quot; content=&quot;-3dH9WIVUsEBJVKO1&quot; /&gt;'));
    }

    public function test_a_plain_code_is_kept_and_blank_becomes_null(): void
    {
        $this->assertSame('ABC123', SiteVerificationCode::normalize('  "ABC123" '));
        $this->assertNull(SiteVerificationCode::normalize('   '));
        $this->assertNull(SiteVerificationCode::normalize(null));
    }

    public function test_the_homepage_prints_only_the_code_even_if_a_whole_tag_was_saved(): void
    {
        SiteSetting::set('seo_gsc_verification', '<meta name="google-site-verification" content="-3dH9WIVUsEBJVKO1" />');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta name="google-site-verification" content="-3dH9WIVUsEBJVKO1">', false);
    }
}
