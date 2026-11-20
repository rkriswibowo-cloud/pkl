<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/prodi_helper.php';
require_once __DIR__ . '/../includes/assessment_helper.php';

// Tentukan ID Mahasiswa yang dicetak
$user_role = $_SESSION['user']['role'];
$id_mahasiswa = 0;

if ($user_role === 'mahasiswa') {
    $id_mahasiswa = (int)$_SESSION['user']['id'];
} elseif (isset($_GET['id_mahasiswa']) && in_array($user_role, ['admin', 'superadmin', 'dosen_pkl', 'dosen_lapangan'])) {
    $id_mahasiswa = (int)$_GET['id_mahasiswa'];
} else {
    die("Akses ditolak. Parameter mahasiswa tidak ditemukan.");
}

// Ambil data lengkap mahasiswa, bimbingan, mitra penempatan, penilaian, dan pengesahan
$query = "
    SELECT 
        u.id AS id_user,
        u.nama AS nama_mahasiswa,
        u.username AS email_mahasiswa,
        m.nim,
        COALESCE(NULLIF(m.prodi, ''), NULLIF(u.prodi, ''), 'Informatika') AS prodi,
        m.jurusan,
        pen.tanggal_mulai,
        pen.tanggal_selesai,
        pen.nama_pembimbing_lapangan AS nama_pbl_penempatan,
        pen.jabatan_pembimbing AS jabatan_pbl,
        mp.nama_perusahaan,
        mp.alamat AS alamat_perusahaan,
        mp.kota AS kota_perusahaan,
        pkl.nama AS dospem_pkl_nama,
        pkl_dosen.nip AS dospem_pkl_nip,
        lapangan.nama AS dospem_lapangan_nama,
        lapangan_dosen.nip AS dospem_lapangan_nip,
        p.*,
        peng.ttd_dospem_pkl,
        peng.ttd_dospem_lapangan,
        peng.judul_laporan
    FROM users u
    LEFT JOIN mahasiswa m ON u.id = m.id
    LEFT JOIN bimbingan b ON u.id = b.id_mahasiswa
    LEFT JOIN users pkl ON b.id_dospem_pkl = pkl.id
    LEFT JOIN dosen pkl_dosen ON pkl.id = pkl_dosen.id
    LEFT JOIN users lapangan ON b.id_dospem_lapangan = lapangan.id
    LEFT JOIN dosen lapangan_dosen ON lapangan.id = lapangan_dosen.id
    LEFT JOIN penempatan_pkl pen ON u.id = pen.id_mahasiswa
    LEFT JOIN mitra_pkl mp ON pen.id_mitra = mp.id
    LEFT JOIN penilaian_pkl p ON u.id = p.id_mahasiswa
    LEFT JOIN pengesahan_pkl peng ON u.id = peng.id_mahasiswa
    WHERE u.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_mahasiswa);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Data mahasiswa tidak ditemukan.");
}

// Pengaturan Institusi & Logo
$app_logo = get_setting('app_logo', 'assets/images/dashboard-logo-uam.png');
$app_name = get_setting('app_name', 'Universitas Anwar Medika');
$kaprodi_info = get_kaprodi_by_prodi($conn, $data['prodi']);
$app_desc = $kaprodi_info['fakultas'] ?: get_setting('app_desc', 'Fakultas Sains dan Teknologi');
$kaprodi_nama = $kaprodi_info['nama_kaprodi'] ?: 'Ketua Program Studi';
$kaprodi_nip  = $kaprodi_info['nidn_kaprodi'] ?: '-';
$kaprodi_ttd  = $kaprodi_info['ttd_kaprodi'] ?: null;

// Perhitungan Nilai
$has_lapangan = ($data['nilai_disiplin_lapangan'] !== null);
$rata_lapangan = $has_lapangan ? hitung_rata_rata_lapangan(
    (float)$data['nilai_disiplin_lapangan'],
    (float)$data['nilai_teknis_lapangan'],
    (float)$data['nilai_etika_lapangan'],
    (float)$data['nilai_kerjasama_lapangan']
) : null;

