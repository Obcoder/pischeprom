<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{
    public function sendMail(Request $request){
        $request->validate([
            'email' => 'required|email',
            'message' => 'required'
        ]);
        $body = [
            'email' => $request->email,
            'message' => $request->message,
            'subject' => 'Новое письмо'
        ];
        Mail::raw($body['message'], function ($message) use ($body) {
            $message->to($body['email'])
                ->subject($body['subject']);

        });

        return 'Mail sent';
    }
}
