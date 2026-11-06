<?php

namespace App\Services;

class DeploymentConfigurationReport
{
    public function checks(): array
    {
        $checks = [];
        $add = function (string $id, string $status, string $detail) use (&$checks): void {
            $checks[] = compact('id', 'status', 'detail');
        };
        $check = function (string $id, bool $configured, string $detail) use ($add): void {
            $add($id, $configured ? 'CONFIGURADO' : 'REVISAR', $detail);
        };
        $present = function (string $key): bool {
            $value = config($key);
            return is_string($value) && trim($value) !== '';
        };
        $https = function (mixed $url): bool {
            if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
                return false;
            }
            $parts = parse_url($url);
            return ($parts['scheme'] ?? '') === 'https'
                && ! isset($parts['user']) && ! isset($parts['pass']);
        };

        $check('entorno', app()->environment('production'), 'Para publicación, usar APP_ENV=production.');
        $check('debug', config('app.debug') === false, 'Para publicación, usar APP_DEBUG=false.');
        $check('url', $https(config('app.url')), 'APP_URL debe ser una URL HTTPS; revisar también el certificado y el dominio real.');
        $check('sesion-segura', config('session.secure') === true, 'Configurar SESSION_SECURE_COOKIE=true para HTTPS.');
        $check('sesion-persistente', in_array(config('session.driver'), ['database', 'file', 'redis', 'memcached', 'dynamodb', 'cookie'], true),
            'Revisar el almacenamiento persistente de sesiones y su funcionamiento real.');

        $hosts = config('turnstile.hostnames');
        $check('turnstile', (bool) config('turnstile.enabled')
            && $present('turnstile.site_key') && $present('turnstile.secret_key')
            && is_array($hosts) && count(array_filter($hosts, fn ($host) => is_string($host) && trim($host) !== '')) > 0,
            'Activar Turnstile con ambas claves y dominios permitidos; probar el registro desde el dominio público.');

        foreach ([
            'google_login' => ['client_id', 'client_secret'],
            'facebook_login' => ['app_id', 'app_secret', 'graph_version'],
        ] as $provider => $keys) {
            if (! config($provider.'.enabled')) {
                $add($provider, 'DESACTIVADO', 'Proveedor opcional desactivado; el acceso directo se conserva.');
                continue;
            }
            $complete = $https(config($provider.'.redirect_uri'));
            foreach ($keys as $key) {
                $complete = $present($provider.'.'.$key) && $complete;
            }
            $check($provider, $complete, 'Revisar credenciales y retorno HTTPS; comprobar acceso real y URL autorizada en el proveedor.');
        }

        $mailer = config('mail.default');
        $transport = is_string($mailer) ? config('mail.mailers.'.$mailer.'.transport') : null;
        $check('correo', in_array($transport, ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'resend', 'mailgun'], true),
            'Se reconoce un transporte de envío directo; log/array no entregan correo. Compuestos o personalizados requieren revisión. Probar recuperación real.');

        $connection = config('queue.default');
        $driver = is_string($connection) ? config('queue.connections.'.$connection.'.driver') : null;
        $check('cola', in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true),
            'Revisar cola persistente y worker activo. sync ejecuta en la petición; no demuestra procesamiento en segundo plano.');

        $complete = true;
        foreach (['access_token', 'phone_number_id', 'verify_token', 'app_secret', 'api_version'] as $key) {
            $complete = $present('meta_whatsapp.'.$key) && $complete;
        }
        $check('whatsapp', $complete, 'Revisar configuración de Meta; probar webhook firmado y recepción real. La presencia de claves no valida su vigencia.');

        return $checks;
    }
}
