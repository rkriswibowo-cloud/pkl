<?php
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'mahasiswa') {
    die("Akses ditolak.");
}

include '../config/db.php';
require_once '../includes/settings.php';
$id_mahasiswa = $_SESSION['user']['id'];

$kaprodi_nama = get_setting('kaprodi_nama', 'Rony Kriswibowo, S.Kom., M.Kom');
$kaprodi_nip  = get_setting('kaprodi_nip', '198801152020121005');

// Ambil data mahasiswa
$mahasiswa = $conn->query("
    SELECT u.nama, u.username, m.nim, m.jurusan 
    FROM users u 
    LEFT JOIN mahasiswa m ON u.id = m.id 
    WHERE u.id = '$id_mahasiswa'
")->fetch_assoc();

// PERBAIKAN: Ambil data dosen pembimbing dengan NIP
$bimbingan = $conn->query("
    SELECT 
        pkl.nama AS dospem_pkl, 
        pkl.username AS email_pkl,
        pkl_dosen.nip AS nip_pkl,
        lapangan.nama AS dospem_lapangan,
        lapangan.username AS email_lapangan,
        lapangan_dosen.nip AS nip_lapangan
    FROM bimbingan b
    LEFT JOIN users pkl ON b.id_dospem_pkl = pkl.id
    LEFT JOIN dosen pkl_dosen ON pkl.id = pkl_dosen.id
    LEFT JOIN users lapangan ON b.id_dospem_lapangan = lapangan.id
    LEFT JOIN dosen lapangan_dosen ON lapangan.id = lapangan_dosen.id
    WHERE b.id_mahasiswa = '$id_mahasiswa'
")->fetch_assoc();

// Jika tidak ada data bimbingan, set default values
if (!$bimbingan) {
    $bimbingan = [
        'dospem_pkl' => 'Belum ditentukan',
        'email_pkl' => '',
        'nip_pkl' => '-',
        'dospem_lapangan' => 'Belum ditentukan',
        'email_lapangan' => '',
        'nip_lapangan' => '-'
    ];
}

// Ambil logbook mahasiswa dengan filter tanggal (opsional)
$where = "id_mahasiswa = '$id_mahasiswa'";
if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $start_date = $conn->real_escape_string($_GET['start_date']);
    $where .= " AND tanggal >= '$start_date'";
}
if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $end_date = $conn->real_escape_string($_GET['end_date']);
    $where .= " AND tanggal <= '$end_date'";
}

