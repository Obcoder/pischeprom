<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\MyTestMail;
use App\Models\Sending;
use App\Models\Email;
use Illuminate\Support\Facades\View;

class MailController extends Controller
{
    public function sendMail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
//            'message' => 'required'
//            'products' => 'array',
        ]);
        $email = $request->input('email');
//        $body = [
//            'email' => 'tradelognets@gmail.com',
//            'message' => 'Это просто невероятно!!!',
//            'subject' => 'ПИЩЕПРОМ-СЕРВЕР::Новое письмо'
//        ];
//        Mail::raw($body['message'], function ($message) use ($body) {
//            $message->to($body['email'])
//                ->subject($body['subject']);
//        });

//        $subject = $request->input('subject');
        $products = $request->input('products');
        // если вдруг приходит строка, распарсить
        if (is_string($products)) {
            $products = json_decode($products, true);
        }

//        return View::make('emails.funEmail', $details);

//        logger('Products:', [$products]);

        $details = array(
            'title' => 'Ингредиенты, Сырьё, Добавки',
            'products' => $products,);

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

//        return response()->json(['message' => 'Mail sent successfully']);
    }
}
