<?php

return [
    // 画面URLのプレフィックス
    'route_prefix' => 'drawings',

    // 画面アクセスのミドルウェア
    'middleware' => ['web', 'auth'],

    // ダウンロード時にポリシーを用いて認可確認を行う
    'authorize_download' => true,

    // Spatie Media Library の保存ディスク
    'media_disk' => 'local',

    // CAD 拡張子の設定（許可や正規化をコンフィグで管理）
    'cad' => [
        // アプリ内部で扱う“論理拡張子”一覧（UI表示や検索の基準）
        // ここに含まれない拡張子は弾かれます
        'extensions' => [
            'dwg',
            'dxf',
            'step',
            'iges',
        ],

        // 実ファイル拡張子 → 論理拡張子 へのエイリアス
        // 例: stp は step、igs は iges に正規化
        'aliases' => [
            'stp' => 'step',
            'igs' => 'iges',
        ],

        // フロントのファイルダイアログで表示させたい“追加の物理拡張子”
        // 例: stp や igs もダイアログに出したい場合
        'accept_extra' => ['stp', 'igs'],
    ],
];
