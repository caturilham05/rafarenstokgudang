<?php

namespace App\Http\Controllers;

use App\Services\Tiktok\TiktokAuthService;
use Illuminate\Http\Request;

class TiktokController extends Controller
{
    public function connect(TiktokAuthService $service)
    {
        return redirect(
            $service->getAuthorizationUrl(route('tiktok.callback'))
        );
    }

    public function callback(Request $request,TiktokAuthService $service) {
        abort_if(
            $request->state !== session('tiktok_state'),
            403
        );

        $token = $service->getAccessToken($request->code);
        dd($token);

        // simpan token ke DB
        // seller_id, access_token, refresh_token, expired_at

        return redirect('/dashboard');
    }
}
