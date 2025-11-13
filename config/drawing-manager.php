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

    // パッケージ内マイグレーションを自動読み込みするか（デフォルト: 無効）
    // 既存アプリにテーブルがある場合は衝突を避けるため false のままにしてください。
    // 新規プロジェクトで本パッケージのテーブルを作成したい場合に true にします。
    'load_migrations' => false,
];
