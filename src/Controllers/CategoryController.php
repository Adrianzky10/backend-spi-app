<?php

namespace App\Controllers;

use App\Models\Category;
use App\Utils\Response;
use App\Middleware\AuthMiddleware;
use App\Config\Database;
use PDO;

class CategoryController {
    private $db;
    private $category;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->category = new Category($this->db);
    }

    public function index() {
      
        AuthMiddleware::authenticate();

        $stmt = $this->category->readAll();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success("Berhasil mengambil data kategori.", $categories);
    }

    public function show($id) {
        AuthMiddleware::authenticate();

        $this->category->id = $id;
        if($this->category->readOne()) {
            $data = [
                "id" => $this->category->id,
                "name" => $this->category->name,
                "created_at" => $this->category->created_at
            ];
            Response::success("Berhasil mengambil detail kategori.", $data);
        } else {
            Response::error("Kategori tidak ditemukan.", 404);
        }
    }

    public function store() {
    
        $currentUser = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($currentUser, [1]);

        $data = json_decode(file_get_contents("php://input"));

        if(empty($data->name)) {
            Response::error("Nama kategori wajib diisi.", 400);
        }

        $this->category->name = $data->name;
        if($this->category->nameExists()) {
            Response::error("Kategori dengan nama tersebut sudah ada.", 400);
        }

        if($this->category->create()) {
            Response::success("Kategori berhasil dibuat.", null, 201);
        } else {
            Response::error("Gagal membuat kategori.", 500);
        }
    }

    public function update($id) {
        $currentUser = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($currentUser, [1]);

        $this->category->id = $id;
        if(!$this->category->readOne()) {
            Response::error("Kategori tidak ditemukan.", 404);
        }

        $data = json_decode(file_get_contents("php://input"));

        if(empty($data->name)) {
            Response::error("Nama kategori wajib diisi.", 400);
        }

        if($data->name !== $this->category->name) {
            $tempCat = new Category($this->db);
            $tempCat->name = $data->name;
            if($tempCat->nameExists()) {
                Response::error("Nama kategori sudah digunakan.", 400);
            }
        }

        $this->category->name = $data->name;

        if($this->category->update()) {
            Response::success("Kategori berhasil diperbarui.");
        } else {
            Response::error("Gagal memperbarui kategori.", 500);
        }
    }

    public function destroy($id) {
        $currentUser = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($currentUser, [1]);

        $this->category->id = $id;
        if(!$this->category->readOne()) {
            Response::error("Kategori tidak ditemukan.", 404);
        }

        try {
            if($this->category->delete()) {
                Response::success("Kategori berhasil dihapus.");
            } else {
                Response::error("Gagal menghapus kategori.", 500);
            }
        } catch(\PDOException $e) {
            Response::error("Kategori tidak bisa dihapus karena masih digunakan oleh inventaris/barang.", 400);
        }
    }
}