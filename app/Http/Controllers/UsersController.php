<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function notifications(Request $request)
    {
        $user = User::find(1);

        return $user->notifications;
    }
}
