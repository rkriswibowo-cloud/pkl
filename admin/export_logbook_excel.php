<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'superadmin', 'dosen_pkl'])) {
    http_response_code(403);
    die("Akses ditolak.");
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';

$current_role = $_SESSION['user']['role'];
$user_id      = (int)$_SESSION['user']['id'];

$format       = strtolower($_GET['format'] ?? 'excel');
$start_date   = trim($_GET['start_date'] ?? '');
$end_date     = trim($_GET['end_date'] ?? '');
$filter_prodi = trim($_GET['prodi'] ?? '');
$filter_mhs   = (int)($_GET['mahasiswa'] ?? 0);
$filter_stat  = trim($_GET['status'] ?? '');

$app_name = get_setting('app_name', 'Universitas Anwar Medika');
$app_desc = get_setting('app_desc', 'Fakultas Sains dan Teknologi');

$where = ["1=1"];
$params = [];
$types  = "";

// Jika dosen_pkl, hanya mahasiswa bimbingannya
if ($current_role === 'dosen_pkl') {
    $where[] = "b.id_dospem_pkl = ?";
    $params[] = $user_id;
    $types .= "i";
}

if (!empty($start_date)) {
    $where[] = "l.tanggal >= ?";
    $params[] = $start_date;
    $types .= "s";
}

if (!empty($end_date)) {
    $where[] = "l.tanggal <= ?";
    $params[] = $end_date;
    $types .= "s";
}

if (!empty($filter_prodi)) {
    $where[] = "(m.prodi = ? OR u.prodi = ?)";
    $params[] = $filter_prodi;
    $params[] = $filter_prodi;
    $types .= "ss";
}

if ($filter_mhs > 0) {
    $where[] = "l.id_mahasiswa = ?";
    $params[] = $filter_mhs;
    $types .= "i";
}

if (!empty($filter_stat)) {
    if ($filter_stat === 'disetujui') {
        $where[] = "(l.status_dospem_pkl = 'disetujui' AND l.status_dospem_lapangan = 'disetujui')";
    } elseif ($filter_stat === 'pending') {
        $where[] = "(l.status_dospem_pkl = 'pending' OR l.status_dospem_lapangan = 'pending')";
    } elseif ($filter_stat === 'ditolak') {
        $where[] = "(l.status_dospem_pkl = 'ditolak' OR l.status_dospem_lapangan = 'ditolak')";
    }
}

$sql = "
    SELECT 
        l.*,
        u.nama AS nama_mahasiswa,
        u.username AS email_mahasiswa,
        m.nim,
        COALESCE(NULLIF(m.prodi, ''), NULLIF(u.prodi, ''), '-') AS prodi,
        COALESCE(NULLIF(l.unit_penempatan, ''), mp.nama_perusahaan, '-') AS tempat_pkl
    FROM logbook l
    JOIN users u ON l.id_mahasiswa = u.id
    LEFT JOIN mahasiswa m ON u.id = m.id
    LEFT JOIN bimbingan b ON u.id = b.id_mahasiswa
    LEFT JOIN penempatan_pkl pen ON u.id = pen.id_mahasiswa
    LEFT JOIN mitra_pkl mp ON pen.id_mitra = mp.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY l.tanggal DESC, u.nama ASC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$data_logbook = $stmt->get_result();

$timestamp = date('Ymd_His');

if ($format === 'csv') {
    $filename = "rekap_logbook_pkl_{$timestamp}.csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, [
        'No',
        'NIM',
        'Nama Mahasiswa',
        'Program Studi',
        'Tempat PKL',
        'Hari',
        'Tanggal',
        'Jam Masuk',
        'Jam Pulang',
        'Uraian Kegiatan',
        'Status Dospem Kampus',
        'Catatan Dospem Kampus',
        'Status Pembimbing Lapangan',
        'Catatan Pembimbing Lapangan'
    ]);

    $no = 1;
    while ($row = $data_logbook->fetch_assoc()) {
        fputcsv($output, [
            $no++,
            $row['nim'] ? "'".$row['nim'] : '-',
            $row['nama_mahasiswa'],
            $row['prodi'],
            $row['tempat_pkl'],
            $row['hari'],
            date('d/m/Y', strtotime($row['tanggal'])),
            $row['jam_datang'] ?: '-',
            $row['jam_pulang'] ?: '-',
            strip_tags($row['kegiatan']),
            strtoupper($row['status_dospem_pkl']),
            $row['catatan_dospem_pkl'] ?: '-',
            strtoupper($row['status_dospem_lapangan']),
            $row['catatan_dospem_lapangan'] ?: '-'
        ]);
    }

    fclose($output);
    exit();
}

