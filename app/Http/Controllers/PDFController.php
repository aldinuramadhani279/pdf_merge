<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PDFController extends Controller
{
    public function index()
    {
        return view('pdf-merger');
    }

    public function downloadTemplate()
    {
        // Simple CSV template
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_daftar_folder.csv"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Nama Folder']);
            fputcsv($file, ['ContohFolder1']);
            fputcsv($file, ['ContohFolder2']);
            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    /**
     * Normalize a filesystem path for use with PHP's is_dir() on Windows.
     * Handles: drive roots (C:\, Z:\), mapped network drives, UNC paths (\\server\share).
     */
    private function normalizePath(string $input): string
    {
        // Strip accidental surrounding quotes and whitespace
        $path = trim($input, " \t\n\r\0\x0B\"'");

        // Replace forward slashes with backslashes for Windows style
        $path = str_replace('/', '\\', $path);

        // UNC paths (\\server\share\...) - do NOT strip trailing slash, return as-is
        if (str_starts_with($path, '\\\\')) {
            return rtrim($path, '\\');
        }

        // Windows drive root like "C:", "Z:", "C:\", "Z:\" 
        // MUST always end with backslash for is_dir() to work on mapped/network drives
        if (preg_match('/^[a-zA-Z]:/', $path)) {
            // Strip trailing slashes first, then add exactly one backslash
            $drive   = substr($path, 0, 2);   // e.g. "C:"
            $rest    = substr($path, 2);       // e.g. "\some\folder" or "\" or ""
            $rest    = rtrim($rest, '\\');     // strip trailing slashes from sub-path

            if ($rest === '') {
                // It's just a drive root: "C:" or "C:\" → always keep as "C:\"
                return $drive . '\\';
            }
            // It's a sub-folder: "C:\Folder\Data"
            return $drive . $rest;
        }

        // Fallback: strip trailing slash (for Linux-style absolute paths, etc.)
        return rtrim($path, '/\\');
    }

    public function merge(Request $request)
    {
        // CRITICAL FIX: Increase limits for heavy processing
        set_time_limit(3600);
        ini_set('memory_limit', '2048M');

        $request->validate([
            'root_path' => 'required|string',
            'excel_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $rootPath = $this->normalizePath($request->input('root_path'));

        // Check if root directory exists
        if (!is_dir($rootPath)) {
            Log::error("merge(): is_dir() failed. Normalized path: [{$rootPath}]");
            return back()->with('error', "Direktori root tidak ditemukan: {$rootPath}");
        }

        try {
            // Load Excel
            $data = Excel::toArray([], $request->file('excel_file'));
            // Assumes first sheet, skipping header if needed. 
            // Let's assume header is row 0, data starts row 1.
            $rows = $data[0] ?? [];
            
            // Remove header if it looks like one (optional, but good practice if user uses template)
            if (count($rows) > 0 && (strtolower($rows[0][0]) === 'folder name' || strtolower($rows[0][0]) === 'nama folder')) {
                array_shift($rows);
            }

            $zip = new \ZipArchive();
            $zipFileName = 'merged_pdfs_' . time() . '.zip';
            // Save zip to system temp dir to avoid modifying user's folder
            $zipFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipFileName;

            if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
                return back()->with('error', 'Gagal membuat file ZIP.');
            }

            $hasFiles = false;

            foreach ($rows as $row) {
                $folderName = $row[0] ?? null;
                if (!$folderName) continue;

                $fullFolderPath = rtrim($rootPath, '\\') . DIRECTORY_SEPARATOR . $folderName;

                if (!is_dir($fullFolderPath)) {
                    $results[] = [
                        'type' => 'error',
                        'message' => "Dilewati: Folder '$folderName' tidak ditemukan.",
                    ];
                    continue;
                }

                // Scan for PDFs
                $files = scandir($fullFolderPath);
                $pdfFiles = [];
                foreach ($files as $file) {
                    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf') {
                        $pdfFiles[] = $fullFolderPath . DIRECTORY_SEPARATOR . $file;
                    }
                }

                if (empty($pdfFiles)) {
                    $results[] = [
                        'type' => 'warning',
                        'message' => "Dilewati: Tidak ada file PDF di '$folderName'.",
                    ];
                    continue;
                }

                // Sorting Logic - Sort files BEFORE merging
                // $pdfFiles contains full paths, but we need to sort by FILENAME only
                $sortBy = $request->input('sort_by', 'default');
                $sortOrder = $request->input('sort_order', 'asc');

                // Helper: Check if filename starts with "SEP" followed by non-letter (space, number, etc)
                // This gives priority to "SEP ", "SEP RI", "SEP-123" but NOT "SEPTEMBER"
                $isSepPriority = function($filename) {
                    // Remove extension for checking
                    $name = pathinfo($filename, PATHINFO_FILENAME);
                    // Check if starts with SEP (case insensitive)
                    if (stripos($name, 'SEP') === 0) {
                        // If exactly "SEP" or followed by non-letter character
                        if (strlen($name) === 3) {
                            return true; // Exactly "SEP"
                        }
                        $charAfterSep = substr($name, 3, 1);
                        // If the 4th character is NOT a letter (a-z, A-Z), it's a SEP priority file
                        if (!ctype_alpha($charAfterSep)) {
                            return true; // "SEP ", "SEP-", "SEP_", "SEP1", etc.
                        }
                    }
                    return false;
                };

                // Helper: Check if filename contains "KWI" or "RawatJalanBPJS" (these go LAST)
                // Matches: "KWI", "KWITANSI", "KWI TANSI", "RawatJalanBPJS" (case insensitive)
                $isLastPriority = function($filename) {
                    $name = pathinfo($filename, PATHINFO_FILENAME);
                    // Check for KWI anywhere in name (case insensitive)
                    if (stripos($name, 'KWI') !== false) {
                        return true;
                    }
                    // Check for RawatJalanBPJS anywhere in name (case insensitive)
                    if (stripos($name, 'RawatJalanBPJS') !== false) {
                        return true;
                    }
                    return false;
                };

                // Custom compare function with 3 priority tiers:
                // 1. SEP files (first)
                // 2. Normal files (middle)
                // 3. KWI/RawatJalanBPJS files (last)
                $compareWithPriority = function($a, $b, $sortOrder) use ($isSepPriority, $isLastPriority) {
                    $nameA = basename($a);
                    $nameB = basename($b);
                    
                    $aSep = $isSepPriority($nameA);
                    $bSep = $isSepPriority($nameB);
                    $aLast = $isLastPriority($nameA);
                    $bLast = $isLastPriority($nameB);
                    
                    // SEP files always come first
                    if ($aSep && !$bSep) return -1;
                    if (!$aSep && $bSep) return 1;
                    
                    // KWI/RawatJalanBPJS files always come last
                    if ($aLast && !$bLast) return 1;
                    if (!$aLast && $bLast) return -1;
                    
                    // Both same priority tier, use natural sort
                    if ($sortOrder === 'asc') {
                        return strnatcasecmp($nameA, $nameB);
                    } else {
                        return strnatcasecmp($nameB, $nameA);
                    }
                };

                if ($sortBy === 'date') {
                    // Sort by file modification time with priority tiers
                    usort($pdfFiles, function($a, $b) use ($sortOrder, $isSepPriority, $isLastPriority) {
                        $nameA = basename($a);
                        $nameB = basename($b);
                        $aSep = $isSepPriority($nameA);
                        $bSep = $isSepPriority($nameB);
                        $aLast = $isLastPriority($nameA);
                        $bLast = $isLastPriority($nameB);
                        
                        // SEP files always come first
                        if ($aSep && !$bSep) return -1;
                        if (!$aSep && $bSep) return 1;
                        
                        // KWI/RawatJalanBPJS files always come last
                        if ($aLast && !$bLast) return 1;
                        if (!$aLast && $bLast) return -1;
                        
                        // Both same priority tier, sort by date
                        $timeA = filemtime($a);
                        $timeB = filemtime($b);
                        if ($timeA == $timeB) return 0;
                        if ($sortOrder === 'asc') {
                            return $timeA < $timeB ? -1 : 1;
                        } else {
                            return $timeA > $timeB ? -1 : 1;
                        }
                    });
                } elseif ($sortBy === 'name') {
                    // Sort by filename with priority tiers
                    usort($pdfFiles, function($a, $b) use ($sortOrder, $compareWithPriority) {
                        return $compareWithPriority($a, $b, $sortOrder);
                    });
                } else {
                    // Default: Natural sort by filename with priority tiers
                    usort($pdfFiles, function($a, $b) use ($sortOrder, $compareWithPriority) {
                        return $compareWithPriority($a, $b, $sortOrder);
                    });
                }

                // DEBUG: Log the sorted order to Laravel log
                $sortedNames = array_map(function($f) { return basename($f); }, $pdfFiles);
                Log::info("Folder: $folderName | Sort: $sortBy $sortOrder | Order: " . implode(', ', $sortedNames));

                // Merge PDFs
                $pdf = new Fpdi();
                $mergedCount  = 0;  // Track actually merged files
                $skippedFiles = []; // Track which files were skipped
                
                foreach ($pdfFiles as $file) {
                    try {
                        $pageCount = $pdf->setSourceFile($file);
                    } catch (\Exception $e) {
                        // If standard import fails, try to normalize/repair the PDF
                        // This usually handles WPS/PDF 1.5+ Compressed streams
                        $fixedFile = $this->normalizePdf($file);
                        
                        if ($fixedFile && file_exists($fixedFile)) {
                            try {
                                $pageCount = $pdf->setSourceFile($fixedFile);
                                Log::info("GS Fix OK: " . basename($file) . " di folder $folderName");
                                // If successful, use this fixed file, but DO NOT delete original
                            } catch (\Exception $e2) {
                                $skippedFiles[] = basename($file);
                                $results[] = [
                                    'type' => 'warning',
                                    'message' => "⚠ Folder '$folderName': File '" . basename($file) . "' DILEWATI (gagal bahkan setelah perbaikan GS). Error: " . $e2->getMessage()
                                ];
                                Log::warning("GS Fix FAILED for: " . basename($file) . " in $folderName. Error: " . $e2->getMessage());
                                continue;
                            }
                        } else {
                            $skippedFiles[] = basename($file);
                            $results[] = [
                                'type' => 'warning',
                                'message' => "⚠ Folder '$folderName': File '" . basename($file) . "' DILEWATI (format tidak didukung / Ghostscript tidak dapat membaca file ini)."
                            ];
                            Log::warning("FPDI + GS both failed for: " . basename($file) . " in $folderName. Original error: " . $e->getMessage());
                            continue;
                        }
                    }

                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        $templateId = $pdf->importPage($pageNo);
                        $size = $pdf->getTemplateSize($templateId);
                        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                        $pdf->useTemplate($templateId);
                    }
                    $mergedCount++;
                }

                // Only add to ZIP if at least 1 file was actually merged
                if ($mergedCount === 0) {
                    $results[] = [
                        'type' => 'error',
                        'message' => "❌ Folder '$folderName': Semua " . count($pdfFiles) . " file gagal diproses. Folder ini TIDAK ditambahkan ke ZIP.",
                    ];
                    Log::error("Folder $folderName: 0 files merged, skipping ZIP entry.");
                    continue;
                }

                $outputFilename = $folderName . '.pdf';
                // Output 'S' = return as string
                $pdfContent = $pdf->Output('S');
                
                // Add string to zip
                $zip->addFromString($outputFilename, $pdfContent);
                $hasFiles = true;

                // Show partial success if some files were skipped
                $totalFound = count($pdfFiles);
                if (count($skippedFiles) > 0) {
                    $results[] = [
                        'type' => 'warning',
                        'message' => "⚠ Folder '$folderName': SEBAGIAN BERHASIL — $mergedCount dari $totalFound file digabung. " . count($skippedFiles) . " file dilewati: " . implode(', ', $skippedFiles),
                    ];
                } else {
                    $results[] = [
                        'type' => 'success',
                        'message' => "✅ Folder '$folderName': Berhasil menggabungkan $mergedCount dari $totalFound file menjadi '$folderName.pdf'.",
                    ];
                }
            }

            $zip->close();

            if (!$hasFiles) {
                if (file_exists($zipFilePath)) {
                    unlink($zipFilePath);
                }
                return back()->with('error', 'Tidak ada file PDF yang berhasil digabungkan.')->with('results', $results);
            }

            return back()
                ->with('success', 'Pemrosesan selesai! Silakan unduh file ZIP di bawah.')
                ->with('results', $results)
                ->with('zip_path', $zipFilePath);

        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat memproses file: ' . $e->getMessage());
        }
    }

    /**
     * Tries to normalize a PDF to version 1.4 using Ghostscript.
     * Returns the path to the temporary fixed file, or null if failed/no GS.
     */
    private function normalizePdf($originalPath)
    {
        // 1. Check .env configuration first
        $gsBinary = env('GS_BINARY_PATH');

        // 2. If not in .env, try auto-detection via 'where' command (Windows) or 'which' (Linux)
        if (!$gsBinary) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $check = shell_exec('where gswin64c');
                if ($check) {
                    $gsBinary = trim($check);
                } else {
                    $check = shell_exec('where gswin32c');
                    if ($check) $gsBinary = trim($check);
                }
            } else {
                 $check = shell_exec('which gs');
                 if ($check) $gsBinary = trim($check);
            }
        }

        // 3. Fallback: Check common Windows installation paths if still not found
        if (!$gsBinary && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Try to find any gswin64c.exe in Program Files/gs
            $gsRoot = 'C:\\Program Files\\gs';
            if (is_dir($gsRoot)) {
                $dirs = scandir($gsRoot);
                foreach ($dirs as $dir) {
                    if ($dir === '.' || $dir === '..') continue;
                    $candidate = $gsRoot . DIRECTORY_SEPARATOR . $dir . '\\bin\\gswin64c.exe';
                    if (file_exists($candidate)) {
                        $gsBinary = $candidate;
                        break;
                    }
                }
            }
        }

        if (!$gsBinary) {
            Log::error("Ghostscript binary not found. Please install Ghostscript or set GS_BINARY_PATH in .env");
            return null; 
        }
        
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fixed_' . uniqid() . '.pdf';
        
        // Command to convert to PDF 1.4
        $command = sprintf(
            '"%s" -o "%s" -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH "%s"',
            $gsBinary,
            $tempPath,
            $originalPath
        );
        
        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists($tempPath)) {
            return $tempPath;
        }

        return null;
    }

    public function checkPath(Request $request)
    {
        $raw = $request->query('path', '');
        if (empty($raw)) {
            return response()->json(['ok' => false, 'message' => 'Path tidak boleh kosong.']);
        }

        $path = $this->normalizePath($raw);
        $accessible = is_dir($path);

        if (!$accessible) {
            return response()->json([
                'ok'      => false,
                'path'    => $path,
                'message' => 'Direktori TIDAK ditemukan atau tidak dapat diakses. Pastikan drive sudah di-map dan path benar.',
            ]);
        }

        // Count sub-folders (these are the folders that will be processed)
        $folderCount = 0;
        try {
            $items = scandir($path);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                if (is_dir($path . DIRECTORY_SEPARATOR . $item)) $folderCount++;
            }
        } catch (\Exception $e) {
            $folderCount = -1;
        }

        // Free disk space info
        $freeBytes  = @disk_free_space($path);
        $totalBytes = @disk_total_space($path);
        $freeGB  = $freeBytes  !== false ? round($freeBytes  / 1073741824, 2) : null;
        $totalGB = $totalBytes !== false ? round($totalBytes / 1073741824, 2) : null;

        return response()->json([
            'ok'           => true,
            'path'         => $path,
            'message'      => 'Direktori ditemukan dan dapat diakses!',
            'folder_count' => $folderCount,
            'free_gb'      => $freeGB,
            'total_gb'     => $totalGB,
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $path = $request->query('path');

        if (!file_exists($path)) {
            abort(404, 'File not found');
        }

        return response()->download($path)->deleteFileAfterSend(true);
    }

    /**
     * API Endpoint to browse server directories (for the UI Directory Picker).
     */
    public function browseDirectories(Request $request)
    {
        $path = $request->query('path');
        
        // Default: Show available drives on Windows if path is empty
        if (empty($path)) {
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $directories = [];
            
            if ($isWindows) {
                // Return all accessible drives e.g., C:\, D:\
                foreach (range('A', 'Z') as $char) {
                    $drive = $char . ':\\';
                    if (is_dir($drive)) {
                        $directories[] = [
                            'name' => 'Local Disk (' . $char . ':)',
                            'path' => $drive,
                            'is_dir' => true
                        ];
                    }
                }
            } else {
                // If not windows, default to /
                $directories[] = [
                    'name' => 'Root System',
                    'path' => '/',
                    'is_dir' => true
                ];
            }
            
            return response()->json([
                'current_path' => '',
                'parent_path' => null,
                'directories' => $directories
            ]);
        }

        // Normalize the incoming path
        $path = $this->normalizePath($path);

        if (!is_dir($path)) {
            return response()->json(['error' => 'Path tidak ditemukan atau akses ditolak.'], 404);
        }

        $directories = [];
        try {
            $items = scandir($path);
            if ($items !== false) {
                // Sort items naturally
                natcasesort($items);
                
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    
                    $fullPath = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $item;
                    
                    // Only collect actual directories to avoid slow performance
                    if (is_dir($fullPath)) {
                        $directories[] = [
                            'name' => $item,
                            'path' => $fullPath,
                            'is_dir' => true
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Akses ditolak ke direktori ini.'], 403);
        }

        // Determine parent path for "Back" button
        $parentPath = null;
        if (preg_match('/^[a-zA-Z]:\\\\$/', $path)) {
            // Root drive (C:\) -> goes back to drives list
            $parentPath = ''; 
        } elseif ($path !== '/' && $path !== '') {
            $parentPath = dirname($path);
            // If dirname returns "C:", fix it to "C:\"
            if (preg_match('/^[a-zA-Z]:$/', $parentPath)) {
                $parentPath .= '\\';
            }
        }

        return response()->json([
            'current_path' => $path,
            'parent_path' => $parentPath,
            'directories' => array_values($directories)
        ]);
    }
}
