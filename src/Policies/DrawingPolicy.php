<?php

declare(strict_types=1);

namespace Lastdino\DrawingManager\Policies;

use App\Models\User; // ホスト側の User を参照
use Lastdino\DrawingManager\Models\DrawingManagerDrawing;

class DrawingPolicy
{
    public function view(User $user, DrawingManagerDrawing $drawing): bool
    {
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        $roleNames = $drawing->allowedRoles()->pluck('name')->all();
        if (!empty($roleNames)) {
            return $user->hasAnyRole($roleNames);
        }

        return false;
    }

    public function download(User $user, DrawingManagerDrawing $drawing): bool
    {
        return $this->view($user, $drawing);
    }

    public function create(User $user): bool
    {
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        return $user->department_id !== null;
    }

    public function update(User $user, DrawingManagerDrawing $drawing): bool
    {
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if ($user->department_id === null) {
            return false;
        }

        return (int) $drawing->managing_department_id === (int) $user->department_id;
    }
}
