<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'superadmin'])) {
    http_response_code(403);
    die("Akses ditolak. Halaman ini hanya untuk admin.");
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/assessment_helper.php';
require_once __DIR__ . '/../includes/settings.php';

$format = strtolower($_GET['format'] ?? 'excel');
$search_query  = trim($_GET['search'] ?? '');
$filter_prodi  = trim($_GET['prodi'] ?? '');
$filter_status = trim($_GET['status'] ?? '');

$app_name = get_setting('app_name', 'Universitas Anwar Medika');
$app_desc = get_setting('app_desc', 'Fakultas Sains dan Teknologi');

// Query Data Nilai
$sql = "
    SELECT 
        u.id AS id_mahasiswa,
        u.nama,
        u.username AS email,
        m.nim,
        COALESCE(NULLIF(m.prodi, ''), NULLIF(u.prodi, ''), '-') AS prodi,
        pkl.nama AS nama_dospem_pkl,
        lapangan.nama AS nama_dospem_lapangan,
        mp.nama_perusahaan AS tempat_pkl,
        p.nilai_disiplin_lapangan,
        p.nilai_teknis_lapangan,
        p.nilai_etika_lapangan,
        p.nilai_kerjasama_lapangan,
        p.catatan_lapangan,
        p.tgl_nilai_lapangan,
        p.nilai_laporan_kampus,
        p.nilai_materi_kampus,
        p.nilai_bimbingan_kampus,
        p.catatan_kampus,
        p.tgl_nilai_kampus,
        p.nilai_akhir,
        p.nilai_huruf,
        COALESCE(p.status_kelulusan, 'belum_selesai') AS status_kelulusan
    FROM users u
    LEFT JOIN mahasiswa m ON u.id = m.id
    LEFT JOIN bimbingan b ON u.id = b.id_mahasiswa
    LEFT JOIN users pkl ON b.id_dospem_pkl = pkl.id
    LEFT JOIN users lapangan ON b.id_dospem_lapangan = lapangan.id
    LEFT JOIN penempatan_pkl pen ON u.id = pen.id_mahasiswa
    LEFT JOIN mitra_pkl mp ON pen.id_mitra = mp.id
    LEFT JOIN penilaian_pkl p ON u.id = p.id_mahasiswa
    WHERE u.role = 'mahasiswa'
";

$params = [];
$types  = "";

if (!empty($search_query)) {
    $sql .= " AND (u.nama LIKE ? OR m.nim LIKE ? OR u.username LIKE ?)";
    $like = "%$search_query%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

if (!empty($filter_prodi)) {
    $sql .= " AND (m.prodi = ? OR u.prodi = ?)";
    $params[] = $filter_prodi;
    $params[] = $filter_prodi;
    $types .= "ss";
}

if (!empty($filter_status)) {
    if ($filter_status === 'belum_selesai') {
        $sql .= " AND (p.status_kelulusan = 'belum_selesai' OR p.status_kelulusan IS NULL)";
    } else {
        $sql .= " AND p.status_kelulusan = ?";
        $params[] = $filter_status;
        $types .= "s";
    }
}

$sql .= " ORDER BY u.nama ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$data_nilai = $stmt->get_result();

$timestamp = date('Ymd_His');

if ($format === 'csv') {
    $filename = "rekap_nilai_pkl_siakad_{$timestamp}.csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // Output UTF-8 BOM agar terbaca akurat di Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header CSV Siap Import SIAKAD
    fputcsv($output, [
        'No',
        'NIM',
        'Nama Mahasiswa',
        'Program Studi',
        'Tempat PKL',
        'Dosen Pembimbing Kampus',
        'Pembimbing Lapangan',
        'Nilai Lapangan (60%)',
        'Nilai Kampus (40%)',
        'Nilai Akhir',
        'Huruf Mutu',
        'Status Kelulusan'
    ]);

    $no = 1;
    while ($row = $data_nilai->fetch_assoc()) {
        $has_lapangan = ($row['nilai_disiplin_lapangan'] !== null);
        $rata_lapangan = $has_lapangan ? hitung_rata_rata_lapangan(
            (float)$row['nilai_disiplin_lapangan'],
            (float)$row['nilai_teknis_lapangan'],
            (float)$row['nilai_etika_lapangan'],
            (float)$row['nilai_kerjasama_lapangan']
        ) : null;

        $has_kampus = ($row['nilai_laporan_kampus'] !== null);
        $nilai_kampus = $has_kampus ? hitung_nilai_kampus(
            (float)$row['nilai_laporan_kampus'],
            (float)$row['nilai_materi_kampus'],
            (float)$row['nilai_bimbingan_kampus']
        ) : null;

        $status_label = match($row['status_kelulusan']) {
            'lulus' => 'LULUS',
            'tidak_lulus' => 'TIDAK LULUS',
            default => 'BELUM LENGKAP'
        };

        fputcsv($output, [
            $no++,
            $row['nim'] ? "'".$row['nim'] : '-',
            $row['nama'],
            $row['prodi'],
            $row['tempat_pkl'] ?: '-',
            $row['nama_dospem_pkl'] ?: '-',
            $row['nama_dospem_lapangan'] ?: '-',
            $rata_lapangan !== null ? number_format($rata_lapangan, 2, '.', '') : '',
            $nilai_kampus !== null ? number_format($nilai_kampus, 2, '.', '') : '',
            $row['nilai_akhir'] !== null ? number_format($row['nilai_akhir'], 2, '.', '') : '',
            $row['nilai_huruf'] ?: '',
            $status_label
        ]);
    }

    fclose($output);
    exit();
}

// FORMAT EXCEL (.xls)
$filename = "rekap_nilai_pkl_{$timestamp}.xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<!--[if gte mso 9]>
<xml>
 <x:ExcelWorkbook>
  <x:ExcelWorksheets>
   <x:ExcelWorksheet>
    <x:Name>Rekap Nilai PKL</x:Name>
    <x:WorksheetOptions>
     <x:DisplayGridlines/>
    </x:WorksheetOptions>
   </x:ExcelWorksheet>
  </x:ExcelWorksheets>
 </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
    body { font-family: 'Calibri', Arial, sans-serif; font-size: 11pt; }
    .title { font-size: 14pt; font-weight: bold; text-align: center; }
    .subtitle { font-size: 11pt; text-align: center; color: #555555; }
    table { border-collapse: collapse; width: 100%; margin-top: 15px; }
    th { background-color: #1e3a8a; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #000000; padding: 8px; vertical-align: middle; }
    td { border: 1px solid #000000; padding: 6px 8px; vertical-align: middle; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    .text-str { mso-number-format:"\@"; }
    .num { mso-number-format:"0.00"; }
    .bg-green { background-color: #dcfce7; font-weight: bold; color: #166534; }
    .bg-red { background-color: #fee2e2; font-weight: bold; color: #991b1b; }
    .bg-yellow { background-color: #fef9c3; color: #854d0e; }
</style>
</head>
<body>

<div class="title"><?= htmlspecialchars(strtoupper($app_name)) ?></div>
<div class="subtitle"><?= htmlspecialchars($app_desc) ?> - SISTEM LOGBOOK PRAKTIK KERJA LAPANGAN</div>
<div class="subtitle" style="font-weight: bold; margin-top: 5px;">REKAPITULASI NILAI AKHIR PKL</div>
<div class="subtitle" style="font-size: 9pt; margin-bottom: 15px;">
    Tanggal Ekspor: <?= date('d F Y, H:i') ?> WIB
    <?= !empty($filter_prodi) ? ' | Program Studi: ' . htmlspecialchars($filter_prodi) : '' ?>
    <?= !empty($filter_status) ? ' | Status: ' . htmlspecialchars(ucfirst($filter_status)) : '' ?>
</div>

<table>
    <thead>
        <tr>
            <th rowspan="2" style="width: 40px;">No</th>
            <th rowspan="2" style="width: 120px;">NIM</th>
            <th rowspan="2" style="width: 220px;">Nama Mahasiswa</th>
            <th rowspan="2" style="width: 160px;">Program Studi</th>
            <th rowspan="2" style="width: 180px;">Tempat PKL</th>
            <th colspan="2" style="background-color: #2563eb;">Pembimbing</th>
            <th colspan="3" style="background-color: #059669;">Evaluasi Nilai</th>
            <th rowspan="2" style="width: 70px;">Huruf Mutu</th>
            <th rowspan="2" style="width: 110px;">Status</th>
        </tr>
        <tr>
            <th style="background-color: #1d4ed8; width: 180px;">Dosen Kampus</th>
            <th style="background-color: #1d4ed8; width: 180px;">Pembimbing Lapangan</th>
            <th style="background-color: #047857; width: 90px;">Lapangan (60%)</th>
            <th style="background-color: #047857; width: 90px;">Kampus (40%)</th>
            <th style="background-color: #047857; width: 90px;">Nilai Akhir</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        $total_lulus = 0;
        $total_tidak = 0;
        $total_belum = 0;

        while ($row = $data_nilai->fetch_assoc()):
            $has_lapangan = ($row['nilai_disiplin_lapangan'] !== null);
            $rata_lapangan = $has_lapangan ? hitung_rata_rata_lapangan(
                (float)$row['nilai_disiplin_lapangan'],
                (float)$row['nilai_teknis_lapangan'],
                (float)$row['nilai_etika_lapangan'],
                (float)$row['nilai_kerjasama_lapangan']
            ) : null;

            $has_kampus = ($row['nilai_laporan_kampus'] !== null);
            $nilai_kampus = $has_kampus ? hitung_nilai_kampus(
                (float)$row['nilai_laporan_kampus'],
                (float)$row['nilai_materi_kampus'],
                (float)$row['nilai_bimbingan_kampus']
            ) : null;

            $status_class = '';
            $status_label = 'Belum Lengkap';
            if ($row['status_kelulusan'] === 'lulus') {
                $status_class = 'bg-green';
                $status_label = 'LULUS';
                $total_lulus++;
            } elseif ($row['status_kelulusan'] === 'tidak_lulus') {
                $status_class = 'bg-red';
                $status_label = 'TIDAK LULUS';
                $total_tidak++;
            } else {
                $status_class = 'bg-yellow';
                $total_belum++;
            }
        ?>
        <tr>
            <td class="text-center"><?= $no++ ?></td>
            <td class="text-str text-center"><?= htmlspecialchars($row['nim'] ?: '-') ?></td>
            <td class="text-left font-bold"><?= htmlspecialchars($row['nama']) ?></td>
            <td class="text-left"><?= htmlspecialchars($row['prodi']) ?></td>
            <td class="text-left"><?= htmlspecialchars($row['tempat_pkl'] ?: '-') ?></td>
            <td class="text-left"><?= htmlspecialchars($row['nama_dospem_pkl'] ?: '-') ?></td>
            <td class="text-left"><?= htmlspecialchars($row['nama_dospem_lapangan'] ?: '-') ?></td>
            <td class="num text-center"><?= $rata_lapangan !== null ? number_format($rata_lapangan, 2) : '-' ?></td>
            <td class="num text-center"><?= $nilai_kampus !== null ? number_format($nilai_kampus, 2) : '-' ?></td>
            <td class="num text-center font-bold"><?= $row['nilai_akhir'] !== null ? number_format($row['nilai_akhir'], 2) : '-' ?></td>
            <td class="text-center font-bold"><?= htmlspecialchars($row['nilai_huruf'] ?: '-') ?></td>
            <td class="text-center <?= $status_class ?>"><?= $status_label ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<br>
<table style="width: 350px; border: none;">
    <tr style="border: none;"><td colspan="2" style="border: none; font-weight: bold;">Ringkasan Data:</td></tr>
    <tr style="border: none;"><td style="border: none;">Total Mahasiswa:</td><td style="border: none; font-weight: bold;"><?= $no - 1 ?></td></tr>
    <tr style="border: none;"><td style="border: none;">Lulus:</td><td style="border: none; font-weight: bold; color: green;"><?= $total_lulus ?></td></tr>
    <tr style="border: none;"><td style="border: none;">Tidak Lulus:</td><td style="border: none; font-weight: bold; color: red;"><?= $total_tidak ?></td></tr>
    <tr style="border: none;"><td style="border: none;">Belum Lengkap:</td><td style="border: none; font-weight: bold; color: orange;"><?= $total_belum ?></td></tr>
</table>

</body>
</html>
