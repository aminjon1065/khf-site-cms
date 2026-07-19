<?php

namespace App\Enums;

/**
 * The six actions in the permission matrix (module × action).
 */
enum PermissionAction: string
{
    case View = 'view';
    case Create = 'create';
    case Edit = 'edit';
    case Delete = 'delete';
    case Publish = 'publish';
    case Approve = 'approve';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Просмотр',
            self::Create => 'Создание',
            self::Edit => 'Правка',
            self::Delete => 'Удаление',
            self::Publish => 'Публикация',
            self::Approve => 'Согласование',
        };
    }
}
