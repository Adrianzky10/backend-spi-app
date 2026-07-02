<?php

namespace App\Controllers;

use App\Models\Item;
use App\Models\Category;
use App\Utils\Response;
use App\Middleware\AuthMiddleware;
use App\Config\Database;
use PDO;

class ItemController {
    private $db;
    private $item;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->item = new Item($this->db);
    }

    // GET /api/items
    public function index() {
        AuthMiddleware::authenticate();

        $stmt = $this->item->readAll();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success("Berhasil mengambil data inventaris.", $items);
    }

    // GET /api/items/{id}
    public function show($id) {
        AuthMiddleware::authenticate();

        $this->item->id = $id;
        if($this->item->readOne()) {
            $data = [
                "id" => $this->item->id,
                "category_id" => $this->item->category_id,
                "category_name" => $this->item->category_name,
                "name" => $this->item->name,
                "description" => $this->item->description,
                "stock" => $this->item->stock,
                "image_url" => $this->item->image_url,
                "created_at" => $this->item->created_at
            ];
            Response::success("Berhasil mengambil detail inventaris.", $data);
        } else {
            Response::error("Barang inventaris tidak ditemukan.", 404);
        }
    }

    // POST /api/items (Admin Only)
    public function store() {
        $currentUser = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($currentUser, [1]);

        // Cek apakah request berupa multipart/form-data (mengirim file)
        $isMultipart = !empty($_FILES) || !empty($_POST);

        if ($isMultipart) {
            $name = $_POST['name'] ?? null;
            $category_id = $_POST['category_id'] ?? null;
            $stock = $_POST['stock'] ?? null;
            $description = $_POST['description'] ?? "";
            $imageFile = $_FILES['image'] ?? null;
            $image_url = "";
        } else {
            $data = json_decode(file_get_contents("php://input"));
            $name = $data->name ?? null;
            $category_id = $data->category_id ?? null;
            $stock = $data->stock ?? null;
            $description = $data->description ?? "";
            $image_url = $data->image_url ?? "";
        }

        if(empty($name) || !isset($category_id) || !isset($stock)) {
            Response::error("Data tidak lengkap. Nama, kategori, dan stok wajib diisi.", 400);
        }

        // Cek apakah kategori ada di database
        $category = new Category($this->db);
        $category->id = $category_id;
        if(!$category->readOne()) {
            Response::error("Kategori yang dimasukkan tidak valid/tidak ditemukan.", 400);
        }

        if($stock < 0) {
            Response::error("Stok tidak boleh negatif.", 400);
        }

        // Jika mengupload file gambar, upload ke Cloudinary
        if ($isMultipart && $imageFile) {
            try {
                $image_url = \App\Utils\CloudinaryUploader::uploadImage($imageFile);
            } catch (Exception $e) {
                Response::error($e->getMessage(), 400);
            }
        }

        $this->item->category_id = $category_id;
        $this->item->name = $name;
        $this->item->description = $description;
        $this->item->stock = $stock;
        $this->item->image_url = $image_url;

        if($this->item->create()) {
            Response::success("Barang inventaris berhasil ditambahkan.", null, 201);
        } else {
            Response::error("Gagal menambahkan barang inventaris.", 500);
        }
    }

    // PUT /api/items/{id} (Admin Only)
    // Bisa juga lewat POST /api/items/{id} di router untuk upload berkas baru
    public function update($id) {
        $currentUser = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($currentUser, [1]);

        $this->item->id = $id;
        if(!$this->item->readOne()) {
            Response::error("Barang inventaris tidak ditemukan.", 404);
        }

        $isMultipart = !empty($_FILES) || !empty($_POST);

        if ($isMultipart) {
            $name = $_POST['name'] ?? null;
            $category_id = $_POST['category_id'] ?? null;
            $stock = $_POST['stock'] ?? null;
            $description = $_POST['description'] ?? "";
            $imageFile = $_FILES['image'] ?? null;
            $image_url = $this->item->image_url; // Default ke gambar lama jika tidak dirubah
        } else {
            $data = json_decode(file_get_contents("php://input"));
            $name = $data->name ?? null;
            $category_id = $data->category_id ?? null;
            $stock = $data->stock ?? null;
            $description = $data->description ?? "";
            $image_url = $data->image_url ?? $this->item->image_url;
        }

        if(empty($name) || !isset($category_id) || !isset($stock)) {
            Response::error("Data tidak lengkap. Nama, kategori, dan stok wajib diisi.", 400);
        }

        // Cek kategori baru
        $category = new Category($this->db);
        $category->id = $category_id;
        if(!$category->readOne()) {
            Response::error("Kategori yang dimasukkan tidak valid/tidak ditemukan.", 400);
        }

        if($stock < 0) {
            Response::error("Stok tidak boleh negatif.", 400);
        }

        // Jika mengupload file gambar baru, upload ke Cloudinary
        if ($isMultipart && $imageFile) {
            try {
                $image_url = \App\Utils\CloudinaryUploader::uploadImage($imageFile);
            } catch (Exception $e) {
                Response::error($e->getMessage(), 400);
            }
        }

        $this->item->category_id = $category_id;
        $this->item->name = $name;
        $this->item->description = $description;
        $this->item->stock = $stock;
        $this->item->image_url = $image_url;

        if($this->item->update()) {
            Response::success("Barang inventaris berhasil diperbarui.");
        } else {
            Response::error("Gagal memperbarui barang inventaris.", 500);
        }
    }

    // DELETE /api/items/{id} (Admin Only)
    public function destroy($id) {
        $currentUser = AuthMiddleware::authenticate();
        AuthMiddleware::authorize($currentUser, [1]);

        $this->item->id = $id;
        if(!$this->item->readOne()) {
            Response::error("Barang inventaris tidak ditemukan.", 404);
        }

        // Catatan: Jika ada riwayat peminjaman barang ini, constraint foreign key RESTRICT di DB akan mencegah penghapusan
        try {
            if($this->item->delete()) {
                Response::success("Barang inventaris berhasil dihapus.");
            } else {
                Response::error("Gagal menghapus barang inventaris.", 500);
            }
        } catch(\PDOException $e) {
            Response::error("Barang tidak dapat dihapus karena memiliki riwayat peminjaman.", 400);
        }
    }
}