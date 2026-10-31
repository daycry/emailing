<?php

namespace App\Support;

use App\Models\MailMessage;

final class MailResult
{
    public static function label(string $status): string
    {
        return match ($status) {
            'smtp_accepted' => 'SMTP aceptado; entrega no acreditada',
            'api_accepted' => 'API aceptada; entrega no acreditada',
            'logged' => 'Log privado; sin conexión SMTP',
            'sending' => 'Transporte iniciado; resultado pendiente',
            'smtp_unconfirmed', 'api_unconfirmed' => 'Aceptación incierta; no reenviar automáticamente',
            'smtp_rejected', 'api_rejected' => 'Envío rechazado; consulta el diagnóstico',
            'blocked' => 'Transporte bloqueado; consulta configuración y autorización',
            'suppressed' => 'Destinatario suprimido; sin transporte',
            default => 'No se pudo completar el mensaje; consulta el diagnóstico',
        };
    }

    public static function notice(MailMessage $message): string
    {
        return match ($message->status) {
            'logged' => 'Mensaje registrado en un archivo privado. No se conectó a SMTP.',
            'smtp_accepted' => $message->mode === 'mailpit' ? 'SMTP aceptado por Mailpit. No acredita entrega al destinatario.'
                : 'SMTP aceptado por el proveedor. No acredita entrega al destinatario.',
            'api_accepted' => 'API aceptada por el proveedor. No acredita entrega al destinatario.',
            'smtp_unconfirmed' => 'Aceptación SMTP no confirmada. No reenvíes automáticamente.',
            'api_unconfirmed' => 'Aceptación API no confirmada. No reenvíes automáticamente.',
            'smtp_rejected', 'api_rejected' => 'El proveedor rechazó el envío. Revisa el diagnóstico en Actividad.',
            'blocked' => 'Transporte bloqueado. Revisa habilitación, configuración y autorización del remitente.',
            'suppressed' => 'Destinatario suprimido. No se inició el transporte.',
            'sending' => 'Transporte iniciado; resultado pendiente.',
            default => 'No se pudo guardar el archivo privado del mensaje.',
        };
    }

    public static function feedback(?string $status): string
    {
        return match ($status) {
            'processed' => 'Procesado por el proveedor', 'deferred' => 'Entrega aplazada', 'delivered' => 'Entrega comunicada por el proveedor',
            'bounced' => 'Rebote permanente; destinatario suprimido', 'complained' => 'Queja; destinatario suprimido',
            'unsubscribed' => 'Baja; destinatario suprimido', 'blocked' => 'Bloqueado por el proveedor',
            default => 'Sin feedback del proveedor',
        };
    }
}
