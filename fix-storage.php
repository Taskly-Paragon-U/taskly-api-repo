<?php

// This is a quick script to help diagnose and fix the storage structure issue
// Save this as a file (e.g., fix-storage.php) in your Laravel project root
// Then run it with: php fix-storage.php

require __DIR__.'/vendor/autoload.php';

// Load the Laravel environment
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

// Step 1: List all storage disks
$disks = config('filesystems.disks');
echo "Available storage disks:\n";
print_r($disks);

// Step 2: Check if storage directories exist
$storagePath = storage_path('app');
echo "\nChecking storage directories:\n";
echo "Main storage path: $storagePath - " . (is_dir($storagePath) ? "EXISTS" : "MISSING") . "\n";

$dirList = [
    'submitted_timesheets',
    'timesheet_templates',
    'temp'
];

foreach ($dirList as $dir) {
    $dirPath = $storagePath . DIRECTORY_SEPARATOR . $dir;
    $exists = is_dir($dirPath);
    echo "$dir: $dirPath - " . ($exists ? "EXISTS" : "MISSING") . "\n";
    
    // Create missing directories
    if (!$exists) {
        echo "Creating missing directory: $dir\n";
        File::makeDirectory($dirPath, 0755, true, true);
    }
}

// Step 3: List all files in storage
echo "\nListing files in storage:\n";
$allFiles = Storage::allFiles();
foreach ($allFiles as $file) {
    echo "$file - " . (Storage::exists($file) ? "EXISTS" : "MISSING") . "\n";
}

// Step 4: Check file paths in database
echo "\nChecking database records:\n";

// Check timesheet_tasks
$tasks = DB::table('timesheet_tasks')->whereNotNull('template_file')->get();
echo "Found " . count($tasks) . " tasks with template files\n";
foreach ($tasks as $task) {
    $filePath = $task->template_file;
    $exists = Storage::exists($filePath);
    echo "Task {$task->id}: {$filePath} - " . ($exists ? "EXISTS" : "MISSING") . "\n";
    
    // Fix path if needed
    if (!$exists && strpos($filePath, 'timesheet_templates/') === 0) {
        $shortPath = str_replace('timesheet_templates/', '', $filePath);
        echo "  Checking alternative path: $shortPath\n";
        
        if (Storage::exists($shortPath)) {
            echo "  File exists at $shortPath, copying to $filePath\n";
            Storage::copy($shortPath, $filePath);
        } else {
            echo "  File not found at alternative path either\n";
        }
    }
}

// Check submitted_timesheets
$submissions = DB::table('submitted_timesheets')->whereNotNull('file_path')->get();
echo "\nFound " . count($submissions) . " submissions with file paths\n";
foreach ($submissions as $submission) {
    $filePath = $submission->file_path;
    $exists = Storage::exists($filePath);
    echo "Submission {$submission->id}: {$filePath} - " . ($exists ? "EXISTS" : "MISSING") . "\n";
}

echo "\nDone checking storage structure.\n";