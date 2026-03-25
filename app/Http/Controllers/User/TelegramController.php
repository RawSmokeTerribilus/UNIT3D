<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TelegramController extends Controller
{
    public function resetToken(Request $request)
    {
        $user = $request->user();
        
        $user->update([
            'telegram_token' => 'TRK-' . Str::random(32),
        ]);

        return redirect()
            ->route('users.notification_settings.edit', ['user' => $user->username])
            ->with('success', 'Token de Telegram regenerado correctamente.');
    }
}
