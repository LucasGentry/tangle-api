<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Tangle API Documentation",
 *     description="API documentation for Tangle application. This API provides endpoints for managing collaborations, user profiles, reviews, and authentication.",
 *     @OA\Contact(
 *         email="admin@example.com",
 *         name="Tangle API Support",
 *         url="https://tangle-api.example.com"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     ),
 *     termsOfService="https://tangle-api.example.com/terms"
 * )
 * 
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     description="Enter token in format (Bearer <token>)"
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="API Endpoints for user authentication"
 * )
 * @OA\Tag(
 *     name="Profile",
 *     description="User profile management endpoints"
 * )
 * @OA\Tag(
 *     name="Collaboration",
 *     description="Collaboration request management endpoints"
 * )
 * @OA\Tag(
 *     name="Applications",
 *     description="Application management endpoints"
 * )
 * @OA\Tag(
 *     name="Reviews",
 *     description="Review management endpoints"
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}