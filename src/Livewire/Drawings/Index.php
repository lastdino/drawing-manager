<?php

declare(strict_types=1);

namespace Lastdino\DrawingManager\Livewire\Drawings;

use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Lastdino\DrawingManager\Models\{DrawingManagerDrawing, DrawingManagerFolder, DrawingManagerTag as PackageTag};

class Index extends Component
{
    use WithFileUploads;

    #[Url(as: 'folder')]
    public ?int $folderId = null;
    public string $search = '';

    /** @var array{tags:list<string>, match:'any'|'all'} */
    public array $filter = ['tags' => [], 'match' => 'any'];
    public string $filterTagInput = '';

    /** @var array<int,bool> */
    public array $open = [];
    /** @var array<int|null, list<array{id:int,name:string,parent_id:?int,drawings_count:int,has_children:bool}>> */
    public array $tree = [];
    /** @var array<int|string,bool> */
    public array $loaded = [];
    public int $windowStart = 0;
    public int $windowSize = 40;

    public string $newFolderName = '';
    public ?int $creatingUnder = null;

    /** @var array{id:?int, name:string} */
    public array $rename = ['id' => null, 'name' => ''];

    public ?int $deletingId = null;
    public string $deletingName = '';
    /** @var array{children:int, drawings:int} */
    public array $deleteStats = ['children' => 0, 'drawings' => 0];

    /** @var array{show:bool, x:int, y:int, folderId:?int} */
    public array $ctx = ['show' => false, 'x' => 0, 'y' => 0, 'folderId' => null];

    public ?int $detailId = null;
    public bool $editOpen = false;

    /** @var array{number:string,title:string,folder_id:?int,managing_department_id:?int,allowed_role_ids:array<int>,tags:list<string>} */
    public array $edit = [
        'number' => '', 'title' => '', 'folder_id' => null,
        'managing_department_id' => null, 'allowed_role_ids' => [], 'tags' => [],
    ];
    public string $editTagInput = '';

    public ?int $uploadFor = null;
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $files = [];
    public int $progress = 0;
    public string $uploadType = 'pdf';
    public ?int $uploadRevision = null;

    public bool $createOpen = false;
    /** @var array{number:string,title:string,folder_id:?int,managing_department_id:?int,allowed_role_ids:array<int>,tags:list<string>} */
    public array $create = [
        'number' => '', 'title' => '', 'folder_id' => null,
        'managing_department_id' => null, 'allowed_role_ids' => [], 'tags' => [],
    ];
    public string $tagInput = '';
    public $createFile = null; // TemporaryUploadedFile|null

    public function rules(): array
    {
        return [
            'newFolderName' => ['required', 'string', 'max:120'],
            'rename.name' => ['required', 'string', 'max:120'],
            'files.*' => ['file', 'mimes:pdf,png,jpg,jpeg,dwg,dxf', 'max:102400'],

            'create.number' => ['required','string','max:120','regex:/^[\x21-\x7E]+$/', Rule::unique('drawings', 'number')],
            'create.title' => ['required','string','max:255'],
            'create.folder_id' => ['required','integer','exists:folders,id'],
            'create.managing_department_id' => ['required','integer','exists:departments,id'],
            'create.allowed_role_ids' => ['required','array','min:1'],
            'create.allowed_role_ids.*' => ['integer','exists:roles,id'],
            'create.tags' => ['array','max:50'],
            'create.tags.*' => ['string','min:1','max:64'],
            'createFile' => ['nullable','file','mimes:pdf,png,jpg,jpeg,dwg,dxf','max:102400'],

            'edit.number' => ['nullable','string','max:120','regex:/^[\x21-\x7E]+$/'],
            'edit.title' => ['nullable','string','max:255'],
            'edit.folder_id' => ['nullable','integer','exists:folders,id'],
            'edit.managing_department_id' => ['nullable','integer','exists:departments,id'],
            'edit.allowed_role_ids' => ['nullable','array'],
            'edit.allowed_role_ids.*' => ['integer','exists:roles,id'],
            'edit.tags' => ['array','max:50'],
            'edit.tags.*' => ['string','min:1','max:64'],
        ];
    }

    #[Computed]
    public function allTags(): array
    {
        return PackageTag::query()->orderBy('name')->pluck('name')->all();
    }

    #[Computed]
    public function breadcrumbs(): array
    {
        $trail = [];
        $node = $this->folderId ? DrawingManagerFolder::find($this->folderId) : null;
        while ($node) { array_unshift($trail, $node); $node = $node->parent; }
        return $trail;
    }

