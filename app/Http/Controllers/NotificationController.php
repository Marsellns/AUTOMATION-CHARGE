<?php

namespace App\Http\Controllers;

use App\Exports\SiteLossExport;
use App\Models\SiteMonthlyMetric;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->latest()->paginate(15),
        ]);
    }

    public function siteLoss(Request $request): View
    {
        [$month, $year] = $this->siteLossPeriod($request);
        $sites = $year === null
            ? collect()
            : SiteMonthlyMetric::query()
                ->with('site.region')
                ->where('tahun', $year)
                ->where('bulan', $month)
                ->where('profit_loss', '<=', 0)
                ->orderBy('profit_loss')
                ->get();

        return view('notifications.site-loss', compact('sites', 'month', 'year'));
    }

    public function exportSiteLoss(Request $request)
    {
        [$month, $year] = $this->siteLossPeriod($request);

        abort_if($year === null, 404, 'Belum ada data site Loss untuk diekspor.');

        return (new SiteLossExport($month, $year))
            ->download(sprintf('site_loss_%04d_%02d.xlsx', $year, $month));
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Semua notifikasi telah ditandai sudah dibaca.');
    }

    public function read(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless(
            (string) $notification->notifiable_id === (string) $request->user()->getKey(),
            404
        );

        $notification->markAsRead();

        $url = $notification->data['url'] ?? route('notifications.index');
        if (($notification->data['title'] ?? null) === 'Peringatan site Loss') {
            $month = filter_var($notification->data['month'] ?? null, FILTER_VALIDATE_INT);
            $year = filter_var($notification->data['year'] ?? null, FILTER_VALIDATE_INT);

            if ($month !== false && $year !== false && $month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100) {
                $url = route('notifications.site-loss', ['bulan' => $month, 'tahun' => $year]);
            }
        }

        return redirect()->to($url);
    }

    /**
     * Return the requested notification period, or the newest available period
     * for legacy notifications that did not store a period in their URL.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function siteLossPeriod(Request $request): array
    {
        $month = $request->integer('bulan');
        $year = $request->integer('tahun');

        if ($month !== 0 || $year !== 0) {
            abort_unless($month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100, 422, 'Periode site Loss tidak valid.');

            return [$month, $year];
        }

        $latestPeriod = SiteMonthlyMetric::query()
            ->selectRaw('MAX(tahun * 100 + bulan) as period_key')
            ->value('period_key');

        if ($latestPeriod === null) {
            return [null, null];
        }

        return [(int) $latestPeriod % 100, intdiv((int) $latestPeriod, 100)];
    }
}
