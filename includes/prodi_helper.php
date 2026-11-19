<?php
/**
 * Helper Master Program Studi & Kaprodi
 * Sinkronisasi data dari proyek OBE (obe_cpl)
 */

if (!function_exists('get_all_prodi')) {
    function get_all_prodi($conn, $active_only = true) {
        $sql = "SELECT * FROM program_studi";
        if ($active_only) {
            $sql .= " WHERE status = 'ACTIVE'";
        }
        $sql .= " ORDER BY nama ASC";
        $result = $conn->query($sql);
        $list = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $list[] = $row;
            }
        }
        return $list;
    }
}

if (!function_exists('get_kaprodi_by_prodi')) {
    function get_kaprodi_by_prodi($conn, $prodi_name) {
        $prodi_name = trim($prodi_name ?? '');
        if (empty($prodi_name)) {
            return [
                'nama_kaprodi' => 'Rony Kriswibowo, S.Kom., M.Kom',
                'nidn_kaprodi' => '198801152020121005',
                'fakultas'     => 'Fakultas Sains dan Teknologi',
                'ttd_kaprodi'  => null,
                'prodi_nama'   => 'Program Studi'
            ];
        }

        $stmt = $conn->prepare("SELECT * FROM program_studi WHERE nama = ? OR kode = ? LIMIT 1");
        $stmt->bind_param("ss", $prodi_name, $prodi_name);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            return [
                'nama_kaprodi' => $row['nama_kaprodi'] ?: 'Ketua Program Studi',
                'nidn_kaprodi' => $row['nidn_kaprodi'] ?: '-',
                'fakultas'     => $row['fakultas'] ?: 'Fakultas Sains dan Teknologi',
                'ttd_kaprodi'  => $row['ttd_kaprodi'],
                'prodi_nama'   => $row['nama']
            ];
        }

        // Cari dengan LIKE jika nama prodi sedikit berbeda
        $like = "%$prodi_name%";
        $stmt2 = $conn->prepare("SELECT * FROM program_studi WHERE nama LIKE ? LIMIT 1");
        $stmt2->bind_param("s", $like);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($row2 = $res2->fetch_assoc()) {
            return [
                'nama_kaprodi' => $row2['nama_kaprodi'] ?: 'Ketua Program Studi',
                'nidn_kaprodi' => $row2['nidn_kaprodi'] ?: '-',
                'fakultas'     => $row2['fakultas'] ?: 'Fakultas Sains dan Teknologi',
                'ttd_kaprodi'  => $row2['ttd_kaprodi'],
                'prodi_nama'   => $row2['nama']
            ];
        }

        return [
            'nama_kaprodi' => 'Ketua Program Studi',
            'nidn_kaprodi' => '-',
            'fakultas'     => 'Fakultas Sains dan Teknologi',
            'ttd_kaprodi'  => null,
            'prodi_nama'   => $prodi_name
        ];
    }
}

