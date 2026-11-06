<?php

namespace App\Console\Commands;

use App\Services\DeploymentConfigurationReport;
use Illuminate\Console\Command;

class CheckDeploymentConfiguration extends Command
{
    protected $signature = 'app:check-deployment {--json : Informe JSON sin valores de credenciales}';
    protected $description = 'Revisar configuración cargada para publicación sin enviar mensajes ni modificar datos';

    public function handle(DeploymentConfigurationReport $report): int
    {
        $checks = $report->checks();
        $pending = count(array_filter($checks, fn ($check) => $check['status'] === 'REVISAR'));
        $manual = [
            'Entrega de correo, Google/Facebook y Turnstile en el dominio público.',
            'Worker y scheduler activos; webhook y avisos WhatsApp reales.',
            'Logo, Open Graph, sitemap, datos legales definitivos y páginas publicadas.',
            'Compra de invitado y cliente, pago, entrega, GPS y valoraciones en móvil.',
            'Copia recuperable de base de datos y archivos; restauración probada.',
        ];

        if ($this->option('json')) {
            $this->line(json_encode([
                'scope' => 'Configuración cargada; no certifica funcionamiento ni publicación completa.',
                'checks' => $checks,
                'requires_review' => $pending,
                'manual_checks' => $manual,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->info('Configuración cargada: revisión local sin envíos ni cambios de datos.');
            $this->table(['Comprobación', 'Estado', 'Revisión'], array_map(fn ($row) => array_values($row), $checks));
            $this->line('Puntos de configuración para revisar: '.$pending.'.');
            $this->warn('CONFIGURADO no demuestra funcionamiento. Aún falta verificar:');
            foreach ($manual as $item) {
                $this->line('- '.$item);
            }
        }

        return $pending > 0 ? self::FAILURE : self::SUCCESS;
    }
}