$result = $conn->query("
    SELECT * FROM logbook 
    WHERE $where 
    ORDER BY tanggal ASC
");

// Hitung total jam kerja
$total_jam = 0;
$logbooks = [];
while ($log = $result->fetch_assoc()) {
    if (!empty($log['jam_datang']) && !empty($log['jam_pulang'])) {
        $jam_datang = strtotime($log['jam_datang']);
        $jam_pulang = strtotime($log['jam_pulang']);
        $total_jam += ($jam_pulang - $jam_datang) / 3600; // Konversi ke jam
    }
    $logbooks[] = $log;
}

// Konfigurasi DOMPDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);

// Buat HTML untuk PDF
$html = '
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Logbook PKL - ' . htmlspecialchars($mahasiswa['nama'] ?? 'Mahasiswa') . '</title>
    <style>
        @page { margin: 20px; }
        body { 
            font-family: Arial, sans-serif; 
            font-size: 10pt; 
            line-height: 1.4;
        }
        .header { 
            text-align: center; 
            margin-bottom: 25px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        .header h1 { 
            font-size: 16pt; 
            margin: 0 0 5px 0;
            color: #1e40af;
        }
        .header h2 { 
            font-size: 12pt; 
            margin: 0;
            font-weight: normal;
            color: #666;
        }
        table { 
            width: 100%; 
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 9pt;
        }
        th { 
            background-color: #1e40af;
            color: white;
            padding: 8px;
            text-align: left;
            border: 1px solid #1e3a8a;
            font-weight: bold;
        }
        td { 
            padding: 8px;
            border: 1px solid #d1d5db;
            vertical-align: top;
        }
        .status-approved { 
            background-color: #d1fae5; 
            color: #065f46;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 8pt;
            display: inline-block;
        }
        .status-pending { 
            background-color: #fef3c7; 
            color: #92400e;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 8pt;
            display: inline-block;
        }
        .status-rejected { 
            background-color: #fee2e2; 
            color: #991b1b;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 8pt;
            display: inline-block;
        }
        .unit-badge {
            background-color: #e0f2fe;
            color: #0369a1;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 8pt;
        }
        
        /* TTD HORIZONTAL */
        .ttd-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }
        .ttd-table td {
            border: none;
            padding: 5px;
            text-align: center;
            vertical-align: top;
        }
        .ttd-box {
            display: inline-block;
            text-align: center;
            padding: 0 15px;
            min-height: 100px;
        }
        .ttd-title {
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 9pt;
        }
        .ttd-line {
            margin-top: 60px;
            border-top: 1px solid #000;
            width: 100%;
            display: block;
        }
        .ttd-name {
            margin-top: 5px;
            font-weight: bold;
            font-size: 10pt;
        }
        .ttd-detail {
            margin-top: 3px;
            font-size: 8pt;
            color: #666;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #d1d5db;
            font-size: 8pt;
            color: #6b7280;
            text-align: center;
        }
        
        /* Info Mahasiswa */
        .info-table {
            width: 100%;
            border: none;
            margin-bottom: 20px;
        }
        .info-table td {
            border: none;
            padding: 5px;
            vertical-align: top;
        }
        .info-label {
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>LOGBOOK PRAKTIK KERJA LAPANGAN (PKL)</h1>
        <h2>Program Studi - ' . htmlspecialchars($mahasiswa['jurusan'] ?? 'Teknik Informatika') . '</h2>
        <h2>Periode: ' . date('F Y') . '</h2>
    </div>

    <!-- Informasi Mahasiswa -->
    <table class="info-table">
        <tr>
            <td width="50%">
                <span class="info-label">NAMA MAHASISWA:</span><br>
                ' . htmlspecialchars($mahasiswa['nama'] ?? '') . '
            </td>
            <td width="50%">
                <span class="info-label">NIM:</span><br>
                ' . htmlspecialchars($mahasiswa['nim'] ?? $mahasiswa['username']) . '
            </td>
        </tr>
        <tr>
            <td>
                <span class="info-label">DOSEN PEMBIMBING PKL:</span><br>
                ' . htmlspecialchars($bimbingan['dospem_pkl'] ?? 'Belum ditentukan') . '<br>
                
            </td>
            <td>
                <span class="info-label">DOSEN PEMBIMBING LAPANGAN:</span><br>
                ' . htmlspecialchars($bimbingan['dospem_lapangan'] ?? 'Belum ditentukan') . '<br>
                
            </td>
        </tr>
    </table>

    <!-- Tabel Logbook -->
    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="10%">Hari/Tanggal</th>
                <th width="15%">Unit Penempatan</th>
                <th width="10%">Jam Kerja</th>
                <th width="30%">Kegiatan & Hasil</th>
                <th width="10%">Status PKL</th>
                <th width="10%">Status Lapangan</th>
                <th width="10%">Catatan</th>
            </tr>
        </thead>
        <tbody>';

$counter = 1;
foreach ($logbooks as $log) {
    // Format jam
    $jam_datang = !empty($log['jam_datang']) ? date('H:i', strtotime($log['jam_datang'])) : '-';
    $jam_pulang = !empty($log['jam_pulang']) ? date('H:i', strtotime($log['jam_pulang'])) : '-';
    $jam_kerja = $jam_datang . ' - ' . $jam_pulang;
    
    // Format tanggal
    $hari_tanggal = $log['hari'] . '<br><small>' . date('d/m/Y', strtotime($log['tanggal'])) . '</small>';
    
    // Status PKL
    $status_pkl = $log['status_dospem_pkl'];
    $status_pkl_class = '';
    if ($status_pkl == 'disetujui') {
        $status_pkl_class = 'status-approved';
    } elseif ($status_pkl == 'ditolak') {
        $status_pkl_class = 'status-rejected';
    } else {
        $status_pkl_class = 'status-pending';
    }
    
    // Status Lapangan
    $status_lapangan = $log['status_dospem_lapangan'];
    $status_lapangan_class = '';
    if ($status_lapangan == 'disetujui') {
        $status_lapangan_class = 'status-approved';
    } elseif ($status_lapangan == 'ditolak') {
        $status_lapangan_class = 'status-rejected';
    } else {
        $status_lapangan_class = 'status-pending';
    }
    
    // Catatan
    $catatan = '';
    if (!empty($log['catatan_dospem_pkl'])) {
        $catatan .= '<strong>PKL:</strong> ' . substr(htmlspecialchars($log['catatan_dospem_pkl']), 0, 50) . '...<br>';
    }
    if (!empty($log['catatan_dospem_lapangan'])) {
        $catatan .= '<strong>Lap.:</strong> ' . substr(htmlspecialchars($log['catatan_dospem_lapangan']), 0, 50) . '...';
    }
    if (empty($catatan)) {
        $catatan = '-';
    }
    
    $html .= '
        <tr>
            <td align="center">' . $counter++ . '</td>
            <td>' . $hari_tanggal . '</td>
            <td><div class="unit-badge">' . htmlspecialchars($log['unit_penempatan'] ?? '-') . '</div></td>
            <td style="font-family: monospace;">' . $jam_kerja . '</td>
            <td>' . nl2br(htmlspecialchars($log['kegiatan'])) . '</td>
            <td align="center"><span class="' . $status_pkl_class . '">' . ucfirst($status_pkl) . '</span></td>
            <td align="center"><span class="' . $status_lapangan_class . '">' . ucfirst($status_lapangan) . '</span></td>
            <td><small>' . $catatan . '</small></td>
        </tr>';
}

$html .= '
        </tbody>
    </table>

    <!-- Summary -->
    <table style="margin-top: 20px; background-color: #f0f9ff; border: 1px solid #bae6fd;">
        <tr>
            <td style="border: none; text-align: center; padding: 15px;">
                <strong>Total Hari:</strong><br>
                <span style="font-size: 14pt; font-weight: bold;">' . count($logbooks) . '</span>
            </td>
            <td style="border: none; text-align: center; padding: 15px;">
                <strong>Total Jam Kerja:</strong><br>
                <span style="font-size: 14pt; font-weight: bold;">' . number_format($total_jam, 1) . ' jam</span>
            </td>
            <td style="border: none; text-align: center; padding: 15px;">
                <strong>Tanggal Cetak:</strong><br>
                <span style="font-size: 14pt; font-weight: bold;">' . date('d/m/Y') . '</span>
            </td>
        </tr>
    </table>

    <!-- Tanda Tangan: 2 Pembimbing di Atas, Kaprodi di Bawah Tengah -->
    <table class="ttd-table" style="margin-top: 30px;">
        <tr>
            <!-- Kolom 1: Pembimbing Lapangan -->
            <td style="width: 50%;">
                <div class="ttd-box">
                    <div class="ttd-title">Menyetujui,</div>
                    <div>Pembimbing Lapangan</div>
                    <div class="ttd-line"></div>
                    <div class="ttd-name">' . htmlspecialchars($bimbingan['dospem_lapangan'] ?? '-') . '</div>
                    <div class="ttd-detail">NIP/NIK: ' . htmlspecialchars($bimbingan['nip_lapangan'] ?? '-') . '</div>
                </div>
            </td>
            
            <!-- Kolom 2: Dosen Pembimbing PKL -->
            <td style="width: 50%;">
                <div class="ttd-box">
                    <div class="ttd-title">Menyetujui,</div>
                    <div>Dosen Pembimbing PKL</div>
                    <div class="ttd-line"></div>
                    <div class="ttd-name">' . htmlspecialchars($bimbingan['dospem_pkl'] ?? '-') . '</div>
                    <div class="ttd-detail">NIP: ' . htmlspecialchars($bimbingan['nip_pkl'] ?? '-') . '</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Baris 2: Mengetahui, Kaprodi di Tengah Bawah -->
    <table class="ttd-table" style="margin-top: 20px;">
        <tr>
            <td align="center" style="width: 100%;">
                <div class="ttd-box" style="width: 320px;">
                    <div class="ttd-title">Mengetahui,</div>
                    <div>Ketua Program Studi ' . htmlspecialchars($mahasiswa['jurusan'] ?? '') . '</div>
                    <div class="ttd-line"></div>
                    <div class="ttd-name">' . htmlspecialchars($kaprodi_nama) . '</div>
                    <div class="ttd-detail">NIP: ' . htmlspecialchars($kaprodi_nip) . '</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <div class="footer">
        <p>Dokumen ini dicetak secara otomatis dari Sistem Logbook PKL</p>
        <p>© ' . date('Y') . ' - Universitas Anwar Medika - Hak Cipta Dilindungi</p>
    </div>

</body>
</html>';

// Load HTML dan render PDF
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// Tambahkan nomor halaman
$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->get_font("helvetica", "normal");
$canvas->page_text(540, 810, "Halaman {PAGE_NUM} dari {PAGE_COUNT}", $font, 8, array(0,0,0));

// Output PDF
$dompdf->stream(
    "logbook_pkl_" . str_replace(' ', '_', $mahasiswa['nama'] ?? 'mahasiswa') . "_" . date('Y-m-d') . ".pdf",
    ["Attachment" => false]
);
exit();