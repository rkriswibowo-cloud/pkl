<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'mahasiswa') {
    header("Location: ../index.php"); exit();
}
require_once __DIR__ . '/../config/db.php';

$page_title   = 'Tempat PKL Saya - Mahasiswa';
$id_mahasiswa = (int)$_SESSION['user']['id'];

// Ambil data penempatan + mitra
$data = $conn->query("
    SELECT
        p.*,
        mp.nama_perusahaan, mp.alamat, mp.kota, mp.provinsi,
        mp.bidang_usaha, mp.nama_pic, mp.jabatan_pic,
        mp.no_telepon AS telp_kantor, mp.email AS email_kantor,
        mp.website, mp.status AS status_mitra
    FROM penempatan_pkl p
    JOIN mitra_pkl mp ON p.id_mitra = mp.id
    WHERE p.id_mahasiswa = $id_mahasiswa
    LIMIT 1
")->fetch_assoc();

// Hitung durasi PKL jika periode tersedia
$durasi_hari = null;
if ($data && $data['tanggal_mulai'] && $data['tanggal_selesai']) {
    $mulai = new DateTime($data['tanggal_mulai']);
    $sel   = new DateTime($data['tanggal_selesai']);
    $durasi_hari = (int)$mulai->diff($sel)->days;
}

// Status periode
$today  = date('Y-m-d');
$status_periode = 'belum_mulai';
if ($data && $data['tanggal_mulai'] && $data['tanggal_selesai']) {
    if ($today >= $data['tanggal_mulai'] && $today <= $data['tanggal_selesai']) $status_periode = 'berjalan';
    elseif ($today > $data['tanggal_selesai']) $status_periode = 'selesai';
    else $status_periode = 'belum_mulai';
}

include '../includes/header.php';
?>

<div class="min-w-0">
<div class="dashboard-page w-full max-w-full">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="dashboard-title text-2xl sm:text-3xl font-bold flex items-center gap-3">
                <i class="fas fa-map-pin text-green-600"></i> Tempat PKL Saya
            </h2>
            <p class="text-sm mt-1" style="color:var(--dashboard-muted);">
                Informasi detail tempat Praktik Kerja Lapangan dan kontak pembimbing Anda.
            </p>
        </div>
    </div>

    <?php if (!$data): ?>
        <div class="dashboard-card p-12 text-center">
            <div class="w-20 h-20 rounded-full bg-amber-100 text-amber-500 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-clock text-3xl"></i>
            </div>
            <h3 class="font-bold text-xl mb-2">Penempatan PKL Belum Ditetapkan</h3>
            <p class="text-sm max-w-sm mx-auto" style="color:var(--dashboard-muted);">
                Admin belum menetapkan tempat PKL untuk Anda. Silakan hubungi koordinator PKL atau admin untuk informasi lebih lanjut.
            </p>
        </div>
    <?php else: ?>

        <!-- Status Periode Banner -->
        <?php
        $status_cfg = [
            'belum_mulai' => ['bg-blue-50 border-blue-200 text-blue-700',   'fa-calendar-days',  'PKL Belum Dimulai'],
            'berjalan'    => ['bg-green-50 border-green-200 text-green-700', 'fa-circle-play',    'PKL Sedang Berjalan'],
            'selesai'     => ['bg-gray-50 border-gray-200 text-gray-600',    'fa-circle-check',   'PKL Telah Selesai'],
        ];
        [$s_cls, $s_icon, $s_text] = $status_cfg[$status_periode];
        ?>
        <div class="rounded-xl p-4 mb-6 flex items-center gap-3 border <?=$s_cls?>">
            <i class="fas <?=$s_icon?> text-xl shrink-0"></i>
            <div class="flex-1">
                <p class="font-semibold"><?=$s_text?></p>
                <?php if ($data['tanggal_mulai'] || $data['tanggal_selesai']): ?>
                    <p class="text-sm mt-0.5">
                        <?=($data['tanggal_mulai'] ? date('d M Y', strtotime($data['tanggal_mulai'])) : '?')?>
                        &rarr;
                        <?=($data['tanggal_selesai'] ? date('d M Y', strtotime($data['tanggal_selesai'])) : '?')?>
                        <?php if ($durasi_hari !== null): ?>
                            <span class="ml-2 text-xs opacity-75">(<?=$durasi_hari?> hari)</span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php if ($status_periode === 'berjalan' && $durasi_hari): ?>
                <?php
                $lewat = (int)(new DateTime($data['tanggal_mulai']))->diff(new DateTime($today))->days;
                $pct   = min(100, round($lewat / $durasi_hari * 100));
                ?>
                <div class="hidden sm:block w-32">
                    <p class="text-xs text-right mb-1 opacity-75">Progres <?=$pct?>%</p>
                    <div class="h-2 bg-white/60 rounded-full border">
                        <div class="h-full bg-green-500 rounded-full" style="width:<?=$pct?>%"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Info Perusahaan -->
            <div class="dashboard-card p-6">
                <h3 class="font-bold text-lg mb-4 flex items-center gap-2 pb-3 border-b" style="border-color:var(--dashboard-border);">
                    <i class="fas fa-building text-blue-500"></i> Informasi Perusahaan / Instansi
                </h3>
                <dl class="flex flex-col gap-3 text-sm">
                    <div>
                        <dt class="text-xs font-medium mb-0.5" style="color:var(--dashboard-muted);">Nama Perusahaan</dt>
                        <dd class="font-semibold text-base"><?=htmlspecialchars($data['nama_perusahaan'])?></dd>
                    </div>
                    <?php if ($data['bidang_usaha']): ?>
                    <div>
                        <dt class="text-xs font-medium mb-0.5" style="color:var(--dashboard-muted);">Bidang Usaha</dt>
                        <dd><?=htmlspecialchars($data['bidang_usaha'])?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($data['alamat'] || $data['kota']): ?>
                    <div>
                        <dt class="text-xs font-medium mb-0.5" style="color:var(--dashboard-muted);">Alamat</dt>
                        <dd>
                            <?php if ($data['alamat']): ?><?=htmlspecialchars($data['alamat'])?><?php endif; ?>
                            <?php if ($data['kota'] || $data['provinsi']): ?>
                                <span class="text-xs block mt-0.5" style="color:var(--dashboard-muted);">
                                    <?=htmlspecialchars(implode(', ', array_filter([$data['kota'], $data['provinsi']])))?>
                                </span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($data['telp_kantor']): ?>
                    <div>
                        <dt class="text-xs font-medium mb-0.5" style="color:var(--dashboard-muted);">Telepon Kantor</dt>
                        <dd><a href="tel:<?=htmlspecialchars($data['telp_kantor'])?>" class="text-blue-600 hover:underline">
                            <i class="fas fa-phone mr-1"></i><?=htmlspecialchars($data['telp_kantor'])?>
                        </a></dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($data['email_kantor']): ?>
                    <div>
                        <dt class="text-xs font-medium mb-0.5" style="color:var(--dashboard-muted);">Email</dt>
                        <dd><a href="mailto:<?=htmlspecialchars($data['email_kantor'])?>" class="text-blue-600 hover:underline">
                            <i class="fas fa-envelope mr-1"></i><?=htmlspecialchars($data['email_kantor'])?>
                        </a></dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($data['website']): ?>
                    <div>
                        <dt class="text-xs font-medium mb-0.5" style="color:var(--dashboard-muted);">Website</dt>
                        <dd><a href="<?=htmlspecialchars($data['website'])?>" target="_blank" class="text-blue-600 hover:underline">
                            <i class="fas fa-globe mr-1"></i><?=htmlspecialchars($data['website'])?>
                        </a></dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($data['nama_pic']): ?>
                    <div class="pt-2 mt-2 border-t" style="border-color:var(--dashboard-border);">
                        <dt class="text-xs font-medium mb-0.5" style="color:var(--dashboard-muted);">PIC / HRD Kampus</dt>
                        <dd class="font-medium"><?=htmlspecialchars($data['nama_pic'])?>
                            <?php if ($data['jabatan_pic']): ?>
                                <span class="text-xs font-normal" style="color:var(--dashboard-muted);"> — <?=htmlspecialchars($data['jabatan_pic'])?></span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>

            <!-- Info Pembimbing & Periode -->
            <div class="flex flex-col gap-5">
                <!-- Pembimbing Lapangan -->
                <div class="dashboard-card p-6">
                    <h3 class="font-bold text-lg mb-4 flex items-center gap-2 pb-3 border-b" style="border-color:var(--dashboard-border);">
                        <i class="fas fa-user-tie text-purple-500"></i> Pembimbing Lapangan
                    </h3>
                    <?php if ($data['nama_pembimbing_lapangan']): ?>
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center shrink-0 text-2xl">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-base"><?=htmlspecialchars($data['nama_pembimbing_lapangan'])?></p>
                                <?php if ($data['jabatan_pembimbing']): ?>
                                    <p class="text-sm" style="color:var(--dashboard-muted);"><?=htmlspecialchars($data['jabatan_pembimbing'])?></p>
                                <?php endif; ?>
                                <?php if ($data['no_telp_pembimbing']): ?>
                                    <a href="https://wa.me/<?=preg_replace('/[^0-9]/','',$data['no_telp_pembimbing'])?>"
                                       target="_blank"
                                       class="inline-flex items-center gap-1.5 mt-2 text-sm text-green-600 hover:text-green-800 bg-green-50 hover:bg-green-100 border border-green-200 px-3 py-1.5 rounded-lg transition">
                                        <i class="fab fa-whatsapp"></i>
                                        <?=htmlspecialchars($data['no_telp_pembimbing'])?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-sm" style="color:var(--dashboard-muted);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Data pembimbing lapangan belum ditetapkan.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Periode PKL -->
                <div class="dashboard-card p-6">
                    <h3 class="font-bold text-lg mb-4 flex items-center gap-2 pb-3 border-b" style="border-color:var(--dashboard-border);">
                        <i class="fas fa-calendar-alt text-green-500"></i> Periode PKL
                    </h3>
                    <div class="grid grid-cols-2 gap-4 text-center">
                        <div class="rounded-xl p-4 bg-green-50 border border-green-200">
                            <p class="text-xs font-medium text-green-600 mb-1">Mulai</p>
                            <p class="font-bold text-green-700">
                                <?=$data['tanggal_mulai'] ? date('d', strtotime($data['tanggal_mulai'])) : '-'?>
                            </p>
                            <p class="text-xs text-green-600">
                                <?=$data['tanggal_mulai'] ? date('M Y', strtotime($data['tanggal_mulai'])) : ''?>
                            </p>
                        </div>
                        <div class="rounded-xl p-4 bg-red-50 border border-red-200">
                            <p class="text-xs font-medium text-red-600 mb-1">Selesai</p>
                            <p class="font-bold text-red-700">
                                <?=$data['tanggal_selesai'] ? date('d', strtotime($data['tanggal_selesai'])) : '-'?>
                            </p>
                            <p class="text-xs text-red-600">
                                <?=$data['tanggal_selesai'] ? date('M Y', strtotime($data['tanggal_selesai'])) : ''?>
                            </p>
                        </div>
                    </div>
                    <?php if ($durasi_hari !== null): ?>
                        <p class="text-center text-sm mt-3 font-medium" style="color:var(--dashboard-muted);">
                            Total <strong><?=$durasi_hari?> hari</strong> PKL
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($data['keterangan']): ?>
                <div class="dashboard-card p-4">
                    <p class="text-xs font-medium mb-1" style="color:var(--dashboard-muted);">Keterangan</p>
                    <p class="text-sm"><?=nl2br(htmlspecialchars($data['keterangan']))?></p>
                </div>
                <?php endif; ?>
            </div>

        </div>
    <?php endif; ?>
</div>
</div>

<?php include '../includes/footer.php'; ?>

