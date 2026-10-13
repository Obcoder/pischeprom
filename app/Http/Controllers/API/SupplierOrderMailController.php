<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mail\SendSupplierOrderMailRequest;
use App\Models\SupplierOrder;
use App\Services\Auth\StaffAccess;
use App\Services\Mail\MailDispatchException;
use App\Services\SupplierOrders\SupplierOrderMailRenderer;
use App\Services\SupplierOrders\SupplierOrderMailSender;
use App\Services\SupplierOrders\SupplierOrderWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierOrderMailController extends Controller
{
    public function preview(Request $request, SupplierOrderWriter $writer, SupplierOrderMailRenderer $renderer, StaffAccess $staff): JsonResponse
    {
        abort_unless($staff->allows($request->user()) && $request->user()->hasVerifiedEmail(), 403);

        return response()->json($renderer->render($writer->normalize($request->all())));
    }

    public function previewSaved(Request $request, SupplierOrder $supplierOrder, SupplierOrderWriter $writer, SupplierOrderMailRenderer $renderer, StaffAccess $staff): JsonResponse
    {
        abort_unless($staff->allows($request->user()) && $request->user()->hasVerifiedEmail(), 403);

        // Historical documents retain their original names and units even when
        // the live catalog changes or a good is deleted. Ignore request overrides.
        return response()->json($renderer->render($writer->mailData($supplierOrder)));
    }

    public function send(SendSupplierOrderMailRequest $request, SupplierOrder $supplierOrder, SupplierOrderMailSender $sender, SupplierOrderWriter $writer): JsonResponse
    {
        try {
            $result = $sender->send($request->user(), $supplierOrder, (int) $request->validated('revision'), $request->safe()->only(['attachments', 'storage_files']));

            return response()->json([...$result, 'order' => $writer->data($result['order'])]);
        } catch (MailDispatchException $exception) {
            $order = $supplierOrder->fresh();
            $warning = $exception->getMessage();
            if (in_array($order->status, ['sending', 'send_unknown'], true)) {
                $warning .= ' Повторная отправка заблокирована. Обновите статус отправки.';
            }

            return response()->json([
                'order' => $writer->data($order), 'warning' => $warning,
                'message' => $exception->getMessage(), 'code' => $exception->safeCode,
            ], $exception->httpStatus);
        }
    }

    public function reconcile(Request $request, SupplierOrder $supplierOrder, SupplierOrderMailSender $sender, SupplierOrderWriter $writer): JsonResponse
    {
        $result = $sender->reconcile($request->user(), $supplierOrder);

        return response()->json([...$result, 'order' => $writer->data($result['order'])]);
    }
}
