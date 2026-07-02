<?php

namespace App\Models;

use PDO;

class Document {
    private $conn;
    private $table_name = "documents";

    public $id;
    public $borrowing_id;
    public $file_name;
    public $file_url;
    public $public_id;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Menyimpan informasi dokumen baru
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (borrowing_id, file_name, file_url, public_id) 
                  VALUES (:borrowing_id, :file_name, :file_url, :public_id)";

        $stmt = $this->conn->prepare($query);

        $this->file_name = htmlspecialchars(strip_tags($this->file_name));
        $this->file_url = htmlspecialchars(strip_tags($this->file_url));
        $this->public_id = htmlspecialchars(strip_tags($this->public_id));

        $stmt->bindParam(":borrowing_id", $this->borrowing_id);
        $stmt->bindParam(":file_name", $this->file_name);
        $stmt->bindParam(":file_url", $this->file_url);
        $stmt->bindParam(":public_id", $this->public_id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Menghapus data dokumen dari database
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}