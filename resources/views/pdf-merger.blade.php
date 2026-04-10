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
                            <div class="flex flex-col sm:flex-row gap-2">
                                <input type="text" name="root_path" id="root_path" required
                                    placeholder="Contoh: Z:\ atau C:\DataPDF atau \\192.168.1.10\share"
                                    oninput="resetPathStatus()"
                                    class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2.5 border">
                                <button type="button" id="btnCheckPath" onclick="checkDrivePath()" class="whitespace-nowrap inline-flex items-center justify-center px-4 py-2.5 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Cek Drive
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Path absolut (lengkap). Contoh: <code>Z:\</code> atau <code>D:\DataPdf</code> atau <code>\\192.168.1.10\Share</code></p>

                            <!-- Status Panel -->
                            <div id="pathStatusPanel" class="hidden mt-3 p-3 rounded-lg border text-sm">
                                <div class="flex items-start gap-2">
                                    <span id="pathStatusIcon" class="text-lg leading-none mt-0.5"></span>
                                    <div class="flex-1">
                                        <p id="pathStatusMsg" class="font-medium"></p>
                                        <p id="pathStatusDetail" class="text-xs mt-1 text-slate-500"></p>
                                    </div>
                                </div>
                            </div>
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

                        <!-- Sorting Options -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                            <div>
                                <label for="sort_by" class="block text-sm font-medium text-slate-700 mb-1">Urutan Berdasarkan</label>
                                <select name="sort_by" id="sort_by" class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2.5 border">
                                    <option value="default">Default (Apa adanya)</option>
                                    <option value="name">Nama File</option>
                                    <option value="date">Tanggal File</option>
                                </select>
                            </div>
                            <div>
                                <label for="sort_order" class="block text-sm font-medium text-slate-700 mb-1">Mode Urutan</label>
                                <select name="sort_order" id="sort_order" class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2.5 border">
                                    <option value="asc">Ascend (Kecil ke Besar)</option>
                                    <option value="desc">Descend (Besar ke Kecil)</option>
                                </select>
                            </div>
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

    <!-- Directory Picker Modal -->
    <div id="dirPickerModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex justify-center items-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[85vh] flex flex-col overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="text-lg font-semibold text-slate-800">Pilih Folder Server</h3>
                <button type="button" onclick="closeDirectoryPicker()" class="text-slate-400 hover:text-red-500 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div class="p-4 border-b border-slate-100 flex gap-2 items-center">
                <button type="button" id="btnUpDir" onclick="goUpDirectory()" class="p-2 border border-slate-200 rounded-md text-slate-600 hover:bg-slate-100 hover:text-slate-900 disabled:opacity-30 disabled:hover:bg-transparent" title="Naik satu tingkat">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                </button>
                <div class="flex-1">
                    <input type="text" id="currentPickerPath" readonly class="w-full text-sm p-2 border border-slate-200 rounded-md bg-white text-slate-800 focus:outline-none">
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-2 min-h-[300px]" id="dirListContainer">
                <!-- Data will be loaded here -->
            </div>

            <div class="px-5 py-4 border-t border-slate-100 flex justify-end gap-3 bg-slate-50">
                <button type="button" onclick="closeDirectoryPicker()" class="px-4 py-2 border border-slate-300 text-sm font-medium rounded-lg text-slate-700 bg-white hover:bg-slate-50">Batal</button>
                <button type="button" onclick="selectCurrentDirectory()" class="px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700">Gunakan Folder Ini</button>
            </div>
        </div>
    </div>

    <script>
        let currentParentPath = null;
        let currentActivePath = '';

        function resetPathStatus() {
            const panel = document.getElementById('pathStatusPanel');
            panel.classList.add('hidden');
        }

        async function checkDrivePath() {
            const input = document.getElementById('root_path');
            const path = input.value.trim();
            if (!path) {
                showPathStatus(false, 'Kolom path tidak boleh kosong.', '');
                return;
            }

            const btn = document.getElementById('btnCheckPath');
            btn.disabled = true;
            btn.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Mengecek...';

            try {
                const url = "{{ route('pdf.check.path') }}?path=" + encodeURIComponent(path);
                const resp = await fetch(url);
                const data = await resp.json();

                if (data.ok) {
                    let detail = `Path: ${data.path}`;
                    if (data.folder_count >= 0) detail += ` · ${data.folder_count} folder di dalamnya`;
                    if (data.free_gb !== null) detail += ` · Sisa disk: ${data.free_gb} GB dari ${data.total_gb} GB`;
                    showPathStatus(true, data.message, detail);
                } else {
                    showPathStatus(false, data.message, data.path ? `Path yang dicoba: ${data.path}` : '');
                }
            } catch (e) {
                showPathStatus(false, 'Gagal menghubungi server. Coba lagi.', e.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Cek Drive';
            }
        }

        function showPathStatus(ok, msg, detail) {
            const panel = document.getElementById('pathStatusPanel');
            const icon  = document.getElementById('pathStatusIcon');
            const msgEl = document.getElementById('pathStatusMsg');
            const detailEl = document.getElementById('pathStatusDetail');

            panel.classList.remove('hidden', 'bg-green-50', 'border-green-200', 'bg-red-50', 'border-red-200');
            icon.classList.remove('text-green-600', 'text-red-600');
            msgEl.classList.remove('text-green-700', 'text-red-700');

            if (ok) {
                panel.classList.add('bg-green-50', 'border-green-200');
                icon.classList.add('text-green-600');
                icon.textContent = '✅';
                msgEl.classList.add('text-green-700');
            } else {
                panel.classList.add('bg-red-50', 'border-red-200');
                icon.classList.add('text-red-600');
                icon.textContent = '❌';
                msgEl.classList.add('text-red-700');
            }

            msgEl.textContent = msg;
            detailEl.textContent = detail;
        }

        function openDirectoryPicker() {
            document.getElementById('dirPickerModal').classList.remove('hidden');
            let initialPath = document.getElementById('root_path').value || '';
            loadDirectory(initialPath);
        }

        function closeDirectoryPicker() {
            document.getElementById('dirPickerModal').classList.add('hidden');
        }

        function selectCurrentDirectory() {
            document.getElementById('root_path').value = currentActivePath;
            closeDirectoryPicker();
        }

        function goUpDirectory() {
            if (currentParentPath !== null) {
                loadDirectory(currentParentPath);
            }
        }

        async function loadDirectory(path) {
            const container = document.getElementById('dirListContainer');
            const inputPath = document.getElementById('currentPickerPath');
            const btnUp = document.getElementById('btnUpDir');
            
            container.innerHTML = '<div class="flex justify-center p-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>';
            
            try {
                const url = "{{ route('pdf.browse.directories') }}?path=" + encodeURIComponent(path);
                const response = await fetch(url);
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.error || 'Terjadi kesalahan pada server');
                }

                currentActivePath = data.current_path;
                currentParentPath = data.parent_path;
                
                inputPath.value = currentActivePath || 'Daftar Disk Drives (PC)';
                
                btnUp.disabled = (currentParentPath === null);

                if (data.directories.length === 0) {
                    container.innerHTML = '<div class="text-center p-8 text-slate-500 text-sm">Folder kosong atau Anda masuk ke folder yang tidak ada Isinya.</div>';
                    return;
                }

                let html = '<ul class="space-y-1">';
                data.directories.forEach(dir => {
                    // Escape paths safely for JavaScript string interpolations
                    const escapedPath = dir.path.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                    
                    html += `
                        <li>
                            <button type="button" onclick="loadDirectory('${escapedPath}')" class="w-full flex items-center text-left p-2.5 hover:bg-blue-50 rounded-lg text-sm text-slate-700 transition duration-150 group">
                                <svg class="w-6 h-6 mr-3 text-yellow-400 group-hover:text-yellow-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                                <span class="truncate block w-full whitespace-nowrap">${dir.name}</span>
                            </button>
                        </li>
                    `;
                });
                html += '</ul>';
                container.innerHTML = html;

            } catch (error) {
                container.innerHTML = `<div class="p-4 m-2 bg-red-50 text-red-600 rounded text-sm border border-red-200">${error.message}</div>`;
            }
        }
    </script>
</body>
</html>
