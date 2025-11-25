<?php

declare(strict_types=1);

namespace Lastdino\DrawingManager\Policies;

use App\Models\User; // ホスト側の User を参照
use Lastdino\DrawingManager\Models\DrawingManagerDrawing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DrawingPolicy
{
    /**
     * ロール名を安全に取得（テーブルが無い場合は空）
     *
     * @param  callable(DrawingManagerDrawing): \Illuminate\Database\Eloquent\Relations\BelongsToMany  $relation
     */
    protected function safeRoleNames(DrawingManagerDrawing $drawing, callable $relation): array
    {
        // Spatie 標準の roles テーブルが無い環境や、中間テーブルが未作成な環境を考慮
        if (! Schema::hasTable('roles')) {
            return [];
        }

        // editableRoles の中間テーブル（カスタム）存在チェック
        // relation の引数により可変（allowedRoles と editableRoles）
        try {
            /** @var Collection $names */
            $names = $relation($drawing)->pluck('name');
            return $names->all();
        } catch (\Throwable $e) {
            // マイグレーション未実行などで中間テーブルが無い場合は空配列を返す
            return [];
        }
    }

    public function view(User $user, DrawingManagerDrawing $drawing): bool
    {
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        $roleNames = $this->safeRoleNames($drawing, fn ($d) => $d->allowedRoles());
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
        // 新規図面は誰でも作成可能（認証済みユーザー）
        return true;
    }

    public function update(User $user, DrawingManagerDrawing $drawing): bool
    {
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        $editorRoleNames = $this->safeRoleNames($drawing, fn ($d) => $d->editableRoles());
        if (! empty($editorRoleNames)) {
            return $user->hasAnyRole($editorRoleNames);
        }

        // 未設定時の既定: DrawingManager 可
        return $user->hasRole('DrawingManager');
    }

    public function delete(User $user, DrawingManagerDrawing $drawing): bool
    {
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        $editorRoleNames = $this->safeRoleNames($drawing, fn ($d) => $d->editableRoles());
        if (! empty($editorRoleNames)) {
            return $user->hasAnyRole($editorRoleNames);
        }

        return $user->hasRole('DrawingManager');
    }
}
