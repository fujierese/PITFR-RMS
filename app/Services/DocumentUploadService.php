<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class DocumentUploadService
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];
    private const MAX_FILE_SIZE = 10485760; // 10MB in bytes

    /**
     * Validate and upload a document file.
     *
     * @param  UploadedFile  $file
     * @param  string  $documentType  Type of document (activity_proposal, igp_receipt, e_signature)
     * @param  string  $controlNumber  The facility request control number
     * @return array  ['success' => bool, 'filename' => ?string, 'error' => ?string]
     */
    public function uploadDocument(UploadedFile $file, string $documentType, string $controlNumber): array
    {
        try {
            // Validate file extension
            $extension = strtolower($file->getClientOriginalExtension());
            $allowedExtensions = $documentType === 'e_signature'
                ? ['jpg', 'jpeg', 'png']
                : self::ALLOWED_EXTENSIONS;
            $allowedMimeTypes = $documentType === 'e_signature'
                ? ['image/jpeg', 'image/png']
                : self::ALLOWED_MIME_TYPES;

            if (!in_array($extension, $allowedExtensions, true)) {
                return [
                    'success' => false,
                    'error' => "Invalid file type. Allowed formats: " . implode(', ', array_map('strtoupper', $allowedExtensions)),
                ];
            }

            // Validate MIME type
            $mimeType = $file->getMimeType();
            if (!in_array($mimeType, $allowedMimeTypes, true)) {
                return [
                    'success' => false,
                    'error' => $documentType === 'e_signature'
                        ? 'Invalid file format detected. Please upload a valid JPEG or PNG image.'
                        : "Invalid file format detected. Please upload a valid PDF, JPEG, or PNG file.",
                ];
            }

            // Validate file size
            if ($file->getSize() > self::MAX_FILE_SIZE) {
                return [
                    'success' => false,
                    'error' => "File size exceeds 10MB limit.",
                ];
            }

            // Prevent executable files
            if ($this->isExecutableFile($file)) {
                return [
                    'success' => false,
                    'error' => "Executable files are not allowed.",
                ];
            }

            // Generate safe filename
            $filename = $this->generateSafeFilename($documentType, $controlNumber, $extension);

            // Store the file
            $path = "documents/{$documentType}";
            $file->storeAs($path, $filename, 'local');

            return [
                'success' => true,
                'filename' => $filename,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => "An error occurred while uploading the file.",
            ];
        }
    }

    /**
     * Generate a safe filename for uploaded documents.
     */
    private function generateSafeFilename(string $documentType, string $controlNumber, string $extension): string
    {
        $timestamp = now()->format('Ymd_His');
        $randomSuffix = substr(md5(uniqid()), 0, 8);
        return "{$controlNumber}_{$documentType}_{$timestamp}_{$randomSuffix}.{$extension}";
    }

    /**
     * Check if the file is potentially executable.
     */
    private function isExecutableFile(UploadedFile $file): bool
    {
        $dangerousExtensions = ['exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar', 'zip', 'rar'];
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, $dangerousExtensions, true)) {
            return true;
        }

        // Check for double extensions (e.g., file.pdf.exe)
        $filename = $file->getClientOriginalName();
        $parts = explode('.', $filename);
        if (count($parts) > 2) {
            $lastExtension = strtolower(array_pop($parts));
            if (in_array($lastExtension, $dangerousExtensions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete a document file.
     */
    public function deleteDocument(string $filename, string $documentType): bool
    {
        try {
            $path = "documents/{$documentType}/{$filename}";
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get the full path to a document file for retrieval.
     */
    public function getDocumentPath(string $filename, string $documentType): ?string
    {
        $path = "documents/{$documentType}/{$filename}";
        if (Storage::disk('local')->exists($path)) {
            return $path;
        }
        return null;
    }
}
