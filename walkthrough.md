# Walkthrough - PDF Merger Application

The application has been successfully installed and verified. It allows you to merge multiple PDFs from specified folders into single files based on an Excel list.

## How to Use

1.  **Open the Application**
    Visit [http://aplikasi-sep.test](http://aplikasi-sep.test) (or your local development URL).

2.  **Download Template**
    - Click "Download Template" on the homepage.
    - You will get `folder_list_template.csv`.
    - Open it and list the *names* of the folders you want to process (e.g., `january_reports`, `invoice_data`).

3.  **Organize Folders**
    Ensure your folders are all in one root directory. For example:
    ```
    C:\MyDocuments\All_Data\
       ├── january_reports\   (contains .pdf files)
       ├── invoice_data\      (contains .pdf files)
    ```

4.  **Proses**
    - **Lokasi Direktori Folder**: tempel path lengkap ke folder induk Anda (misal `C:\MyDocuments\Data_Saya`).
    - **Upload Daftar Excel**: Upload file CSV/Excel yang sudah diisi.
    - Klik **Proses PDF**.

## Hasil
Aplikasi akan memindai setiap folder yang terdaftar.
-   **Output Utama**: Jika berhasil, akan muncul tombol besar **"Unduh Semua Hasil (ZIP)"**.
-   File PDF yang digabungkan akan dikemas dalam satu file ZIP untuk kemudahan pengunduhan.

## Verification Results
- **PDF Library**: Validated that the system can generate and merge PDFs correctly.
- **Dependencies**: `maatwebsite/excel` and `setasign/fpdi` are installed and functioning.
- **Routes**: Web interface is active at `/`.
