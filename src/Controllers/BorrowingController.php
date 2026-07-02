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
}