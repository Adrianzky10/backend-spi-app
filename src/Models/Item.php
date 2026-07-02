<?php

namespace App\Models;

use PDO;

class Item {
    private $conn;
    private $table_name = "items";

    public $id;
    public $category_id;
    public $category_name; // Properti JOIN
    public $name;
    public $description;
    public $stock;
    public $image_url;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Mengambil semua barang beserta nama kategorinya
    public function readAll() {
        $query = "SELECT i.id, i.category_id, c.name as category_name, i.name, i.description, i.stock, i.image_url, i.created_at 
                  FROM " . $this->table_name . " i
                  JOIN categories c ON i.category_id = c.id
                  ORDER BY i.created_at DESC";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Mengambil detail satu barang berdasarkan ID
    public function readOne() {
        $query = "SELECT i.id, i.category_id, c.name as category_name, i.name, i.description, i.stock, i.image_url, i.created_at 
                  FROM " . $this->table_name . " i
                  JOIN categories c ON i.category_id = c.id
                  WHERE i.id = ? LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->category_id = $row['category_id'];
            $this->category_name = $row['category_name'];
            $this->name = $row['name'];
            $this->description = $row['description'];
            $this->stock = $row['stock'];
            $this->image_url = $row['image_url'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    // Membuat barang baru
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (category_id, name, description, stock, image_url) 
                  VALUES (:category_id, :name, :description, :stock, :image_url)";

        $stmt = $this->conn->prepare($query);

        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->image_url = htmlspecialchars(strip_tags($this->image_url));

        $stmt->bindParam(":category_id", $this->category_id);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":stock", $this->stock);
        $stmt->bindParam(":image_url", $this->image_url);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Memperbarui data barang
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET category_id = :category_id, name = :name, description = :description, stock = :stock, image_url = :image_url 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->image_url = htmlspecialchars(strip_tags($this->image_url));

        $stmt->bindParam(":category_id", $this->category_id);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":stock", $this->stock);
        $stmt->bindParam(":image_url", $this->image_url);
        $stmt->bindParam(":id", $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Menghapus barang
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Memperbarui stok (digunakan saat barang dipinjam atau dikembalikan)
    public function updateStock($newStock) {
        $query = "UPDATE " . $this->table_name . " SET stock = :stock WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":stock", $newStock);
        $stmt->bindParam(":id", $this->id);
        
        return $stmt->execute();
    }
}