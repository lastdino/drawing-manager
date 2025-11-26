<?php

declare(strict_types=1);

namespace Lastdino\DrawingManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadController
{
    public function latest(Request $request, \Lastdino\DrawingManager\Models\DrawingManagerDrawing $drawing, string $type): BinaryFileResponse
    {
        if (config('drawing-manager.authorize_download', true)) {
            Gate::authorize('download', $drawing);
        }

        $type = $this->normalizeCadType($type);

        $all = $drawing->getMedia('drawings');

        if ($type === 'pdf') {
            $candidates = $all->filter(function ($m) {
                $kind = $m->getCustomProperty('kind');
                if (($kind ?? 'pdf') === 'pdf') {
                    return true;
                }
                $ext = strtolower(pathinfo($m->file_name, PATHINFO_EXTENSION));
                return $ext === 'pdf' || str_starts_with((string) $m->mime_type, 'application/pdf');
            });
        } else {
            $candidates = $all->filter(function ($m) use ($type) {
                $kind = $m->getCustomProperty('kind');
                if (($kind ?? 'pdf') === 'cad') {
                    $cadType = $this->normalizeCadType((string) $m->getCustomProperty('cad_type'));
                    return $cadType === $type;
                }
                $ext = $this->normalizeCadType(strtolower(pathinfo($m->file_name, PATHINFO_EXTENSION)));
                return $ext === $type;
            });
        }

        $media = $candidates
            ->sortByDesc(function ($m) {
                $rev = (int) $m->getCustomProperty('revision', 0);
                return sprintf('%010d-%010d', $rev, $m->id);
            })
            ->first();

        abort_if(!$media, 404, '指定種別の最新版が見つかりません。');

        return response()->download(
            $media->getPath(),
            $media->file_name,
            [
                'Content-Type' => $media->mime_type ?? 'application/octet-stream',
            ]
        );
    }

    public function revision(Media $media): BinaryFileResponse
    {
        $drawing = $media->model;
        if (! $drawing instanceof \Lastdino\DrawingManager\Models\DrawingManagerDrawing && ! $drawing instanceof \App\Models\Drawing) {
            abort(404);
        }

        if (config('drawing-manager.authorize_download', true)) {
            Gate::authorize('download', $drawing);
        }

        return response()->download(
            $media->getPath(),
            $media->file_name,
            [
                'Content-Type' => $media->mime_type ?? 'application/octet-stream',
            ]
        );
    }

    /**
     * 実拡張子を論理拡張子へ正規化
     */
    private function normalizeCadType(string $ext): string
    {
        $ext = strtolower($ext);
        $aliases = array_change_key_case((array) (config('drawing-manager.cad.aliases') ?? []), CASE_LOWER);
        if (isset($aliases[$ext])) {
            return $aliases[$ext];
        }
        return $ext;
    }
}
