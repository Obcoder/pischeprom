<?php

namespace App\Http\Controllers;

use App\Models\Sending;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EmailTrackingController extends Controller
{
    public function open(Request $request, string $token)
    {
        // Обновляем аккуратно (без гонок) одним запросом
        DB::table('sendings')
            ->where('tracking_token', $token)
            ->update([
                         'opened_at' => DB::raw('COALESCE(opened_at, NOW())'),
                         'open_count' => DB::raw('COALESCE(open_count, 0) + 1'),
                         'last_open_ip' => $request->ip(),
                         'last_open_ua' => substr((string) $request->userAgent(), 0, 1000),
                         'updated_at' => Carbon::now(),
                     ]);

        // Возвращаем 1x1 прозрачный GIF
        $gif = base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==');

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => strlen($gif),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function click(Request $request, string $token)
    {
        $url = (string) $request->query('url', '');

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            abort(400, 'Invalid URL');
        }

        DB::table('sendings')
            ->where('tracking_token', $token)
            ->update([
                         'clicked_at' => DB::raw('COALESCE(clicked_at, NOW())'),
                         'click_count' => DB::raw('COALESCE(click_count, 0) + 1'),
                         'updated_at' => Carbon::now(),
                     ]);

        return redirect()->away($url);
    }
}
