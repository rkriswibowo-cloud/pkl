<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/prodi_helper.php';

// Tentukan ID Mahasiswa yang dicetak (bisa dari session mahasiswa, atau admin/dosen via $_GET['id_mahasiswa'])
$id_mahasiswa = 0;
if ($_SESSION['user']['role'] === 'mahasiswa') {
    $id_mahasiswa = (int)$_SESSION['user']['id'];
} elseif (isset($_GET['id_mahasiswa']) && in_array($_SESSION['user']['role'], ['admin', 'dosen_pkl', 'dosen_lapangan'])) {
    $id_mahasiswa = (int)$_GET['id_mahasiswa'];
} else {
    die("Akses tidak diizinkan.");
}

// Ambil data mahasiswa & pengesahan
$query = "
    SELECT 
        u.nama AS nama_mahasiswa,
        u.username AS email_mahasiswa,
        m.nim,
        COALESCE(NULLIF(m.prodi, ''), NULLIF(u.prodi, ''), 'Informatika') AS prodi,
        m.jurusan,
        p.*,
        pkl.nama AS dospem_pkl_nama,
        pkl_dosen.nip AS dospem_pkl_nip,
        lapangan.nama AS dospem_lapangan_nama,
        lapangan_dosen.nip AS dospem_lapangan_nip
    FROM users u
    LEFT JOIN mahasiswa m ON u.id = m.id
    LEFT JOIN pengesahan_pkl p ON u.id = p.id_mahasiswa
    LEFT JOIN bimbingan b ON u.id = b.id_mahasiswa
    LEFT JOIN users pkl ON b.id_dospem_pkl = pkl.id
    LEFT JOIN dosen pkl_dosen ON pkl.id = pkl_dosen.id
    LEFT JOIN users lapangan ON b.id_dospem_lapangan = lapangan.id
    LEFT JOIN dosen lapangan_dosen ON lapangan.id = lapangan_dosen.id
    WHERE u.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_mahasiswa);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data || empty($data['judul_laporan'])) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'>
        <h3>Lembar Pengesahan Belum Diisi / Belum Diajukan</h3>
        <p>Mahasiswa belum melengkapi formulir pengajuan pengesahan.</p>
        <button onclick='window.close()' style='padding:8px 16px; cursor:pointer;'>Tutup</button>
    </div>");
}

$app_logo = get_setting('app_logo', 'assets/images/dashboard-logo-uam.png');
$app_name = get_setting('app_name', 'Universitas Anwar Medika');
$kaprodi_info = get_kaprodi_by_prodi($conn, $data['prodi']);
$app_desc = $kaprodi_info['fakultas'] ?: get_setting('app_desc', 'Fakultas Sains dan Teknologi');
$kaprodi_nama = $data['nama_kaprodi'] ?: $kaprodi_info['nama_kaprodi'];
$kaprodi_nip  = $data['nip_kaprodi'] ?: $kaprodi_info['nidn_kaprodi'];
$kaprodi_ttd  = $data['ttd_kaprodi'] ?: $kaprodi_info['ttd_kaprodi'];

