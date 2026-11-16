<?php

namespace App\Console\Commands;

use App\Services\AdminPushDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendAdminPushNotifications extends Command
{
    protected $signature = 'app:send-admin-push';
    protected $description = 'Procesa hasta diez avisos pendientes del administrador sin mostrar credenciales';

    public function handle(AdminPushDelivery $delivery): int
    {
        if (! config('admin_push.enabled')) {
            $this->info('Avisos automáticos desactivados.');
            return self::SUCCESS;
        }
        DB::table('admin_push_messages')->where('status', 'processing')
            ->where('claimed_at', '<', now()->subMinutes(10))
            ->update(['status' => 'uncertain', 'result' => 'interrupted', 'updated_at' => now()]);
        $ids = DB::table('admin_push_messages')->where('status', 'pending')
            ->where('available_at', '<=', now())->orderBy('id')->limit(10)->pluck('id');
        foreach ($ids as $id) $delivery->send((int) $id);
        DB::table('admin_push_messages')->where('created_at', '<', now()->subDays(30))->delete();
        $this->info('Avisos revisados: '.$ids->count().'. Consultar estados en admin_push_messages.');
        return self::SUCCESS;
    }
}