$has_kampus = ($data['nilai_laporan_kampus'] !== null);
$nilai_kampus = $has_kampus ? hitung_nilai_kampus(
    (float)$data['nilai_laporan_kampus'],
    (float)$data['nilai_materi_kampus'],
    (float)$data['nilai_bimbingan_kampus']
) : null;

$bobot_lapangan = $rata_lapangan !== null ? round($rata_lapangan * 0.60, 2) : null;
$bobot_kampus   = $nilai_kampus !== null ? round($nilai_kampus * 0.40, 2) : null;

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
    <title>Lembar Penilaian PKL - <?= htmlspecialchars($data['nama_mahasiswa']) ?></title>
    <link rel="icon" type="image/x-icon" href="../<?= htmlspecialchars(get_setting('app_favicon', 'assets/images/favicon.ico')) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 20mm 15mm 20mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.35;
            color: #000;
            background: #f1f5f9;
            margin: 0;
            padding: 20px;
        }
        .page-container {
            max-width: 820px;
            margin: 0 auto;
            background: #fff;
            padding: 35px 45px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-radius: 4px;
        }
        
        /* Action Bar */
        .action-bar {
            max-width: 820px;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-family: system-ui, -apple-system, sans-serif;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.2s;
        }
        .btn-print {
            background: #2563eb;
            color: #fff;
        }
        .btn-print:hover {
            background: #1d4ed8;
        }
        .btn-back {
            background: #fff;
            color: #475569;
            border-color: #cbd5e1;
        }
        .btn-back:hover {
            background: #f8fafc;
        }

        /* Kop Surat */
        .kop-surat {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding-bottom: 10px;
            border-bottom: 3px double #000;
            margin-bottom: 18px;
            text-align: center;
        }
        .kop-logo {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 70px;
            height: 70px;
            object-fit: contain;
        }
        .kop-teks h2 {
            font-size: 14pt;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .kop-teks h3 {
            font-size: 12pt;
            font-weight: bold;
            margin: 2px 0;
            text-transform: uppercase;
        }
        .kop-teks p {
            font-size: 9.5pt;
            margin: 0;
            font-style: italic;
        }

        /* Judul Dokumen */
        .judul-dokumen {
            text-align: center;
            margin-bottom: 15px;
        }
        .judul-dokumen h1 {
            font-size: 13pt;
            font-weight: bold;
            text-decoration: underline;
            margin: 0;
            text-transform: uppercase;
        }
        .judul-dokumen p {
            font-size: 10pt;
            margin: 3px 0 0 0;
            font-weight: bold;
        }

        /* Tabel Identitas */
        .tabel-identitas {
            width: 100%;
            margin-bottom: 15px;
            font-size: 10.5pt;
            border-collapse: collapse;
        }
        .tabel-identitas td {
            padding: 2.5px 0;
            vertical-align: top;
        }
        .tabel-identitas .label {
            width: 170px;
        }
        .tabel-identitas .colon {
            width: 15px;
            text-align: center;
        }

        /* Tabel Penilaian */
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            margin: 12px 0 6px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .tabel-nilai {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 10pt;
        }
        .tabel-nilai th, .tabel-nilai td {
            border: 1px solid #000;
            padding: 5px 8px;
        }
        .tabel-nilai th {
            background-color: #f1f5f9;
            text-align: center;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }

        /* Rekapitulasi Akhir */
        .rekap-card {
            border: 1.5px solid #000;
            padding: 10px 14px;
            margin-top: 12px;
            margin-bottom: 20px;
            background-color: #fafafa;
        }
        .rekap-grid {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Tanda Tangan */
        .ttd-section {
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .ttd-row-top {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .ttd-box {
            width: 45%;
            text-align: center;
        }
        .ttd-row-bottom {
            display: flex;
            justify-content: center;
        }
        .ttd-box-center {
            width: 55%;
            text-align: center;
        }
        .ttd-image-area {
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 4px 0;
        }
        .ttd-image-area img {
            max-height: 65px;
            max-width: 180px;
            object-fit: contain;
        }
        .ttd-nama {
            font-weight: bold;
            text-decoration: underline;
        }
        .ttd-nip {
            font-size: 10pt;
        }

        /* Media Print */
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .action-bar {
                display: none !important;
            }
            .page-container {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Tombol Aksi Layar -->
    <div class="action-bar">
        <a href="javascript:window.history.back()" class="btn btn-back">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()" class="btn btn-print">
                <i class="fas fa-print"></i> Cetak / Simpan PDF
            </button>
        </div>
    </div>

    <div class="page-container">

        <!-- Kop Surat -->
        <div class="kop-surat">
            <?php if (!empty($app_logo) && file_exists(__DIR__ . '/../' . $app_logo)): ?>
                <img src="../<?= htmlspecialchars($app_logo) ?>" alt="Logo" class="kop-logo">
            <?php endif; ?>
            <div class="kop-teks">
                <h2><?= htmlspecialchars($app_name) ?></h2>
                <h3><?= htmlspecialchars($app_desc) ?></h3>
                <p>Program Studi <?= htmlspecialchars($data['prodi']) ?></p>
            </div>
        </div>

        <!-- Judul Dokumen -->
        <div class="judul-dokumen">
            <h1>LEMBAR PENILAIAN PRAKTIK KERJA LAPANGAN</h1>
            <p>Tahun Akademik <?= date('Y') ?>/<?= date('Y')+1 ?></p>
        </div>

        <!-- Identitas Mahasiswa -->
        <table class="tabel-identitas">
            <tr>
                <td class="label">Nama Mahasiswa</td>
                <td class="colon">:</td>
                <td class="val font-bold"><?= htmlspecialchars($data['nama_mahasiswa']) ?></td>
            </tr>
            <tr>
                <td class="label">Nomor Induk Mahasiswa (NIM)</td>
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
                <td class="val"><?= htmlspecialchars($data['nama_perusahaan'] ?: ($data['nama_instansi'] ?? '-')) ?></td>
            </tr>
            <tr>
                <td class="label">Waktu Pelaksanaan</td>
                <td class="colon">:</td>
                <td class="val">
                    <?php if ($data['tanggal_mulai'] && $data['tanggal_selesai']): ?>
                        <?= tgl_indo($data['tanggal_mulai']) ?> s.d. <?= tgl_indo($data['tanggal_selesai']) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <!-- 1. Penilaian Pembimbing Lapangan -->
        <div class="section-title">
            <span>A. Penilaian Kinerja Lapangan (Bobot 60%)</span>
            <span style="font-size: 9.5pt; font-weight: normal;">Pembimbing: <?= htmlspecialchars($data['dospem_lapangan_nama'] ?: '-') ?></span>
        </div>
        <table class="tabel-nilai">
            <thead>
                <tr>
                    <th style="width: 35px;">No</th>
                    <th>Komponen Penilaian Lapangan</th>
                    <th style="width: 85px;">Bobot</th>
                    <th style="width: 95px;">Nilai (0-100)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center">1</td>
                    <td>Kedisiplinan, Kehadiran & Ketepatan Waktu</td>
                    <td class="text-center">20%</td>
                    <td class="text-center font-bold"><?= $data['nilai_disiplin_lapangan'] !== null ? number_format($data['nilai_disiplin_lapangan'], 1) : '-' ?></td>
                </tr>
                <tr>
                    <td class="text-center">2</td>
                    <td>Keterampilan Teknis & Kualitas Hasil Kerja</td>
                    <td class="text-center">40%</td>
                    <td class="text-center font-bold"><?= $data['nilai_teknis_lapangan'] !== null ? number_format($data['nilai_teknis_lapangan'], 1) : '-' ?></td>
                </tr>
                <tr>
                    <td class="text-center">3</td>
                    <td>Etika, Sikap & Tanggung Jawab Kerja</td>
                    <td class="text-center">20%</td>
                    <td class="text-center font-bold"><?= $data['nilai_etika_lapangan'] !== null ? number_format($data['nilai_etika_lapangan'], 1) : '-' ?></td>
                </tr>
                <tr>
                    <td class="text-center">4</td>
                    <td>Inisiatif, Komunikasi & Kerjasama Tim</td>
                    <td class="text-center">20%</td>
                    <td class="text-center font-bold"><?= $data['nilai_kerjasama_lapangan'] !== null ? number_format($data['nilai_kerjasama_lapangan'], 1) : '-' ?></td>
                </tr>
                <tr style="background: #fafafa;">
                    <td colspan="3" class="text-right font-bold">Rata-rata Nilai Lapangan (N<sub>1</sub>) :</td>
                    <td class="text-center font-bold" style="font-size: 11pt;"><?= $rata_lapangan !== null ? number_format($rata_lapangan, 2) : '-' ?></td>
                </tr>
            </tbody>
        </table>
        <?php if (!empty($data['catatan_lapangan'])): ?>
            <p style="font-size: 9.5pt; font-style: italic; margin: -4px 0 10px 0;">Catatan Pembimbing Lapangan: "<?= htmlspecialchars($data['catatan_lapangan']) ?>"</p>
        <?php endif; ?>

        <!-- 2. Penilaian Dosen Pembimbing Kampus -->
        <div class="section-title">
            <span>B. Penilaian Akademik & Laporan Kampus (Bobot 40%)</span>
            <span style="font-size: 9.5pt; font-weight: normal;">Dosen: <?= htmlspecialchars($data['dospem_pkl_nama'] ?: '-') ?></span>
        </div>
        <table class="tabel-nilai">
            <thead>
                <tr>
                    <th style="width: 35px;">No</th>
                    <th>Komponen Penilaian Akademik</th>
                    <th style="width: 85px;">Bobot</th>
                    <th style="width: 95px;">Nilai (0-100)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center">1</td>
                    <td>Sistematika & Mutu Penulisan Laporan Akhir PKL</td>
                    <td class="text-center">40%</td>
                    <td class="text-center font-bold"><?= $data['nilai_laporan_kampus'] !== null ? number_format($data['nilai_laporan_kampus'], 1) : '-' ?></td>
                </tr>
                <tr>
                    <td class="text-center">2</td>
                    <td>Penguasaan Materi & Presentasi / Ujian PKL</td>
                    <td class="text-center">30%</td>
                    <td class="text-center font-bold"><?= $data['nilai_materi_kampus'] !== null ? number_format($data['nilai_materi_kampus'], 1) : '-' ?></td>
                </tr>
                <tr>
                    <td class="text-center">3</td>
                    <td>Keaktifan & Kedisiplinan Proses Bimbingan</td>
                    <td class="text-center">30%</td>
                    <td class="text-center font-bold"><?= $data['nilai_bimbingan_kampus'] !== null ? number_format($data['nilai_bimbingan_kampus'], 1) : '-' ?></td>
                </tr>
                <tr style="background: #fafafa;">
                    <td colspan="3" class="text-right font-bold">Nilai Akhir Bimbingan Kampus (N<sub>2</sub>) :</td>
                    <td class="text-center font-bold" style="font-size: 11pt;"><?= $nilai_kampus !== null ? number_format($nilai_kampus, 2) : '-' ?></td>
                </tr>
            </tbody>
        </table>
        <?php if (!empty($data['catatan_kampus'])): ?>
            <p style="font-size: 9.5pt; font-style: italic; margin: -4px 0 10px 0;">Catatan Dosen Pembimbing: "<?= htmlspecialchars($data['catatan_kampus']) ?>"</p>
        <?php endif; ?>

        <!-- 3. Rekapitulasi Nilai Akhir -->
        <div class="rekap-card">
            <div class="rekap-grid">
                <div>
                    <div style="font-size: 10pt; color: #444;">Perhitungan Nilai Akhir (NA):</div>
                    <div style="font-size: 10.5pt; font-weight: bold; margin-top: 2px;">
                        NA = (60% &times; <?= $rata_lapangan !== null ? number_format($rata_lapangan, 2) : '0' ?>) + (40% &times; <?= $nilai_kampus !== null ? number_format($nilai_kampus, 2) : '0' ?>)
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 9.5pt; color: #444;">NILAI AKHIR :</div>
                    <div style="font-size: 18pt; font-weight: bold; color: #0f172a; line-height: 1.1;">
                        <?= $data['nilai_akhir'] !== null ? number_format($data['nilai_akhir'], 2) : '-' ?>
                        <span style="font-size: 13pt; margin-left: 4px;">(<?= htmlspecialchars($data['nilai_huruf'] ?: '-') ?>)</span>
                    </div>
                    <div style="font-size: 9.5pt; font-weight: bold; color: <?= $data['status_kelulusan'] === 'lulus' ? '#16a34a' : ($data['status_kelulusan'] === 'tidak_lulus' ? '#dc2626' : '#d97706') ?>;">
                        STATUS: <?= strtoupper($data['status_kelulusan'] === 'lulus' ? 'LULUS' : ($data['status_kelulusan'] === 'tidak_lulus' ? 'TIDAK LULUS' : 'BELUM LENGKAP')) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Blok 3 Tanda Tangan (2 Pembimbing di Atas, Kaprodi di Bawah Tengah) -->
        <div class="ttd-section">
            <div class="ttd-row-top">
                <!-- Pembimbing Lapangan -->
                <div class="ttd-box">
                    <div>Sidoarjo, <?= date('d') ?> <?= tgl_indo(date('Y-m-d')) ?></div>
                    <div style="font-weight: bold; margin-top: 2px;">Pembimbing Lapangan,</div>
                    <div class="ttd-image-area">
                        <?php if (!empty($data['ttd_dospem_lapangan'])): ?>
                            <img src="../<?= htmlspecialchars($data['ttd_dospem_lapangan']) ?>" alt="TTD">
                        <?php endif; ?>
                    </div>
                    <div class="ttd-nama">
                        <?= htmlspecialchars($data['dospem_lapangan_nama'] ?: ($data['nama_pbl_penempatan'] ?: 'Pembimbing Lapangan')) ?>
                    </div>
                    <div class="ttd-nip">
                        NIP/NIK: <?= htmlspecialchars($data['dospem_lapangan_nip'] ?: '-') ?>
                    </div>
                </div>

                <!-- Dosen Pembimbing PKL Kampus -->
                <div class="ttd-box">
                    <div>Sidoarjo, <?= date('d') ?> <?= tgl_indo(date('Y-m-d')) ?></div>
                    <div style="font-weight: bold; margin-top: 2px;">Dosen Pembimbing PKL,</div>
                    <div class="ttd-image-area">
                        <?php if (!empty($data['ttd_dospem_pkl'])): ?>
                            <img src="../<?= htmlspecialchars($data['ttd_dospem_pkl']) ?>" alt="TTD">
                        <?php endif; ?>
                    </div>
                    <div class="ttd-nama">
                        <?= htmlspecialchars($data['dospem_pkl_nama'] ?: 'Dosen Pembimbing PKL') ?>
                    </div>
                    <div class="ttd-nip">
                        NIP: <?= htmlspecialchars($data['dospem_pkl_nip'] ?: '-') ?>
                    </div>
                </div>
            </div>

            <!-- Mengetahui Kaprodi -->
            <div class="ttd-row-bottom">
                <div class="ttd-box-center">
                    <div>Mengetahui,</div>
                    <div style="font-weight: bold; margin-top: 2px;">Ketua Program Studi <?= htmlspecialchars($data['prodi']) ?></div>
                    <div class="ttd-image-area">
                        <?php if (!empty($kaprodi_ttd)): ?>
                            <img src="../<?= htmlspecialchars($kaprodi_ttd) ?>" alt="TTD Kaprodi">
                        <?php endif; ?>
                    </div>
                    <div class="ttd-nama">
                        <?= htmlspecialchars($kaprodi_nama) ?>
                    </div>
                    <div class="ttd-nip">
                        NIDN: <?= htmlspecialchars($kaprodi_nip ?: '-') ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

</body>
</html>
