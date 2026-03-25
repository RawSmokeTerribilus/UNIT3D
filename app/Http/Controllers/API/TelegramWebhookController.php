<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Recon de la señal de Telegram
        $message = $request->input('message');
        if (!$message || !isset($message['text'])) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $chatId = $message['chat']['id'];
        $text = $message['text']; 

        // 2. Extraer el token del comando /start TRK-XXXX
        if (preg_match('/^\/start\s+(TRK-[a-zA-Z0-9]+)$/', $text, $matches)) {
            $token = $matches[1];

            // 3. Transacción: Evitar race condition si múltiples /start llegan simultáneamente
            DB::transaction(function () use ($chatId, $token) {
                $user = User::where('telegram_token', $token)
                    ->lockForUpdate() // Lock hasta que complete la transacción
                    ->first();

                if ($user) {
                    // 4. LA PULICIÓN: Vincular, limpiar y disparar respuesta
                    $user->telegram_chat_id = $chatId;
                    $user->telegram_token = null;
                    $user->save();

                    $botToken = env('TELEGRAM_BOT_TOKEN');
                    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

                    \Illuminate\Support\Facades\Http::post($url, [
                        'chat_id' => $chatId,
                        'text' => "¡Acho! Cuenta vinculada con éxito, {$user->username}. Ya estás en el búnker de NOBS.",
                    ]);

                    Log::info("Telegram vinculado exitosamente para el usuario: {$user->username}");
                } else {
                    Log::warning("Intento de vinculación con token inválido: {$token}");
                }
            });
        }

        // 5. El OK táctico para que Telegram no reintente el envío
        return response()->json(['status' => 'ok'], 200);
    }
}
