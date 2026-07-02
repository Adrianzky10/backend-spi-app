<?php

namespace App\Controllers;

use App\Models\User;
use App\Utils\Response;
use App\Utils\Mailer;
use App\Config\Database;
use Firebase\JWT\JWT;

class AuthController {
    private $db;
    private $user;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
    }

    public function register() {
        $data = json_decode(file_get_contents("php://input"));

        // Validation
        if(empty($data->full_name) || empty($data->nim) || empty($data->email) || empty($data->password)) {
            Response::error("Data tidak lengkap.", 400);
        }

        if(strlen($data->password) < 8) {
            Response::error("Password minimal 8 karakter.", 400);
        }

        if(!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            Response::error("Format email tidak valid.", 400);
        }

        $this->user->email = $data->email;
        if($this->user->emailExists()) {
            Response::error("Email sudah terdaftar.", 400);
        }

        $this->user->nim = $data->nim;
        if($this->user->nimExists()) {
            Response::error("NIM sudah terdaftar.", 400);
        }

        // Set properties
        // Role ID 3 = Mahasiswa (berdasarkan seeder)
        $this->user->role_id = 3; 
        $this->user->full_name = $data->full_name;
        $this->user->password = password_hash($data->password, PASSWORD_BCRYPT);
        $this->user->is_verified = 0; // Harus aktivasi
        $this->user->activation_token = bin2hex(random_bytes(32)); // Generate token

        if($this->user->create()) {
            // Kirim email aktivasi
            $mailSent = Mailer::sendActivationEmail(
                $this->user->email, 
                $this->user->full_name, 
                $this->user->activation_token
            );

            if($mailSent === true) {
                Response::success("Registrasi berhasil. Silakan cek email Anda untuk aktivasi akun.", null, 201);
            } else {
                Response::success("Registrasi berhasil, tetapi gagal mengirim email aktivasi. Detail Error: " . $mailSent, null, 201);
            }
        } else {
            Response::error("Registrasi gagal. Terjadi kesalahan pada server.", 500);
        }
    }

    public function activate() {
        $token = isset($_GET['code']) ? $_GET['code'] : '';

        if(empty($token)) {
            Response::error("Token aktivasi tidak valid atau kosong.", 400);
        }

        $this->user->activation_token = $token;

        if($this->user->activateAccount()) {
            Response::success("Akun berhasil diaktifkan. Silakan login.");
        } else {
            Response::error("Aktivasi gagal. Token tidak valid atau akun sudah aktif.", 400);
        }
    }

    public function login() {
        $data = json_decode(file_get_contents("php://input"));

        if(empty($data->email) || empty($data->password)) {
            Response::error("Email dan password harus diisi.", 400);
        }

        $this->user->email = $data->email;
        $email_exists = $this->user->emailExists();

        if($email_exists && password_verify($data->password, $this->user->password)) {
            if($this->user->is_verified == 0) {
                Response::error("Akun belum diaktifkan. Silakan cek email Anda.", 403);
            }

            $token = array(
                "iss" => rtrim($_ENV['FRONTEND_URL'], '/'),
                "aud" => rtrim($_ENV['FRONTEND_URL'], '/'),
                "iat" => time(),
                "exp" => time() + $_ENV['JWT_EXPIRATION'],
                "data" => array(
                    "id" => $this->user->id,
                    "full_name" => $this->user->full_name,
                    "email" => $this->user->email,
                    "role_id" => $this->user->role_id
                )
            );

            $jwt = JWT::encode($token, $_ENV['JWT_SECRET'], 'HS256');

            Response::success("Login berhasil.", array("token" => $jwt));
        } else {
            Response::error("Email atau password salah.", 401);
        }
    }

    public function me() {
        // Memverifikasi JWT dan mendapatkan data user dari token
        $currentUser = \App\Middleware\AuthMiddleware::authenticate();

        // Ambil data terbaru dari database
        $this->user->id = $currentUser['id'];
        if($this->user->readOne()) {
            $user_data = [
                "id" => $this->user->id,
                "role_id" => $this->user->role_id,
                "role_name" => $this->user->role_name,
                "full_name" => $this->user->full_name,
                "nim" => $this->user->nim,
                "email" => $this->user->email,
                "is_verified" => $this->user->is_verified,
                "created_at" => $this->user->created_at
            ];
            Response::success("Berhasil mengambil data profil.", $user_data);
        } else {
            Response::error("Pengguna tidak ditemukan.", 404);
        }
    }

    public function logout() {
        // Karena JWT stateless, logout biasanya hanya dihapus tokennya dari frontend (local storage).
        // Untuk REST API, kita cukup mengirim pesan sukses.
        Response::success("Logout berhasil.");
    }
}
