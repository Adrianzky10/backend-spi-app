<?php

namespace App\Middleware;

use App\Utils\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class AuthMiddleware {
    /**
     * Memverifikasi JWT token dari request header dan mengembalikan payload data user.
     * 
     * @return array
     */
    public static function authenticate() {
        $headers = apache_request_headers();
        
        $authHeader = null;
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }

        if (!$authHeader) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        }

        if (!$authHeader) {
            Response::error("Token autentikasi tidak ditemukan. Silakan login terlebih dahulu.", 401);
        }

        $arr = explode(" ", $authHeader);
        $jwt = isset($arr[1]) ? $arr[1] : null;

        if (!$jwt) {
            Response::error("Format token tidak valid. Gunakan format 'Bearer <token>'.", 401);
        }

        try {
            $secret_key = $_ENV['JWT_SECRET'];
            $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
            
            return (array) $decoded->data;
        } catch (Exception $e) {
            Response::error("Token tidak valid atau telah kedaluwarsa. Detail: " . $e->getMessage(), 401);
        }
    }

    /**
     * Memastikan pengguna memiliki salah satu role yang diizinkan.
     * 
     * @param array $user Data user hasil authenticate()
     * @param array $allowedRoles Array ID role yang diizinkan (1: Admin, 2: Petugas, 3: Mahasiswa)
     * @return void
     */
    public static function authorize($user, $allowedRoles) {
        if (!in_array($user['role_id'], $allowedRoles)) {
            Response::error("Anda tidak memiliki hak akses (akses ditolak).", 403);
        }
    }
}
