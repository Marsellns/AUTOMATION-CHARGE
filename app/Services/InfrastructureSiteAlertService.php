<?php

namespace App\Services;

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

class InfrastructureSiteAlertService
{
    /**
     * @return array{website_categories: list<string>, email_sent: bool, warning_counts: array<string, int>}
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
            'email_sent' => false,
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

        $emailClaim = DailyNotificationGate::reserve('email', 'infrastructure-sites');
        if ($emailClaim === null) {
            return $result;
        }

        try {
            $lines = [
                'Peringatan harian site Infrastruktur Management',
                "Tanggal: {$notificationDate}",
                '',
            ];

            foreach ($activeSummaries as $summary) {
                $counts = $summary['status_counts'];
                $lines[] = sprintf(
                    '%s: %d dari %d site perlu perhatian (berakhir: %d, ≤90 hari: %d, 91–180 hari: %d, tanpa tanggal akhir: %d).',
                    $summary['label'],
                    $summary['warning_count'],
                    $summary['total_count'],
                    $counts['expired'],
                    $counts['within_90'],
                    $counts['within_180'],
                    $counts['unknown'],
                );
                $lines[] = $summary['url'];
                $lines[] = '';
            }

            Mail::raw(implode("\n", $lines), function ($message) use ($recipient, $notificationDate): void {
                $message->to(trim($recipient))
                    ->subject("Peringatan harian site Infrastruktur - {$notificationDate}");
            });
            $result['email_sent'] = true;
        } catch (\Throwable $exception) {
            DailyNotificationGate::release($emailClaim);
            Log::error('Email site Infrastruktur gagal dikirim.', [
                'recipient' => $recipient,
                'exception' => $exception,
            ]);
        }

        return $result;
    }

    /**
     * @return array<string, array{label: string, warning_count: int, total_count: int, status_counts: array<string, int>, url: string}>
     */
    private function summaries(): array
    {
        $ownersBySite = SiteOwner::query()
            ->get()
            ->keyBy(fn (SiteOwner $owner): string => strtoupper(trim((string) $owner->site_code)));
        $sewaSites = $this->uniqueSites(SewaLahanRenewal::query()->get());
        $combatSites = $this->uniqueSites(CombatSite::query()->get());

        $definitions = [
            'site_telkomsel' => [
                'label' => 'Site Telkomsel',
                'rows' => $sewaSites->filter(
                    fn ($row): bool => InfrastructureOwnership::bucketForRow($row, $ownersBySite) === InfrastructureOwnership::TELKOMSEL
                ),
                'url' => route('infrastruktur.sewa-lahan.index', ['ownership_scope' => InfrastructureOwnership::TELKOMSEL]),
            ],
            'site_tp' => [
                'label' => 'Site TP',
                'rows' => $sewaSites->filter(
                    fn ($row): bool => InfrastructureOwnership::bucketForRow($row, $ownersBySite) === InfrastructureOwnership::TP
                ),
                'url' => route('infrastruktur.sewa-lahan.index', ['ownership_scope' => InfrastructureOwnership::TP]),
            ],
            'combat' => [
                'label' => 'Combat',
                'rows' => $combatSites,
                'url' => route('infrastruktur.combat.index'),
            ],
        ];

        return collect($definitions)
            ->map(fn (array $definition): array => $this->summarize($definition))
            ->all();
    }

    /**
     * @param  array{label: string, rows: Collection, url: string}  $definition
     * @return array{label: string, warning_count: int, total_count: int, status_counts: array<string, int>, url: string}
     */
    private function summarize(array $definition): array
    {
        $statusCounts = [
            'expired' => 0,
            'within_90' => 0,
            'within_180' => 0,
            'unknown' => 0,
        ];

        foreach ($definition['rows'] as $row) {
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
        }

        return [
            'label' => $definition['label'],
            'warning_count' => array_sum($statusCounts),
            'total_count' => $definition['rows']->count(),
            'status_counts' => $statusCounts,
            'url' => $definition['url'],
        ];
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
