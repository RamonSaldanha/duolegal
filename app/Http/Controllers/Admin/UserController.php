<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display a listing of all users.
     */
    public function index()
    {
        // Get all users with their subscription information
        $users = User::select('id', 'name', 'email', 'lives', 'is_admin', 'created_at')
            ->withCount('subscriptions as subscription_count')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'lives' => $user->lives,
                    'is_admin' => $user->is_admin,
                    'is_premium' => $user->hasActiveSubscription(),
                    'created_at' => $user->created_at->format('d/m/Y H:i'),
                ];
            });
        
        return Inertia::render('admin/Users/Index', [
            'users' => $users
        ]);
    }
}
