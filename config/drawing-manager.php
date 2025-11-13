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
];
