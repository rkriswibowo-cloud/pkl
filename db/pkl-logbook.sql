-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 11 Bulan Mei 2025 pada 18.35
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pkl-logbook`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `bimbingan`
--

CREATE TABLE `bimbingan` (
  `id` int(11) NOT NULL,
  `id_mahasiswa` int(11) NOT NULL,
  `id_dospem_pkl` int(11) DEFAULT NULL,
  `id_dospem_lapangan` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `bimbingan`
--

INSERT INTO `bimbingan` (`id`, `id_mahasiswa`, `id_dospem_pkl`, `id_dospem_lapangan`) VALUES
(2, 11, 12, 15),
(3, 17, 12, 8),
(4, 10, 12, 8);

-- --------------------------------------------------------

--
-- Struktur dari tabel `dosen`
--

CREATE TABLE `dosen` (
  `id` int(11) NOT NULL,
  `nip` varchar(20) DEFAULT NULL,
  `bidang` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `logbook`
--

CREATE TABLE `logbook` (
  `id` int(11) NOT NULL,
  `id_mahasiswa` int(11) DEFAULT NULL,
  `hari` varchar(20) DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `kegiatan` text DEFAULT NULL,
  `status_dospem_pkl` enum('pending','disetujui','ditolak') DEFAULT 'pending',
  `status_dospem_lapangan` enum('pending','disetujui','ditolak') DEFAULT 'pending',
  `catatan_dospem_pkl` text DEFAULT NULL,
  `catatan_dospem_lapangan` text DEFAULT NULL,
  `ttd_dospem_pkl` varchar(255) DEFAULT NULL,
  `ttd_dospem_lapangan` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mahasiswa`
--

CREATE TABLE `mahasiswa` (
  `id` int(11) NOT NULL,
  `nim` varchar(20) DEFAULT NULL,
  `jurusan` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','mahasiswa','dosen_pkl','dosen_lapangan') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remember_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `nama`, `username`, `password`, `role`, `created_at`, `remember_token`) VALUES
(8, 'Sugeng Widodo, S.M', 'widodo@gmail.com', '$2y$10$QtJmukODX5T9EYmj3c204uYgy3CrLSf8ZkrTneYNEHOwIyC.BEG8C', 'dosen_lapangan', '2025-05-11 07:05:53', NULL),
(9, 'admin', 'admin@gmail.com', '$2y$10$hXe4IoeeNsip7xbJqM1Uuuiy8213XGFzGU86co.i5Ls7IF.6aySPi', 'admin', '2025-05-11 07:07:51', NULL),
(10, 'citra', 'citra@gmail.com', '$2y$10$xK77dH80Vul7whrMc1GOC.AFj5Kmr31vSsn9DIdysPhN2HNBOA/Sq', 'mahasiswa', '2025-05-11 07:08:55', NULL),
(11, 'Arjuna', 'mhs1@gmail.com', '$2y$10$ORJoDiF6/IaP2bCXM3ZKJ.6IHOCA35MTmdhDpGYzEWGa3gKazXHHu', 'mahasiswa', '2025-05-11 08:06:47', NULL),
(12, 'Rony Kriswibowo, S.Kom., M.Kom', 'rkriswibowo@gmail.com', '$2y$10$AfCBBADGOTc6YiMa5B1AjeaxGK2E/vAw/4Dy6aPqsGb5KwLYjhWfe', 'dosen_pkl', '2025-05-11 10:22:31', NULL),
(14, 'Danuditya, M.Or', 'danu@gmail.com', '$2y$10$fQUWm6CGbeF1B69gH9Et/ODGZU./Z91W4bAxG.2pRo34tV0SsZoAC', 'dosen_pkl', '2025-05-11 11:14:42', NULL),
(15, 'Agung Teguh Setyadi, S.Kom., M.Kom', 'agung@gmail.com', '$2y$10$CvmJPORrOusivmgrjv8/seho3I7asaekTDy.1YWnxjzE/3xxURDzy', 'dosen_lapangan', '2025-05-11 11:16:43', NULL),
(17, 'tony', 'tony@gmail.com', '$2y$10$tEtKmj6IG7TD/Jv5zMJojuInUGgbzqDb52C4PQCZFFCZUE0YjkxKm', 'mahasiswa', '2025-05-11 13:29:00', NULL);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `bimbingan`
--
ALTER TABLE `bimbingan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_mahasiswa` (`id_mahasiswa`),
  ADD KEY `id_dospem_pkl` (`id_dospem_pkl`),
  ADD KEY `id_dospem_lapangan` (`id_dospem_lapangan`);

--
-- Indeks untuk tabel `dosen`
--
ALTER TABLE `dosen`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `logbook`
--
ALTER TABLE `logbook`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_mahasiswa` (`id_mahasiswa`);

--
-- Indeks untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `bimbingan`
--
ALTER TABLE `bimbingan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `logbook`
--
ALTER TABLE `logbook`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `bimbingan`
--
ALTER TABLE `bimbingan`
  ADD CONSTRAINT `bimbingan_ibfk_1` FOREIGN KEY (`id_mahasiswa`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bimbingan_ibfk_2` FOREIGN KEY (`id_dospem_pkl`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bimbingan_ibfk_3` FOREIGN KEY (`id_dospem_lapangan`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `dosen`
--
ALTER TABLE `dosen`
  ADD CONSTRAINT `dosen_ibfk_1` FOREIGN KEY (`id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `logbook`
--
ALTER TABLE `logbook`
  ADD CONSTRAINT `logbook_ibfk_1` FOREIGN KEY (`id_mahasiswa`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD CONSTRAINT `mahasiswa_ibfk_1` FOREIGN KEY (`id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
