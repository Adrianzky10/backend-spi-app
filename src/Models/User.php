<?php

namespace App\Models;

use PDO;

class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $role_id;
    public $role_name;
    public $full_name;
    public $nim;
    public $email;
    public $password;
    public $is_verified;
    public $activation_token;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function emailExists() {
        $query = "SELECT id, full_name, password, role_id, is_verified FROM " . $this->table_name . " WHERE email = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->email);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->full_name = $row['full_name'];
            $this->password = $row['password'];
            $this->role_id = $row['role_id'];
            $this->is_verified = $row['is_verified'];
            return true;
        }
        return false;
    }

    public function nimExists() {
        $query = "SELECT id FROM " . $this->table_name . " WHERE nim = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->nim);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                (role_id, full_name, nim, email, password, is_verified, activation_token) 
                VALUES (:role_id, :full_name, :nim, :email, :password, :is_verified, :activation_token)";

        $stmt = $this->conn->prepare($query);

        $this->full_name = htmlspecialchars(strip_tags($this->full_name));
        $this->nim = htmlspecialchars(strip_tags($this->nim));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->activation_token = htmlspecialchars(strip_tags($this->activation_token));

        $stmt->bindParam(":role_id", $this->role_id);
        $stmt->bindParam(":full_name", $this->full_name);
        $stmt->bindParam(":nim", $this->nim);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":is_verified", $this->is_verified, PDO::PARAM_INT);
        $stmt->bindParam(":activation_token", $this->activation_token);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function activateAccount() {
        $query = "UPDATE " . $this->table_name . " 
                SET is_verified = 1, activation_token = NULL 
                WHERE activation_token = :token";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":token", $this->activation_token);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }


    public function readAll() {
        $query = "SELECT u.id, u.role_id, r.name as role_name, u.full_name, u.nim, u.email, u.is_verified, u.created_at 
                  FROM " . $this->table_name . " u
                  JOIN roles r ON u.role_id = r.id
                  ORDER BY u.created_at DESC";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function readOne() {
        $query = "SELECT u.id, u.role_id, r.name as role_name, u.full_name, u.nim, u.email, u.is_verified, u.created_at 
                  FROM " . $this->table_name . " u
                  JOIN roles r ON u.role_id = r.id
                  WHERE u.id = ? LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->role_id = $row['role_id'];
            $this->role_name = $row['role_name']; 
            $this->full_name = $row['full_name'];
            $this->nim = $row['nim'];
            $this->email = $row['email'];
            $this->is_verified = $row['is_verified'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET role_id = :role_id, full_name = :full_name, nim = :nim, email = :email, is_verified = :is_verified 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->full_name = htmlspecialchars(strip_tags($this->full_name));
        $this->nim = htmlspecialchars(strip_tags($this->nim));
        $this->email = htmlspecialchars(strip_tags($this->email));

        $stmt->bindParam(":role_id", $this->role_id);
        $stmt->bindParam(":full_name", $this->full_name);
        $stmt->bindParam(":nim", $this->nim);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":is_verified", $this->is_verified, PDO::PARAM_INT);
        $stmt->bindParam(":id", $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

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
