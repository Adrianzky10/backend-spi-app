<?php

namespace App\Utils;

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Exception;

class CloudinaryUploader {
    /**
     * Mengunggah file PDF ke Cloudinary
     * 
     * @param array $file Elemen $_FILES['nama_input']
     * @return array Data file_name, file_url, dan public_id
     * @throws Exception
     */
    public static function uploadPdf($file) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Berkas tidak terunggah dengan benar atau tidak ditemukan.");
        }

        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileMime = mime_content_type($file['tmp_name']);
        
        if ($fileExtension !== 'pdf' || $fileMime !== 'application/pdf') {
            throw new Exception("Format berkas tidak diizinkan. Wajib menggunakan format PDF.");
        }

        $maxSize = 5 * 1024 * 1024; 
        if ($file['size'] > $maxSize) {
            throw new Exception("Ukuran berkas terlalu besar. Maksimal ukuran adalah 5MB.");
        }

        try {
            Configuration::instance($_ENV['CLOUDINARY_URL']);
            
            $uploadApi = new UploadApi();
            

            $result = $uploadApi->upload($file['tmp_name'], [
                'resource_type' => 'raw',
                'folder' => 'spi_campus_documents',
                'use_filename' => true,
                'unique_filename' => true
            ]);

            return [
                'file_name' => $file['name'],
                'file_url' => $result['secure_url'],
                'public_id' => $result['public_id']
            ];
        } catch (Exception $e) {
            throw new Exception("Gagal mengunggah berkas ke Cloudinary. Detail: " . $e->getMessage());
        }
    }

    /**
     * Mengunggah file Gambar (JPG/PNG) ke Cloudinary
     * 
     * @param array $file Elemen $_FILES['image']
     * @return string URL gambar yang aman dari Cloudinary
     * @throws Exception
     */
    public static function uploadImage($file) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Berkas gambar tidak ditemukan atau rusak.");
        }

        $fileMime = mime_content_type($file['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!in_array($fileMime, $allowedMimes)) {
            throw new Exception("Format gambar harus berupa JPG, PNG, WEBP, atau GIF.");
        }

        $maxSize = 2 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new Exception("Ukuran gambar terlalu besar. Maksimal ukuran adalah 2MB.");
        }

        try {
            Configuration::instance($_ENV['CLOUDINARY_URL']);
            $uploadApi = new UploadApi();
            

            $result = $uploadApi->upload($file['tmp_name'], [
                'resource_type' => 'image',
                'folder' => 'spi_item_images',
                'use_filename' => true,
                'unique_filename' => true
            ]);

            return $result['secure_url']; 
        } catch (Exception $e) {
            throw new Exception("Gagal mengunggah gambar ke Cloudinary. Detail: " . $e->getMessage());
        }
    }
}
