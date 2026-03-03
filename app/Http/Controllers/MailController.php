<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\MyTestMail;
use App\Models\Sending;
use App\Models\Email;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MailController extends Controller
{
//    public function sendMail(Request $request)
//    {
//        $request->validate([
//            'email' => 'required|email',
////            'message' => 'required'
////            'products' => 'array',
//        ]);
//        $email = $request->input('email');
////        $body = [
////            'email' => 'tradelognets@gmail.com',
////            'message' => 'Это просто невероятно!!!',
////            'subject' => 'ПИЩЕПРОМ-СЕРВЕР::Новое письмо'
////        ];
////        Mail::raw($body['message'], function ($message) use ($body) {
////            $message->to($body['email'])
////                ->subject($body['subject']);
////        });
//
////        $subject = $request->input('subject');
//        $products = $request->input('products');
//        // если вдруг приходит строка, распарсить
//        if (is_string($products)) {
//            $products = json_decode($products, true);
//        }
//
////        return View::make('emails.funEmail', $details);
//
////        logger('Products:', [$products]);
//
//        $details = array(
//            'title' => 'Ингредиенты, Сырьё, Добавки',
//            'products' => $products,);
//
//        Mail::to($email)
//            ->bcc('tradelognets@gmail.com')
//            ->send(new MyTestMail($details));
////            ->send(new MyTestMail($details));
//
//        Sending::create(
//            [
//                'email_id' => Email::where('address', $email)->firstOrFail()->id,
////                'subject' => json_encode($products),
//                'subject' => $details['title'],
//            ]
//        );
//
////        return response()->json(['message' => 'Mail sent successfully']);
//    }

    public function sendMail(Request $request)
    {
        $request->validate([
                               'email' => 'required|email',
                           ]);

        $email = $request->input('email');

        $products = $request->input('products');
        if (is_string($products)) {
            $products = json_decode($products, true);
        }

        $details = [
            'title' => 'Ингредиенты, Сырьё, Добавки',
            'products' => $products,
        ];

        $emailModel = Email::where('address', $email)->firstOrFail();

        // Важно: делаем sending ДО отправки, чтобы был token
        $sending = Sending::create([
                                       'email_id' => $emailModel->id,
                                       'subject' => $details['title'],
                                       'tracking_token' => (string) Str::uuid(),
                                       'status' => 'created', // если есть поле status
                                   ]);

        // Передаём token в шаблон
        $details['tracking_token'] = $sending->tracking_token;
        $details['sending_id'] = $sending->id;

        try {
            Mail::to($email)
                ->bcc('tradelognets@gmail.com')
                ->send(new MyTestMail($details));

            $sending->update([
                                 'sent_at' => Carbon::now(),
                                 'status' => 'sent',
                             ]);
        } catch (\Throwable $e) {
            $sending->update([
                                 'status' => 'failed',
                             ]);
            throw $e;
        }
    }
}
