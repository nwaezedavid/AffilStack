<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsSegment;
use App\Services\Analytics\AnalyticsExporter;
use App\Services\Analytics\SegmentEvaluator;
use App\Support\Analytics\IpPrivacy;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Agent 04: streamed CSV downloads for Analytics → Downloads / Audiences.
 * Full admins only (these files hold personal data), every download is
 * written to the activity log (log "analytics-export") before it starts,
 * and rows stream in batches so a big export never exhausts memory.
 *
 * Spreadsheet safety: report CSVs neutralise cells that start with = + - @
 * (CSV/formula injection) and carry a UTF-8 BOM so Excel shows non-English
 * city names correctly. Audience CSVs are left exactly as Meta/Google
 * expect — hashes only, no BOM.
 */
class AnalyticsExportController extends Controller
{
    public function download(Request $request, string $type, AnalyticsExporter $exporter, SegmentEvaluator $evaluator): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user && $user->canAccessPanel(Filament::getPanel('admin')) && $user->isFullAdmin(), 403);

        [$from, $to] = $this->range($request);

        if ($type === 'audience') {
            $segment = AnalyticsSegment::query()->findOrFail((int) $request->query('segment'));
            $platform = $request->query('platform') === 'google' ? 'google' : 'meta';
            [$headers, $rows] = $exporter->audience($segment, $platform, $evaluator);
            $filename = sprintf('%s-audience-%s-%s.csv', $platform, Str::slug($segment->name), now()->format('Y-m-d'));

            $this->audit($user, 'audience', ['segment_id' => $segment->id, 'segment' => $segment->name, 'platform' => $platform]);

            return $this->stream($filename, $headers, $rows, spreadsheetSafe: false);
        }

        abort_unless(array_key_exists($type, AnalyticsExporter::TYPES), 404);

        [$headers, $rows] = $exporter->export($type, $from, $to);
        $dated = AnalyticsExporter::TYPES[$type]['dated'];
        $filename = sprintf('affilstack-%s-%s.csv', Str::slug($type), $dated ? $from->format('Ymd').'-'.$to->format('Ymd') : now()->format('Ymd'));

        $this->audit($user, $type, $dated ? ['from' => $from->toDateString(), 'to' => $to->toDateString()] : []);

        return $this->stream($filename, $headers, $rows, spreadsheetSafe: true);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function range(Request $request): array
    {
        try {
            $from = $request->filled('from') ? Carbon::parse((string) $request->query('from'))->startOfDay() : now()->subDays(29)->startOfDay();
            $to = $request->filled('to') ? Carbon::parse((string) $request->query('to'))->endOfDay() : now()->endOfDay();
        } catch (Throwable) {
            abort(422, 'Invalid date range.');
        }

        return $from->greaterThan($to) ? [$to->copy()->startOfDay(), $from->copy()->endOfDay()] : [$from, $to];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected function audit($user, string $type, array $properties): void
    {
        activity('analytics-export')
            ->causedBy($user)
            ->event('export')
            ->withProperties(['type' => $type] + $properties + ['ip_hash' => IpPrivacy::hash(request()->ip())])
            ->log('Downloaded '.(AnalyticsExporter::TYPES[$type]['label'] ?? 'audience').' export');
    }

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    protected function stream(string $filename, array $headers, iterable $rows, bool $spreadsheetSafe): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows, $spreadsheetSafe): void {
            $out = fopen('php://output', 'w');

            if ($spreadsheetSafe) {
                fwrite($out, "\xEF\xBB\xBF");
            }

            fputcsv($out, $headers, escape: '');

            foreach ($rows as $row) {
                fputcsv($out, $spreadsheetSafe ? array_map($this->safeCell(...), $row) : $row, escape: '');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    protected function safeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
