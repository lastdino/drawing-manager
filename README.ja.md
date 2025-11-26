Lastdino Drawing Manager
=======================

[English](README.md) | 日本語

Laravel 12 + Livewire 3 + Flux UI で、図面（フォルダ、タグ、版管理）を扱うためのパッケージです。

機能
----
- 仮想化付きフォルダツリー（遅延読み込み）
- 図面一覧（検索、タグの OR/AND フィルタ）
- 詳細フライアウト（最新版のインジケーター表示）
- 図面の作成/編集（図番、表題、フォルダ、管理部門、許可ロール、タグ）
- 版のアップロード（PDF で新規版を作成、CAD は既存の PDF 版に添付）
- ポリシーによるセキュアなダウンロード（PDF と CAD 種別）
 - CAD 拡張子のエイリアスを設定可能（例: `stp` → `step`, `igs` → `iges`）

要件
----
- PHP >= 8.4
- Laravel ^12.0
- 必要なテーブル: `folders`, `tags`, `drawings` と中間テーブル `drawing_tag`, `drawing_role`
- 依存パッケージ: `spatie/laravel-permission`, `spatie/laravel-medialibrary`
- Livewire 3, Flux UI 2

注: 本パッケージはグリーンフィールド向けに独自の Eloquent モデル（`Lastdino\DrawingManager\Models\{DrawingManagerDrawing,DrawingManagerDrawingFolder,DrawingManagerDrawingTag}`）およびマイグレーションを同梱しています。既存アプリに同等のテーブル/モデルがある場合、これらのマイグレーションを実行しない選択が可能です。

インストール
------------
1) パッケージのインストール

```
composer require lastdino/drawing-manager
```

モノレポで開発している場合は、ルートの `composer.json` に PSR-4 を追加してください:

```
{
  "autoload": {
    "psr-4": {
      "Lastdino\\\\DrawingManager\\\\": "packages/lastdino/drawing-manager/src/"
    }
  }
}
```

その後 `composer dump-autoload` を実行します。

2) 必要なピア依存が未導入なら追加

```
composer require livewire/livewire:^3.6 livewire/flux:^2.6 spatie/laravel-permission:^6.10 spatie/laravel-medialibrary:^11.12
```

3) 設定（任意）

```
php artisan vendor:publish --tag=drawing-manager-config --no-interaction
```

`config/drawing-manager.php` の主な項目:
- `route_prefix`: UI のマウントパス（既定 `drawings`）
- `middleware`: UI 用ミドルウェア（既定 `['web','auth']`）
- `authorize_download`: ストリーム前にポリシーを確認（既定 `true`）
- `media_disk`: Spatie Media Library のディスク（既定 `local`）
- `cad.aliases`: CAD 拡張子の正規化/エイリアス。キーが入力拡張子、値が正規名。

CAD エイリアス設定の例:

```
return [
    // ...
    'cad' => [
        // ユーザーのリクエストやアップロード時に、
        // 左の拡張子は右の正規名として扱われます。
        'aliases' => [
            'stp' => 'step',
            'igs' => 'iges',
            'dft' => 'dxf',
        ],
    ],
];
```

4) ルート
---------
パッケージは自動で以下のルートを登録します（括弧内はルート名）。
- GET `/{prefix}` → 図面一覧（Livewire コンポーネント）(`drawings.index`)
- GET `/{prefix}/{drawing}/latest/{type}` → 指定種別（`pdf`, `dwg`, `dxf`, `step`, `iges`）の最新版をダウンロード (`drawings.download.latest`)
- GET `/{prefix}/revisions/{media}` → 指定のメディアをダウンロード (`drawings.download.revision`)

同一プレフィックス（既定 `/drawings`）で競合するホストアプリ側のルートがある場合は削除してください。

最新版ダウンロードの挙動:
- URL セグメント `{type}` は `config('drawing-manager.cad.aliases')` に基づいて正規化されます。例: `/drawings/{id}/latest/stp` は `step` の最新版を返します。
- `pdf` の場合、カスタムプロパティ `kind=pdf` を持つメディア、または拡張子/ MIME Type により PDF と判断されるものが対象です。
- CAD の場合、`custom_properties: { kind: 'cad', cad_type: ... }` に一致するもの、または拡張子一致のものから選択します。

認可（Authorization）
---------------------
本パッケージのモデルクラスに対して、以下のポリシーが自動登録されます:
- `Lastdino\DrawingManager\Models\DrawingManagerDrawing`

既定のロジック（`DrawingPolicy`）:
- `view` / `download`: 図面に紐づく「許可ロール名」のいずれかをユーザーが持っていれば許可。管理者は常に許可。
- `create`: 管理者は許可。管理者以外は `department_id != null` が必要。
- `update`: 管理者、または `user.department_id === drawing.managing_department_id` の場合に許可。

ホストアプリ側で `App\Models\Drawing` など独自モデルを使う場合は、アプリ側でそのモデルへのポリシーをマッピングしてください。

ダウンロードの認可:
- `config('drawing-manager.authorize_download')` が `true`（既定）のとき、ダウンロード前に `Gate::authorize('download', $drawing)` を呼び出します。
- ルートをミドルウェアで十分に保護しており、モデル単位のポリシーチェックを省略したい場合のみ `false` を検討してください。

Livewire UI
-----------
UI は Flux UI コンポーネントを使用しています。フロントの変更が反映されない場合はビルダーを実行してください:
- `npm run dev` または `composer run dev`

UI をカスタマイズする場合、ビューの公開が可能です:

```
php artisan vendor:publish --tag=drawing-manager-views --no-interaction
```

テスト
------
Pest または PHPUnit でフィーチャテストを書けます。例:

```
use Lastdino\\DrawingManager\\Livewire\\Drawings\\Index;

it('shows drawings page', function () {
    $this->actingAs(User::factory()->create());
    $this->get('/drawings')->assertSeeLivewire(Index::class);
});
```

ダウンロードエンドポイントの例（Pest）:

```
it('downloads latest step using stp alias', function () {
    $user = User::factory()->create();
    $drawing = DrawingManagerDrawing::factory()->create();
    // attach media with custom properties: kind=cad, cad_type=step, revision=1
    // ...
    $this->actingAs($user)
        ->get("/drawings/{$drawing->getKey()}/latest/stp")
        ->assertOk();
});
```

ロードマップ
------------
- グリーンフィールド向けマイグレーションの公開
- ダウンロードのアクティビティログ
- PDF サムネイル
- S3 や外部ストレージのサンプル

マイグレーション
----------------
本パッケージはグリーンフィールド向けマイグレーションを同梱し、サービスプロバイダ経由で自動ロードします。`php artisan migrate` 実行時に適用されます。

テーブル一覧:
- `folders`（自己参照の親、親+名称でユニーク）
- `tags`（`slug` ユニーク）
- `drawings`（`number` ユニーク、`folders` への FK、必要ならホスト `departments` 参照）
- `drawing_tag`（中間）
- `drawing_role`（Spatie Permission の `roles` への中間）

注意:
- 既存アプリに同等スキーマがある環境では、これらのマイグレーションを実行しないでください。
- Spatie Permission のマイグレーションを先に適用してから、`drawing_role` を使用してください。

Tips（UI / ビルド）
------------------
- UI は Livewire 3 と Flux UI で構築されています。フロント変更が反映されない場合は `npm run dev` または `composer run dev` を実行してください。
- UI をカスタマイズする際は、パッケージのビューを公開し、変更をバージョン管理下に置くことをおすすめします。

ライセンス
---------
MIT
