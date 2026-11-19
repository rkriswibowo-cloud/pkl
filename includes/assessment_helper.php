<?php
/**
 * Helper Perhitungan Penilaian PKL
 */

if (!function_exists('hitung_rata_rata_lapangan')) {
    function hitung_rata_rata_lapangan($disiplin, $teknis, $etika, $kerjasama) {
        if ($disiplin === null || $teknis === null || $etika === null || $kerjasama === null) {
            return null;
        }
        return round(($disiplin + $teknis + $etika + $kerjasama) / 4, 2);
    }
}

if (!function_exists('hitung_nilai_kampus')) {
    function hitung_nilai_kampus($laporan, $materi, $bimbingan) {
        if ($laporan === null || $materi === null || $bimbingan === null) {
            return null;
        }
        return round(($laporan * 0.40) + ($materi * 0.30) + ($bimbingan * 0.30), 2);
    }
}

if (!function_exists('hitung_nilai_akhir_pkl')) {
    function hitung_nilai_akhir_pkl($rata_lapangan, $rata_kampus) {
        if ($rata_lapangan === null || $rata_kampus === null) {
            return null;
        }
        return round(($rata_lapangan * 0.60) + ($rata_kampus * 0.40), 2);
    }
}

if (!function_exists('konversi_huruf_mutu')) {
    function konversi_huruf_mutu($nilai) {
        if ($nilai === null) return '-';
        if ($nilai >= 85) return 'A';
        if ($nilai >= 80) return 'A-';
        if ($nilai >= 75) return 'B+';
        if ($nilai >= 70) return 'B';
        if ($nilai >= 65) return 'B-';
        if ($nilai >= 60) return 'C+';
        if ($nilai >= 55) return 'C';
        if ($nilai >= 40) return 'D';
        return 'E';
    }
}

if (!function_exists('tentukan_status_kelulusan')) {
    function tentukan_status_kelulusan($nilai_akhir) {
        if ($nilai_akhir === null) return 'belum_selesai';
        return ($nilai_akhir >= 60) ? 'lulus' : 'tidak_lulus';
    }
}

if (!function_exists('sinkronkan_nilai_akhir')) {
    function sinkronkan_nilai_akhir($conn, $id_mahasiswa) {
        $stmt = $conn->prepare("SELECT * FROM penilaian_pkl WHERE id_mahasiswa = ?");
        $stmt->bind_param("i", $id_mahasiswa);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $has_lapangan = ($row['nilai_disiplin_lapangan'] !== null && $row['nilai_teknis_lapangan'] !== null && $row['nilai_etika_lapangan'] !== null && $row['nilai_kerjasama_lapangan'] !== null);
            $has_kampus   = ($row['nilai_laporan_kampus'] !== null && $row['nilai_materi_kampus'] !== null && $row['nilai_bimbingan_kampus'] !== null);

            $rata_lapangan = $has_lapangan ? hitung_rata_rata_lapangan(
                (float)$row['nilai_disiplin_lapangan'],
                (float)$row['nilai_teknis_lapangan'],
                (float)$row['nilai_etika_lapangan'],
                (float)$row['nilai_kerjasama_lapangan']
            ) : null;

            $rata_kampus = $has_kampus ? hitung_nilai_kampus(
                (float)$row['nilai_laporan_kampus'],
                (float)$row['nilai_materi_kampus'],
                (float)$row['nilai_bimbingan_kampus']
            ) : null;

            if ($rata_lapangan !== null && $rata_kampus !== null) {
                $nilai_akhir = hitung_nilai_akhir_pkl($rata_lapangan, $rata_kampus);
                $huruf       = konversi_huruf_mutu($nilai_akhir);
                $status      = tentukan_status_kelulusan($nilai_akhir);
            } else {
                $nilai_akhir = null;
                $huruf       = null;
                $status      = 'belum_selesai';
            }

            $upd = $conn->prepare("UPDATE penilaian_pkl SET nilai_akhir = ?, nilai_huruf = ?, status_kelulusan = ? WHERE id_mahasiswa = ?");
            $upd->bind_param("dssi", $nilai_akhir, $huruf, $status, $id_mahasiswa);
            $upd->execute();
        }
    }
}