// FORMAT EXCEL (.xls)
$filename = "rekap_logbook_pkl_{$timestamp}.xls";
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
    <x:Name>Rekap Logbook PKL</x:Name>
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
    th { background-color: #0f766e; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #000000; padding: 8px; vertical-align: middle; }
    td { border: 1px solid #000000; padding: 6px 8px; vertical-align: middle; }
    .text-center { text-align: center; }
    .text-left { text-align: left; }
    .text-str { mso-number-format:"\@"; }
    .kegiatan { width: 350px; text-align: left; }
    .status-disetujui { background-color: #dcfce7; color: #166534; font-weight: bold; }
    .status-pending { background-color: #fef9c3; color: #854d0e; }
    .status-ditolak { background-color: #fee2e2; color: #991b1b; font-weight: bold; }
</style>
</head>
<body>

<div class="title"><?= htmlspecialchars(strtoupper($app_name)) ?></div>
<div class="subtitle"><?= htmlspecialchars($app_desc) ?> - SISTEM LOGBOOK PRAKTIK KERJA LAPANGAN</div>
<div class="subtitle" style="font-weight: bold; margin-top: 5px;">REKAPITULASI AKTIVITAS LOGBOOK HARIAN PKL</div>
<div class="subtitle" style="font-size: 9pt; margin-bottom: 15px;">
    Tanggal Ekspor: <?= date('d F Y, H:i') ?> WIB
    <?= !empty($start_date) && !empty($end_date) ? " | Periode: $start_date s.d $end_date" : '' ?>
    <?= !empty($filter_prodi) ? ' | Prodi: ' . htmlspecialchars($filter_prodi) : '' ?>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 40px;">No</th>
            <th style="width: 110px;">NIM</th>
            <th style="width: 200px;">Nama Mahasiswa</th>
            <th style="width: 150px;">Program Studi</th>
            <th style="width: 180px;">Tempat PKL</th>
            <th style="width: 80px;">Hari</th>
            <th style="width: 100px;">Tanggal</th>
            <th style="width: 80px;">Jam Kerja</th>
            <th style="width: 380px;">Uraian Kegiatan</th>
            <th style="width: 110px;">Verifikasi Kampus</th>
            <th style="width: 160px;">Catatan Kampus</th>
            <th style="width: 110px;">Verifikasi Lapangan</th>
            <th style="width: 160px;">Catatan Lapangan</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        while ($row = $data_logbook->fetch_assoc()):
            $cls_kampus = 'status-' . $row['status_dospem_pkl'];
            $cls_lapangan = 'status-' . $row['status_dospem_lapangan'];
        ?>
        <tr>
            <td class="text-center"><?= $no++ ?></td>
            <td class="text-str text-center"><?= htmlspecialchars($row['nim'] ?: '-') ?></td>
            <td class="text-left font-bold"><?= htmlspecialchars($row['nama_mahasiswa']) ?></td>
            <td class="text-left"><?= htmlspecialchars($row['prodi']) ?></td>
            <td class="text-left"><?= htmlspecialchars($row['tempat_pkl']) ?></td>
            <td class="text-center"><?= htmlspecialchars($row['hari']) ?></td>
            <td class="text-center"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
            <td class="text-center"><?= ($row['jam_datang'] && $row['jam_pulang']) ? substr($row['jam_datang'],0,5) . ' - ' . substr($row['jam_pulang'],0,5) : '-' ?></td>
            <td class="kegiatan"><?= nl2br(htmlspecialchars($row['kegiatan'])) ?></td>
            <td class="text-center <?= $cls_kampus ?>"><?= strtoupper($row['status_dospem_pkl']) ?></td>
            <td class="text-left"><?= htmlspecialchars($row['catatan_dospem_pkl'] ?: '-') ?></td>
            <td class="text-center <?= $cls_lapangan ?>"><?= strtoupper($row['status_dospem_lapangan']) ?></td>
            <td class="text-left"><?= htmlspecialchars($row['catatan_dospem_lapangan'] ?: '-') ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<br>
<p style="font-size: 10pt; color: #555;">Total <?= $no - 1 ?> baris catatan logbook.</p>

</body>
</html>
