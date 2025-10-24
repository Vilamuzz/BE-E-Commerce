<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Pembelian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    /**
     * Store a newly created review
     */
    public function store(Request $request, $id_pembelian)
    {
        try {
            $user = Auth::user();
            
            // Validate the purchase belongs to user and is completed
            $pembelian = Pembelian::where('id_pembelian', $id_pembelian)
                ->where('id_pembeli', $user->id_user)
                ->where('status_pembelian', 'Selesai')
                ->first();
                
            if (!$pembelian) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase not found or not eligible for review'
                ], 404);
            }
            
            // Check if review already exists
            $existingReview = Review::where('id_pembelian', $id_pembelian)
                ->where('id_user', $user->id_user)
                ->first();
                
            if ($existingReview) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Review already exists for this purchase'
                ], 400);
            }
            
            // Validate request
            $validator = Validator::make($request->all(), [
                'rating' => 'required|integer|min:1|max:5',
                'komentar' => 'required|string|max:1000',
                'image_review' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('image_review')) {
                $image = $request->file('image_review');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $imagePath = $image->storeAs('reviews', $imageName, 'public');
            }
            
            // Create review
            $review = Review::create([
                'id_user' => $user->id_user,
                'id_pembelian' => $id_pembelian,
                'rating' => $request->rating,
                'komentar' => $request->komentar,
                'image_review' => $imagePath
            ]);
            
            // Load relationships for response
            $review->load([
                'user:id_user,name',
                'pembelian' => function($query) {
                    $query->select('id_pembelian', 'kode_pembelian')
                          ->with(['detailPembelian' => function($q) {
                              $q->with('barang:id_barang,nama_barang');
                          }]);
                }
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Review created successfully',
                'data' => $review
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create review',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * Show review for a purchase
     */
    public function show($id_pembelian)
    {
        try {
            $user = Auth::user();
            
            $review = Review::where('id_pembelian', $id_pembelian)
                ->where('id_user', $user->id_user)
                ->with([
                    'user:id_user,name',
                    'pembelian' => function($query) {
                        $query->select('id_pembelian', 'kode_pembelian')
                              ->with(['detailPembelian' => function($q) {
                                  $q->with('barang:id_barang,nama_barang');
                              }]);
                    }
                ])
                ->first();
                
            if (!$review) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Review not found'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $review
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch review',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * Delete review
     */
    public function destroy($id_review)
    {
        try {
            $user = Auth::user();
            
            $review = Review::where('id_review', $id_review)
                ->where('id_user', $user->id_user)
                ->first();
                
            if (!$review) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Review not found'
                ], 404);
            }
            
            // Delete image if exists
            if ($review->image_review) {
                Storage::disk('public')->delete($review->image_review);
            }
            
            $review->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Review deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete review',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * Get review by pembelian
     */
    public function getByPembelian($id_pembelian)
    {
        try {
            $user = Auth::user();
            
            // Verify the purchase belongs to the user
            $pembelian = Pembelian::where('id_pembelian', $id_pembelian)
                ->where('id_pembeli', $user->id_user)
                ->first();
                
            if (!$pembelian) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase not found'
                ], 404);
            }
            
            $review = Review::where('id_pembelian', $id_pembelian)
                ->with([
                    'user:id_user,name',
                    'pembelian' => function($query) {
                        $query->select('id_pembelian', 'kode_pembelian')
                              ->with(['detailPembelian' => function($q) {
                                  $q->with('barang:id_barang,nama_barang');
                              }]);
                    }
                ])
                ->first();
                
            return response()->json([
                'status' => 'success',
                'data' => $review,
                'can_review' => $review === null && $pembelian->status_pembelian === 'Selesai'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch review',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
