<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SMS is gone (owner decision, 2026-10-16): messages cost money, and the
 * Committee has no budget for them. Alerts no longer list an «SMS-шлюз
 * оповещения» channel — the wizard hid it already, but alerts saved with it
 * would fail validation on their next save — and the unused gateway-status
 * setting is dropped. Proposals waiting for approval carry the raw column
 * value and are cleaned too.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('alerts')->where('channels', 'like', '%"sms"%')->select(['id', 'channels'])->lazyById()
            ->each(function (object $row): void {
                DB::table('alerts')->where('id', $row->id)->update([
                    'channels' => $this->withoutSms((string) $row->channels),
                ]);
            });

        DB::table('pending_changes')
            ->where('changeable_type', 'App\\Models\\Alert')
            ->where('status', 'pending')
            ->where('changes', 'like', '%sms%')
            ->select(['id', 'changes'])
            ->lazyById()
            ->each(function (object $row): void {
                $changes = json_decode((string) $row->changes, true);

                if (! is_array($changes) || ! is_string($changes['channels'] ?? null)) {
                    return;
                }

                $changes['channels'] = $this->withoutSms($changes['channels']);

                DB::table('pending_changes')->where('id', $row->id)->update([
                    'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
            });

        DB::table('settings')->where('group', 'integrations')->where('key', 'sms_gateway_status')->delete();
    }

    /**
     * Nothing to bring back: which alerts had SMS ticked is not kept.
     */
    public function down(): void {}

    private function withoutSms(string $json): string
    {
        $channels = json_decode($json, true);
        $channels = is_array($channels) ? array_values(array_filter($channels, fn (mixed $c): bool => $c !== 'sms')) : [];

        return json_encode($channels, JSON_THROW_ON_ERROR);
    }
};
