<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Traits\ApiPagination;

/**
 * ApiController
 * -------------
 * The base class that ALL API controllers extend.
 * It pulls in the ApiResponse trait so every endpoint can call
 * $this->success(...) and $this->error(...) for a consistent reply shape.
 *
 * Keep shared API behaviour here later (current user helper, etc.).
 */
abstract class ApiController extends Controller
{
    use ApiResponse, ApiPagination;
}
