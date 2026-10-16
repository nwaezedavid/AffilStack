<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\DeveloperProfile;
use App\Support\Marketplace\MarketplaceSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Registering as a developer (a short profile and the developer terms),
 * and editing that profile later.
 */
class ProfileController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->developerProfile) {
            return redirect()->route('developer.dashboard');
        }

        return view('developer.join', [
            'terms' => MarketplaceSettings::terms(),
            'share' => MarketplaceSettings::revenueSharePercent(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->developerProfile) {
            return redirect()->route('developer.dashboard');
        }

        $data = $request->validate($this->rules() + ['accept_terms' => ['accepted']], [
            'accept_terms.accepted' => 'Please accept the developer terms.',
        ]);

        DeveloperProfile::create([
            'user_id' => $user->id,
            'display_name' => $data['display_name'],
            'slug' => $this->uniqueSlug($data['display_name']),
            'website' => $data['website'] ?? null,
            'support_email' => $data['support_email'],
            'bio' => $data['bio'] ?? null,
            'status' => 'active',
            'terms_accepted_at' => now(),
        ]);

        return redirect()->route('developer.dashboard')->with('success', 'Welcome! Create your first add-on to get started.');
    }

    public function edit(Request $request): View
    {
        return view('developer.profile', ['developer' => $request->user()->developerProfile]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->user()->developerProfile->update($request->validate($this->rules()));

        return redirect()->route('developer.profile')->with('success', 'Profile saved.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'min:2', 'max:80'],
            'website' => ['nullable', 'url:https,http', 'max:255'],
            'support_email' => ['required', 'email', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'developer', 70, '');
        $slug = $base;
        $i = 2;

        while (DeveloperProfile::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
