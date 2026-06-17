<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Service;
use App\Models\Booking;
use App\Models\Review;

class DashboardController extends Controller
{

    public function index(){
        $user = Auth::user();
        if ($user->hasRole('admin')){
            return response()->json([
                'success'=> 'true',
                'role' => 'admin',
                 'data' => [
                     'users'=> User::count(),
                     'services'=> Service::count(),
                     'bookings'=> Booking::count(),
                     'reviews' => Review::count()
                 ]
            ]);
            }

            //if ($user->hasRole('seller'))
    }

}
