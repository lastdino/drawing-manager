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

    /**
     * ドラッグ中の図面ID（複数対応）
     * @var array<int>
     */
    public array $draggingIds = [];

    /**
     * 選択中の図面ID（複数選択）
     * @var array<int>
     */
    public array $selectedIds = [];

    #[Url(as: 'folder')]
    public $folderId = '';
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

    /** @var array{number:string,title:string,folder_id:?int,allowed_role_ids:array<int>,tags:list<string>} */
    public array $edit = [
        'number' => '', 'title' => '', 'folder_id' => null,
        'allowed_role_ids' => [], 'tags' => [],
    ];
    public string $editTagInput = '';

    public ?int $uploadFor = null;
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $files = [];
    public int $progress = 0;
    public string $uploadType = 'pdf';
    public $uploadRevision = '';

    public bool $createOpen = false;
    /** @var array{number:string,title:string,folder_id:?int,allowed_role_ids:array<int>,tags:list<string>} */
    public array $create = [
        'number' => '', 'title' => '', 'folder_id' => null,
        'allowed_role_ids' => [], 'tags' => [],
    ];
    public string $tagInput = '';
    public $createFile = null; // TemporaryUploadedFile|null

    public function rules(): array
    {
        return [
            'newFolderName' => ['required', 'string', 'max:120'],
            'rename.name' => ['required', 'string', 'max:120'],
            'files.*' => ['file', 'mimes:pdf,png,jpg,jpeg,dwg,dxf', 'max:102400'],

            // テーブル名はパッケージのプレフィックス付きに統一
            'create.number' => ['required','string','max:120','regex:/^[\x21-\x7E]+$/', Rule::unique('drawing_manager_drawings', 'number')],
            'create.title' => ['required','string','max:255'],
            'create.folder_id' => ['required','integer','exists:drawing_manager_folders,id'],
            // 管理部署は廃止
            'create.allowed_role_ids' => ['required','array','min:1'],
            'create.allowed_role_ids.*' => ['integer','exists:roles,id'],
            'create.tags' => ['array','max:50'],
            'create.tags.*' => ['string','min:1','max:64'],
            // 新規図面の初回ファイルは PDF のみ受け付ける
            'createFile' => ['nullable','file','mimes:pdf','max:102400'],

            'edit.number' => ['nullable','string','max:120','regex:/^[\x21-\x7E]+$/'],
            'edit.title' => ['nullable','string','max:255'],
            'edit.folder_id' => ['nullable','integer','exists:drawing_manager_folders,id'],
            // 管理部署は廃止
            'edit.allowed_role_ids' => ['nullable','array'],
            'edit.allowed_role_ids.*' => ['integer','exists:roles,id'],
            'edit.editor_role_ids' => ['nullable','array'],
            'edit.editor_role_ids.*' => ['integer','exists:roles,id'],
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
            // フォルダ指定は空文字を除外し、数値のみ受け付ける
            ->when($this->folderId !== '' && is_numeric($this->folderId), fn ($q) => $q->where('folder_id', (int) $this->folderId))
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
                // すべてのタグを持つ図面のみ（AND 条件）
                foreach ($tagIds as $id) {
                    $q->whereHas('tags', fn ($qq) => $qq->where('drawing_manager_tags.id', $id));
                }
            } else {
                // いずれかのタグを持つ図面（OR 条件）
                $q->whereHas('tags', fn ($qq) => $qq->whereIn('drawing_manager_tags.id', $tagIds));
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
            ->sortByDesc(fn($m) => (int) $m->getCustomProperty('revision', 0))
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

    /**
     * カード選択のトグル
     */
    public function toggleSelect(int $drawingId): void
    {
        $id = (int) $drawingId;
        $exists = in_array($id, $this->selectedIds, true);
        if ($exists) {
            $this->selectedIds = array_values(array_filter($this->selectedIds, fn ($x) => (int) $x !== $id));
            return;
        }
        $this->selectedIds[] = $id;
    }

    /**
     * 選択をクリア
     */
    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    /**
     * ドラッグ開始時に呼ぶ（フロントから）
     */
    public function startDrag(int $sourceId): void
    {
        $sid = (int) $sourceId;
        // 複数選択済みならそれを採用、そうでなければ単一
        if (!empty($this->selectedIds) && in_array($sid, $this->selectedIds, true)) {
            $this->draggingIds = array_values(array_unique(array_map('intval', $this->selectedIds)));
            return;
        }
        $this->draggingIds = [$sid];
    }

    /**
     * ドラッグ終了
     */
    public function endDrag(): void
    {
        $this->draggingIds = [];
    }

    /**
     * 図面のまとめて移動
     * @param array<int> $ids
     */
    public function moveDrawings(array $ids, ?int $toFolderId): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            return;
        }

        $dest = null;
        if (!empty($toFolderId)) {
            $dest = DrawingManagerFolder::query()->findOrFail((int) $toFolderId);
        }

        // 件数補正のため、フォルダごとの増減をカウント
        $dec = [];
        $inc = [];

        foreach ($ids as $id) {
            $drawing = DrawingManagerDrawing::query()->with('folder')->findOrFail((int) $id);
            Gate::authorize('update', $drawing);

            $fromId = $drawing->folder_id ? (int) $drawing->folder_id : null;
            $toId = $dest?->id;
            if ($fromId === ($toId ?? null)) {
                continue;
            }

            $drawing->update(['folder_id' => $toId]);

            if ($fromId !== null) { $dec[$fromId] = ($dec[$fromId] ?? 0) + 1; }
            if ($toId !== null) { $inc[$toId] = ($inc[$toId] ?? 0) + 1; }
        }

        // ツリー件数の簡易補正
        if (!empty($dec) || !empty($inc)) {
            foreach ($this->tree as $pid => $nodes) {
                foreach (($nodes ?? []) as $i => $n) {
                    $nid = (int) $n['id'];
                    if (isset($dec[$nid])) {
                        $this->tree[$pid][$i]['drawings_count'] = max(0, ((int) $n['drawings_count']) - $dec[$nid]);
                    }
                    if (isset($inc[$nid])) {
                        $this->tree[$pid][$i]['drawings_count'] = ((int) $n['drawings_count']) + $inc[$nid];
                    }
                }
            }
        }

        // UI 状態リセット
        $this->draggingIds = [];
        $this->selectedIds = [];
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

    /**
     * フォルダを「開く」だけに限定（冪等）。
     * ドラッグオーバーの自動展開では toggle() ではなくこちらを使用する。
     */
    public function ensureOpen(int $id): void
    {
        if (! empty($this->open[$id])) {
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

    public function openCreateModal(?int $parentId = null): void
    {
        $this->creatingUnder = $parentId;
        $this->newFolderName = '';
        $this->closeContextMenu();
        Flux::modal('create-folder-modal')->show();
    }

    public function createFolder(): void
    {
        $this->validateOnly('newFolderName');

        $exists = DrawingManagerFolder::query()
            ->where('parent_id', $this->creatingUnder)
            ->where('name', $this->newFolderName)
            ->exists();
        if ($exists) {
            $this->addError('newFolderName', '同じフォルダ名が既に存在します。');
            return;
        }

        $f = DrawingManagerFolder::create([
            'name' => (string) $this->newFolderName,
            'parent_id' => $this->creatingUnder,
        ]);

        if ($this->creatingUnder === null) {
            $this->loaded['root'] = false;
            $this->initTree();
        } else {
            $parent = (int) $this->creatingUnder;
            $this->loaded[$parent] = false;
            $this->loadChildren($parent);
            $this->open[$parent] = true;
        }

        // 任意: 新しく作成したフォルダを選択したい場合は下記を有効化
        // $this->folderId = (int) $f->id;

        $this->newFolderName = '';
        $this->creatingUnder = null;
        Flux::modals()->close();
    }

    public function openUpload(int $drawingId): void
    {
        // 認可チェック：版アップロードは更新権限が必要
        $drawing = DrawingManagerDrawing::query()->findOrFail($drawingId);
        Gate::authorize('update', $drawing);

        $this->uploadFor = $drawingId;
        $this->files = [];
        $this->progress = 0;
        $this->uploadType = 'pdf';
        $this->uploadRevision = '';
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
            'allowed_role_ids' => $drawing->allowedRoles()->pluck('roles.id')->all(),
            'editor_role_ids' => $drawing->editableRoles()->pluck('roles.id')->all(),
            'tags' => $drawing->tags()->pluck('name')->all(),
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
            'edit.number' => ['required','string','max:120','regex:/^[\x21-\x7E]+$/', Rule::unique('drawing_manager_drawings','number')->ignore($drawing->id)],
            'edit.title' => ['required','string','max:255'],
            'edit.folder_id' => ['required','integer','exists:drawing_manager_folders,id'],
            'edit.allowed_role_ids' => ['required','array','min:1'],
            'edit.allowed_role_ids.*' => ['integer','exists:roles,id'],
            'edit.editor_role_ids' => ['nullable','array'],
            'edit.editor_role_ids.*' => ['integer','exists:roles,id'],
            'edit.tags' => ['array','max:50'],
            'edit.tags.*' => ['string','min:1','max:64'],
        ]);

        try {
            $drawing->fill([
                'number' => $this->edit['number'],
                'title' => $this->edit['title'],
                'folder_id' => $this->edit['folder_id'],
            ])->save();

            $roleIds = array_map('intval', (array) ($this->edit['allowed_role_ids'] ?? []));
            $drawing->allowedRoles()->sync($roleIds);

            $editorRoleIds = array_map('intval', (array) ($this->edit['editor_role_ids'] ?? []));
            $drawing->editableRoles()->sync($editorRoleIds);

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
        // 作成モーダルを開く前に作成権限をチェック
        Gate::authorize('create', DrawingManagerDrawing::class);

        $this->createOpen = true;

        $this->create = [
            'number' => '', 'title' => '', 'folder_id' => (int)$this->folderId === 0 ? '' : $this->folderId,
            'allowed_role_ids' => [], 'editor_role_ids' => [], 'tags' => [],
        ];
        $this->tagInput = '';
        $this->createFile = null;
        Flux::modal('create-drawing-modal')->show();
    }

    public function saveCreateDrawing(): void
    {
        $this->validate([
            'create.number' => ['required','string','max:120','regex:/^[\x21-\x7E]+$/', Rule::unique('drawing_manager_drawings','number')],
            'create.title' => ['required','string','max:255'],
            'create.folder_id' => ['required','integer','exists:drawing_manager_folders,id'],
            'create.allowed_role_ids' => ['required','array','min:1'],
            'create.allowed_role_ids.*' => ['integer','exists:roles,id'],
            'create.tags' => ['array','max:50'],
            'create.tags.*' => ['string','min:1','max:64'],
            // 新規図面の初回ファイルは PDF のみ受け付ける
            'createFile' => ['nullable','file','mimes:pdf','max:102400'],
        ]);

        Gate::authorize('create', DrawingManagerDrawing::class);

        try {
            $drawing = DrawingManagerDrawing::create([
                'number' => $this->create['number'],
                'title' => $this->create['title'],
                'folder_id' => $this->create['folder_id'],
            ]);

            $roleIds = array_map('intval', (array) ($this->create['allowed_role_ids'] ?? []));
            if (!empty($roleIds)) {
                $drawing->allowedRoles()->sync($roleIds);
            }

            $editorRoleIds = array_map('intval', (array) ($this->create['editor_role_ids'] ?? []));
            if (!empty($editorRoleIds)) {
                $drawing->editableRoles()->sync($editorRoleIds);
            }

            if ($this->createFile) {
                $revNumber = $drawing->nextMediaRevisionNumber();
                $media = $drawing
                    ->addMedia($this->createFile)
                    ->usingFileName($this->createFile->getClientOriginalName())
                    ->withCustomProperties(['revision' => $revNumber, 'kind' => 'pdf', 'notes' => null])
                    ->toMediaCollection('drawings');
                $drawing->forceFill(['current_media_id' => $media->id])->save();
            }

            $ids = $this->ensureTagIdsFromNames((array) ($this->create['tags'] ?? []));
            $drawing->tags()->sync($ids);

            $this->createOpen = false;
            $this->create = [
                'number' => '', 'title' => '', 'folder_id' => $this->folderId, 'allowed_role_ids' => [], 'tags' => [],
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

            // 日本語など非ラテン文字のみのタグは Str::slug() が空文字になるため、
            // 衝突しないフォールバックスラッグを生成する
            $slug = $this->makeSlug($trim);

            // まずは名前一致で既存タグを優先的に取得（以前に空スラッグで作られたものを救済）
            $tag = PackageTag::query()->where('name', $trim)->first();

            if ($tag === null) {
                $tag = PackageTag::firstOrCreate(['slug' => $slug], ['name' => $trim]);
            } else {
                // 既存タグにスラッグが未設定（空文字）の場合は、今回の規則で補完する
                $current = (string) ($tag->slug ?? '');
                if ($current === '') {
                    $tag->slug = $slug;
                    $tag->save();
                }
            }

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
            ->map(fn($n) => $this->makeSlug($n))
            ->unique()
            ->values();
        if ($slugs->isEmpty()) { return []; }
        return PackageTag::query()->whereIn('slug', $slugs->all())
            ->pluck('id')->map(fn($id) => (int) $id)->all();
    }

    /**
     * 日本語のみ等で slug() が空になる場合のフォールバックを含むスラッグ生成。
     */
    protected function makeSlug(string $name): string
    {
        $base = (string) str($name)->slug('-');
        if ($base !== '') {
            return mb_substr($base, 0, 80);
        }

        // 先頭に識別用プレフィックスを付け、名前のハッシュで衝突を避ける
        $hash = substr(sha1(mb_strtolower($name)), 0, 40); // 40 <= 80
        return 'u-' . $hash;
    }
}
