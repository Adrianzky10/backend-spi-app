<?php

namespace App\Controllers;

use App\Models\Borrowing;
use App\Models\Item;
use App\Models\Document;
use App\Utils\Response;
use App\Utils\CloudinaryUploader;
use App\Middleware\AuthMiddleware;
use App\Config\Database;
use Exception;
use PDO;

class BorrowingController {
    private $db;
    private $borrowing;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->borrowing = new Borrowing($this->db);
    }

    // GET /api/borrowings
    public function index() {
        $currentUser = AuthMiddleware::authenticate();

        // Admin (1) & Petugas (2) bisa melihat semua data, Mahasiswa (3) hanya miliknya sendiri
        if (in_array($currentUser['role_id'], [1, 2])) {
            $stmt = $this->borrowing->readAll();
        } else {
            $stmt = $this->borrowing->readByUser($currentUser['id']);
        }

        $borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::success("Berhasil mengambil data peminjaman.", $borrowings);
    }

    // GET /api/borrowings/my
    public function my() {
        $currentUser = AuthMiddleware::authenticate();

        $stmt = $this->borrowing->readByUser($currentUser['id']);
        $borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success("Berhasil mengambil data peminjaman saya.", $borrowings);
    }

    // GET /api/borrowings/{id}
    public function show($id) {
        $currentUser = AuthMiddleware::authenticate();

        $this->borrowing->id = $id;
        if (!$this->borrowing->readOne()) {
            Response::error("Data peminjaman tidak ditemukan.", 404);
        }

        // Mahasiswa (3) hanya bisa melihat data peminjamannya sendiri
        if ($currentUser['role_id'] == 3 && $this->borrowing->user_id != $currentUser['id']) {
            Response::error("Anda tidak memiliki akses ke data ini.", 403);
        }

        $data = [
            'id' => $this->borrowing->id,
            'user_id' => $this->borrowing->user_id,
            'user_name' => $this->borrowing->user_name,
            'item_id' => $this->borrowing->item_id,
            'item_name' => $this->borrowing->item_name,
            'borrow_date' => $this->borrowing->borrow_date,
            'return_date' => $this->borrowing->return_date,
            'status' => $this->borrowing->status,
            'rejection_reason' => $this->borrowing->rejection_reason,
            'created_at' => $this->borrowing->created_at,
            'document' => $this->borrowing->document_id ? [
                'id' => $this->borrowing->document_id,
                'file_name' => $this->borrowing->document_name,
                'file_url' => $this->borrowing->document_url
            ] : null
        ];

        Response::success("Berhasil mengambil detail peminjaman.", $data);
    }

    // POST /api/borrowings (Pengajuan oleh Mahasiswa)
    public function store() {
        $currentUser = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($currentUser, [3]); // Hanya Mahasiswa (role_id = 3)

        // Karena menggunakan file upload, data dikirim lewat $_POST (bukan php://input)
        $item_id = $_POST['item_id'] ?? null;
        $borrow_date = $_POST['borrow_date'] ?? null;
        $return_date = $_POST['return_date'] ?? null;
        $documentFile = $_FILES['document'] ?? null;

        if (!$item_id || !$borrow_date || !$return_date || !$documentFile) {
            Response::error("Data tidak lengkap. Item ID, tanggal pinjam, tanggal kembali, dan file dokumen wajib diisi.", 400);
        }

        // Validasi tanggal kembali tidak boleh lebih kecil dari tanggal pinjam
        if (strtotime($return_date) < strtotime($borrow_date)) {
            Response::error("Tanggal kembali tidak boleh lebih kecil dari tanggal pinjam.", 400);
        }

        // Cek apakah barang ada dan stoknya ada
        $item = new Item($this->db);
        $item->id = $item_id;
        if (!$item->readOne()) {
            Response::error("Barang tidak ditemukan.", 404);
        }

        if ($item->stock <= 0) {
            Response::error("Stok barang saat ini kosong. Peminjaman tidak dapat diajukan.", 400);
        }

        // Transaksi DB untuk keamanan data
        $this->db->beginTransaction();

        try {
            // 1. Upload PDF ke Cloudinary
            $uploadResult = CloudinaryUploader::uploadPdf($documentFile);

            // 2. Buat Peminjaman (Status awal: Pending)
            $this->borrowing->user_id = $currentUser['id'];
            $this->borrowing->item_id = $item_id;
            $this->borrowing->borrow_date = $borrow_date;
            $this->borrowing->return_date = $return_date;

            if (!$this->borrowing->create()) {
                throw new Exception("Gagal menyimpan data pengajuan peminjaman.");
            }

            // 3. Simpan relasi dokumen
            $document = new Document($this->db);
            $document->borrowing_id = $this->borrowing->id;
            $document->file_name = $uploadResult['file_name'];
            $document->file_url = $uploadResult['file_url'];
            $document->public_id = $uploadResult['public_id'];

            if (!$document->create()) {
                throw new Exception("Gagal menyimpan data dokumen bukti peminjaman.");
            }

            $this->db->commit();
            Response::success("Pengajuan peminjaman berhasil diajukan dan sedang menunggu verifikasi petugas.", null, 201);

        } catch (Exception $e) {
            $this->db->rollBack();
            Response::error("Pengajuan peminjaman gagal. Detail: " . $e->getMessage(), 500);
        }
    }
    
        // PATCH /api/borrowings/{id}/approve (Petugas/Admin Only)
    public function approve($id) {
        $currentUser = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($currentUser, [1, 2]); // Admin & Petugas

        $this->borrowing->id = $id;
        if (!$this->borrowing->readOne()) {
            Response::error("Data peminjaman tidak ditemukan.", 404);
        }

        if ($this->borrowing->status !== 'Pending') {
            Response::error("Hanya pengajuan berstatus 'Pending' yang bisa disetujui. Status saat ini: " . $this->borrowing->status, 400);
        }

        // Cek stok barang saat ini
        $item = new Item($this->db);
        $item->id = $this->borrowing->item_id;
        $item->readOne();

        if ($item->stock <= 0) {
            Response::error("Persetujuan gagal. Stok barang kosong.", 400);
        }

        $this->db->beginTransaction();

        try {
            // Kurangi stok barang
            $newStock = $item->stock - 1;
            $item->updateStock($newStock);

            // Update status peminjaman ke Approved
            $this->borrowing->updateStatus('Approved');

            $this->db->commit();
            Response::success("Pengajuan peminjaman berhasil disetujui.");
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::error("Gagal menyetujui peminjaman. Detail: " . $e->getMessage(), 500);
        }
    }

    // PATCH /api/borrowings/{id}/reject (Petugas/Admin Only)
    public function reject($id) {
        $currentUser = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($currentUser, [1, 2]);

        $this->borrowing->id = $id;
        if (!$this->borrowing->readOne()) {
            Response::error("Data peminjaman tidak ditemukan.", 404);
        }

        if ($this->borrowing->status !== 'Pending') {
            Response::error("Hanya pengajuan berstatus 'Pending' yang bisa ditolak.", 400);
        }

        $data = json_decode(file_get_contents("php://input"));
        $reason = $data->rejection_reason ?? null;

        if (empty($reason)) {
            Response::error("Alasan penolakan (rejection_reason) wajib diisi.", 400);
        }

        if ($this->borrowing->updateStatus('Rejected', $reason)) {
            Response::success("Pengajuan peminjaman telah ditolak.");
        } else {
            Response::error("Gagal menolak pengajuan peminjaman.", 500);
        }
    }

    // PATCH /api/borrowings/{id}/borrow (Petugas/Admin Only)
    public function borrow($id) {
        $currentUser = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($currentUser, [1, 2]);

        $this->borrowing->id = $id;
        if (!$this->borrowing->readOne()) {
            Response::error("Data peminjaman tidak ditemukan.", 404);
        }

        if ($this->borrowing->status !== 'Approved') {
            Response::error("Hanya peminjaman berstatus 'Approved' yang bisa ditandai sedang dipinjam.", 400);
        }

        if ($this->borrowing->updateStatus('Borrowed')) {
            Response::success("Peminjaman berhasil ditandai sebagai 'Dipinjam'.");
        } else {
            Response::error("Gagal mengubah status peminjaman.", 500);
        }
    }

    // PATCH /api/borrowings/{id}/return (Petugas/Admin Only)
    public function returnItem($id) {
        $currentUser = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($currentUser, [1, 2]);

        $this->borrowing->id = $id;
        if (!$this->borrowing->readOne()) {
            Response::error("Data peminjaman tidak ditemukan.", 404);
        }

        // Hanya barang yang berstatus 'Borrowed' atau 'Approved' yang bisa dikembalikan
        if ($this->borrowing->status !== 'Borrowed' && $this->borrowing->status !== 'Approved') {
            Response::error("Peminjaman tidak dalam status yang valid untuk dikembalikan. Status saat ini: " . $this->borrowing->status, 400);
        }

        // Ambil info barang
        $item = new Item($this->db);
        $item->id = $this->borrowing->item_id;
        $item->readOne();

        $this->db->beginTransaction();

        try {
            // Tambah stok barang kembali
            $newStock = $item->stock + 1;
            $item->updateStock($newStock);

            // Update status peminjaman ke Returned
            $this->borrowing->updateStatus('Returned');

            $this->db->commit();
            Response::success("Barang inventaris telah berhasil dikembalikan.");
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::error("Gagal memproses pengembalian barang. Detail: " . $e->getMessage(), 500);
        }
    }
}