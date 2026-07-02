<?php

namespace App\Controllers;

use App\Models\User;
use App\Utils\Response;
use App\Middleware\AuthMiddleware;
use App\Config\Database;
use PDO;

class UserController {
    private $db;
    private $user;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
    }

    /**
     * Helper to authenticate Admin role (ID = 1)
     */
    private function authenticateAdmin() {
        $currentUser = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($currentUser, [1]); // Hanya Admin (role_id = 1)
        return $currentUser;
    }

    // GET /api/users
    public function index() {
        $this->authenticateAdmin();

        $stmt = $this->user->readAll();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success("Berhasil mengambil data pengguna.", $users);
    }

    // GET /api/users/{id}
    public function show($id) {
        $this->authenticateAdmin();

        $this->user->id = $id;
        if($this->user->readOne()) {
            $user_data = [
                "id" => $this->user->id,
                "role_id" => $this->user->role_id,
                "full_name" => $this->user->full_name,
                "nim" => $this->user->nim,
                "email" => $this->user->email,
                "is_verified" => $this->user->is_verified,
                "created_at" => $this->user->created_at
            ];
            Response::success("Berhasil mengambil detail pengguna.", $user_data);
        } else {
            Response::error("Pengguna tidak ditemukan.", 404);
        }
    }

    // POST /api/users (Menambah user baru oleh Admin)
    public function store() {
        $this->authenticateAdmin();

        $data = json_decode(file_get_contents("php://input"));

        if(empty($data->full_name) || empty($data->email) || empty($data->password) || empty($data->role_id)) {
            Response::error("Data tidak lengkap.", 400);
        }

        if(strlen($data->password) < 8) {
            Response::error("Password minimal 8 karakter.", 400);
        }

        $this->user->email = $data->email;
        if($this->user->emailExists()) {
            Response::error("Email sudah terdaftar.", 400);
        }

        // NIM opsional untuk role non-mahasiswa, tapi jika diisi harus unik
        if(!empty($data->nim)) {
            $this->user->nim = $data->nim;
            if($this->user->nimExists()) {
                Response::error("NIM sudah terdaftar.", 400);
            }
        }

        $this->user->role_id = $data->role_id;
        $this->user->full_name = $data->full_name;
        $this->user->nim = $data->nim ?? null;
        $this->user->password = password_hash($data->password, PASSWORD_BCRYPT);
        $this->user->is_verified = isset($data->is_verified) ? $data->is_verified : 1; // Default langsung aktif jika dibuat oleh admin
        $this->user->activation_token = null;

        if($this->user->create()) {
            Response::success("Pengguna berhasil dibuat.", null, 201);
        } else {
            Response::error("Gagal membuat pengguna.", 500);
        }
    }

    // PUT /api/users/{id}
    public function update($id) {
        $this->authenticateAdmin();

        $this->user->id = $id;
        if(!$this->user->readOne()) {
            Response::error("Pengguna tidak ditemukan.", 404);
        }

        $data = json_decode(file_get_contents("php://input"));

        if(empty($data->full_name) || empty($data->email) || empty($data->role_id)) {
            Response::error("Data tidak lengkap.", 400);
        }

        // Cek jika email dirubah, harus tetap unik
        if($data->email !== $this->user->email) {
            $tempUser = new User($this->db);
            $tempUser->email = $data->email;
            if($tempUser->emailExists()) {
                Response::error("Email sudah terdaftar pada pengguna lain.", 400);
            }
        }

        // Cek jika NIM dirubah, harus tetap unik
        if(!empty($data->nim) && $data->nim !== $this->user->nim) {
            $tempUser = new User($this->db);
            $tempUser->nim = $data->nim;
            if($tempUser->nimExists()) {
                Response::error("NIM sudah terdaftar pada pengguna lain.", 400);
            }
        }

        $this->user->role_id = $data->role_id;
        $this->user->full_name = $data->full_name;
        $this->user->nim = $data->nim ?? null;
        $this->user->email = $data->email;
        $this->user->is_verified = isset($data->is_verified) ? $data->is_verified : $this->user->is_verified;

        if($this->user->update()) {
            Response::success("Data pengguna berhasil diperbarui.");
        } else {
            Response::error("Gagal memperbarui data pengguna.", 500);
        }
    }

    // DELETE /api/users/{id}
    public function destroy($id) {
        $currentUser = $this->authenticateAdmin();

        // Mencegah admin menghapus akunnya sendiri
        if($id == $currentUser['id']) {
            Response::error("Anda tidak bisa menghapus akun Anda sendiri.", 400);
        }

        $this->user->id = $id;
        if(!$this->user->readOne()) {
            Response::error("Pengguna tidak ditemukan.", 404);
        }

        if($this->user->delete()) {
            Response::success("Pengguna berhasil dihapus.");
        } else {
            Response::error("Gagal menghapus pengguna.", 500);
        }
    }
}
