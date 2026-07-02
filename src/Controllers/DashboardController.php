<?php

namespace App\Controllers;

use App\Utils\Response;
use App\Middleware\AuthMiddleware;
use App\Config\Database;
use PDO;

class DashboardController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    // GET /api/dashboard (Admin & Petugas Only)
    public function index() {
        $currentUser = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($currentUser, [1, 2]);

        // Total inventaris
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM items");
        $total_inventory = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Total kategori
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM categories");
        $total_categories = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Total mahasiswa (role_id = 3)
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM users WHERE role_id = 3");
        $total_students = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Total semua peminjaman
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM borrowings");
        $total_borrowings = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Jumlah per status peminjaman
        $stmt = $this->db->query("
            SELECT status, COUNT(*) as total
            FROM borrowings
            GROUP BY status
        ");
        $statusCounts = ['Pending' => 0, 'Approved' => 0, 'Borrowed' => 0, 'Returned' => 0, 'Rejected' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $statusCounts[$row['status']] = (int) $row['total'];
        }

        // Inventaris habis (stok = 0)
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM items WHERE stock = 0");
        $out_of_stock = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Dikembalikan hari ini (status Returned & updated hari ini)
        $today = date('Y-m-d');
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total FROM borrowings
            WHERE status = 'Returned' AND DATE(updated_at) = ?
        ");
        $stmt->execute([$today]);
        $returned_today = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // 5 peminjaman terbaru
        $stmt = $this->db->query("
            SELECT b.id, u.full_name as user_name, i.name as item_name,
                   b.borrow_date, b.return_date, b.status, b.created_at
            FROM borrowings b
            JOIN users u ON b.user_id = u.id
            JOIN items i ON b.item_id = i.id
            ORDER BY b.created_at DESC
            LIMIT 5
        ");
        $latest_borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [
            'total_inventory'   => $total_inventory,
            'total_categories'  => $total_categories,
            'total_students'    => $total_students,
            'total_borrowings'  => $total_borrowings,

            'pending'  => $statusCounts['Pending'],
            'approved' => $statusCounts['Approved'],
            'borrowed' => $statusCounts['Borrowed'],
            'returned' => $statusCounts['Returned'],

            'out_of_stock'    => $out_of_stock,
            'returned_today'  => $returned_today,

            'latest_borrowings' => $latest_borrowings,
        ];

        Response::success("Berhasil mengambil data dashboard.", $data);
    }
}
