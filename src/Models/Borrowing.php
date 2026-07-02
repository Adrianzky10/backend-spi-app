<?php

namespace App\Models;

use PDO;

class Borrowing {
    private $conn;
    private $table_name = "borrowings";

    public $id;
    public $user_id;
    public $user_name;
    public $item_id;
    public $item_name;
    public $borrow_date;
    public $return_date;
    public $status;
    public $rejection_reason;
    public $created_at;

    // Dokumen Relasi
    public $document_id;
    public $document_name;
    public $document_url;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Mengambil semua data peminjaman (untuk Admin/Petugas)
    public function readAll() {
        $query = "SELECT b.id, b.user_id, u.full_name as user_name, b.item_id, i.name as item_name, 
                         b.borrow_date, b.return_date, b.status, b.rejection_reason, b.created_at,
                         d.id as document_id, d.file_name as document_name, d.file_url as document_url
                  FROM " . $this->table_name . " b
                  JOIN users u ON b.user_id = u.id
                  JOIN items i ON b.item_id = i.id
                  LEFT JOIN documents d ON d.borrowing_id = b.id
                  ORDER BY b.created_at DESC";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Mengambil riwayat peminjaman khusus untuk user/mahasiswa tertentu
    public function readByUser($userId) {
        $query = "SELECT b.id, b.user_id, u.full_name as user_name, b.item_id, i.name as item_name, 
                         b.borrow_date, b.return_date, b.status, b.rejection_reason, b.created_at,
                         d.id as document_id, d.file_name as document_name, d.file_url as document_url
                  FROM " . $this->table_name . " b
                  JOIN users u ON b.user_id = u.id
                  JOIN items i ON b.item_id = i.id
                  LEFT JOIN documents d ON d.borrowing_id = b.id
                  WHERE b.user_id = ?
                  ORDER BY b.created_at DESC";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $userId);
        $stmt->execute();
        return $stmt;
    }

    // Mengambil detail satu peminjaman
    public function readOne() {
        $query = "SELECT b.id, b.user_id, u.full_name as user_name, b.item_id, i.name as item_name, 
                         b.borrow_date, b.return_date, b.status, b.rejection_reason, b.created_at,
                         d.id as document_id, d.file_name as document_name, d.file_url as document_url
                  FROM " . $this->table_name . " b
                  JOIN users u ON b.user_id = u.id
                  JOIN items i ON b.item_id = i.id
                  LEFT JOIN documents d ON d.borrowing_id = b.id
                  WHERE b.id = ? LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->user_id = $row['user_id'];
            $this->user_name = $row['user_name'];
            $this->item_id = $row['item_id'];
            $this->item_name = $row['item_name'];
            $this->borrow_date = $row['borrow_date'];
            $this->return_date = $row['return_date'];
            $this->status = $row['status'];
            $this->rejection_reason = $row['rejection_reason'];
            $this->created_at = $row['created_at'];
            $this->document_id = $row['document_id'];
            $this->document_name = $row['document_name'];
            $this->document_url = $row['document_url'];
            return true;
        }
        return false;
    }

    // Mengajukan peminjaman baru
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, item_id, borrow_date, return_date, status) 
                  VALUES (:user_id, :item_id, :borrow_date, :return_date, 'Pending')";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":item_id", $this->item_id);
        $stmt->bindParam(":borrow_date", $this->borrow_date);
        $stmt->bindParam(":return_date", $this->return_date);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId(); // Dapatkan ID peminjaman yang baru dibuat
            return true;
        }
        return false;
    }

    // Mengubah status peminjaman (Approve / Reject / Return)
    public function updateStatus($newStatus, $rejectionReason = null) {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status, rejection_reason = :rejection_reason 
                  WHERE id = :id";
                  
        $stmt = $this->conn->prepare($query);
        
        $rejectionReason = $rejectionReason ? htmlspecialchars(strip_tags($rejectionReason)) : null;

        $stmt->bindParam(":status", $newStatus);
        $stmt->bindParam(":rejection_reason", $rejectionReason);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }
}
