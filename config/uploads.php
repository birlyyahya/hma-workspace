<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Project Files — Direct Multipart Upload ke MinIO
    |--------------------------------------------------------------------------
    |
    | Konfigurasi flow upload langsung browser → MinIO (S3 multipart dengan
    | presigned URL). Backend hanya control plane: initiate, sign, complete,
    | abort. Byte file tidak pernah melewati PHP-FPM.
    |
    */

    'project_files' => [

        // Disk filesystem yang menunjuk bucket MinIO khusus project files.
        'disk' => env('PROJECT_FILES_DISK', 'project-files'),

        // Ukuran maksimum satu file (bytes). Default 2 GB.
        'max_file_size' => (int) env('PROJECT_FILES_MAX_SIZE', 2 * 1024 * 1024 * 1024),

        // Ukuran part multipart (bytes). Default 8 MB (minimum S3 = 5 MB).
        'part_size' => (int) env('PROJECT_FILES_PART_SIZE', 8 * 1024 * 1024),

        // Batas jumlah part per upload (hard limit S3 = 10.000).
        'max_parts' => 10000,

        // Batas jumlah part yang boleh di-sign dalam satu request batch.
        'sign_batch_limit' => 50,

        // Whitelist ekstensi yang boleh diupload.
        'allowed_extensions' => [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
            'zip', 'rar', '7z',
            'dwg', 'dxf',
        ],

        // Masa berlaku presigned URL (menit).
        'presign_ttl' => (int) env('PROJECT_FILES_PRESIGN_TTL', 30),

        // Multipart upload menggantung lebih tua dari ini (jam) di-abort
        // oleh command project-files:cleanup-multipart.
        'stale_multipart_hours' => (int) env('PROJECT_FILES_STALE_HOURS', 24),
    ],

];
