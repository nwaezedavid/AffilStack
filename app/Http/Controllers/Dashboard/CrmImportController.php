<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use App\Models\CrmList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CSV import into a chosen list, in two steps: upload → a preview with a
 * column-mapping form (columns are guessed from the header row) → import.
 * Existing contacts (same email) are reused and just added to the list, so
 * re-importing a file never duplicates anyone; autopilot campaigns on the
 * list fire for every genuinely new member.
 */
class CrmImportController extends Controller
{
    public const FIELDS = ['name', 'first_name', 'last_name', 'company', 'title', 'email', 'phone', 'website', 'location', 'notes'];

    protected const MAX_ROWS = 5000;

    public function upload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:4096',
            'list_id' => ['required', 'integer', Rule::exists('crm_lists', 'id')->where('user_id', auth()->id())],
        ], ['list_id.required' => 'Choose which list these contacts belong to.']);

        $token = Str::random(24);
        Storage::disk('local')->putFileAs('crm-imports', $request->file('file'), auth()->id().'-'.$token.'.csv');

        return redirect()->route('crm.import.preview', ['token' => $token, 'list' => $validated['list_id']]);
    }

    public function preview(Request $request, string $token): View|RedirectResponse
    {
        $rows = $this->readRows($token);

        if ($rows === null || count($rows) < 2) {
            return redirect()->route('crm.index')->with('error', 'That file had no rows to import — export it from your spreadsheet as CSV with a header row.');
        }

        $headers = array_map(fn ($h) => trim((string) $h), $rows[0]);

        return view('dashboard.crm.import', [
            'token' => $token,
            'headers' => $headers,
            'sample' => array_slice($rows, 1, 5),
            'rowCount' => min(count($rows) - 1, self::MAX_ROWS),
            'mapping' => $this->guessMapping($headers),
            'lists' => auth()->user()->crmLists()->orderBy('name')->get(),
            'selectedList' => (int) $request->query('list'),
            'fields' => self::FIELDS,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $validated = $request->validate([
            'list_id' => ['required', 'integer', Rule::exists('crm_lists', 'id')->where('user_id', auth()->id())],
            'mapping' => 'required|array',
            'mapping.*' => ['nullable', Rule::in(self::FIELDS)],
        ]);

        $rows = $this->readRows($token);

        if ($rows === null) {
            return redirect()->route('crm.index')->with('error', 'That upload has expired — please upload the file again.');
        }

        $mapping = array_filter($validated['mapping']);

        if (! in_array('email', $mapping, true) && ! array_intersect(['name', 'first_name', 'company'], $mapping)) {
            return back()->with('error', 'Map at least an Email, Name or Company column.');
        }

        $user = auth()->user();
        $list = CrmList::findOrFail($validated['list_id']);
        $limit = $user->activeSubscription?->plan?->contact_limit ?? 0;
        $existingCount = $user->crmContacts()->count();
        $created = 0;
        $addedToList = 0;
        $skipped = 0;
        $limitHit = false;

        foreach (array_slice($rows, 1, self::MAX_ROWS) as $row) {
            $data = [];

            foreach ($mapping as $index => $field) {
                $value = trim((string) ($row[(int) $index] ?? ''));

                if ($value !== '') {
                    $data[$field] = Str::limit($value, $field === 'notes' ? 2000 : 250, '');
                }
            }

            if (isset($data['first_name']) || isset($data['last_name'])) {
                $data['name'] ??= trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
            }

            unset($data['first_name'], $data['last_name']);

            if (isset($data['email'])) {
                $data['email'] = strtolower($data['email']);

                if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    unset($data['email']);
                }
            }

            if (isset($data['website']) && ! preg_match('#^https?://#i', $data['website'])) {
                $data['website'] = 'https://'.$data['website'];
            }

            if (empty($data['email']) && empty($data['name']) && empty($data['company'])) {
                $skipped++;

                continue;
            }

            $contact = ! empty($data['email'])
                ? $user->crmContacts()->whereRaw('lower(email) = ?', [$data['email']])->first()
                : null;

            if (! $contact) {
                if ($limit > 0 && $existingCount + $created >= $limit) {
                    $limitHit = true;

                    break;
                }

                $contact = CrmContact::create($data + ['user_id' => $user->id, 'source' => 'import', 'status' => 'new']);
                $created++;
            }

            if ($list->addContact($contact)) {
                $addedToList++;
            }
        }

        Storage::disk('local')->delete($this->path($token));

        $message = "Imported into {$list->name}: {$created} new contact(s), {$addedToList} added to the list".($skipped ? ", {$skipped} empty row(s) skipped" : '').'.';

        if ($limitHit) {
            $message .= ' Your plan\'s contact limit was reached, so the rest of the file was not imported.';
        }

        return redirect()->route('crm.lists.show', $list)->with('success', $message);
    }

    protected function path(string $token): string
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{24}$/', $token) === 1, 404);

        return 'crm-imports/'.auth()->id().'-'.$token.'.csv';
    }

    /**
     * @return array<int, array<int, string|null>>|null
     */
    protected function readRows(string $token): ?array
    {
        $path = $this->path($token);

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        $handle = fopen(Storage::disk('local')->path($path), 'r');
        $first = fgets($handle) ?: '';
        rewind($handle);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false && count($rows) <= self::MAX_ROWS) {
            if ($row === [null]) {
                continue;
            }

            $rows[] = array_map(fn ($cell) => $cell === null ? null : mb_scrub(preg_replace('/^\xEF\xBB\xBF/', '', $cell) ?? $cell, 'UTF-8'), $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Header text → CRM field, e.g. "E-mail Address" → email.
     *
     * @param  array<int, string>  $headers
     * @return array<int, string|null>
     */
    protected function guessMapping(array $headers): array
    {
        $patterns = [
            'email' => '/e-?mail/',
            'first_name' => '/^(first|given)[ _-]?name$|^first$/',
            'last_name' => '/^(last|family|sur)[ _-]?name$|^last$|^surname$/',
            'name' => '/^(full[ _-]?)?name$|contact[ _-]?name|^contact$/',
            'company' => '/company|business|organi[sz]ation|^org$|account/',
            'title' => '/title|role|position|job/',
            'phone' => '/phone|mobile|tel|whatsapp/',
            'website' => '/web|site|url|domain/',
            'location' => '/location|city|address|country|region|state/',
            'notes' => '/note|comment|description/',
        ];
        $used = [];

        return array_map(function (string $header) use ($patterns, &$used) {
            $normalised = strtolower(trim($header));

            foreach ($patterns as $field => $pattern) {
                if (! in_array($field, $used, true) && preg_match($pattern, $normalised)) {
                    $used[] = $field;

                    return $field;
                }
            }

            return null;
        }, $headers);
    }
}
