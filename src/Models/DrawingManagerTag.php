<?php

declare(strict_types=1);

namespace Lastdino\DrawingManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DrawingManagerTag extends Model
{

    protected $fillable = ['name', 'slug'];

    public function drawings(): BelongsToMany
    {
        return $this->belongsToMany(DrawingManagerDrawing::class, 'drawing_manager_drawing_tag', 'tag_id', 'drawing_id');
    }
}
