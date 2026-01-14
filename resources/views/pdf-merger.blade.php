<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplikasi Bot Pemrosesan Penggabungan PDF Aldi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen py-12 px-4 sm:px-6 lg:px-8 text-slate-900">
    <div class="max-w-2xl mx-auto space-y-8">
        
        <div class="text-center space-y-2">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Aplikasi Bot Pemrosesan PDF Aldi</h1>
            <p class="text-lg text-slate-600">Gabungkan beberapa file PDF dari folder berdasarkan daftar Excel.</p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-100 p-8 space-y-6">
            
            <!-- Step 1: Download Template -->
            <div class="space-y-4 pb-6 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 text-blue-600 font-semibold text-sm">1</div>
                    <h2 class="text-lg font-semibold">Siapkan daftar folder</h2>
                </div>
                <div class="ml-11">
                    <p class="text-slate-500 mb-4 text-sm">Unduh template Excel/CSV dan isi dengan nama-nama folder yang berisi file PDF.</p>
                    <a href="{{ route('pdf.template') }}" class="inline-flex items-center justify-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-medium rounded-lg text-slate-700 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Unduh Template
                    </a>
                </div>
            </div>

            <!-- Step 2: Form -->
            <form action="{{ route('pdf.merge') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 text-blue-600 font-semibold text-sm">2</div>
                        <h2 class="text-lg font-semibold">Proses Folder</h2>
                    </div>
                    
                    <div class="ml-11 space-y-4">
                        <!-- Root Path Input -->
                        <div>
                            <label for="root_path" class="block text-sm font-medium text-slate-700 mb-1">Lokasi Direktori Folder</label>
                            <input type="text" name="root_path" id="root_path" required
                                placeholder="C:\laragon\www\proyek-saya\storage\pdf"
                                class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2.5 border">
                            <p class="mt-1 text-xs text-slate-500">Path absolut (lengkap) di mana folder-folder berada.</p>
                        </div>

                        <!-- File Input -->
                        <div>
                            <label for="excel_file" class="block text-sm font-medium text-slate-700 mb-1">Upload Daftar Excel</label>
                            <input type="file" name="excel_file" id="excel_file" required accept=".xlsx,.xls,.csv"
                                class="block w-full text-sm text-slate-500
                                file:mr-4 file:py-2.5 file:px-4
                                file:rounded-lg file:border-0
                                file:text-sm file:font-semibold
                                file:bg-blue-50 file:text-blue-700
                                hover:file:bg-blue-100
                                transition-all">
                        </div>
                    </div>
                </div>

                <div class="pt-4 ml-11">
                    <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all transform hover:scale-[1.01]">
                        Proses PDF
                    </button>
                </div>
            </form>
        </div>

        <!-- Messages -->
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">Selesai</h3>
                        <div class="mt-2 text-sm text-green-700">
                            <p>{{ session('success') }}</p>
                            
                            @if(session('zip_path'))
                                <div class="mt-4 mb-6">
                                    <a href="{{ route('pdf.download.result', ['path' => session('zip_path')]) }}" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700 md:text-lg shadow-lg hover:shadow-xl transition-all">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                        Unduh Semua Hasil (ZIP)
                                    </a>
                                </div>
                            @endif

                            @if(session('results'))
                                <ul class="list-none pl-0 mt-4 space-y-3">
                                    @foreach(session('results') as $result)
                                        <li class="flex items-center justify-between p-3 rounded-lg border {{ $result['type'] === 'success' ? 'bg-green-50 border-green-100' : ($result['type'] === 'warning' ? 'bg-amber-50 border-amber-100' : 'bg-red-50 border-red-100') }}">
                                            <span class="{{ $result['type'] === 'success' ? 'text-green-800' : ($result['type'] === 'warning' ? 'text-amber-800' : 'text-red-800') }}">
                                                {{ $result['message'] }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Error</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <ul class="list-disc pl-5 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        
        @if(session('error'))
            <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                <div class="flex">
                     <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Error</h3>
                         <div class="mt-2 text-sm text-red-700">
                            {{ session('error') }}
                        </div>
                    </div>
                 </div>
            </div>
        @endif


    </div>
</body>
</html>
