<?php

namespace App\Utils;

class Response {
    /**
     * Send a JSON response
     *
     * @param mixed $data
     * @param int $statusCode
     * @return void
     */
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    /**
     * Send an error JSON response
     *
     * @param string $message
     * @param int $statusCode
     * @return void
     */
    public static function error($message, $statusCode = 400) {
        self::json([
            'status' => 'error',
            'message' => $message
        ], $statusCode);
    }

    /**
     * Send a success JSON response
     *
     * @param string $message
     * @param mixed $data
     * @param int $statusCode
     * @return void
     */
    public static function success($message, $data = null, $statusCode = 200) {
        $response = [
            'status' => 'success',
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }

        self::json($response, $statusCode);
    }
}
