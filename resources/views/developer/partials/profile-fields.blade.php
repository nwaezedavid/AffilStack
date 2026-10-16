{{-- Developer profile fields. Optional $developer. --}}
@php($input = 'w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm text-navy-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30')
<x-ui.field label="Developer or company name" for="display_name" hint="Shown on your add-ons." :error="$errors->first('display_name')">
    <input id="display_name" name="display_name" value="{{ old('display_name', $developer->display_name ?? auth()->user()->name) }}" required maxlength="80" class="{{ $input }}">
</x-ui.field>
<x-ui.field label="Support email" for="support_email" hint="Customers contact you here for help with your add-ons." :error="$errors->first('support_email')">
    <input id="support_email" type="email" name="support_email" value="{{ old('support_email', $developer->support_email ?? auth()->user()->email) }}" required class="{{ $input }}">
</x-ui.field>
<x-ui.field label="Website" for="website" optional :error="$errors->first('website')">
    <input id="website" type="url" name="website" value="{{ old('website', $developer->website ?? '') }}" placeholder="https://" class="{{ $input }}">
</x-ui.field>
<x-ui.field label="About you" for="bio" optional :error="$errors->first('bio')">
    <textarea id="bio" name="bio" rows="3" maxlength="1000" class="{{ $input }}">{{ old('bio', $developer->bio ?? '') }}</textarea>
</x-ui.field>