    #[Computed]
    public function drawings()
    {
        $q = DrawingManagerDrawing::query()
            ->with(['tags:id,name'])
            ->when(!is_null($this->folderId), fn ($q) => $q->where('folder_id', $this->folderId))
            ->when($this->search, function ($qq) {
                $qq->where(function ($qqq) {
                    $qqq->where('number', 'like', "%{$this->search}%")
                        ->orWhere('title', 'like', "%{$this->search}%");
                });
            });

        $names = (array) ($this->filter['tags'] ?? []);
        $tagIds = $this->resolveTagIdsByNames($names);
        if (!empty($tagIds)) {
            if (($this->filter['match'] ?? 'any') === 'all') {
                $q->whereHas('tags', function ($qq) use ($tagIds) {
                    foreach ($tagIds as $id) {
                        $qq->whereHas('tags', fn($q2) => $q2->where('tags.id', $id));
                    }
                });
            } else {
                $q->whereHas('tags', fn($qq) => $qq->whereIn('tags.id', $tagIds));
            }
        }

        return $q->orderBy('number')->limit(200)->get();
    }

    #[Computed]
    public function flatTree(): array
    {
        $out = [];
        $walk = function (?int $parentId, int $depth) use (&$walk, &$out): void {
            $items = $this->tree[$parentId] ?? [];
            foreach ($items as $node) {
                $out[] = [
                    'id' => $node['id'],
                    'name' => $node['name'],
                    'depth' => $depth,
                    'drawings_count' => $node['drawings_count'],
                    'has_children' => !empty($this->tree[$node['id']]) || (bool) ($node['has_children'] ?? false),
                ];
                if (!empty($this->open[$node['id']])) {
                    $walk($node['id'], $depth + 1);
                }
            }
        };
        $walk(null, 0);
        return $out;
    }

    #[Computed]
    public function detail(): ?DrawingManagerDrawing
    {
        return $this->detailId ? DrawingManagerDrawing::query()->with(['folder', 'managingDepartment'])->find($this->detailId) : null;
    }

    #[Computed]
    public function detailMedias()
    {
        if (!$this->detailId) { return collect(); }
        $d = DrawingManagerDrawing::find($this->detailId);
        if (!$d) { return collect(); }
        return $d->getMedia('drawings')
            ->sortBy(fn($m) => (int) $m->getCustomProperty('revision', 0))
            ->values();
    }

    #[Computed]
    public function downloadOptions(): array
    {
        $out = ['has_pdf' => false, 'cad_types' => collect()];
        if (!$this->detailId) { return $out; }
        $d = DrawingManagerDrawing::find($this->detailId);
        if (!$d) { return $out; }
        $all = $d->getMedia('drawings');
        $hasPdf = false; $types = [];
        foreach ($all as $m) {
            $kind = $m->getCustomProperty('kind');
            $ext = strtolower(pathinfo($m->file_name, PATHINFO_EXTENSION));
            $mime = (string) ($m->mime_type ?? '');
            if (($kind ?? 'pdf') === 'pdf' || $ext === 'pdf' || str_starts_with($mime, 'application/pdf')) {
                $hasPdf = true;
            }
            $type = null;
            if (($kind ?? 'pdf') === 'cad') {
                $type = strtolower((string) $m->getCustomProperty('cad_type'));
            }
            if (!$type) {
                if (in_array($ext, ['dwg','dxf','stp','step','igs','iges'], true)) {
                    $type = $ext;
                }
            }
            if ($type === 'stp') { $type = 'step'; }
            if ($type === 'igs') { $type = 'iges'; }
            if ($type && in_array($type, ['dwg','dxf','step','iges'], true)) {
                $types[] = $type;
            }
        }
        return ['has_pdf' => $hasPdf, 'cad_types' => collect($types)->unique()->values()];
    }

    #[Computed]
    public function uploadRevisionOptions()
    {
        if (!$this->uploadFor) { return collect(); }
        $d = DrawingManagerDrawing::find((int) $this->uploadFor);
        if (!$d) { return collect(); }
        return $d->getMedia('drawings')
            ->filter(fn($m) => ($m->getCustomProperty('kind') ?? 'pdf') === 'pdf')
            ->map(fn($m) => (int) $m->getCustomProperty('revision', 0))
            ->unique()
            ->sort()
            ->values();
    }

    public function mount(): void
    {
        $this->windowStart = 0;
        $this->windowSize = 40;
    }

