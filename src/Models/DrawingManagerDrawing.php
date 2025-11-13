<?php

declare(strict_types=1);

namespace Lastdino\DrawingManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DrawingManagerDrawing extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $table = 'drawings';

    protected $fillable = [
        'number',
        'title',
        'folder_id',
        'managing_department_id',
        'current_media_id',
    ];

    // ポリモーフィック関連の上書きは不要となったため削除

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DrawingManagerFolder::class);
    }

    public function managingDepartment(): BelongsTo
    {
        // Department はホストアプリ側のモデルを利用
        return $this->belongsTo(\App\Models\Department::class, 'managing_department_id');
    }

    public function allowedRoles(): BelongsToMany
    {
        // 役割は Spatie Permission の Role を利用
        return $this->belongsToMany(\Spatie\Permission\Models\Role::class, 'drawing_manager_drawing_role', 'drawing_id', 'role_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DrawingManagerTag::class, 'drawing_manager_drawing_tag', 'drawing_id', 'tag_id');
    }

    public function currentMedia(): ?Media
    {
        return $this->current_media_id ? Media::find($this->current_media_id) : null;
    }

    public function nextMediaRevisionNumber(): int
    {
        $max = $this->getMedia('drawings')
            ->map(fn ($m) => (int) $m->getCustomProperty('revision', 0))
            ->max();

        return $max ? ($max + 1) : 1;
    }

    public function latestMediaByType(string $type): ?Media
    {
        $normalize = static function (string $t): string {
            return match (strtolower($t)) {
                'stp' => 'step',
                'igs' => 'iges',
                default => strtolower($t),
            };
        };

        $type = $normalize($type);
        $all = $this->getMedia('drawings');

        if ($type === 'pdf') {
            $candidates = $all->filter(function ($m) {
                $kind = $m->getCustomProperty('kind');
                if (($kind ?? 'pdf') === 'pdf') {
                    return true;
                }
                $ext = strtolower(pathinfo($m->file_name, PATHINFO_EXTENSION));
                $mime = (string) ($m->mime_type ?? '');
                return $ext === 'pdf' || str_starts_with($mime, 'application/pdf');
            });
        } else {
            $candidates = $all->filter(function ($m) use ($type, $normalize) {
                $kind = $m->getCustomProperty('kind');
                if (($kind ?? 'pdf') === 'cad') {
                    $cadType = $normalize((string) $m->getCustomProperty('cad_type'));
                    return $cadType === $type;
                }
                $ext = $normalize(strtolower(pathinfo($m->file_name, PATHINFO_EXTENSION)));
                return $ext === $type;
            });
        }

        return $candidates
            ->sortByDesc(function ($m) {
                $rev = (int) $m->getCustomProperty('revision', 0);
                return sprintf('%010d-%010d', $rev, $m->id);
            })
            ->first();
    }
}