if (!function_exists('sync_prodi_from_obe')) {
    function sync_prodi_from_obe($conn) {
        $obe_db = 'obe_cpl';
        // Pastikan tabel program_studi ada di pkl-logbook
        $conn->query("
            CREATE TABLE IF NOT EXISTS program_studi (
                id INT AUTO_INCREMENT PRIMARY KEY,
                kode VARCHAR(20) NOT NULL,
                nama VARCHAR(100) NOT NULL,
                jenjang VARCHAR(10) NOT NULL DEFAULT 'S1',
                fakultas VARCHAR(100) NOT NULL DEFAULT 'Fakultas Sains dan Teknologi',
                nama_kaprodi VARCHAR(150) DEFAULT NULL,
                nidn_kaprodi VARCHAR(50) DEFAULT NULL,
                ttd_kaprodi VARCHAR(255) DEFAULT NULL,
                status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Sinkronisasi data master dari obe_cpl
        $sync_query = "
            INSERT INTO program_studi (id, kode, nama, jenjang, fakultas, nama_kaprodi, nidn_kaprodi, status)
            SELECT id, kode, nama, jenjang, fakultas, nama_kaprodi, nidn_kaprodi, status
            FROM {$obe_db}.program_studi
            ON DUPLICATE KEY UPDATE 
                kode = VALUES(kode),
                nama = VALUES(nama),
                jenjang = VALUES(jenjang),
                fakultas = VALUES(fakultas),
                nama_kaprodi = VALUES(nama_kaprodi),
                nidn_kaprodi = VALUES(nidn_kaprodi),
                status = VALUES(status);
        ";

        if ($conn->query($sync_query)) {
            return ['status' => true, 'message' => 'Data Program Studi dan Kaprodi berhasil disinkronkan dari proyek OBE.'];
        } else {
            return ['status' => false, 'message' => 'Gagal sinkronisasi: ' . $conn->error];
        }
    }
}

if (!function_exists('sync_dosen_from_obe')) {
    function sync_dosen_from_obe($conn) {
        $obe_db = 'obe_cpl';

        // 1. Ambil data dosen dari OBE (role_id 3 = Dosen, role_id 4 = Kaprodi)
        $query = "
            SELECT 
                u.id AS obe_user_id,
                u.username AS obe_username,
                u.password AS obe_password,
                u.nama_lengkap,
                u.email,
                u.nidn,
                p.nama AS prodi_nama
            FROM {$obe_db}.users u
            LEFT JOIN {$obe_db}.program_studi p ON u.prodi_id = p.id
            WHERE u.role_id IN (3, 4)
            ORDER BY p.id, u.nama_lengkap
        ";

        $res = $conn->query($query);
        if (!$res) {
            return ['status' => false, 'message' => 'Gagal membaca data dari database OBE: ' . $conn->error];
        }

        $imported = 0;
        $updated = 0;

        while ($dosen = $res->fetch_assoc()) {
            $nama = trim($dosen['nama_lengkap']);
            $obe_username = trim($dosen['obe_username']);
            $password = $dosen['obe_password'];
            $prodi = trim($dosen['prodi_nama'] ?? '');
            $nidn = trim($dosen['nidn'] ?? '');
            if ($nidn === '' || $nidn === '-') {
                $nidn = $obe_username;
            }

            // Cek apakah user sudah ada di pkl-logbook
            $existing = null;

            // 1. Cek berdasarkan username
            $stmt = $conn->prepare("SELECT id, nama, username, prodi FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $obe_username);
            $stmt->execute();
            $check_res = $stmt->get_result();
            if ($row = $check_res->fetch_assoc()) {
                $existing = $row;
            }

            // 2. Jika belum ketemu, cek berdasarkan nama
            if (!$existing) {
                $stmt = $conn->prepare("SELECT id, nama, username, prodi FROM users WHERE nama = ? AND role = 'dosen_pkl' LIMIT 1");
                $stmt->bind_param("s", $nama);
                $stmt->execute();
                $check_res = $stmt->get_result();
                if ($row = $check_res->fetch_assoc()) {
                    $existing = $row;
                }
            }

            // 3. Jika belum ketemu, cek berdasarkan NIP di tabel dosen
            if (!$existing && !empty($nidn) && $nidn !== '-') {
                $stmt = $conn->prepare("SELECT u.id, u.nama, u.username, u.prodi FROM users u JOIN dosen d ON u.id = d.id WHERE d.nip = ? LIMIT 1");
                $stmt->bind_param("s", $nidn);
                $stmt->execute();
                $check_res = $stmt->get_result();
                if ($row = $check_res->fetch_assoc()) {
                    $existing = $row;
                }
            }

            if ($existing) {
                // Update prodi & role jika belum dosen_pkl
                $user_id = $existing['id'];
                $update_stmt = $conn->prepare("UPDATE users SET prodi = ?, role = 'dosen_pkl' WHERE id = ?");
                $update_stmt->bind_param("si", $prodi, $user_id);
                $update_stmt->execute();

                // Upsert tabel dosen
                $dosen_stmt = $conn->prepare("
                    INSERT INTO dosen (id, nip, bidang) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE nip = VALUES(nip), bidang = VALUES(bidang)
                ");
                $dosen_stmt->bind_param("iss", $user_id, $nidn, $prodi);
                $dosen_stmt->execute();

                $updated++;
            } else {
                // Insert new user
                $role = 'dosen_pkl';
                $insert_stmt = $conn->prepare("
                    INSERT INTO users (nama, username, password, role, prodi)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insert_stmt->bind_param("sssss", $nama, $obe_username, $password, $role, $prodi);
                if ($insert_stmt->execute()) {
                    $new_id = $conn->insert_id;
                    $dosen_stmt = $conn->prepare("
                        INSERT INTO dosen (id, nip, bidang) 
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE nip = VALUES(nip), bidang = VALUES(bidang)
                    ");
                    $dosen_stmt->bind_param("iss", $new_id, $nidn, $prodi);
                    $dosen_stmt->execute();
                    $imported++;
                }
            }
        }

        return [
            'status' => true,
            'imported' => $imported,
            'updated' => $updated,
            'message' => "Sinkronisasi dosen berhasil: {$imported} dosen baru ditambahkan, {$updated} data dosen diperbarui."
        ];
    }
}


