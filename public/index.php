<?php

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Autoloader dari Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Error reporting untuk development (matikan di production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Simple Router
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request_method = $_SERVER['REQUEST_METHOD'];

// Normalisasi URI dengan menghilangkan nama folder project jika berjalan di XAMPP/localhost
// Base Pathnya (misal: /SPI-APP/public)
$base_path = '/backend-spi-app/public'; 
if (strpos($request_uri, $base_path) === 0) {
    $request_uri = substr($request_uri, strlen($base_path));
}

// Endpoint routing
if ($request_uri === '/api/health' && $request_method === 'GET') {
    \App\Utils\Response::success("API is running");
}

// Authentication Routes
if ($request_uri === '/api/auth/register' && $request_method === 'POST') {
    $controller = new \App\Controllers\AuthController();
    $controller->register();
} elseif (strpos($request_uri, '/api/auth/activation') === 0 && $request_method === 'GET') {
    $controller = new \App\Controllers\AuthController();
    $controller->activate();
} elseif ($request_uri === '/api/auth/login' && $request_method === 'POST') {
    $controller = new \App\Controllers\AuthController();
    $controller->login();
} elseif ($request_uri === '/api/auth/me' && $request_method === 'GET') {
    $controller = new \App\Controllers\AuthController();
    $controller->me();
} elseif ($request_uri === '/api/auth/logout' && $request_method === 'POST') {
    $controller = new \App\Controllers\AuthController();
    $controller->logout();
}

// User Management Routes (Admin Only)
elseif ($request_uri === '/api/users' && $request_method === 'GET') {
    $controller = new \App\Controllers\UserController();
    $controller->index();
} elseif ($request_uri === '/api/users' && $request_method === 'POST') {
    $controller = new \App\Controllers\UserController();
    $controller->store();
} elseif (preg_match('/^\/api\/users\/(\d+)$/', $request_uri, $matches)) {
    $id = $matches[1];
    $controller = new \App\Controllers\UserController();
    if ($request_method === 'GET') {
        $controller->show($id);
    } elseif ($request_method === 'PUT') {
        $controller->update($id);
    } elseif ($request_method === 'DELETE') {
        $controller->destroy($id);
    }
}

// Category Management Routes
elseif ($request_uri === '/api/categories' && $request_method === 'GET') {
    $controller = new \App\Controllers\CategoryController();
    $controller->index();
} elseif ($request_uri === '/api/categories' && $request_method === 'POST') {
    $controller = new \App\Controllers\CategoryController();
    $controller->store();
} elseif (preg_match('/^\/api\/categories\/(\d+)$/', $request_uri, $matches)) {
    $id = $matches[1];
    $controller = new \App\Controllers\CategoryController();
    if ($request_method === 'GET') {
        $controller->show($id);
    } elseif ($request_method === 'PUT') {
        $controller->update($id);
    } elseif ($request_method === 'DELETE') {
        $controller->destroy($id);
    }
}

// Item (Inventaris) Management Routes
elseif ($request_uri === '/api/items' && $request_method === 'GET') {
    $controller = new \App\Controllers\ItemController();
    $controller->index();
} elseif ($request_uri === '/api/items' && $request_method === 'POST') {
    $controller = new \App\Controllers\ItemController();
    $controller->store();
} elseif (preg_match('/^\/api\/items\/(\d+)$/', $request_uri, $matches)) {
    $id = $matches[1];
    $controller = new \App\Controllers\ItemController();
    if ($request_method === 'GET') {
        $controller->show($id);
    } elseif ($request_method === 'PUT' || $request_method === 'POST') {
        $controller->update($id);
    } elseif ($request_method === 'DELETE') {
        $controller->destroy($id);
    }

        // Borrowings (Peminjaman) Routes
    elseif ($request_uri === '/api/borrowings/my' && $request_method === 'GET') {
        $controller = new \App\Controllers\BorrowingController();
        $controller->my();
    } elseif ($request_uri === '/api/borrowings' && $request_method === 'GET') {
        $controller = new \App\Controllers\BorrowingController();
        $controller->index();
    } elseif ($request_uri === '/api/borrowings' && $request_method === 'POST') {
        $controller = new \App\Controllers\BorrowingController();
        $controller->store();
    } elseif (preg_match('/^/api/borrowings/(\d+)$/', $request_uri, $matches)) {
        $id = $matches[1];
        $controller = new \App\Controllers\BorrowingController();
        if ($request_method === 'GET') {
            $controller->show($id);
        }
    } elseif (preg_match('/^/api/borrowings/(\d+)/approve$/', $request_uri, $matches)) {
        $id = $matches[1];
        $controller = new \App\Controllers\BorrowingController();
        if ($request_method === 'PATCH') {
            $controller->approve($id);
        }
    } elseif (preg_match('/^/api/borrowings/(\d+)/reject$/', $request_uri, $matches)) {
        $id = $matches[1];
        $controller = new \App\Controllers\BorrowingController();
        if ($request_method === 'PATCH') {
            $controller->reject($id);
        }
    } elseif (preg_match('/^/api/borrowings/(\d+)/borrow$/', $request_uri, $matches)) {
        $id = $matches[1];
        $controller = new \App\Controllers\BorrowingController();
        if ($request_method === 'PATCH') {
            $controller->borrow($id);
        }
    } elseif (preg_match('/^/api/borrowings/(\d+)/return$/', $request_uri, $matches)) {
        $id = $matches[1];
        $controller = new \App\Controllers\BorrowingController();
        if ($request_method === 'PATCH') {
            $controller->returnItem($id);
        }
    }
    
    // Dashboard Route (Admin & Petugas Only)
    elseif ($request_uri === '/api/dashboard' && $request_method === 'GET') {
        $controller = new \App\Controllers\DashboardController();
        $controller->index();
    }

}



// Jika endpoint tidak ditemukan
\App\Utils\Response::error("Endpoint not found: " . $request_uri, 404);