    public function render()
    {
        return view('drawing-manager::livewire.drawings.index');
    }

    public function initTree(): void
    {
        if (!empty($this->loaded['root'])) { return; }
        $this->tree[null] = DrawingManagerFolder::query()
            ->select(['id','name','parent_id'])
            ->withCount('drawings')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'parent_id' => $f->parent_id,
                'drawings_count' => $f->drawings_count,
                'has_children' => DrawingManagerFolder::where('parent_id', $f->id)->exists(),
            ])->all();
        $this->loaded['root'] = true;
    }

    public function loadChildren(int $parentId): void
    {
        if (!empty($this->loaded[$parentId])) { return; }
        $children = DrawingManagerFolder::query()
            ->select(['id','name','parent_id'])
            ->withCount('drawings')
            ->where('parent_id', $parentId)
            ->orderBy('name')
            ->get();
        $this->tree[$parentId] = $children->map(fn ($f) => [
            'id' => $f->id,
            'name' => $f->name,
            'parent_id' => $f->parent_id,
            'drawings_count' => $f->drawings_count,
            'has_children' => DrawingManagerFolder::where('parent_id', $f->id)->exists(),
        ])->all();
        $this->loaded[$parentId] = true;
    }

    public function closeSubtree(int $parentId): void
    {
        $children = $this->tree[$parentId] ?? [];
        foreach ($children as $child) {
            $cid = (int) $child['id'];
            $this->open[$cid] = false;
            if (!empty($this->tree[$cid])) {
                $this->closeSubtree($cid);
            }
        }
    }

    public function toggle(int $id): void
    {
        $isOpen = (bool) ($this->open[$id] ?? false);
        if ($isOpen) {
            $this->open[$id] = false;
            $this->closeSubtree($id);
            return;
        }
        $this->open[$id] = true;
        $this->loadChildren($id);
    }

    public function openContextMenu(?int $folderId, int $x, int $y): void
    {
        $this->ctx = ['show' => true, 'x' => $x, 'y' => $y, 'folderId' => $folderId];
    }

    public function closeContextMenu(): void
    {
        $this->ctx['show'] = false;
    }

    public function startRename(int $folderId): void
    {
        $f = DrawingManagerFolder::query()->findOrFail($folderId);
        $this->rename = ['id' => (int) $f->id, 'name' => (string) $f->name];
        $this->closeContextMenu();
        Flux::modal('rename-folder-modal')->show();
    }

    public function renameFolder(): void
    {
        $fid = (int) ($this->rename['id'] ?? 0);
        if ($fid <= 0) { return; }
        $this->validateOnly('rename.name');

        $f = DrawingManagerFolder::query()->findOrFail($fid);
        $exists = DrawingManagerFolder::query()
            ->where('parent_id', $f->parent_id)
            ->where('name', $this->rename['name'])
            ->where('id', '!=', $f->id)
            ->exists();
        if ($exists) {
            $this->addError('rename.name', '同じフォルダ名が既に存在します。');
            return;
        }
        $f->update(['name' => (string) $this->rename['name']]);

        $parent = $f->parent_id;
        if ($parent === null) {
            $this->loaded['root'] = false;
            $this->initTree();
        } else {
            $this->loaded[$parent] = false;
            $this->loadChildren($parent);
            $this->open[$parent] = true;
        }
        Flux::modals()->close();
    }

    public function confirmDelete(int $folderId): void
    {
        $f = DrawingManagerFolder::query()->findOrFail($folderId);
        $this->deletingId = (int) $f->id;
        $this->deletingName = (string) $f->name;
        $this->deleteStats = [
            'children' => (int) $f->children()->count(),
            'drawings' => (int) $f->drawings()->count(),
        ];
        $this->closeContextMenu();
        Flux::modal('delete-folder-modal')->show();
    }

    public function deleteFolder(): void
    {
        $fid = (int) ($this->deletingId ?? 0);
        if ($fid <= 0) { return; }
        $f = DrawingManagerFolder::query()->findOrFail($fid);

        $children = (int) $f->children()->count();
        $drawings = (int) $f->drawings()->count();
        if ($children > 0 || $drawings > 0) {
            $this->addError('deletingId', 'フォルダ内に項目があるため削除できません。');
            return;
        }

        $parent = $f->parent_id;
        $selectedDeleted = ((int)($this->folderId ?? 0) === (int) $f->id);

        $f->delete();

        if ($parent === null) {
            $this->loaded['root'] = false;
            $this->initTree();
        } else {
            $this->loaded[$parent] = false;
            $this->loadChildren($parent);
            $this->open[$parent] = true;
        }

        if ($selectedDeleted) {
            $this->folderId = $parent;
        }

        $this->deletingId = null;
        $this->deletingName = '';
        $this->deleteStats = ['children' => 0, 'drawings' => 0];
        Flux::modals()->close();
    }

    public function openUpload(int $drawingId): void
    {
        $this->uploadFor = $drawingId;
        $this->files = [];
        $this->progress = 0;
        $this->uploadType = 'pdf';
        $this->uploadRevision = null;
        Flux::modal('upload-revisions-modal')->show();
    }

    public function closeUpload(): void
    {
        $this->uploadFor = null;
        $this->files = [];
        $this->progress = 0;
        Flux::modals()->close();
    }

    public function saveRevisions(): void
    {
        if (!$this->uploadFor) {
            $this->addError('files', 'アップロード対象の図面が選択されていません。');
            return;
        }
        $drawing = DrawingManagerDrawing::query()->findOrFail((int) $this->uploadFor);
        Gate::authorize('update', $drawing);

        if ($this->uploadType === 'pdf') {
            $this->validate(['files.*' => ['file','mimes:pdf','max:102400']]);
        } else {
            $this->validate([
                'uploadRevision' => ['required','integer','min:1'],
                'files.*' => ['file','extensions:dwg,dxf,step,stp,iges,igs','max:102400'],
            ]);
        }

        foreach ((array) $this->files as $file) {
            if ($this->uploadType === 'pdf') {
                $nextRev = $drawing->nextMediaRevisionNumber();
                $media = $drawing
                    ->addMedia($file)
                    ->usingFileName($file->getClientOriginalName())
                    ->withCustomProperties(['revision' => $nextRev,'kind' => 'pdf','notes' => null])
                    ->toMediaCollection('drawings');
                $drawing->forceFill(['current_media_id' => $media->id])->save();
            } else {
                $rev = (int) $this->uploadRevision;
                $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
                $cadType = match ($ext) {
                    'dwg' => 'dwg', 'dxf' => 'dxf', 'stp','step' => 'step', 'igs','iges' => 'iges', default => $ext,
                };
                $drawing->addMedia($file)
                    ->usingFileName($file->getClientOriginalName())
                    ->withCustomProperties(['revision' => $rev,'kind' => 'cad','cad_type' => $cadType,'notes' => null])
                    ->toMediaCollection('drawings');
            }
        }
        $this->files = [];
        $this->progress = 0;
        $this->uploadRevision = null;
        $this->uploadType = 'pdf';
        Flux::modals()->close();
    }

    public function openDetail(int $drawingId): void
    {
        $this->detailId = $drawingId;
        Flux::modal('drawing-detail-flyout')->show();
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
        $this->editOpen = false;
        Flux::modals()->close();
    }

    public function openEdit(int $drawingId): void
    {
        $drawing = DrawingManagerDrawing::query()->with(['tags'])->findOrFail($drawingId);
        Gate::authorize('update', $drawing);
        $this->editOpen = true;
        $this->edit = [
            'number' => $drawing->number,
            'title' => $drawing->title,
            'folder_id' => $drawing->folder_id,
            'managing_department_id' => $drawing->managing_department_id,
            'allowed_role_ids' => $drawing->allowedRoles()->pluck('roles.id')->all(),
            'tags' => $drawing->tags()->pluck('tags.name')->all(),
        ];
    }

    public function cancelEdit(): void
    {
        $this->editOpen = false;
    }

    public function saveEdit(): void
    {
        if (!$this->detailId) { return; }
        $drawing = DrawingManagerDrawing::query()->findOrFail((int) $this->detailId);
        Gate::authorize('update', $drawing);

        $this->validate([
            'edit.number' => ['required','string','max:120','regex:/^[\x21-\x7E]+$/', Rule::unique('drawings','number')->ignore($drawing->id)],
            'edit.title' => ['required','string','max:255'],
            'edit.folder_id' => ['required','integer','exists:folders,id'],
            'edit.managing_department_id' => ['required','integer','exists:departments,id'],
            'edit.allowed_role_ids' => ['required','array','min:1'],
            'edit.allowed_role_ids.*' => ['integer','exists:roles,id'],
            'edit.tags' => ['array','max:50'],
            'edit.tags.*' => ['string','min:1','max:64'],
        ]);

        try {
            $drawing->fill([
                'number' => $this->edit['number'],
                'title' => $this->edit['title'],
                'folder_id' => $this->edit['folder_id'],
                'managing_department_id' => $this->edit['managing_department_id'],
            ])->save();

            $roleIds = array_map('intval', (array) ($this->edit['allowed_role_ids'] ?? []));
            $drawing->allowedRoles()->sync($roleIds);

            $ids = $this->ensureTagIdsFromNames((array) ($this->edit['tags'] ?? []));
            $drawing->tags()->sync($ids);

            $this->editOpen = false;
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains((string) $e->getMessage(), 'drawings_number_unique')) {
                $this->addError('edit.number', '同じ図番が既に存在します。');
                return;
            }
            throw $e;
        }
    }

    public function openCreateDrawing(): void
    {
        $this->createOpen = true;
        $this->create = [
            'number' => '', 'title' => '', 'folder_id' => $this->folderId,
            'managing_department_id' => '', 'allowed_role_ids' => [], 'tags' => [],
        ];
        $this->tagInput = '';
        $this->createFile = null;
        Flux::modal('create-drawing-modal')->show();
    }

    public function saveCreateDrawing(): void
    {
        $this->validate([
            'create.number' => ['required','string','max:120','regex:/^[\x21-\x7E]+$/', Rule::unique('drawings','number')],
            'create.title' => ['required','string','max:255'],
            'create.folder_id' => ['required','integer','exists:folders,id'],
            'create.managing_department_id' => ['required','integer','exists:departments,id'],
            'create.allowed_role_ids' => ['required','array','min:1'],
            'create.allowed_role_ids.*' => ['integer','exists:roles,id'],
            'create.tags' => ['array','max:50'],
            'create.tags.*' => ['string','min:1','max:64'],
            'createFile' => ['nullable','file','mimes:pdf,png,jpg,jpeg,dwg,dxf','max:102400'],
        ]);

        Gate::authorize('create', DrawingManagerDrawing::class);

        try {
            $drawing = DrawingManagerDrawing::create([
                'number' => $this->create['number'],
                'title' => $this->create['title'],
                'folder_id' => $this->create['folder_id'],
                'managing_department_id' => $this->create['managing_department_id'],
            ]);

            $roleIds = array_map('intval', (array) ($this->create['allowed_role_ids'] ?? []));
            if (!empty($roleIds)) {
                $drawing->allowedRoles()->sync($roleIds);
            }

            if ($this->createFile) {
                $revNumber = $drawing->nextMediaRevisionNumber();
                $media = $drawing
                    ->addMedia($this->createFile)
                    ->usingFileName($this->createFile->getClientOriginalName())
                    ->withCustomProperties(['revision' => $revNumber, 'notes' => null])
                    ->toMediaCollection('drawings');
                $drawing->forceFill(['current_media_id' => $media->id])->save();
            }

            $ids = $this->ensureTagIdsFromNames((array) ($this->create['tags'] ?? []));
            $drawing->tags()->sync($ids);

            $this->createOpen = false;
            $this->create = [
                'number' => '', 'title' => '', 'folder_id' => $this->folderId, 'managing_department_id' => null, 'allowed_role_ids' => [], 'tags' => [],
            ];
            $this->createFile = null;
            Flux::modals()->close();
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains((string) $e->getMessage(), 'drawings_number_unique')) {
                $this->addError('create.number', '同じ図番が既に存在します。');
                return;
            }
            throw $e;
        }
    }

    /**
     * @param list<string> $names
     * @return list<int>
     */
    protected function ensureTagIdsFromNames(array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $trim = trim((string) $name);
            if ($trim === '') { continue; }
            $slug = str($trim)->slug('-');
            $tag = PackageTag::firstOrCreate(['slug' => $slug], ['name' => $trim]);
            $ids[] = (int) $tag->id;
        }
        return array_values(array_unique($ids));
    }

    /**
     * @param list<string> $names
     * @return list<int>
     */
    protected function resolveTagIdsByNames(array $names): array
    {
        $slugs = collect($names)
            ->map(fn($n) => trim((string) $n))
            ->filter()
            ->map(fn($n) => (string) str($n)->slug('-'))
            ->unique()
            ->values();
        if ($slugs->isEmpty()) { return []; }
        return PackageTag::query()->whereIn('slug', $slugs->all())
            ->pluck('id')->map(fn($id) => (int) $id)->all();
    }
}
