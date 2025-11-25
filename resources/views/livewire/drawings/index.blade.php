<div class="h-dvh grid grid-rows-[auto_1fr]">
    <div class="flex flex-col gap-2 mb-3">
        <div class="flex items-center gap-1">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item wire:click="$set('folderId', null)">ホーム</flux:breadcrumbs.item>
                @foreach ($this->breadcrumbs as $bc)
                    <flux:breadcrumbs.item
                        wire:click="$set('folderId', {{ $bc->id }})">{{ $bc->name }}</flux:breadcrumbs.item>
                @endforeach
            </flux:breadcrumbs>
        </div>
        <div class="flex gap-2">
            <flux:input placeholder="図番/件名で検索" wire:model.live.debounce.300ms="search"/>

            <!-- タグ絞り込み（microcms風：チップス + サジェスト） -->
            <div class="min-w-[18rem] max-w-[28rem]" x-data="{
                  value: @entangle('filter.tags').live,
                  input: @entangle('filterTagInput'),
                  open: false,
                  max: 50,
                  all: @js($this->allTags),
                  get filtered() {
                    const q = (this.input || '').toLowerCase();
                    if (!q) return this.all.filter(n => !this.value.includes(n)).slice(0, 10);
                    return this.all.filter(n => n.toLowerCase().includes(q) && !this.value.includes(n)).slice(0, 10);
                  },
                  add(name) {
                    name = (name || '').trim();
                    if (!name) return;
                    if (this.value.includes(name)) { this.input=''; return; }
                    if (this.value.length >= this.max) return;
                    this.value = [...this.value, name];
                    this.input = '';
                    this.open = false;
                  },
                  remove(idx) { this.value = this.value.filter((_, i) => i !== idx); },
                  handleKey(e) {
                    if (['Enter','Tab',','].includes(e.key)) { e.preventDefault(); this.add(this.input); }
                    else if (e.key==='Backspace' && !this.input) { this.value = this.value.slice(0, -1); }
                  },
                  clear() { this.value = []; this.input=''; }
                }">
                <div
                    class="flex flex-wrap gap-1 border rounded px-2 py-1 focus-within:ring-2 ring-blue-500 bg-white/50 dark:bg-white/5">
                    <template x-for="(name, i) in value" :key="name">
                        <div class="inline-flex items-center gap-1">
                            <flux:badge x-text="name"></flux:badge>
                            <button type="button" class="text-xs text-black/50 dark:text-white/50" @click="remove(i)">
                                ×
                            </button>
                        </div>
                    </template>
                    <input x-model="input" @input="open = true" @keydown="handleKey($event)" @blur="add(input)"
                           placeholder="タグ名で絞り込み"
                           class="flex-1 min-w-[8rem] outline-none bg-transparent py-0.5"/>
                </div>
                <div x-show="open && filtered.length" @mousedown.prevent
                     class="mt-1 border rounded bg-white dark:bg-gray-900 shadow">
                    <ul class="max-h-44 overflow-auto">
                        <template x-for="name in filtered" :key="name">
                            <li>
                                <button type="button"
                                        class="w-full text-left px-3 py-1.5 hover:bg-black/5 dark:hover:bg-white/10"
                                        @click="add(name)">
                                    <span x-text="name"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <flux:select wire:model="filter.match" size="sm" placeholder="OR/AND">
                    <flux:select.option value="any">OR</flux:select.option>
                    <flux:select.option value="all">AND</flux:select.option>
                </flux:select>
                <flux:button size="xs" variant="ghost" wire:click="$set('filter.tags', []); $set('filterTagInput', '')">
                    クリア
                </flux:button>
            </div>

            @can('create', \Lastdino\DrawingManager\Models\DrawingManagerDrawing::class)
                <flux:button icon="plus" variant="primary" wire:click="openCreateDrawing">新規図面を追加</flux:button>
            @endcan
        </div>
    </div>
    <div class="min-h-0 grid grid-cols-[280px_1fr] gap-4 p-4">
        <aside class="min-h-0 overflow-hidden border rounded p-2">
            <div class="flex items-center justify-between gap-2 mb-2　w-full">
                <flux:button
                    size="sm"
                    variant="ghost"
                    wire:click="$set('folderId', null)"
                    x-data="{ over:false }"
                    @dragover.prevent="over = ($wire.draggingIds || []).length > 0"
                    @dragleave.prevent="over = false"
                    @drop.prevent="((($wire.draggingIds||[]).length>0) && $wire.moveDrawings($wire.draggingIds, null)), (over=false)"
                    x-bind:class="over ? 'bg-black/5 dark:bg-white/10' : ''"
                >
                    すべて/未分類
                </flux:button>
                <flux:button size="sm" icon="plus" variant="ghost" inset
                             wire:click="openCreateModal({{ $folderId ?? 'null' }})"
                             tooltip="現在の階層にフォルダ作成"/>
            </div>

            <div
                x-data="{ itemH: 28, onScroll(e){ const el = e.target; const start = Math.max(0, Math.floor(el.scrollTop / this.itemH) - 5); const size = Math.ceil(el.clientHeight / this.itemH) + 10; $wire.$set('windowStart', start); $wire.$set('windowSize', size); } }"
                class="h-full overflow-auto"
                x-init="$wire.initTree()"
                @scroll.throttle.50ms="onScroll($event)"
                @contextmenu.self.prevent="$wire.openContextMenu(null, $event.clientX, $event.clientY)"
            >
                <?php /* 仮想化: フラット配列のウィンドウのみ描画 */ ?>
                @php
                    $total = count($this->flatTree);
                    $start = max(0, (int) $this->windowStart);
                    $size = max(1, (int) $this->windowSize);
                    $slice = array_slice($this->flatTree, $start, $size);
                @endphp

                @if ($total === 0)
                    <div class="p-4 text-sm text-gray-600 flex flex-col items-start gap-2">
                        <div>フォルダーがありません。</div>
                        <flux:button size="sm" variant="primary" wire:click="openCreateModal(null)">
                            最初のフォルダを作成
                        </flux:button>
                    </div>
                @endif

                <div style="height: {{ $start * 28 }}px"></div>

                <ul class="space-y-0.5">
                    @foreach ($slice as $node)
                        @php($isOpen = !empty($open[$node['id']]))
                        <li class="flex items-center justify-between"
                            wire:key="folder-node-{{ $node['id'] }}-{{ $node['depth'] }}"
                            @contextmenu.stop.prevent="$wire.openContextMenu({{ $node['id'] }}, $event.clientX, $event.clientY)"
                            x-data="{
                                over:false,
                                timer:null,
                                startHover(){
                                    if (this.timer) { return }
                                    // 一定時間（500ms）ドラッグオーバーしたら自動展開
                                    this.timer = setTimeout(() => {
                                        this.timer = null;
                                        // 子がある場合のみ開く（ensureOpen は冪等で、連続実行しても閉じません）
                                        if ({{ $node['has_children'] ? 'true' : 'false' }}) { $wire.ensureOpen({{ (int) $node['id'] }}); }
                                    }, 500)
                                },
                                endHover(){ if (this.timer) { clearTimeout(this.timer); this.timer = null } }
                            }"
                            @dragover.prevent="((($wire.draggingIds||[]).length>0) && (over=true, startHover()))"
                            @dragleave.prevent="(over=false, endHover())"
                            @drop.prevent="((($wire.draggingIds||[]).length>0) && $wire.moveDrawings($wire.draggingIds, {{ (int) $node['id'] }})), (over=false, endHover())"
                        >
                            <div class="flex items-center min-w-0" x-bind:class="over ? 'bg-black/5 dark:bg-white/10 rounded' : ''">
                                @if ($node['has_children'])
                                    <button class="text-xs text-gray-500 me-1"
                                            style="padding-left: {{ $node['depth'] * 16 }}px"
                                            wire:click="toggle({{ $node['id'] }})">
                                        {{ !empty($open[$node['id']]) ? '▼' : '▶' }}
                                    </button>
                                @else
                                    <span class="text-xs text-gray-400 me-1"
                                          style="padding-left: {{ $node['depth'] * 16 }}px">•</span>
                                @endif

                                @if (!empty($open[$node['id']]))
                                    <span class="me-1" aria-hidden="true">📂</span>
                                @else
                                    <span class="me-1" aria-hidden="true">📁</span>
                                @endif

                                <button
                                    class="text-left hover:underline truncate {{ (int)($folderId ?? 0) === (int)$node['id'] ? 'text-blue-600 font-semibold' : '' }}"
                                    title="{{ $node['name'] }}"
                                    wire:click="$set('folderId', {{ $node['id'] }})"
                                >
                                    {{ $node['name'] }}
                                </button>
                            </div>
                            <span class="text-xs text-gray-500 ms-2 shrink-0">{{ $node['drawings_count'] }}</span>
                        </li>
                    @endforeach
                </ul>

                <div style="height: {{ max(0, ($total - $start - count($slice)) * 28) }}px"></div>

                {{-- 右クリックメニュー --}}
                <div
                    x-data
                    x-show="$wire.ctx.show"
                    x-cloak
                    @click.outside="$wire.closeContextMenu()"
                    @keydown.escape.window="$wire.closeContextMenu()"
                    :style="`position: fixed; z-index: 50; top: ${$wire.ctx.y}px; left: ${$wire.ctx.x}px;`"
                    class="min-w-[180px] rounded border bg-white shadow-lg dark:bg-gray-900 dark:border-gray-700"
                >
                    <ul class="py-1 text-sm">
                        <li>
                            <button
                                class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-800"
                                x-on:click="$wire.openCreateModal($wire.ctx.folderId); $wire.closeContextMenu()"
                            >
                                新規フォルダ
                            </button>
                        </li>
                        <template x-if="$wire.ctx.folderId">
                            <li>
                                <button
                                    class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-800"
                                    x-on:click="$wire.startRename($wire.ctx.folderId)"
                                >
                                    名前を変更
                                </button>
                            </li>
                        </template>
                        <template x-if="$wire.ctx.folderId">
                            <li>
                                <button
                                    class="w-full text-left px-3 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-gray-800"
                                    x-on:click="$wire.confirmDelete($wire.ctx.folderId)"
                                >
                                    削除
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- フォルダ名変更モーダル --}}
                <flux:modal name="rename-folder-modal">
                    <div class="p-4 space-y-3">
                        <div class="text-sm text-gray-600">対象: ID {{ $rename['id'] }}</div>
                        <flux:field label="新しいフォルダ名">
                            <flux:input wire:model="rename.name" placeholder="例: 設計/2025"/>
                        </flux:field>
                        @error('rename.name')
                        <div class="text-red-600 text-sm">{{ $message }}</div>
                        @enderror
                        <div class="flex justify-end gap-2 pt-2">
                            <flux:button variant="ghost" x-on:click="$flux.modal('rename-folder-modal').close()">
                                キャンセル
                            </flux:button>
                            <flux:button variant="primary" wire:click="renameFolder">保存</flux:button>
                        </div>
                    </div>
                </flux:modal>

                {{-- フォルダ削除モーダル --}}
                <flux:modal name="delete-folder-modal">
                    <div class="p-4 space-y-3">
                        <div class="text-sm">本当にフォルダ「<span class="font-semibold">{{ $deletingName }}</span>」を削除しますか？
                        </div>
                        <div class="text-xs text-gray-600">子フォルダ: {{ $deleteStats['children'] }} /
                            図面: {{ $deleteStats['drawings'] }}</div>
                        @error('deletingId')
                        <div class="text-red-600 text-sm">{{ $message }}</div>
                        @enderror
                        <div class="flex justify-end gap-2 pt-2">
                            <flux:button variant="ghost" x-on:click="$flux.modal('delete-folder-modal').close()">
                                キャンセル
                            </flux:button>
                            <flux:button
                                variant="danger"
                                wire:click="deleteFolder"
                                title="空のフォルダのみ削除できます"
                                :disabled="$deleteStats['children'] > 0 || $deleteStats['drawings'] > 0"
                            >
                                削除する
                            </flux:button>
                        </div>
                    </div>
                </flux:modal>
                {{-- 新規フォルダモーダル --}}
                <flux:modal name="create-folder-modal">
                    <div class="p-4 space-y-3">
                        <div class="text-sm text-gray-600">親: {{ $creatingUnder === null ? '（ルート）' : $creatingUnder }}</div>
                        <flux:field label="フォルダ名">
                            <flux:input wire:model="newFolderName" placeholder="例: 設計/2025" />
                        </flux:field>
                        @error('newFolderName')
                        <div class="text-red-600 text-sm">{{ $message }}</div>
                        @enderror
                        <div class="flex justify-end gap-2 pt-2">
                            <flux:button variant="ghost" x-on:click="$flux.modal('create-folder-modal').close()">キャンセル</flux:button>
                            <flux:button variant="primary" wire:click="createFolder">作成</flux:button>
                        </div>
                    </div>
                </flux:modal>
            </div>
        </aside>

        <main class="min-h-0 min-w-0 overflow-auto">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($this->drawings as $d)
                    @php($selected = in_array($d->id, $selectedIds ?? []))
                    <div
                        class="border rounded p-3 cursor-pointer hover:bg-black/5 dark:hover:bg-white/5 select-none {{ $selected ? 'ring-2 ring-blue-600 bg-black/5 dark:bg-white/5' : '' }}"
                        draggable="true"
                        x-data
                        @dragstart.stop="$wire.startDrag({{ $d->id }})"
                        @dragend.stop="$wire.endDrag()"
                        @click.prevent="(($wire.draggingIds || []).length > 0) || $wire.openDetail({{ $d->id }})"
                    >
                        <div class="flex items-start gap-2">
                            <label class="mt-1 inline-flex items-center gap-2 text-xs text-black/60 dark:text-white/60">
                                <input type="checkbox"
                                       @click.stop
                                       wire:click="toggleSelect({{ $d->id }})"
                                       {{ $selected ? 'checked' : '' }}
                                >
                                選択
                            </label>
                            <div class="ms-auto"></div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="font-semibold">{{ $d->number }}</div>
                            @php($cm = $d->currentMedia())
                            @if($cm)
                                <span
                                    class="text-xs bg-blue-600 text-white px-2 rounded">Rev {{ (int) ($cm->getCustomProperty('revision') ?? 0) }}</span>
                            @endif
                        </div>
                        <div class="text-gray-700 truncate" title="{{ $d->title }}">{{ $d->title }}</div>
                        <div class="flex flex-wrap gap-1 py-1">
                            @foreach ($d->tags as $tag)
                                <flux:badge>{{ $tag->name }}</flux:badge>
                            @endforeach
                        </div>
                        <div class="ms-auto text-sm text-gray-500">{{ $d->updated_at->diffForHumans() }}</div>
                    </div>
                @endforeach
            </div>
        </main>

        {{-- 詳細フライアウト（右側） --}}
        <flux:modal name="drawing-detail-flyout" variant="flyout" position="right" @close="cancelEdit">
            <div class="p-4 space-y-4 w-full md:w-[28rem]">
                @if($this->detail)
                    {{-- ヘッダ行：図番 + Rev + 編集ボタン --}}
                    <div class="flex items-start gap-3">
                        <div>
                            <div class="text-xs text-black/60 dark:text-white/60">図番</div>
                            <div class="text-lg font-semibold">{{ $this->detail->number }}</div>
                        </div>
                        <div class="ms-auto flex items-center gap-2">
                            @php($cm = optional($this->detail)->currentMedia())
                            @if($cm)
                                <span
                                    class="text-xs bg-blue-600 text-white px-2 py-0.5 rounded">Rev {{ (int) ($cm->getCustomProperty('revision') ?? 0) }}</span>
                            @else
                                <span class="text-xs bg-gray-400 text-white px-2 py-0.5 rounded">ファイルなし</span>
                            @endif
                            @if(\Illuminate\Support\Facades\Gate::allows('update', $this->detail))
                                @if(!$editOpen)
                                    <flux:button size="xs" variant="ghost"
                                                 wire:click="openEdit({{ (int) $this->detailId }})">編集
                                    </flux:button>
                                @endif
                            @endif
                        </div>
                    </div>

                    {{-- 編集フォーム or 表示モード --}}
                    @if($editOpen)
                        <div class="space-y-3">
                            <div class="grid gap-3">
                                <flux:input label="図番" wire:model.defer="edit.number"/>
                                <flux:input label="タイトル" wire:model.defer="edit.title"/>
                                <flux:select wire:model="edit.folder_id" placeholder="保存先" label="フォルダ">
                                    @foreach(\Lastdino\DrawingManager\Models\DrawingManagerFolder::orderBy('name')->get() as $f)
                                        <flux:select.option value="{{ $f->id }}">{{ $f->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                {{-- 管理部署は廃止（UI削除） --}}
                                <flux:field>
                                    <flux:label>ダウンロード許可ロール</flux:label>
                                    <select multiple class="w-full border rounded px-2 py-1 min-h-[120px]"
                                            wire:model="edit.allowed_role_ids">
                                        @foreach(\Spatie\Permission\Models\Role::orderBy('name')->get() as $role)
                                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                    <flux:error name="edit.allowed_role_ids"/>
                                </flux:field>

                                <flux:field>
                                    <flux:label>編集可能ロール</flux:label>
                                    <select multiple class="w-full border rounded px-2 py-1 min-h-[120px]"
                                            wire:model="edit.editor_role_ids">
                                        @foreach(\Spatie\Permission\Models\Role::orderBy('name')->get() as $role)
                                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                    <flux:error name="edit.editor_role_ids"/>
                                </flux:field>

                                {{-- タグ（microcms 風：チップス + サジェスト） --}}
                                <flux:field label="タグ">
                                    <div
                                        x-data="{
                    value: @entangle('edit.tags').live,
                    input: @entangle('editTagInput'),
                    open: false,
                    max: 50,
                    all: @js($this->allTags),
                    get filtered() {
                      const q = (this.input || '').toLowerCase();
                      if (!q) return this.all.filter(n => !this.value.includes(n)).slice(0, 10);
                      return this.all.filter(n => n.toLowerCase().includes(q) && !this.value.includes(n)).slice(0, 10);
                    },
                    add(name) {
                      name = (name || '').trim();
                      if (!name) return;
                      if (this.value.includes(name)) { this.input=''; return; }
                      if (this.value.length >= this.max) return;
                      this.value = [...this.value, name];
                      this.input = '';
                      this.open = false;
                    },
                    remove(idx) {
                      this.value = this.value.filter((_, i) => i !== idx);
                    },
                    handleKey(e) {
                      if (['Enter', 'Tab', ','].includes(e.key)) {
                        e.preventDefault();
                        this.add(this.input);
                      } else if (e.key === 'Backspace' && !this.input) {
                        this.value = this.value.slice(0, -1);
                      }
                    }
                  }"
                                        class="w-full"
                                    >
                                        <div
                                            class="flex flex-wrap gap-1 border rounded px-2 py-1 focus-within:ring-2 ring-blue-500">
                                            <template x-for="(name, i) in value" :key="name">
                                                <div class="inline-flex items-center gap-1">
                                                    <flux:badge x-text="name"></flux:badge>
                                                    <button type="button"
                                                            class="text-xs text-black/50 dark:text-white/50"
                                                            @click="remove(i)">×
                                                    </button>
                                                </div>
                                            </template>

                                            <input
                                                x-model="input"
                                                @input="open = true"
                                                @keydown="handleKey($event)"
                                                @blur="add(input)"
                                                placeholder="タグを入力して Enter"
                                                class="flex-1 min-w-[8rem] outline-none bg-transparent py-1"
                                            />
                                        </div>

                                        <div x-show="open && filtered.length" @mousedown.prevent
                                             class="mt-1 border rounded bg-white dark:bg-gray-900 shadow">
                                            <ul class="max-h-44 overflow-auto">
                                                <template x-for="name in filtered" :key="name">
                                                    <li>
                                                        <button type="button"
                                                                class="w-full text-left px-3 py-1.5 hover:bg-black/5 dark:hover:bg-white/10"
                                                                @click="add(name)">
                                                            <span x-text="name"></span>
                                                        </button>
                                                    </li>
                                                </template>
                                            </ul>
                                        </div>

                                        <div class="text-xs text-black/50 dark:text-white/50 mt-1">最大 50
                                            個。重複は自動で除外します。
                                        </div>
                                    </div>
                                    <flux:error name="edit.tags"/>
                                </flux:field>
                            </div>

                            @error('edit.number')
                            <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror

                            <div class="flex justify-end gap-2 pt-2">
                                <flux:button variant="ghost" wire:click="cancelEdit">キャンセル</flux:button>
                                <flux:button variant="primary" wire:click="saveEdit" wire:loading.attr="disabled">
                                    <span wire:loading.remove>保存</span>
                                    <span wire:loading>保存中...</span>
                                </flux:button>
                            </div>
                        </div>
                    @else
                        <div>
                            <div class="text-xs text-black/60 dark:text-white/60">件名</div>
                            <div class="text-sm">{{ $this->detail->title ?: '—' }}</div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <div class="text-xs text-black/60 dark:text-white/60">フォルダ</div>
                                <div class="text-sm">{{ optional($this->detail->folder)->name }}</div>
                            </div>
                            {{-- 管理部署は廃止（表示削除） --}}
                        </div>

                        <div class="mt-2">
                            <div class="text-xs text-black/60 dark:text-white/60">タグ</div>
                            <div class="flex flex-wrap gap-1 pt-1">
                                @forelse ($this->detail->tags as $tag)
                                    <flux:badge>{{ $tag->name }}</flux:badge>
                                @empty
                                    <span class="text-sm text-black/40 dark:text-white/40">—</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="flex gap-2 pt-1 items-center">
                            @can('download', $this->detail)
                                <flux:dropdown>
                                    <flux:button size="sm" variant="ghost">種別を選んで最新版をダウンロード</flux:button>
                                    <flux:menu>
                                        @if($this->downloadOptions['has_pdf'])
                                            <flux:menu.item as="a"
                                                            href="{{ route('drawings.download.latest', ['drawing' => $this->detail, 'type' => 'pdf']) }}">
                                                PDF の最新版
                                            </flux:menu.item>
                                            <flux:menu.separator/>
                                        @endif

                                        @forelse ($this->downloadOptions['cad_types'] as $t)
                                            <flux:menu.item as="a"
                                                            href="{{ route('drawings.download.latest', ['drawing' => $this->detail, 'type' => $t]) }}">
                                                {{ strtoupper($t) }} の最新版
                                            </flux:menu.item>
                                        @empty
                                            @if(!$this->downloadOptions['has_pdf'])
                                                <flux:menu.item disabled>ダウンロード可能な種別がありません</flux:menu.item>
                                            @endif
                                        @endforelse
                                    </flux:menu>
                                </flux:dropdown>
                            @endcan

                            @can('update', $this->detail)
                                <flux:button size="sm" variant="primary"
                                             wire:click="openUpload({{ (int) $this->detailId }})">版をアップロード
                                </flux:button>
                            @endcan
                        </div>

                        <div class="pt-2">
                            <div class="text-sm font-semibold">すべての版</div>
                            <div class="mt-2 grid gap-2">
                                @forelse ($this->detailMedias as $m)
                                    <div class="flex items-center gap-2 rounded border p-2">
                                        <div class="text-xs w-16">
                                            Rev {{ (int) ($m->getCustomProperty('revision') ?? 0) }}</div>
                                        <div class="text-sm truncate"
                                             title="{{ $m->file_name }}">{{ $m->file_name }}</div>
                                        <div class="ms-auto flex items-center gap-2">
                                            @can('download', $this->detail)
                                                <a class="text-blue-600 underline"
                                                   href="{{ route('drawings.download.revision', $m) }}">DL</a>
                                            @endcan
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-sm text-black/60 dark:text-white/60">
                                        登録されたファイルはありません。
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </flux:modal>

        {{-- 版アップロードモーダル --}}
        <flux:modal name="upload-revisions-modal">
            <div class="p-4 space-y-4">
                <div class="text-sm text-gray-600">図面ID: {{ $uploadFor }}</div>

                <div class="flex items-center gap-4">
                    <label class="inline-flex items-center gap-1 text-sm">
                        <input type="radio" value="pdf" wire:model="uploadType"> PDF（新しい版を作成）
                    </label>
                    <label class="inline-flex items-center gap-1 text-sm">
                        <input type="radio" value="cad" wire:model="uploadType"> CAD（既存の版に紐づけ）
                    </label>
                </div>
                <div x-show="$wire.uploadType === 'cad'">
                    <flux:select wire:model="uploadRevision" label="紐づける版（Rev）" placeholder="選択してください">
                        @foreach($this->uploadRevisionOptions as $revNo)
                            <flux:select.option value="{{ $revNo }}">Rev {{ $revNo }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="uploadRevision"/>
                </div>

                <div
                    x-data="{
          dragging:false,
          progress:0,
          handleDrop(e){
            e.preventDefault();
            this.dragging=false;
            const files = Array.from(e.dataTransfer.files || []);
            if(!files.length) return;
            $wire.uploadMultiple('files', files,
              () => {},
              () => {},
              (event) => { this.progress = event.detail.progress; $wire.progress = this.progress; },
              () => {}
            );
          }
        }"
                    x-on:dragover.prevent="dragging=true"
                    x-on:dragleave.prevent="dragging=false"
                    x-on:drop="handleDrop"
                    class="rounded border border-dashed p-8 text-center select-none cursor-pointer"
                    :class="dragging ? 'bg-black/5 dark:bg-white/10' : 'bg-transparent'"
                >
                    <div class="space-y-2">
                        <p class="text-sm">ここにファイルをドラッグ＆ドロップ</p>
                        <p class="text-xs text-black/60 dark:text-white/60">または</p>
                        <label class="inline-flex items-center gap-2 px-3 py-2 rounded border cursor-pointer">
                            <input type="file" class="hidden" multiple wire:model="files"
                                   x-bind:accept="$wire.uploadType === 'pdf' ? '.pdf' : '.dwg,.dxf,.step,.stp,.iges,.igs'">
                            ファイルを選択
                        </label>
                    </div>

                    <template x-if="progress > 0 && progress < 100">
                        <div class="mt-4">
                            <div class="h-2 rounded bg-black/10 dark:bg-white/20">
                                <div class="h-2 rounded bg-blue-600" :style="`width: ${progress}%`"></div>
                            </div>
                            <div class="text-xs mt-1" x-text="progress + '%'"/>
                        </div>
                    </template>
                </div>

                @error('files.*')
                <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror

                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($files as $i => $f)
                        <div class="rounded border p-2 space-y-1" wire:key="tmp-{{ $i }}">
                            <div class="text-sm">{{ $f->getClientOriginalName() }}</div>
                            <button type="button" class="text-xs text-red-600"
                                    wire:click="$removeUpload('files', '{{ $f->getFilename() }}')">除外
                            </button>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <flux:button variant="ghost" x-on:click="$flux.modal('upload-revisions-modal').close()">キャンセル
                    </flux:button>
                    <flux:button variant="primary" wire:click="saveRevisions" wire:loading.attr="disabled">
                        <span wire:loading.remove>アップロード</span>
                        <span wire:loading>アップロード中...</span>
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- 新規図面作成モーダル --}}
        <flux:modal name="create-drawing-modal">
            <div class="p-4 space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input label="図番" wire:model.defer="create.number" placeholder="例: A-101"/>
                    <flux:input label="タイトル" wire:model.defer="create.title" placeholder="例: 1F 平面図"/>
                    <flux:select wire:model="create.folder_id" placeholder="保存先" label="フォルダ">
                        @foreach(\Lastdino\DrawingManager\Models\DrawingManagerFolder::orderBy('name')->get() as $f)
                            <flux:select.option value="{{ $f->id }}">{{ $f->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    {{-- 管理部署は廃止（UI削除） --}}
                </div>

                <flux:field>
                    <flux:label>ダウンロード許可ロール</flux:label>
                    <select multiple class="w-full border rounded px-2 py-1 min-h-[120px]"
                            wire:model="create.allowed_role_ids">
                        @foreach(\Spatie\Permission\Models\Role::orderBy('name')->get() as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <flux:error name="create.allowed_role_ids"/>
                </flux:field>

                <flux:field>
                    <flux:label>編集可能ロール</flux:label>
                    <select multiple class="w-full border rounded px-2 py-1 min-h-[120px]"
                            wire:model="create.editor_role_ids">
                        @foreach(\Spatie\Permission\Models\Role::orderBy('name')->get() as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <flux:error name="create.editor_role_ids"/>
                </flux:field>

                {{-- タグ（microcms 風：チップス + サジェスト） --}}
                <flux:field label="タグ">
                    <div
                        x-data="{
                  value: @entangle('create.tags').live,
                  input: @entangle('tagInput'),
                  open: false,
                  max: 50,
                  all: @js($this->allTags),
                  get filtered() {
                    const q = (this.input || '').toLowerCase();
                    if (!q) return this.all.filter(n => !this.value.includes(n)).slice(0, 10);
                    return this.all.filter(n => n.toLowerCase().includes(q) && !this.value.includes(n)).slice(0, 10);
                  },
                  add(name) {
                    name = (name || '').trim();
                    if (!name) return;
                    if (this.value.includes(name)) { this.input=''; return; }
                    if (this.value.length >= this.max) return;
                    this.value = [...this.value, name];
                    this.input = '';
                    this.open = false;
                  },
                  remove(idx) {
                    this.value = this.value.filter((_, i) => i !== idx);
                  },
                  handleKey(e) {
                    if (['Enter', 'Tab', ','].includes(e.key)) {
                      e.preventDefault();
                      this.add(this.input);
                    } else if (e.key === 'Backspace' && !this.input) {
                      this.value = this.value.slice(0, -1);
                    }
                  }
                }"
                        class="w-full"
                    >
                        <div class="flex flex-wrap gap-1 border rounded px-2 py-1 focus-within:ring-2 ring-blue-500">
                            <template x-for="(name, i) in value" :key="name">
                                <div class="inline-flex items-center gap-1">
                                    <flux:badge x-text="name"></flux:badge>
                                    <button type="button" class="text-xs text-black/50 dark:text-white/50"
                                            @click="remove(i)">×
                                    </button>
                                </div>
                            </template>

                            <input
                                x-model="input"
                                @input="open = true"
                                @keydown="handleKey($event)"
                                @blur="add(input)"
                                placeholder="タグを入力して Enter"
                                class="flex-1 min-w-[8rem] outline-none bg透明 py-1"
                            />
                        </div>

                        <div x-show="open && filtered.length" @mousedown.prevent
                             class="mt-1 border rounded bg-white dark:bg-gray-900 shadow">
                            <ul class="max-h-44 overflow-auto">
                                <template x-for="name in filtered" :key="name">
                                    <li>
                                        <button type="button"
                                                class="w-full text-left px-3 py-1.5 hover:bg-black/5 dark:hover:bg-white/10"
                                                @click="add(name)">
                                            <span x-text="name"></span>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <div class="text-xs text-black/50 dark:text-white/50 mt-1">最大 50 個。重複は自動で除外します。
                        </div>
                    </div>
                    <flux:error name="create.tags"/>
                </flux:field>

                <div>
                    <flux:input type="file" wire:model="createFile" label="初回ファイル（任意）" accept="application/pdf,.pdf"/>
                    <div class="text-xs text-black/50 dark:text-white/50 mt-1">PDF のみアップロード可能です。</div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <flux:button variant="ghost" x-on:click="$flux.modal('create-drawing-modal').close()">キャンセル
                    </flux:button>
                    <flux:button variant="primary" wire:click="saveCreateDrawing" wire:loading.attr="disabled">
                        <span wire:loading.remove>作成</span>
                        <span wire:loading>作成中...</span>
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
