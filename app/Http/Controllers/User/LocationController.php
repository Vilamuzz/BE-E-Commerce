<?php

namespace App\Http\Controllers\User;

use App\Models\Province;
use App\Models\Regency;
use App\Models\District;
use App\Models\Village;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Get all provinces
     */
    public function getProvinces()
    {
        try {
            $provinces = Province::orderBy('name', 'asc')->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $provinces
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch provinces'
            ], 500);
        }
    }

    /**
     * Get regencies by province ID
     */
    public function getRegencies($provinceId)
    {
        try {
            $regencies = Regency::where('province_id', $provinceId)
                               ->orderBy('name', 'asc')
                               ->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $regencies
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch regencies'
            ], 500);
        }
    }

    /**
     * Get districts by regency ID
     */
    public function getDistricts($regencyId)
    {
        try {
            $districts = District::where('regency_id', $regencyId)
                                ->orderBy('name', 'asc')
                                ->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $districts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch districts'
            ], 500);
        }
    }

    /**
     * Get villages by district ID
     */
    public function getVillages($districtId)
    {
        try {
            $villages = Village::where('district_id', $districtId)
                              ->orderBy('name', 'asc')
                              ->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $villages
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch villages'
            ], 500);
        }
    }
}
