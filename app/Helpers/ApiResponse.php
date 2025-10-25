<?php

namespace App\Helpers;

class ApiResponse
{
    public static function NewResponse($status, $message, $data = [])
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
