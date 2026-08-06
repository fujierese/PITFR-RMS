<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Api\FacilityRequestApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// API Authentication
Route::post('/login', function (Request $request) {
    $request->validate([
        'username' => 'required|string',
        'password' => 'required',
    ]);

    $user = User::where('username', $request->string('username'))->first();

    if ($user && Hash::check($request->string('password'), $user->password)) {
        $token = $user->createToken('api-access')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => $user,
            'token' => $token,
            'message' => 'Login successful'
        ]);
    }

    return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
});

Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
    $request->user()->currentAccessToken()?->delete();
    return response()->json(['success' => true, 'message' => 'Logged out successfully']);
});

// Public calendar reservations API for guest access
Route::get('reservations', [App\Http\Controllers\CalendarController::class, 'getEvents']);

// Facility Request APIs
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('facility-requests', FacilityRequestApiController::class);
    Route::post('facility-requests/{facility_request}/approve', [FacilityRequestApiController::class, 'approve']);
    Route::post('facility-requests/{facility_request}/reject', [FacilityRequestApiController::class, 'reject']);
    Route::post('facility-requests/{facility_request}/cancel', [FacilityRequestApiController::class, 'cancel']);
    Route::post('facility-requests/{facility_request}/return-equipment', [FacilityRequestApiController::class, 'returnEquipment']);
    Route::get('equipment/availability', [FacilityRequestApiController::class, 'equipmentAvailability']);
    Route::get('venue/availability', [FacilityRequestApiController::class, 'venueAvailability']);
});