// Helper format tanggal Indonesia
function tgl_indo($tgl) {
    if (empty($tgl)) return '-';
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $pecahkan = explode('-', date('Y-m-d', strtotime($tgl)));
    return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lembar Pengesahan PKL - <?= htmlspecialchars($data['nama_mahasiswa']) ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 20mm 25mm 20mm 25mm;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000;
            background: #f8fafc;
            margin: 0;
            padding: 20px;
        }
        .page-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 40px 50px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-radius: 4px;
        }
        .kop-surat {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            border-bottom: 3px double #000;
            padding-bottom: 12px;
            margin-bottom: 25px;
            text-align: center;
        }
        .kop-logo {
            width: 75px;
            height: 75px;
            object-contain: contain;
        }
        .kop-text h2 {
            margin: 0;
            font-size: 15pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .kop-text h3 {
            margin: 3px 0;
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .kop-text p {
            margin: 0;
            font-size: 9pt;
            font-family: Arial, sans-serif;
            color: #333;
        }
        .judul-dokumen {
            text-align: center;
            margin-bottom: 25px;
        }
        .judul-dokumen h1 {
            font-size: 14pt;
            font-weight: bold;
            text-decoration: underline;
            margin: 0 0 5px 0;
            text-transform: uppercase;
        }
        .judul-dokumen p {
            margin: 0;
            font-size: 11pt;
            font-style: italic;
        }
        .tabel-identitas {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .tabel-identitas td {
            padding: 4px 0;
            vertical-align: top;
            font-size: 11pt;
        }
        .tabel-identitas td.label {
            width: 25%;
        }
        .tabel-identitas td.colon {
            width: 3%;
        }
        .tabel-identitas td.val {
            width: 72%;
            font-weight: 500;
        }
        .paragraf-pengesahan {
            text-align: justify;
            margin-bottom: 30px;
            font-size: 11pt;
            text-indent: 30px;
            line-height: 1.6;
        }
        
        /* Layout Tanda Tangan: 2 di atas bersisian, 1 Kaprodi di bawah tengah */
        .ttd-section {
            width: 100%;
            margin-top: 15px;
        }
        .ttd-row-top {
            display: flex;
            justify-content: space-between;
            margin-bottom: 35px;
        }
        .ttd-box {
            width: 45%;
            text-align: center;
            font-size: 11pt;
            display: inline-block;
            vertical-align: top;
        }
        .ttd-row-bottom {
            width: 100%;
            text-align: center;
            display: flex;
            justify-content: center;
        }
        .ttd-box-center {
            width: 60%;
            text-align: center;
            font-size: 11pt;
            margin: 0 auto;
        }
        .ttd-image-area {
            height: 75px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 5px 0;
        }
        .ttd-image-area img {
            max-height: 70px;
            max-width: 150px;
            object-fit: contain;
        }
        .ttd-nama {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 2px;
        }
        .ttd-nip {
            font-size: 10pt;
        }

        /* Action Bar (Hanya Tampil di Layar, Hilang Saat Print) */
        .no-print {
            max-width: 800px;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            background: #1e293b;
            color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-family: Arial, sans-serif;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            transition: 0.2s;
            text-decoration: none;
        }
        .btn-print {
            background: #2563eb;
            color: #fff;
        }
        .btn-print:hover { background: #1d4ed8; }
        .btn-back {
            background: #475569;
            color: #fff;
        }
        .btn-back:hover { background: #334155; }

        @media print {
            body {
                background: #fff;
                padding: 0;
                margin: 0;
            }
            .page-container {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Bar Aksi Tombol Cetak -->
    <div class="no-print">
        <div>
            <strong>Lembar Pengesahan Resmi PKL</strong> - Status: 
            <?php if ($data['status_dospem_pkl'] === 'disetujui' && $data['status_dospem_lapangan'] === 'disetujui' && $data['status_kaprodi'] === 'disetujui'): ?>
                <span style="color:#4ade80;">✔ Lengkap (3 Tanda Tangan)</span>
            <?php else: ?>
                <span style="color:#fde047;">⏳ Menunggu Kelengkapan Verifikasi</span>
            <?php endif; ?>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn-action btn-print">
                🖨 Cetak Dokumen / Simpan PDF
            </button>
            <button onclick="window.close()" class="btn-action btn-back">
                ✕ Tutup
            </button>
        </div>
    </div>

    <!-- Lembar Kertas Pengesahan -->
    <div class="page-container">

        <!-- Kop Surat -->
        <div class="kop-surat">
            <img src="../<?= htmlspecialchars($app_logo) ?>" alt="Logo" class="kop-logo">
            <div class="kop-text">
                <h2><?= htmlspecialchars($app_name) ?></h2>
                <h3><?= htmlspecialchars($app_desc) ?></h3>
                <p>Program Studi <?= htmlspecialchars($data['prodi']) ?></p>
                <p>Sistem Informasi Monitoring Praktik Kerja Lapangan (PKL)</p>
            </div>
        </div>

        <!-- Judul -->
        <div class="judul-dokumen">
            <h1>LEMBAR PENGESAHAN</h1>
            <p>LAPORAN PRAKTIK KERJA LAPANGAN (PKL)</p>
        </div>

        <!-- Data Mahasiswa & Laporan -->
        <table class="tabel-identitas">
            <tr>
                <td class="label">Judul Laporan PKL</td>
                <td class="colon">:</td>
                <td class="val" style="text-transform: uppercase;"><strong><?= htmlspecialchars($data['judul_laporan']) ?></strong></td>
            </tr>
            <tr>
                <td class="label">Nama Mahasiswa</td>
                <td class="colon">:</td>
                <td class="val"><?= htmlspecialchars($data['nama_mahasiswa']) ?></td>
            </tr>
            <tr>
                <td class="label">Nomor Induk Mahasiswa</td>
                <td class="colon">:</td>
                <td class="val"><?= htmlspecialchars($data['nim'] ?: '-') ?></td>
            </tr>
            <tr>
                <td class="label">Program Studi</td>
                <td class="colon">:</td>
                <td class="val"><?= htmlspecialchars($data['prodi']) ?></td>
            </tr>
            <tr>
                <td class="label">Tempat / Instansi PKL</td>
                <td class="colon">:</td>
                <td class="val"><?= htmlspecialchars($data['nama_instansi']) ?></td>
            </tr>
            <tr>
                <td class="label">Alamat Instansi</td>
                <td class="colon">:</td>
                <td class="val"><?= htmlspecialchars($data['alamat_instansi'] ?: '-') ?></td>
            </tr>
            <tr>
                <td class="label">Waktu Pelaksanaan</td>
                <td class="colon">:</td>
                <td class="val">
                    <?= tgl_indo($data['tgl_mulai']) ?> s.d. <?= tgl_indo($data['tgl_selesai']) ?>
                </td>
            </tr>
        </table>

        <!-- Kalimat Pernyataan Pengesahan -->
        <p class="paragraf-pengesahan">
            Laporan Praktik Kerja Lapangan (PKL) ini telah diperiksa, disetujui, dan disahkan sebagai salah satu syarat kelulusan mata kuliah Praktik Kerja Lapangan (PKL) pada Program Studi <?= htmlspecialchars($data['prodi']) ?>.
        </p>

        <!-- Blok 3 Tanda Tangan (2 Pembimbing di Atas, Kaprodi di Bawah Tengah) -->
        <div class="ttd-section">
            
            <!-- Baris 1 (Atas): Pembimbing Lapangan (Kiri) dan Dosen Pembimbing PKL (Kanan) -->
            <div class="ttd-row-top">
                <!-- 1. Pembimbing Lapangan -->
                <div class="ttd-box">
                    <div>Menyetujui,</div>
                    <div style="font-weight: bold;">Pembimbing Lapangan</div>
                    <div class="ttd-image-area">
                        <?php if (!empty($data['ttd_dospem_lapangan'])): ?>
                            <img src="../<?= htmlspecialchars($data['ttd_dospem_lapangan']) ?>" alt="TTD Pembimbing Lapangan">
                        <?php endif; ?>
                    </div>
                    <div class="ttd-nama">
                        <?= htmlspecialchars($data['dospem_lapangan_nama'] ?: 'Pembimbing Lapangan') ?>
                    </div>
                    <div class="ttd-nip">
                        NIP/NIK: <?= htmlspecialchars($data['dospem_lapangan_nip'] ?: '-') ?>
                    </div>
                </div>

                <!-- 2. Dosen Pembimbing PKL -->
                <div class="ttd-box">
                    <div>Menyetujui,</div>
                    <div style="font-weight: bold;">Dosen Pembimbing PKL</div>
                    <div class="ttd-image-area">
                        <?php if (!empty($data['ttd_dospem_pkl'])): ?>
                            <img src="../<?= htmlspecialchars($data['ttd_dospem_pkl']) ?>" alt="TTD Dospem PKL">
                        <?php endif; ?>
                    </div>
                    <div class="ttd-nama">
                        <?= htmlspecialchars($data['dospem_pkl_nama'] ?: 'Dosen Pembimbing') ?>
                    </div>
                    <div class="ttd-nip">
                        NIP: <?= htmlspecialchars($data['dospem_pkl_nip'] ?: '-') ?>
                    </div>
                </div>
            </div>

            <!-- Baris 2 (Bawah): Ketua Program Studi (Tengah) -->
            <div class="ttd-row-bottom">
                <div class="ttd-box-center">
                    <div>Mengetahui,</div>
                    <div style="font-weight: bold;">Ketua Program Studi <?= htmlspecialchars($data['prodi']) ?></div>
                    <div class="ttd-image-area">
                        <?php if (!empty($kaprodi_ttd)): ?>
                            <img src="../<?= htmlspecialchars($kaprodi_ttd) ?>" alt="TTD Kaprodi">
                        <?php endif; ?>
                    </div>
                    <div class="ttd-nama">
                        <?= htmlspecialchars($kaprodi_nama) ?>
                    </div>
                    <div class="ttd-nip">
                        NIDN / NIP: <?= htmlspecialchars($kaprodi_nip ?: '-') ?>
                    </div>
                </div>
            </div>

        </div>

    </div>

</body>
</html>

