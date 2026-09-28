<?php

namespace App\Services;

use App\Exports\InfrastructureSiteAlertExport;
use App\Models\CombatSite;
use App\Models\SewaLahanRenewal;
use App\Models\SiteOwner;
use App\Models\User;
use App\Notifications\InfrastructureSiteAlertNotification;
use App\Support\CombatSourceDetails;
use App\Support\DailyNotificationGate;
use App\Support\InfrastructureOwnership;
use App\Support\LeaseStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class InfrastructureSiteAlertService
{
    /**
     * @return array{website_categories: list<string>, email_categories: list<string>, warning_counts: array<string, int>}
     */
    public function send(): array
    {
        $summaries = $this->summaries();
        $activeSummaries = array_filter(
            $summaries,
            static fn (array $summary): bool => $summary['warning_count'] > 0
        );
        $result = [
            'website_categories' => [],
            'email_categories' => [],
            'warning_counts' => array_map(
                static fn (array $summary): int => $summary['warning_count'],
                $summaries
            ),
        ];

        if ($activeSummaries === []) {
            return $result;
        }

        $notificationDate = CarbonImmutable::now(
            (string) config('notifications.timezone', 'Asia/Jakarta')
        )->toDateString();
        $recipients = User::query()
            ->where('account_status', 'approved')
            ->get();

        if ($recipients->isNotEmpty()) {
            foreach ($activeSummaries as $category => $summary) {
                $claim = DailyNotificationGate::reserve('website', 'infrastructure-'.$category);
                if ($claim === null) {
                    continue;
                }

                try {
                    DB::transaction(function () use ($recipients, $category, $summary, $notificationDate): void {
                        $recipients->each(fn (User $user) => $user->notify(
                            new InfrastructureSiteAlertNotification(
                                $category,
                                $summary['label'],
                                $summary['warning_count'],
                                $summary['total_count'],
                                $summary['status_counts'],
                                $summary['url'],
                                $notificationDate,
                            )
                        ));
                    });
                    $result['website_categories'][] = $category;
                } catch (\Throwable $exception) {
                    DailyNotificationGate::release($claim);
                    Log::error('Notifikasi website site Infrastruktur gagal dikirim.', [
                        'category' => $category,
                        'exception' => $exception,
                    ]);
                }
            }
        }

        $recipient = config('mail.infrastructure_alert_to');
        if (! is_string($recipient) || trim($recipient) === '') {
            Log::warning('Email site Infrastruktur tidak dikirim karena penerima belum dikonfigurasi.');

            return $result;
        }

        foreach ($activeSummaries as $category => $summary) {
            $emailClaim = DailyNotificationGate::reserve('email', 'infrastructure-'.$category);
            if ($emailClaim === null) {
                continue;
            }

            try {
                $counts = $summary['status_counts'];
                $body = implode("\n", [
                    'Peringatan harian site Infrastruktur Management',
                    "Kategori: {$summary['label']}",
                    "Tanggal: {$notificationDate}",
                    '',
                    sprintf(
                        '%s: %d dari %d site perlu perhatian (berakhir: %d, ≤90 hari: %d, 91–180 hari: %d, tanpa tanggal akhir: %d).',
                        $summary['label'],
                        $summary['warning_count'],
                        $summary['total_count'],
                        $counts['expired'],
                        $counts['within_90'],
                        $counts['within_180'],
                        $counts['unknown'],
                    ),
                    '',
                    'Rincian site tersedia pada lampiran Excel.',
                    $summary['url'],
                ]);
                $attachment = Excel::raw(
                    new InfrastructureSiteAlertExport($summary['label'], $summary['alert_rows']),
                    ExcelFormat::XLSX
                );
                $filename = sprintf(
                    'peringatan_%s_%s.xlsx',
                    str($category)->slug('_'),
                    str_replace('-', '', $notificationDate)
                );

                Mail::raw($body, function ($message) use ($recipient, $summary, $notificationDate, $attachment, $filename): void {
                    $message->to(trim($recipient))
                        ->subject("Peringatan harian {$summary['label']} - {$notificationDate}")
                        ->attachData($attachment, $filename, [
                            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ]);
                });
                $result['email_categories'][] = $category;
            } catch (\Throwable $exception) {
                DailyNotificationGate::release($emailClaim);
                Log::error('Email site Infrastruktur gagal dikirim.', [
                    'recipient' => $recipient,
                    'category' => $category,
                    'exception' => $exception,
                ]);
            }
        }

        return $result;
    }

    /**
     * @return array<string, array{label: string, warning_count: int, total_count: int, status_counts: array<string, int>, url: string, alert_rows: Collection}>
     */
    private function summaries(): array
    {
        $ownersBySite = SiteOwner::query()
            ->get()
            ->keyBy(fn (SiteOwner $owner): string => strtoupper(trim((string) $owner->site_code)));
        $sewaSites = $this->uniqueSites(SewaLahanRenewal::query()->get());
        $combatSites = $this->uniqueSites(CombatSite::query()->get());

        $definitions = [
            'sewa_lahan' => [
                'label' => 'Sewa Lahan',
                'rows' => $sewaSites,
                'url' => $this->alertUrl('infrastruktur.sewa-lahan.index'),
            ],
            'site_tp' => [
                'label' => 'Site TP',
                'rows' => $sewaSites->filter(
                    fn ($row): bool => InfrastructureOwnership::bucketForRow($row, $ownersBySite) === InfrastructureOwnership::TP
                ),
                'url' => $this->alertUrl('infrastruktur.sewa-lahan.index', InfrastructureOwnership::TP),
            ],
            'site_telkomsel' => [
                'label' => 'Site Telkomsel',
                'rows' => $sewaSites->filter(
                    fn ($row): bool => InfrastructureOwnership::bucketForRow($row, $ownersBySite) === InfrastructureOwnership::TELKOMSEL
                ),
                'url' => $this->alertUrl('infrastruktur.sewa-lahan.index', InfrastructureOwnership::TELKOMSEL),
            ],
            'combat' => [
                'label' => 'Combat',
                'rows' => $combatSites,
                'url' => $this->alertUrl('infrastruktur.combat.index'),
            ],
        ];

        return collect($definitions)
            ->map(fn (array $definition): array => $this->summarize($definition))
            ->all();
    }

    /**
     * @param  array{label: string, rows: Collection, url: string}  $definition
     * @return array{label: string, warning_count: int, total_count: int, status_counts: array<string, int>, url: string, alert_rows: Collection}
     */
    private function summarize(array $definition): array
    {
        $statusCounts = [
            'expired' => 0,
            'within_90' => 0,
            'within_180' => 0,
            'unknown' => 0,
        ];

        $alertRows = $definition['rows']->filter(function ($row) use (&$statusCounts): bool {
            $status = LeaseStatus::fromEndDate($row->end_date_baru ?? $row->end_date_lama);
            $key = match ($status) {
                LeaseStatus::EXPIRED => 'expired',
                LeaseStatus::WITHIN_90_DAYS => 'within_90',
                LeaseStatus::WITHIN_180_DAYS => 'within_180',
                LeaseStatus::NO_END_DATE => 'unknown',
                default => null,
            };

            if ($key !== null) {
                $statusCounts[$key]++;
            }

            return $key !== null;
        })->values();

        return [
            'label' => $definition['label'],
            'warning_count' => $alertRows->count(),
            'total_count' => $definition['rows']->count(),
            'status_counts' => $statusCounts,
            'url' => $definition['url'],
            'alert_rows' => $alertRows,
        ];
    }

    private function alertUrl(string $routeName, ?string $ownershipScope = null): string
    {
        $parameters = [
            'filter_field' => 'lease_alert',
            'filter_value' => 'active',
            'unique_sites' => 1,
        ];
        if ($ownershipScope !== null) {
            $parameters['ownership_scope'] = $ownershipScope;
        }

        return route($routeName, $parameters).'#infrastructure-data';
    }

    private function uniqueSites(Collection $rows): Collection
    {
        return $rows
            ->sortByDesc(static function ($row): int {
                $details = CombatSourceDetails::flattened($row->source_details);
                $score = (filled($row->status_dokumen) ? 16 : 0)
                    + (filled($row->status_perpanjangan) ? 8 : 0)
                    + (filled($row->end_date_baru ?? $row->end_date_lama) ? 4 : 0)
                    + (filled($details['status'] ?? null) ? 2 : 0)
                    + (filled($row->site_name) ? 1 : 0);

                return ($score * 1000000) + (int) $row->id;
            })
            ->unique(fn ($row): string => strtoupper(trim((string) $row->site_code)))
            ->values();
    }
}
