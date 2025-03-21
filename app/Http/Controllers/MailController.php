<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\MyTestMail;

class MailController extends Controller
{
    public function sendMail(){
//        $request->validate([
//            'email' => 'required|email',
//            'message' => 'required'
//        ]);
//        $body = [
//            'email' => $request->email,
//            'message' => $request->message,
//            'subject' => 'Новое письмо'
//        ];
//        $body = [
//            'email' => 'tradelognets@gmail.com',
//            'message' => 'Это просто невероятно!!!',
//            'subject' => 'ПИЩЕПРОМ-СЕРВЕР::Новое письмо'
//        ];
//        Mail::raw($body['message'], function ($message) use ($body) {
//            $message->to($body['email'])
//                ->subject($body['subject']);
//        });


        $details = [
            'title' => 'Привет!',
        ];

        Mail::to('tradelognets@gmail.com')
            ->send(new MyTestMail($details));


        return response()->json(['message' => 'Mail sent successfully']);
    }
}
