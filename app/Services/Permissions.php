<?php

namespace App\Services;

use App\Models\Membership;

class Permissions
{
    public const SCOPES = [
        'contacts.read' => 'Consultar contactos y listas',
        'contacts.write' => 'Gestionar contactos y listas',
        'templates.read' => 'Consultar plantillas',
        'templates.write' => 'Editar plantillas',
        'campaigns.read' => 'Consultar campañas',
        'campaigns.write' => 'Gestionar campañas',
        'mail.send' => 'Enviar emails',
        'activity.read' => 'Consultar actividad',
        'suppressions.read' => 'Consultar supresiones',
        'suppressions.write' => 'Gestionar supresiones',
        'settings.read' => 'Consultar configuración',
        'settings.write' => 'Gestionar transporte y remitentes',
        'api-keys.read' => 'Consultar llaves API propias',
        'api-keys.write' => 'Gestionar llaves API propias',
    ];

    public const PRESETS = [
        'developer' => ['templates.read', 'mail.send', 'activity.read', 'api-keys.read', 'api-keys.write'],
        'marketer' => ['contacts.read', 'contacts.write', 'templates.read', 'templates.write', 'campaigns.read', 'campaigns.write', 'mail.send', 'activity.read', 'suppressions.read', 'suppressions.write'],
        'accountant' => ['activity.read', 'settings.read'],
    ];

    public static function allows(Membership $membership, string $scope): bool
    {
        if (! array_key_exists($scope, self::SCOPES)) {
            return false;
        }

        return match ($membership->role) {
            'admin' => true,
            'read-only' => str_ends_with($scope, '.read'),
            'restricted' => in_array($scope, $membership->scopes ?? [], true),
            default => false,
        };
    }
}
