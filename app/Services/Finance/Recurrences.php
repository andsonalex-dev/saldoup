<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Memberships\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class Recurrences
{
    public function generate(User $u, Workspace $w, array $data): int
    {
        return DB::transaction(function () use ($u, $w, $data) {
            app(Permissions::class)->authorize($u, $w, 'transactions.create');
            $a = app(Ledger::class)->account($u, $w, (int) $data['account_id'], true);
            Account::whereKey($a->id)->lockForUpdate()->first();
            $data['amount'] = Money::normalize($data['amount'], true);
            $fingerprint = hash('sha256', json_encode($data));
            $batch = DB::table('recurrence_batches')->where('workspace_id', $w->id)->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($batch) {
                abort_unless($batch->created_by === $u->id && $batch->payload_hash === $fingerprint, 422);

                return $batch->occurrences;
            }
            $start = CarbonImmutable::parse($data['date']);
            $end = CarbonImmutable::parse($data['until']);
            $count = 0;
            for ($index = 0; ; $index++) {
                $date = $data['frequency'] === 'monthly' ? $start->addMonthsNoOverflow($index) : $start->addWeeks($index);
                if ($date->gt($end)) {
                    break;
                }abort_if($count >= 120, 422, 'Gere no máximo 120 ocorrências por lote.');
                app(Ledger::class)->transaction($u, $w, ['account_id' => $a->id, 'category_id' => $data['category_id'] ?? null, 'type' => $data['type'], 'status' => 'planned', 'description' => $data['description'], 'amount' => $data['amount'], 'date' => $date->toDateString(), 'idempotency_key' => hash('sha256', 'recurrence:'.$data['idempotency_key'].':'.$index)]);
                $count++;
            }
            $id = DB::table('recurrence_batches')->insertGetId(['workspace_id' => $w->id, 'created_by' => $u->id, 'idempotency_key' => $data['idempotency_key'], 'payload_hash' => $fingerprint, 'occurrences' => $count, 'created_at' => now(), 'updated_at' => now()]);
            app(Ledger::class)->audit($u, $w->id, 'recurrence_batch', $id, 'generated', ['occurrences']);

            return $count;
        }, 3);
    }
}
