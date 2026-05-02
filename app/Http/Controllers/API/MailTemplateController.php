<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $templates = MailTemplate::query()
            ->search($request->input('search'))
            ->orderBy('name')
            ->get();

        return response()->json($templates);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
                                       'name' => ['required', 'string', 'max:255'],
                                       'subject' => ['nullable', 'string', 'max:255'],
                                       'body' => ['nullable', 'string'],
                                       'is_active' => ['nullable', 'boolean'],
                                   ]);

        $template = MailTemplate::create([
                                             'name' => $data['name'],
                                             'subject' => $data['subject'] ?? null,
                                             'body' => $data['body'] ?? null,
                                             'is_active' => $data['is_active'] ?? true,
                                         ]);

        return response()->json($template, 201);
    }

    public function update(Request $request, MailTemplate $mailTemplate): JsonResponse
    {
        $data = $request->validate([
                                       'name' => ['required', 'string', 'max:255'],
                                       'subject' => ['nullable', 'string', 'max:255'],
                                       'body' => ['nullable', 'string'],
                                       'is_active' => ['nullable', 'boolean'],
                                   ]);

        $mailTemplate->update([
                                  'name' => $data['name'],
                                  'subject' => $data['subject'] ?? null,
                                  'body' => $data['body'] ?? null,
                                  'is_active' => $data['is_active'] ?? true,
                              ]);

        return response()->json($mailTemplate->fresh());
    }

    public function destroy(MailTemplate $mailTemplate): JsonResponse
    {
        $mailTemplate->delete();

        return response()->json([
                                    'message' => 'Template deleted',
                                ]);
    }
}
