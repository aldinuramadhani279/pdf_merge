<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Storage;
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

    public function merge(Request $request)
    {
        $request->validate([
            'root_path' => 'required|string',
            'excel_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $rootPath = rtrim($request->input('root_path'), '/\\');
        
        // Check if root directory exists
        if (!is_dir($rootPath)) {
            return back()->with('error', "Direktori root tidak ditemukan: $rootPath");
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

                $fullFolderPath = $rootPath . DIRECTORY_SEPARATOR . $folderName;

                if (!is_dir($fullFolderPath)) {
                    $results[] = [
                        'type' => 'error',
                        'message' => "Dilewati: Folder '$folderName' tidak ditemukan."
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
                        'message' => "Dilewati: Tidak ada file PDF di '$folderName'."
                    ];
                    continue;
                }

                // Sort PDFs naturally (like Windows explorer)
                natsort($pdfFiles);

                // Merge PDFs
                $pdf = new Fpdi();
                
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
                                // If successful, use this fixed file, but DO NOT delete original
                                // We might want to clean up temp fixed file later?
                                // For simplicity, we just use it.
                            } catch (\Exception $e2) {
                                $results[] = [
                                    'type' => 'warning',
                                    'message' => "Gagal membaca: " . basename($file) . " (Bahkan setelah perbaikan). Error: " . $e2->getMessage()
                                ];
                                continue;
                            }
                        } else {
                             $results[] = [
                                'type' => 'warning',
                                'message' => "Gagal membaca: " . basename($file) . ". Mungkin format tidak didukung (Anda butuh Ghostscript di server untuk file WPS)."
                            ];
                            continue;
                        }
                    }

                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        $templateId = $pdf->importPage($pageNo);
                        $size = $pdf->getTemplateSize($templateId);
                        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                        $pdf->useTemplate($templateId);
                    }
                }

                $outputFilename = $folderName . '.pdf';
                // Output 'S' = return as string
                $pdfContent = $pdf->Output('S');
                
                // Add string to zip
                $zip->addFromString($outputFilename, $pdfContent);
                $hasFiles = true;

                $results[] = [
                    'type' => 'success',
                    'message' => "Sukses: Menggabungkan " . count($pdfFiles) . " file menjadi '$folderName.pdf' (ditambahkan ke ZIP).",
                ];
            }

            $zip->close();
            
            // Clean up any temp files if tracked (omitted for now for simplicity, rely on OS temp clean)

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
        // Check for Ghostscript
        // Windows normally uses gswin64c or gswin32c. Linux uses gs.
        $gsBinary = null;
        
        // Simple check
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $check = shell_exec('where gswin64c');
            if ($check) $gsBinary = 'gswin64c';
            else {
                $check = shell_exec('where gswin32c');
                if ($check) $gsBinary = 'gswin32c';
            }
        } else {
             $check = shell_exec('which gs');
             if ($check) $gsBinary = 'gs';
        }

        if (!$gsBinary) {
            return null; // GS not found
        }

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fixed_' . uniqid() . '.pdf';
        
        // Command to convert to PDF 1.4 (safe for FPDI)
        // -sDEVICE=pdfwrite -dCompatibilityLevel=1.4
        $command = "$gsBinary -o \"$tempPath\" -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH \"$originalPath\"";
        
        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists($tempPath)) {
            return $tempPath;
        }

        return null;
    }

    public function downloadPdf(Request $request)
    {
        $path = $request->query('path');

        if (!file_exists($path)) {
            abort(404, 'File not found');
        }

        return response()->download($path)->deleteFileAfterSend(true);
    }
}
