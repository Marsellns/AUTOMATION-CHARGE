<?php

use App\Support\RecurringTagihanSummary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve every original row and source_details in the detail table.
        Schema::create('recurring_tagihan_ipas_sites', function (Blueprint $table): void {
            $table->id();
            $table->string('site_code', 30)->unique();
            foreach (['site_name', 'tp', 'contract_type', 'termin', 'periode_ke', 'batch_name'] as $column) {
                $table->text($column)->nullable();
            }
            $table->date('termin_start')->nullable()->index();
            $table->date('termin_end')->nullable()->index();
            $table->decimal('amount', 24, 2)->nullable()->comment('Total seluruh rincian termin site');
            $table->unsignedInteger('term_count')->default(0);
            $table->json('source_details')->nullable();
            $table->timestamps();
        });
        Schema::table('recurring_tagihan_ipas', function (Blueprint $table): void {
            $table->unsignedBigInteger('site_id')->nullable()->index();
        });

        DB::transaction(function (): void {
            DB::table('recurring_tagihan_ipas_sites')->insertUsing(['site_code'],
                DB::table('recurring_tagihan_ipas')->selectRaw('DISTINCT UPPER(TRIM(site_code))'));
            DB::table('recurring_tagihan_ipas_sites')->orderBy('id')->chunkById(100, function ($sites): void {
                $codes = $sites->pluck('site_code');
                $rows = DB::table('recurring_tagihan_ipas')->whereIn(DB::raw('UPPER(TRIM(site_code))'), $codes)->get();
                $groups = $rows->groupBy(fn ($row) => RecurringTagihanSummary::siteCode($row->site_code));
                $totals = DB::table('recurring_tagihan_ipas')->whereIn(DB::raw('UPPER(TRIM(site_code))'), $codes)
                    ->selectRaw('UPPER(TRIM(site_code)) AS code, SUM(amount) AS total')->groupByRaw('UPPER(TRIM(site_code))')->pluck('total', 'code');
                foreach ($sites as $site) {
                    $group = $groups[$site->site_code];
                    DB::table('recurring_tagihan_ipas_sites')->where('id', $site->id)->update(
                        RecurringTagihanSummary::attributes($group, $totals[$site->site_code]) + [
                            'created_at' => $group->pluck('created_at')->filter()->min(),
                            'updated_at' => $group->pluck('updated_at')->filter()->max(),
                        ]);
                    DB::table('recurring_tagihan_ipas')->whereIn('id', $group->pluck('id'))->update(['site_id' => $site->id]);
                }
            });
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('RENAME TABLE recurring_tagihan_ipas TO recurring_tagihan_ipas_terms, recurring_tagihan_ipas_sites TO recurring_tagihan_ipas');
        } else {
            Schema::rename('recurring_tagihan_ipas', 'recurring_tagihan_ipas_terms');
            Schema::rename('recurring_tagihan_ipas_sites', 'recurring_tagihan_ipas');
        }
        Schema::table('recurring_tagihan_ipas_terms', function (Blueprint $table): void {
            $table->foreign('site_id', 'ipas_terms_site_fk')->references('id')->on('recurring_tagihan_ipas')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recurring_tagihan_ipas_terms', function (Blueprint $table): void {
            $table->dropForeign('ipas_terms_site_fk');
            $table->dropColumn('site_id');
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('RENAME TABLE recurring_tagihan_ipas TO recurring_tagihan_ipas_sites, recurring_tagihan_ipas_terms TO recurring_tagihan_ipas');
        } else {
            Schema::rename('recurring_tagihan_ipas', 'recurring_tagihan_ipas_sites');
            Schema::rename('recurring_tagihan_ipas_terms', 'recurring_tagihan_ipas');
        }
        Schema::drop('recurring_tagihan_ipas_sites');
    }
};
