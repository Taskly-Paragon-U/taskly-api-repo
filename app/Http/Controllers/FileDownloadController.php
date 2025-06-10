<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use App\Models\SubmittedTimesheet;
use App\Models\TimesheetTask;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class FileDownloadController extends Controller
{
    /**
     * Download a single file
     */
    public function downloadFile(Request $request, $id)
    {
        try {
            // Find the submission
            $submission = SubmittedTimesheet::findOrFail($id);
            
            // Get the file path and add the public/ prefix
            $filePath = $submission->file_path;
            $publicFilePath = 'public/' . $filePath;
            
            // Generate custom filename based on submitter's name
            $submitter = $submission->submitter;
            $submitterName = $submitter ? $submitter->name : 'unknown';
            $customFileName = $submitterName . '_timesheet.pdf';
            
            Log::info("Attempting to download file ID: {$id}, path: {$filePath}, checking public path: {$publicFilePath}");
            Log::info("Custom filename: {$customFileName}");
            
            // Check if the file exists in the public directory
            if (Storage::exists($publicFilePath)) {
                Log::info("File found at public path: {$publicFilePath}");
                return Storage::download($publicFilePath, $customFileName);
            }
            
            // If we reach here, file not found
            Log::error("File not found for submission {$id}. Tried paths: {$filePath}, {$publicFilePath}");
            return response()->json(['error' => 'File not found'], 404);
            
        } catch (\Exception $e) {
            Log::error("Error downloading file: " . $e->getMessage());
            return response()->json(['error' => 'Error downloading file: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Download all approved files related to a specific task
     */
    public function downloadTaskFiles(Request $request, $taskId)
    {
        try {
            // Check if task exists
            $task = TimesheetTask::findOrFail($taskId);
            
            // Debug task template information
            Log::info("Task {$taskId} template info - title: " . ($task->title ?? 'null'));
            
            // Get ONLY approved submissions for the given task
            $submissions = SubmittedTimesheet::where('task_id', $taskId)
                ->where('status', 'approved')  // Only include APPROVED submissions
                ->whereNotNull('file_path')
                ->get();
            
            Log::info("Found " . $submissions->count() . " approved submissions for task {$taskId}");
            
            // Double check if we actually got approved submissions
            if ($submissions->isEmpty()) {
                Log::warning("No approved submissions found for task {$taskId}");
                return response()->json(['error' => 'No approved files to download'], 404);
            }
            
            // Log the IDs and statuses of all submissions we're downloading
            foreach ($submissions as $sub) {
                Log::info("Including submission ID: {$sub->id}, status: {$sub->status}, user: " . 
                         ($sub->submitter ? $sub->submitter->name : 'unknown'));
            }
            
            // Create temp directory
            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) {
                File::makeDirectory($tempDir, 0755, true, true);
            }
            
            // Create a unique zip file name
            $zipFileName = 'task_' . $taskId . '_approved_files_' . time() . '.zip';
            $zipFilePath = $tempDir . DIRECTORY_SEPARATOR . $zipFileName;
            
            Log::info("Creating zip file at {$zipFilePath}");
            
            // Create a new zip archive
            $zip = new ZipArchive();
            $result = $zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            
            if ($result !== TRUE) {
                Log::error("Failed to create zip file, error code: {$result}");
                return response()->json(['error' => 'Cannot create zip file'], 500);
            }
            
            $fileCount = 0;
            
            // Process all submissions and add their files to the zip
            foreach ($submissions as $submission) {
                $filePath = $submission->file_path;
                $publicFilePath = 'public/' . $filePath;
                
                Log::info("Processing approved submission {$submission->id}, file path: {$filePath}, public path: {$publicFilePath}");
                
                $fileExists = false;
                $fileContent = null;
                
                // Try to get the file from public directory
                if (Storage::exists($publicFilePath)) {
                    $fileContent = Storage::get($publicFilePath);
                    $fileExists = true;
                    Log::info("File found at public path: {$publicFilePath}");
                }
                
                if (!$fileExists) {
                    Log::warning("File not found for submission {$submission->id}. Tried paths: {$filePath}, {$publicFilePath}");
                    continue;
                }
                
                // Get submitter info
                $submitter = $submission->submitter;
                $submitterName = $submitter ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $submitter->name) : 'unknown';
                
                // Use custom filename with submitter name
                $fileInZipName = $submitterName . '_timesheet.pdf';
                
                try {
                    $zip->addFromString($fileInZipName, $fileContent);
                    $fileCount++;
                    Log::info("Added file to zip: {$fileInZipName}");
                } catch (\Exception $e) {
                    Log::error("Failed to add file to zip: {$filePath}, error: " . $e->getMessage());
                }
            }
            
            $zip->close();
            
            if ($fileCount === 0) {
                Log::error("No approved files were added to the zip archive");
                if (file_exists($zipFilePath)) {
                    unlink($zipFilePath);
                }
                return response()->json(['error' => 'No valid files to download'], 404);
            }
            
            // Check if the zip file was actually created
            if (!file_exists($zipFilePath)) {
                Log::error("Zip file was not created at {$zipFilePath}");
                return response()->json(['error' => 'Failed to create zip file'], 500);
            }
            
            Log::info("Zip file created successfully with {$fileCount} approved files. Size: " . filesize($zipFilePath) . " bytes");
            
            // Return the file as a download
            return response()->download($zipFilePath, $zipFileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error("Error in downloadTaskFiles: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}